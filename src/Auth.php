<?php
declare(strict_types=1);
namespace ThesisFlow;

use PDO;

final class Auth
{
    public function __construct(private PDO $db) {}

    public function login(
        string $email,
        string $password,
        string $client,
    ): array {
        $key = hash("sha256", $client);
        $now = time();
        $q = $this->db->prepare(
            "DELETE FROM login_attempts WHERE reset_at <= ?",
        );
        $q->execute([$now]);
        $q = $this->db->prepare(
            "SELECT * FROM login_attempts WHERE client_hash = ?",
        );
        $q->execute([$key]);
        $attempt = $q->fetch();
        if ($attempt && $attempt["attempts"] >= 10) {
            throw new HttpError(
                429,
                "Too many attempts. Try again in 15 minutes.",
            );
        }

        $q = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $q->execute([strtolower(trim($email))]);
        $user = $q->fetch();
        // A fixed dummy hash keeps the password verification path for unknown users.
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = password_verify($password, $user["password_hash"] ?? $dummy);
        if (!$valid || !$user || strlen($password) > 128) {
            $q = $this->db->prepare(
                "INSERT INTO login_attempts VALUES (?, 1, ?) ON CONFLICT(client_hash) DO UPDATE SET attempts=attempts+1",
            );
            $q->execute([$key, $now + 900]);
            throw new HttpError(401, "Email or password is incorrect.");
        }
        $q = $this->db->prepare(
            "DELETE FROM login_attempts WHERE client_hash = ?",
        );
        $q->execute([$key]);
        unset($user["password_hash"]);
        return $user;
    }

    public function user(int $id): ?array
    {
        $q = $this->db->prepare(
            "SELECT id, name, email, role FROM users WHERE id = ?",
        );
        $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    public static function verifyCsrf(string $expected, mixed $actual): void
    {
        if (
            !is_string($actual) ||
            $expected === "" ||
            !hash_equals($expected, $actual)
        ) {
            throw new HttpError(
                403,
                "Your form expired. Refresh the page and try again.",
            );
        }
    }
}
