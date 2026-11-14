<?php
declare(strict_types=1);
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
// Only serve known static assets directly; all application paths use the front controller.
if ($path === "/style.css") {
    return false;
}
require __DIR__ . "/index.php";
