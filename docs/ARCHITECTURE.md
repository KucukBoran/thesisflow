# Architecture

## Boundaries

```mermaid
flowchart TD
  Browser["Browser form"] --> Controller["Front controller"]
  Controller --> Auth["Session and CSRF checks"]
  Auth --> Service["ThesisService"]
  Service --> Access["Record ownership and workflow rules"]
  Access --> SQL["PDO / SQLite transaction"]
  Access --> Files["Private PDF storage"]
  Controller --> Views["Escaped HTML templates"]
```

The controller handles HTTP and delegates business rules to the service. Service
methods can be tested without a browser. There is no framework or ORM; prepared
PDO statements expose the underlying relational model.

## State machine

```mermaid
stateDiagram-v2
  [*] --> draft
  draft --> submitted: Student submits
  submitted --> revision: Advisor requests changes
  revision --> submitted: Student resubmits
  submitted --> approved: Advisor approves
  approved --> [*]
```

An admin can review all submitted records. A student cannot approve their own
proposal; an advisor cannot read another advisor's records merely by changing an
ID. Missing and inaccessible records both return 404.

## Relational model

```mermaid
erDiagram
  USERS ||--o{ THESES : submits
  USERS ||--o{ THESES : advises
  THESES ||--o{ DOCUMENTS : contains
  THESES ||--o{ COMMENTS : discusses
  THESES ||--o{ ACTIVITY : records
```

`comments.author_id` and `activity.user_id` also reference users. The service
verifies user roles when assigning an advisor; the foreign key alone only checks
that the user exists.

## Transactions and conflicts

Write operations begin an immediate transaction to lock writers before checking
the current version. Forms carry a version number. Stale edits and reviews return
409; successful edits, transitions and uploads increment it. Reviews and their
feedback/history commit together.

PDF storage and SQL cannot share one atomic transaction. The app validates and
moves a file to a random name, then rechecks record state under the database lock.
If SQL fails, it removes the moved file. A process crash between moving and
committing can still leave an orphan; a future maintenance job should reconcile
unreferenced files. The download route never accepts a path from the caller.

## Security scope

Sessions use strict mode, regenerated IDs on login, HttpOnly/SameSite cookies,
an eight-hour app lifetime and a per-session CSRF token. Every dynamic template
value is escaped with `e()`. SQL values are bound. Login failures are persisted
by hashed client IP. Behind a proxy, configure trusted forwarding and rate limits
at the edge; the demo intentionally does not trust arbitrary forwarded headers.

The app does not scan PDFs for malicious content, manage real accounts, handle
multi-institution tenancy or automatically migrate old database versions.
