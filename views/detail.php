<a class="back" href="/">← All proposals</a>
<div class="heading detail-heading">
<div>
<span class="mini-label">PROPOSAL #<?= str_pad(
    (string) $thesis["id"],
    3,
    "0",
    STR_PAD_LEFT,
) ?>
</span>
<h1>
<?= e($thesis["title"]) ?>
</h1>
<p class="muted">
<?= e(
    $thesis["student_name"],
) ?>
<span>·</span>
<?= e(
     $thesis["advisor_name"],
 ) ?>
</p>
</div>
<span class="badge <?= e($thesis["status"]) ?>">
<?= e(
    ucfirst($thesis["status"]),
) ?>
</span>
</div>
<div class="detail-grid">
<div>
<section class="panel padded">
<div class="section-heading">
<h2>Research abstract</h2>
<?php if (
    $canEdit
): ?>
<a class="button" href="/?page=edit&id=<?= $thesis[
    "id"
] ?>">Edit proposal ↗︎</a>
<?php endif; ?>
</div>
<p class="abstract">
<?= nl2br(
    e($thesis["abstract"]),
) ?>
</p>
</section>
<section class="panel padded">
<h2>Documents</h2>
<p class="muted small">PDFs are private to the student, assigned advisor and administrators.</p>
<?php
foreach (
    $documents
    as $document
): ?>
<a class="file-row" href="/?page=download&id=<?= $document[
    "id"
] ?>">
<span>▤ <?= e($document["name"]) ?>
</span>
<small>
<?= round(
    $document["size"] / 1024,
    1,
) ?> KB ↓</small>
</a>
<?php endforeach;
if (
    !$documents
): ?>
<p class="muted small">No documents attached.</p>
<?php endif;
if (
    $canEdit
): ?>
<form class="upload-form" enctype="multipart/form-data" method="post" action="/?page=upload&id=<?= $thesis[
    "id"
] ?>">
<?= csrf_field() ?>
<label>Attach a PDF · up to 5 MB<input name="document" type="file" accept="application/pdf,.pdf" required>
</label>
<button>Upload document</button>
</form>
<?php endif;
?>
</section>
<section class="panel padded">
<h2>Discussion</h2>
<form method="post" action="/?page=comment&id=<?= $thesis[
    "id"
] ?>">
<?= csrf_field() ?>
<label>
<span class="sr-only">Comment</span>
<textarea name="body" rows="3" maxlength="2000" required placeholder="Add a question or a specific piece of feedback…">
</textarea>
</label>
<button class="primary">Add comment</button>
</form>
<div class="comments">
<?php foreach (
    $comments
    as $comment
): ?>
<article>
<div>
<b>
<?= e($comment["name"]) ?>
</b>
<small>
<?= e(
    $comment["created_at"],
) ?> UTC</small>
</div>
<p>
<?= nl2br(
     e($comment["body"]),
 ) ?>
</p>
</article>
<?php endforeach; ?>
</div>
</section>
</div>
<div>
<section class="panel padded">
<h2>Next step</h2>
<?php if (
    $user["role"] === "student" &&
    in_array($thesis["status"], ["draft", "revision"], true)
): ?>
<p class="muted small">Review your abstract and documents, then send the proposal to your advisor.</p>
<form method="post" action="/?page=transition&id=<?= $thesis[
    "id"
] ?>">
<?= csrf_field() ?>
<input type="hidden" name="version" value="<?= $thesis[
    "version"
] ?>">
<input type="hidden" name="status" value="submitted">
<button class="primary full">Submit for review →</button>
</form>
<?php elseif (
    in_array($user["role"], ["advisor", "admin"], true) &&
    $thesis["status"] === "submitted"
): ?>
<form method="post" action="/?page=transition&id=<?= $thesis[
    "id"
] ?>">
<?= csrf_field() ?>
<input type="hidden" name="version" value="<?= $thesis[
    "version"
] ?>">
<label>Review feedback<textarea name="feedback" rows="4" maxlength="2000" placeholder="For a revision, explain what needs to change (10+ characters).">
</textarea>
</label>
<div class="review-actions">
<button name="status" value="approved" class="primary">Approve ✓</button>
<button name="status" value="revision">Request revision</button>
</div>
</form>
<?php elseif (
    $thesis["status"] === "approved"
): ?>
<div class="approved-box">
<span>✓</span>
<p>This proposal is approved.</p>
</div>
<?php else: ?>
<p class="muted small">
<?= $thesis[
    "status"
] === "submitted"
    ? "Waiting for advisor feedback."
    : "The student is preparing the next version." ?>
</p>
<?php endif; ?>
</section>
<section class="panel padded">
<h2>Activity</h2>
<div class="timeline">
<?php foreach (
    $activity
    as $entry
): ?>
<article>
<span>●</span>
<div>
<p>
<?= e($entry["action"]) ?>
</p>
<small>
<?= e(
    $entry["name"],
) ?>
<br>
<?= e(
    $entry["created_at"],
) ?> UTC</small>
</div>
</article>
<?php endforeach; ?>
</div>
</section>
</div>
</div>
