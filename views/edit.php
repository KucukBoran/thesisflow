<a class="back" href="<?= $thesis
    ? "/?page=detail&id=" . $thesis["id"]
    : "/" ?>">← Back</a>
<div class="heading">
<div>
<span class="mini-label">TURN A QUESTION INTO A PLAN</span>
<h1>
<?= $thesis
    ? "Edit proposal"
    : "New thesis proposal" ?>
</h1>
<p class="muted">Start with a clear title and a focused research question.</p>
</div>
</div>
<section class="panel form-panel">
<form method="post" action="<?= $thesis
    ? "/?page=edit&id=" . $thesis["id"]
    : "/?page=create" ?>">
<?= csrf_field() ?>
<?php if ($thesis): ?>
<input type="hidden" name="version" value="<?= $thesis[
    "version"
] ?>">
<?php endif;
?>
<label>Proposal title<input name="title" minlength="5" maxlength="180" required value="<?= e(
    $thesis["title"] ?? "",
) ?>" placeholder="What are you investigating?">
</label>
<label>Abstract<textarea name="abstract" minlength="30" maxlength="5000" rows="8" required placeholder="Explain the problem, your approach and how you will evaluate the result.">
<?= e(
    $thesis["abstract"] ?? "",
) ?>
</textarea>
<small>30–5000 characters. You can refine this before submission.</small>
</label>
<?php if (
    !$thesis
): ?>
<label>Advisor<select name="advisor_id" required>
<option value="">Select an advisor</option>
<?php foreach (
    $advisors
    as $advisor
): ?>
<option value="<?= $advisor["id"] ?>">
<?= e(
    $advisor["name"],
) ?>
</option>
<?php endforeach; ?>
</select>
</label>
<?php endif; ?>
<button class="primary">
<?= $thesis
    ? "Save changes"
    : "Create proposal" ?> →</button>
</form>
</section>
