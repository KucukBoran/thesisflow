<?php
declare(strict_types=1);
require dirname(__DIR__) . "/src/bootstrap.php";

use ThesisFlow\Auth;
use ThesisFlow\Database;
use ThesisFlow\HttpError;
use ThesisFlow\ThesisService;

$passed = 0;
function check(bool $condition, string $message = "Assertion failed"): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function fails(int $status, callable $action): void
{
    try {
        $action();
    } catch (HttpError $error) {
        check(
            $error->status === $status,
            "Unexpected error status: " . $error->status,
        );
        return;
    }
    throw new RuntimeException("Expected failure " . $status);
}
function fixture(): array
{
    $db = Database::connect(":memory:");
    $users = [];
    foreach (
        ["student", "student", "advisor", "advisor", "admin"]
        as $index => $role
    ) {
        $id = $index + 1;
        $db->prepare("INSERT INTO users VALUES (?,?,?,?,?)")->execute([
            $id,
            "User $id",
            "u$id@example.test",
            password_hash("DemoPass123!", PASSWORD_BCRYPT, ["cost" => 4]),
            $role,
        ]);
        $users[$id] = ["id" => $id, "role" => $role, "name" => "User $id"];
    }
    return [$db, new ThesisService($db), $users];
}
function proposal(): array
{
    return [
        "title" => "Test research proposal",
        "abstract" => str_repeat("A clear research question. ", 3),
        "advisor_id" => 3,
    ];
}
function run(string $name, callable $test): void
{
    global $passed;
    try {
        $test();
        $passed++;
        echo "PASS $name\n";
    } catch (Throwable $error) {
        fwrite(STDERR, "FAIL $name: {$error->getMessage()}\n");
        exit(1);
    }
}
run("student ownership and advisor assignment are enforced", function () {
    [$db, $service, $users] = fixture();
    $id = $service->create($users[1], proposal());
    check($service->get($id, $users[1])["student_id"] === 1);
    check($service->get($id, $users[3])["advisor_id"] === 3);
    fails(404, fn() => $service->get($id, $users[2]));
    fails(404, fn() => $service->get($id, $users[4]));
    check($service->get($id, $users[5])["id"] === $id);
});
run("only students create and advisor must be valid", function () {
    [$db, $service, $users] = fixture();
    fails(403, fn() => $service->create($users[3], proposal()));
    fails(
        422,
        fn() => $service->create($users[1], [...proposal(), "advisor_id" => 2]),
    );
});
run("submitted proposals cannot be edited", function () {
    [$db, $service, $u] = fixture();
    $id = $service->create($u[1], proposal());
    $service->transition($id, $u[1], "submitted", 1);
    fails(
        403,
        fn() => $service->update($id, $u[1], [...proposal(), "version" => 2]),
    );
});
run(
    "revision feedback and subsequent approval form a complete workflow",
    function () {
        [$db, $s, $u] = fixture();
        $id = $s->create($u[1], proposal());
        $s->transition($id, $u[1], "submitted", 1);
        fails(422, fn() => $s->transition($id, $u[3], "revision", 2, "short"));
        $s->transition(
            $id,
            $u[3],
            "revision",
            2,
            "Please expand the evaluation section.",
        );
        $s->update($id, $u[1], [
            ...proposal(),
            "title" => "Revised research proposal",
            "version" => 3,
        ]);
        $s->transition($id, $u[1], "submitted", 4);
        $s->transition($id, $u[3], "approved", 5);
        check($s->get($id, $u[1])["status"] === "approved");
        check(count($s->details($id, $u[1])["comments"]) === 1);
        check(count($s->details($id, $u[1])["activity"]) === 6);
    },
);
run("students cannot approve their own work", function () {
    [$db, $s, $u] = fixture();
    $id = $s->create($u[1], proposal());
    $s->transition($id, $u[1], "submitted", 1);
    fails(403, fn() => $s->transition($id, $u[1], "approved", 2));
});
run("stale changes do not overwrite another edit", function () {
    [$db, $s, $u] = fixture();
    $id = $s->create($u[1], proposal());
    $s->update($id, $u[1], [
        ...proposal(),
        "title" => "Newer research title",
        "version" => 1,
    ]);
    fails(409, fn() => $s->update($id, $u[1], [...proposal(), "version" => 1]));
    check($s->get($id, $u[1])["title"] === "Newer research title");
});
run("search and counts are scoped to the current user", function () {
    [$db, $s, $u] = fixture();
    $s->create($u[1], proposal());
    $s->create($u[2], proposal());
    check($s->list($u[1])["total"] === 1);
    check(array_sum($s->stats($u[1])) === 1);
    check($s->list($u[4])["total"] === 0);
    check($s->list($u[5])["total"] === 2);
    check($s->list($u[1], "%' OR 1=1 --")["total"] === 0);
});
run("comments require record access and have length limits", function () {
    [$db, $s, $u] = fixture();
    $id = $s->create($u[1], proposal());
    fails(404, fn() => $s->addComment($id, $u[2], "hello"));
    fails(422, fn() => $s->addComment($id, $u[1], ""));
    $s->addComment($id, $u[3], "A useful question.");
    check(count($s->details($id, $u[1])["comments"]) === 1);
});
run("private document metadata cannot be read by another student", function () {
    [$db, $s, $u] = fixture();
    $id = $s->create($u[1], proposal());
    $db->prepare(
        "INSERT INTO documents(thesis_id,name,stored_name,size) VALUES (?,?,?,?)",
    )->execute([$id, "paper.pdf", "private.pdf", 100]);
    fails(404, fn() => $s->document(1, $u[2]));
    check($s->document(1, $u[3])["name"] === "paper.pdf");
});
run("CSRF rejects missing, incorrect and non-string values", function () {
    Auth::verifyCsrf("secret", "secret");
    fails(403, fn() => Auth::verifyCsrf("secret", null));
    fails(403, fn() => Auth::verifyCsrf("secret", "other"));
    fails(403, fn() => Auth::verifyCsrf("secret", []));
});
run("authentication throttles repeated failures", function () {
    [$db, $s, $u] = fixture();
    $auth = new Auth($db);
    for ($i = 0; $i < 10; $i++) {
        fails(
            401,
            fn() => $auth->login("u1@example.test", "wrong", "test-client"),
        );
    }
    fails(
        429,
        fn() => $auth->login("u1@example.test", "DemoPass123!", "test-client"),
    );
    check(
        $auth->login("u1@example.test", "DemoPass123!", "other-client")[
            "id"
        ] === 1,
    );
});
run("HTML escaping protects stored text in templates", function () {
    check(
        e('<script>alert("x")</script>') ===
            "&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;",
    );
});
echo "\n$passed tests passed.\n";
