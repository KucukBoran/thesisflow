<?php
declare(strict_types=1);
namespace ThesisFlow;

use PDO;
use Throwable;

final class ThesisService
{
    public function __construct(private PDO $db) {}

    private function transaction(callable $action): mixed
    {
        $this->db->exec("BEGIN IMMEDIATE");
        try {
            $result = $action();
            $this->db->exec("COMMIT");
            return $result;
        } catch (Throwable $error) {
            $this->db->exec("ROLLBACK");
            throw $error;
        }
    }

    private function audit(int $id, array $user, string $message): void
    {
        $q = $this->db->prepare(
            "INSERT INTO activity(thesis_id, user_id, action) VALUES (?, ?, ?)",
        );
        $q->execute([$id, $user["id"], $message]);
    }

    public function get(int $id, array $user): array
    {
        $q = $this->db->prepare(
            "SELECT t.*, s.name student_name, a.name advisor_name FROM theses t JOIN users s ON t.student_id=s.id JOIN users a ON t.advisor_id=a.id WHERE t.id=?",
        );
        $q->execute([$id]);
        $thesis = $q->fetch();
        // Return the same 404 for missing and inaccessible records.
        if (
            !$thesis ||
            !(
                $user["role"] === "admin" ||
                ($user["role"] === "student" &&
                    $thesis["student_id"] === $user["id"]) ||
                ($user["role"] === "advisor" &&
                    $thesis["advisor_id"] === $user["id"])
            )
        ) {
            throw new HttpError(404, "Thesis not found.");
        }
        return $thesis;
    }

    public function list(
        array $user,
        string $search = "",
        string $status = "all",
        int $page = 1,
    ): array {
        if (
            !in_array(
                $status,
                ["all", "draft", "submitted", "revision", "approved"],
                true,
            )
        ) {
            $status = "all";
        }
        $page = max(1, $page);
        $where = ["(t.title LIKE ? ESCAPE '\\' OR s.name LIKE ? ESCAPE '\\')"];
        $term =
            "%" .
            str_replace(
                ["\\", "%", "_"],
                ["\\\\", "\\%", "\\_"],
                mb_substr($search, 0, 120),
            ) .
            "%";
        $args = [$term, $term];
        if ($user["role"] !== "admin") {
            $column = $user["role"] === "student" ? "student_id" : "advisor_id";
            $where[] = "t.$column = ?";
            $args[] = $user["id"];
        }
        if ($status !== "all") {
            $where[] = "t.status = ?";
            $args[] = $status;
        }
        $sql =
            " FROM theses t JOIN users s ON s.id=t.student_id JOIN users a ON a.id=t.advisor_id WHERE " .
            implode(" AND ", $where);
        $q = $this->db->prepare("SELECT COUNT(*)" . $sql);
        $q->execute($args);
        $total = (int) $q->fetchColumn();
        $q = $this->db->prepare(
            "SELECT t.*, s.name student_name, a.name advisor_name" .
                $sql .
                " ORDER BY t.updated_at DESC, t.id DESC LIMIT 10 OFFSET ?",
        );
        $args[] = ($page - 1) * 10;
        foreach ($args as $i => $arg) {
            $q->bindValue(
                $i + 1,
                $arg,
                is_int($arg) ? PDO::PARAM_INT : PDO::PARAM_STR,
            );
        }
        $q->execute();
        return ["items" => $q->fetchAll(), "total" => $total, "page" => $page];
    }

    public function stats(array $user): array
    {
        $sql = "SELECT status, COUNT(*) count FROM theses";
        $args = [];
        if ($user["role"] !== "admin") {
            $sql .=
                " WHERE " .
                ($user["role"] === "student" ? "student_id" : "advisor_id") .
                "=?";
            $args[] = $user["id"];
        }
        $q = $this->db->prepare($sql . " GROUP BY status");
        $q->execute($args);
        $result = array_fill_keys(
            ["draft", "submitted", "revision", "approved"],
            0,
        );
        foreach ($q as $row) {
            $result[$row["status"]] = $row["count"];
        }
        return $result;
    }

    public function advisors(): array
    {
        return $this->db
            ->query(
                "SELECT id, name FROM users WHERE role='advisor' ORDER BY name",
            )
            ->fetchAll();
    }

    private function validate(array $input): array
    {
        $title = trim((string) ($input["title"] ?? ""));
        $abstract = trim((string) ($input["abstract"] ?? ""));
        if (mb_strlen($title) < 5 || mb_strlen($title) > 180) {
            throw new HttpError(422, "Title must be 5–180 characters.");
        }
        if (mb_strlen($abstract) < 30 || mb_strlen($abstract) > 5000) {
            throw new HttpError(422, "Abstract must be 30–5000 characters.");
        }
        return [$title, $abstract];
    }

    public function create(array $user, array $input): int
    {
        if ($user["role"] !== "student") {
            throw new HttpError(403, "Only students create thesis proposals.");
        }
        [$title, $abstract] = $this->validate($input);
        $advisor = (int) ($input["advisor_id"] ?? 0);
        if (!in_array($advisor, array_column($this->advisors(), "id"), true)) {
            throw new HttpError(422, "Choose an advisor.");
        }
        return $this->transaction(function () use (
            $user,
            $title,
            $abstract,
            $advisor,
        ): int {
            $q = $this->db->prepare(
                "INSERT INTO theses(title, abstract, student_id, advisor_id) VALUES (?, ?, ?, ?)",
            );
            $q->execute([$title, $abstract, $user["id"], $advisor]);
            $id = (int) $this->db->lastInsertId();
            $this->audit($id, $user, "Created thesis proposal");
            return $id;
        });
    }

    public function canEdit(array $thesis, array $user): bool
    {
        return in_array($thesis["status"], ["draft", "revision"], true) &&
            ($user["role"] === "admin" ||
                ($user["role"] === "student" &&
                    $thesis["student_id"] === $user["id"]));
    }

    public function update(int $id, array $user, array $input): void
    {
        [$title, $abstract] = $this->validate($input);
        $this->transaction(function () use (
            $id,
            $user,
            $input,
            $title,
            $abstract,
        ): void {
            $old = $this->get($id, $user);
            if (!$this->canEdit($old, $user)) {
                throw new HttpError(
                    403,
                    "This thesis cannot be edited in its current state.",
                );
            }
            if ($old["version"] !== (int) ($input["version"] ?? 0)) {
                throw new HttpError(
                    409,
                    "This thesis changed. Reload before saving.",
                );
            }
            $q = $this->db->prepare(
                "UPDATE theses SET title=?, abstract=?, version=version+1, updated_at=datetime('now') WHERE id=?",
            );
            $q->execute([$title, $abstract, $id]);
            $this->audit($id, $user, "Updated proposal");
        });
    }

    public function transition(
        int $id,
        array $user,
        string $target,
        int $version,
        string $feedback = "",
    ): void {
        $this->transaction(function () use (
            $id,
            $user,
            $target,
            $version,
            $feedback,
        ): void {
            $old = $this->get($id, $user);
            if ($old["version"] !== $version) {
                throw new HttpError(
                    409,
                    "This thesis changed. Reload before reviewing.",
                );
            }
            $studentSubmit =
                $user["role"] === "student" &&
                in_array($old["status"], ["draft", "revision"], true) &&
                $target === "submitted";
            $review =
                in_array($user["role"], ["advisor", "admin"], true) &&
                $old["status"] === "submitted" &&
                in_array($target, ["revision", "approved"], true);
            if (!$studentSubmit && !$review) {
                throw new HttpError(
                    403,
                    "This transition is not allowed for your role.",
                );
            }
            $feedback = trim($feedback);
            if ($target === "revision" && mb_strlen($feedback) < 10) {
                throw new HttpError(
                    422,
                    "Explain the requested revision in at least 10 characters.",
                );
            }
            if (mb_strlen($feedback) > 2000) {
                throw new HttpError(422, "Feedback is too long.");
            }
            $q = $this->db->prepare(
                "UPDATE theses SET status=?, version=version+1, updated_at=datetime('now') WHERE id=?",
            );
            $q->execute([$target, $id]);
            if ($feedback !== "") {
                $q = $this->db->prepare(
                    "INSERT INTO comments(thesis_id, author_id, body) VALUES (?, ?, ?)",
                );
                $q->execute([$id, $user["id"], $feedback]);
            }
            $this->audit($id, $user, $old["status"] . " → " . $target);
        });
    }

    public function addComment(int $id, array $user, string $body): void
    {
        $this->get($id, $user);
        $body = trim($body);
        if ($body === "" || mb_strlen($body) > 2000) {
            throw new HttpError(
                422,
                "Comments must contain 1–2000 characters.",
            );
        }
        $q = $this->db->prepare(
            "INSERT INTO comments(thesis_id, author_id, body) VALUES (?, ?, ?)",
        );
        $q->execute([$id, $user["id"], $body]);
    }

    public function details(int $id, array $user): array
    {
        $this->get($id, $user);
        $q = $this->db->prepare(
            "SELECT c.*, u.name FROM comments c JOIN users u ON u.id=c.author_id WHERE thesis_id=? ORDER BY c.id DESC",
        );
        $q->execute([$id]);
        $comments = $q->fetchAll();
        $q = $this->db->prepare(
            "SELECT a.*, u.name FROM activity a JOIN users u ON u.id=a.user_id WHERE thesis_id=? ORDER BY a.id DESC",
        );
        $q->execute([$id]);
        $activity = $q->fetchAll();
        $q = $this->db->prepare(
            "SELECT id, name, size, created_at FROM documents WHERE thesis_id=? ORDER BY id DESC",
        );
        $q->execute([$id]);
        return [
            "comments" => $comments,
            "activity" => $activity,
            "documents" => $q->fetchAll(),
        ];
    }

    public function addDocument(
        int $id,
        array $user,
        array $file,
        string $directory,
    ): void {
        $thesis = $this->get($id, $user);
        if (!$this->canEdit($thesis, $user)) {
            throw new HttpError(
                403,
                "Documents can only be added to editable proposals.",
            );
        }
        if (
            ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
            !is_uploaded_file($file["tmp_name"] ?? "")
        ) {
            throw new HttpError(422, "Choose a PDF file under 5 MB.");
        }
        $size = filesize($file["tmp_name"]);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
        if (
            !$size ||
            $size > 5 * 1024 * 1024 ||
            $mime !== "application/pdf" ||
            file_get_contents($file["tmp_name"], false, null, 0, 5) !== "%PDF-"
        ) {
            throw new HttpError(
                422,
                "Only PDF documents under 5 MB are accepted.",
            );
        }
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $storedName = bin2hex(random_bytes(20)) . ".pdf";
        $path = $directory . "/" . $storedName;
        if (!move_uploaded_file($file["tmp_name"], $path)) {
            throw new HttpError(500, "Could not store the document.");
        }
        try {
            $this->transaction(function () use (
                $id,
                $user,
                $file,
                $storedName,
                $size,
            ): void {
                // Recheck state under the write lock; submission may have raced the upload.
                if (!$this->canEdit($this->get($id, $user), $user)) {
                    throw new HttpError(
                        409,
                        "This thesis changed during upload.",
                    );
                }
                $q = $this->db->prepare(
                    "INSERT INTO documents(thesis_id, name, stored_name, size) VALUES (?, ?, ?, ?)",
                );
                $name = mb_substr(
                    basename(str_replace("\\", "/", $file["name"])),
                    0,
                    180,
                );
                $q->execute([$id, $name, $storedName, $size]);
                $this->db
                    ->prepare(
                        "UPDATE theses SET version=version+1, updated_at=datetime('now') WHERE id=?",
                    )
                    ->execute([$id]);
                $this->audit($id, $user, "Uploaded a PDF document");
            });
        } catch (Throwable $error) {
            unlink($path);
            throw $error;
        }
    }

    public function document(int $id, array $user): array
    {
        $q = $this->db->prepare("SELECT * FROM documents WHERE id=?");
        $q->execute([$id]);
        $document = $q->fetch();
        if (!$document) {
            throw new HttpError(404, "Document not found.");
        }
        $this->get($document["thesis_id"], $user);
        return $document;
    }
}
