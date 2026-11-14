<?php
declare(strict_types=1);
require dirname(__DIR__) . "/src/bootstrap.php";

use ThesisFlow\Auth;
use ThesisFlow\Database;
use ThesisFlow\HttpError;
use ThesisFlow\ThesisService;

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: same-origin");
header(
    "Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'",
);
header("Cache-Control: no-store");
ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
session_name("thesisflow_session");
$sessionDirectory = project_path(getenv("SESSION_PATH") ?: "storage/sessions");
if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0700, true);
}
session_save_path($sessionDirectory);
session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Strict",
    "secure" => getenv("SESSION_SECURE") === "true",
    "path" => "/",
]);
session_start();
if (
    isset($_SESSION["signed_in_at"]) &&
    time() - $_SESSION["signed_in_at"] > 28800
) {
    $_SESSION = [];
}
$_SESSION["csrf"] ??= bin2hex(random_bytes(32));

function render(string $template, array $variables = []): never
{
    extract($variables, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . "/views/" . $template . ".php";
    $content = ob_get_clean();
    require dirname(__DIR__) . "/views/layout.php";
    exit();
}

$user = null;
try {
    if (
        !in_array(
            parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH),
            ["/", "/index.php"],
            true,
        )
    ) {
        throw new HttpError(404, "Page not found.");
    }
    $db = Database::connect();
    $auth = new Auth($db);
    $service = new ThesisService($db);
    $page = is_string($_GET["page"] ?? null) ? $_GET["page"] : "dashboard";
    $method = $_SERVER["REQUEST_METHOD"];
    $id = filter_var($_GET["id"] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    if (!in_array($method, ["GET", "POST"], true)) {
        throw new HttpError(405, "Method not allowed.");
    }
    if ($page === "health" && $method === "GET") {
        $db->query("SELECT 1");
        header("Content-Type: application/json");
        echo '{"status":"ok"}';
        exit();
    }
    if ($method === "POST") {
        Auth::verifyCsrf($_SESSION["csrf"], $_POST["csrf"] ?? null);
        foreach ($_POST as $value) {
            if (!is_string($value)) {
                throw new HttpError(422, "Invalid form value.");
            }
        }
    }
    $user = isset($_SESSION["user_id"])
        ? $auth->user((int) $_SESSION["user_id"])
        : null;
    if ($page === "login") {
        if ($user) {
            redirect_to("/");
        }
        $loginError = null;
        if ($method === "POST") {
            try {
                $signedIn = $auth->login(
                    $_POST["email"] ?? "",
                    $_POST["password"] ?? "",
                    $_SERVER["REMOTE_ADDR"] ?? "unknown",
                );
                session_regenerate_id(true);
                $_SESSION = [
                    "user_id" => $signedIn["id"],
                    "signed_in_at" => time(),
                    "csrf" => bin2hex(random_bytes(32)),
                ];
                redirect_to("/");
            } catch (HttpError $error) {
                http_response_code($error->status);
                $loginError = $error->getMessage();
            }
        }
        render("login", ["user" => null, "error" => $loginError]);
    }
    if (!$user) {
        redirect_to("/?page=login");
    }
    if ($page === "logout" && $method === "POST") {
        $_SESSION = [];
        session_destroy();
        setcookie("thesisflow_session", "", [
            "expires" => time() - 3600,
            "path" => "/",
            "httponly" => true,
            "samesite" => "Strict",
            "secure" => getenv("SESSION_SECURE") === "true",
        ]);
        redirect_to("/?page=login");
    }
    if ($page === "create") {
        if ($user["role"] !== "student") {
            throw new HttpError(403, "Only students can create proposals.");
        }
        if ($method === "POST") {
            $id = $service->create($user, $_POST);
            $_SESSION["flash"] =
                "Proposal created. Add your document when it is ready.";
            redirect_to("/?page=detail&id=" . $id);
        }
        render("edit", [
            "user" => $user,
            "thesis" => null,
            "advisors" => $service->advisors(),
        ]);
    }
    if ($page === "edit") {
        $thesis = $service->get($id, $user);
        if (!$service->canEdit($thesis, $user)) {
            throw new HttpError(
                403,
                "This proposal cannot be edited right now.",
            );
        }
        if ($method === "POST") {
            $service->update($id, $user, $_POST);
            $_SESSION["flash"] = "Proposal updated.";
            redirect_to("/?page=detail&id=" . $id);
        }
        render("edit", [
            "user" => $user,
            "thesis" => $thesis,
            "advisors" => [],
        ]);
    }
    if ($page === "transition" && $method === "POST") {
        $service->transition(
            $id,
            $user,
            $_POST["status"] ?? "",
            (int) ($_POST["version"] ?? 0),
            $_POST["feedback"] ?? "",
        );
        $_SESSION["flash"] = "Workflow updated.";
        redirect_to("/?page=detail&id=" . $id);
    }
    if ($page === "comment" && $method === "POST") {
        $service->addComment($id, $user, $_POST["body"] ?? "");
        $_SESSION["flash"] = "Comment added.";
        redirect_to("/?page=detail&id=" . $id);
    }
    if ($page === "upload" && $method === "POST") {
        $service->addDocument(
            $id,
            $user,
            $_FILES["document"] ?? [],
            project_path(getenv("UPLOAD_DIR") ?: "storage/documents"),
        );
        $_SESSION["flash"] = "PDF uploaded.";
        redirect_to("/?page=detail&id=" . $id);
    }
    if ($page === "download" && $method === "GET") {
        $document = $service->document($id, $user);
        $path =
            project_path(getenv("UPLOAD_DIR") ?: "storage/documents") .
            "/" .
            $document["stored_name"];
        if (!is_file($path)) {
            throw new HttpError(404, "Document is unavailable.");
        }
        header("Content-Type: application/pdf");
        header(
            'Content-Disposition: attachment; filename="thesis-document.pdf"; filename*=UTF-8\'\'' .
                rawurlencode($document["name"]),
        );
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit();
    }
    if ($page === "detail" && $method === "GET") {
        $thesis = $service->get($id, $user);
        render("detail", [
            "user" => $user,
            "thesis" => $thesis,
            "canEdit" => $service->canEdit($thesis, $user),
            ...$service->details($id, $user),
        ]);
    }
    if ($page !== "dashboard" || $method !== "GET") {
        throw new HttpError(404, "Page not found.");
    }
    $search = is_string($_GET["q"] ?? null)
        ? mb_substr($_GET["q"], 0, 120)
        : "";
    $status = is_string($_GET["status"] ?? null) ? $_GET["status"] : "all";
    if (
        !in_array(
            $status,
            ["all", "draft", "submitted", "revision", "approved"],
            true,
        )
    ) {
        $status = "all";
    }
    $number = max(1, filter_var($_GET["p"] ?? 1, FILTER_VALIDATE_INT) ?: 1);
    render("dashboard", [
        "user" => $user,
        "search" => $search,
        "status" => $status,
        "stats" => $service->stats($user),
        "list" => $service->list($user, $search, $status, $number),
    ]);
} catch (HttpError $error) {
    http_response_code($error->status);
    render("error", [
        "user" => $user,
        "code" => $error->status,
        "message" => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(500);
    render("error", [
        "user" => $user,
        "code" => 500,
        "message" =>
            "Something went wrong. Check the server log and try again.",
    ]);
}
