<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = "ThesisFlow\\";
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . "/" . substr($class, strlen($prefix)) . ".php";
        if (is_file($file)) {
            require $file;
        }
    }
});

$envFile = dirname(__DIR__) . "/.env";
if (is_file($envFile)) {
    foreach (
        parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: []
        as $key => $value
    ) {
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );
}
function project_path(string $path): string
{
    return str_starts_with($path, "/") ||
        preg_match("/^[A-Za-z]:[\\\\\/]/", $path)
        ? $path
        : dirname(__DIR__) . "/" . $path;
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' .
        e($_SESSION["csrf"]) .
        '">';
}
function redirect_to(string $path): never
{
    header("Location: " . $path, true, 303);
    exit();
}
