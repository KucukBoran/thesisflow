<div class="heading">
<div>
<span class="mini-label">A CLEARER PATH TO COMPLETION</span>
<h1>Your research workspace<span>.</span>
</h1>
<p class="muted">
<?= $user[
    "role"
] === "student"
    ? "Your proposals, documents and next steps."
    : "Review ideas. Give direction. Keep research moving." ?>
</p>
</div>
<?php if (
    $user["role"] === "student"
): ?>
<a href="/?page=create" class="button primary">＋ New proposal</a>
<?php endif; ?>
</div>
<div class="stats">
<article>
<span>Total proposals <i>▤</i>
</span>
<strong>
<?= array_sum(
    $stats,
) ?>
</strong>
<small>Within your workspace</small>
</article>
<article>
<span>Awaiting review <i>◷</i>
</span>
<strong>
<?= $stats[
    "submitted"
] ?>
</strong>
<small>Ready for advisor feedback</small>
</article>
<article>
<span>In revision <i>↻</i>
</span>
<strong>
<?= $stats[
    "revision"
] ?>
</strong>
<small>Ideas being refined</small>
</article>
<article>
<span>Approved <i>✓</i>
</span>
<strong>
<?= $stats[
    "approved"
] ?>
</strong>
<small>A milestone worth celebrating</small>
</article>
</div>
<section class="panel">
<div class="panel-title">
<div>
<h2>Thesis proposals <span class="count">
<?= $list[
    "total"
] ?>
</span>
</h2>
<p class="muted">A focused view of the work that needs your attention.</p>
</div>
<span class="scope-label">
<?= e(
    ucfirst($user["role"]),
) ?> view</span>
</div>
<form method="get" class="filters">
<label class="search">
<span class="sr-only">Search proposals</span>
<input name="q" value="<?= e(
     $search,
 ) ?>" placeholder="Search title or student…">
</label>
<label>
<span class="sr-only">Status</span>
<select name="status">
<?php foreach (
    [
        "all" => "All statuses",
        "draft" => "Draft",
        "submitted" => "Awaiting review",
        "revision" => "In revision",
        "approved" => "Approved",
    ]
    as $value => $label
): ?>
<option value="<?= $value ?>" <?= $status === $value
    ? "selected"
    : "" ?>>
<?= $label ?>
</option>
<?php endforeach; ?>
</select>
</label>
<button>Filter →</button>
<?php if (
    $search !== "" ||
    $status !== "all"
): ?>
<a class="clear" href="/">Clear</a>
<?php endif; ?>
</form>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>PROPOSAL</th>
<th>STUDENT</th>
<th>STATUS</th>
<th>UPDATED</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach (
    $list["items"]
    as $thesis
): ?>
<tr>
<td>
<a class="proposal" href="/?page=detail&id=<?= $thesis[
    "id"
] ?>">
<span class="document-icon">▤</span>
<span>
<b>
<?= e(
    $thesis["title"],
) ?>
</b>
<small>Advisor: <?= e(
    $thesis["advisor_name"],
) ?>
</small>
</span>
</a>
</td>
<td>
<span class="student-name">
<?= e(
    $thesis["student_name"],
) ?>
</span>
</td>
<td>
<span class="badge <?= e(
    $thesis["status"],
) ?>">
<span>●</span>
<?= e(
    [
        "draft" => "Draft",
        "submitted" => "Awaiting review",
        "revision" => "In revision",
        "approved" => "Approved",
    ][$thesis["status"]],
) ?>
</span>
</td>
<td class="muted">
<?= e(
    date("d M Y", strtotime($thesis["updated_at"])),
) ?>
</td>
<td>
<a class="open-arrow" aria-label="Open <?= e(
    $thesis["title"],
) ?>" href="/?page=detail&id=<?= $thesis[
    "id"
] ?>">↗︎</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php if (
    !$list["items"]
): ?>
<div class="empty">
<h3>No proposals here yet.</h3>
<p>Try another filter or create your first proposal.</p>
</div>
<?php endif; ?>
</div>
<div class="table-footer">
<span>
<?= $list[
    "total"
] ?> proposals · Page <?= $list["page"] ?> of <?= max(
     1,
     (int) ceil($list["total"] / 10),
 ) ?>
</span>
<div>
<?php
if ($list["page"] > 1): ?>
<a href="/?<?= e(
    http_build_query([
        "q" => $search,
        "status" => $status,
        "p" => $list["page"] - 1,
    ]),
) ?>">← Previous</a>
<?php endif;
if ($list["page"] * 10 < $list["total"]): ?>
<a href="/?<?= e(
    http_build_query([
        "q" => $search,
        "status" => $status,
        "p" => $list["page"] + 1,
    ]),
) ?>">Next →</a>
<?php endif;
?>
</div>
</div>
</section>
<div class="process-note">
<span>↳</span>
<p>
<b>Feedback moves research forward.</b> Request a revision with clear, actionable notes so the next submission is stronger.</p>
</div>
