# Testing

Verified on **2026-11-14**, using PHP **8.4.27** and Python **3.12.14** on Linux.

| Check | Observed result |
| --- | --- |
| PHP syntax checks | All 15 PHP files passed |
| `php tests/run.php` | 12 tests passed |
| `python3 tests/http_smoke.py` | Passed against an isolated PHP server and temporary data |
| Desktop browser, 1440 px | Advisor dashboard and proposal detail inspected |
| Mobile browser, 390 px | No document-level horizontal overflow |
| Browser JavaScript errors | None observed |

The HTTP check logs in, opens create/edit forms, rejects missing CSRF and another
student's record, creates a proposal, checks output escaping, uploads/downloads
a PDF, rejects direct storage access, logs out and rejects a second student's
attempt to download the private document.

Service tests cover record ownership, advisor assignment, proposal states,
required revision feedback, stale writes, scoped lists/counts, comments, document
access, CSRF, persistent login throttling and HTML escaping.

## Validation scope

The CI workflow targets Ubuntu with PHP 8.4. Docker image builds, container
execution and native Windows execution are outside the verified test matrix.

## GitHub Actions

The Checks workflow runs on pushes and pull requests.
[View workflow results](https://github.com/KucukBoran/thesisflow/actions/workflows/ci.yml).
The README badge displays the latest workflow status.
