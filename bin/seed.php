<?php
declare(strict_types=1);
require dirname(__DIR__) . "/src/bootstrap.php";

use ThesisFlow\Database;

$db = Database::connect();
if ($db->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) {
    echo "Database already contains users. Nothing changed.\n";
    exit();
}
$db->beginTransaction();
try {
    $q = $db->prepare(
        "INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)",
    );
    foreach (
        [
            ["Alex Morgan", "student@example.test", "student"],
            ["Sam Rivera", "student2@example.test", "student"],
            ["Dr. Maya Ellis", "advisor@example.test", "advisor"],
            ["Dr. Robin Shaw", "advisor2@example.test", "advisor"],
            ["Demo Administrator", "admin@example.test", "admin"],
        ]
        as [$name, $email, $role]
    ) {
        $q->execute([
            $name,
            $email,
            password_hash("DemoPass123!", PASSWORD_DEFAULT),
            $role,
        ]);
    }
    $rows = [
        ["Event-driven workflow automation", 1, 3, "submitted"],
        ["Accessible interfaces for public services", 1, 3, "revision"],
        ["A relational model for research archives", 2, 3, "approved"],
        ["Measuring API reliability with service metrics", 2, 3, "submitted"],
        ["Privacy-aware attendance systems", 1, 4, "draft"],
    ];
    $q = $db->prepare(
        "INSERT INTO theses(title,abstract,student_id,advisor_id,status) VALUES (?,?,?,?,?)",
    );
    foreach ($rows as [$title, $student, $advisor, $status]) {
        $q->execute([
            $title,
            "This fictional proposal explores practical software design, evaluation methods and measurable outcomes. The scope includes a working prototype, a documented data model and repeatable tests.",
            $student,
            $advisor,
            $status,
        ]);
        $id = (int) $db->lastInsertId();
        $db->prepare(
            "INSERT INTO activity(thesis_id,user_id,action) VALUES (?,?,?)",
        )->execute([$id, $student, "Created demo proposal"]);
    }
    $db->prepare(
        "INSERT INTO comments(thesis_id,author_id,body) VALUES (2,3,?)",
    )->execute([
        "Please define the accessibility evaluation criteria and include keyboard navigation in your test plan.",
    ]);
    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
echo "Demo ready. student@example.test, advisor@example.test or admin@example.test / DemoPass123!\n";
