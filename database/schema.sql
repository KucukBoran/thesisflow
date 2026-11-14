CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('student', 'advisor', 'admin'))
);
CREATE TABLE IF NOT EXISTS theses (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    abstract TEXT NOT NULL,
    student_id INTEGER NOT NULL REFERENCES users(id),
    advisor_id INTEGER NOT NULL REFERENCES users(id),
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft', 'submitted', 'revision', 'approved')),
    version INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS theses_student_status ON theses(student_id, status);
CREATE INDEX IF NOT EXISTS theses_advisor_status ON theses(advisor_id, status);
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY,
    thesis_id INTEGER NOT NULL REFERENCES theses(id) ON DELETE CASCADE,
    author_id INTEGER NOT NULL REFERENCES users(id),
    body TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY,
    thesis_id INTEGER NOT NULL REFERENCES theses(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    stored_name TEXT NOT NULL UNIQUE,
    size INTEGER NOT NULL CHECK(size > 0),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS activity (
    id INTEGER PRIMARY KEY,
    thesis_id INTEGER NOT NULL REFERENCES theses(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id),
    action TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS login_attempts (
    client_hash TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL,
    reset_at INTEGER NOT NULL
);
