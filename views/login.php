<div class="login-shell">
<section class="login-intro">
<a href="/" class="brand">
<span class="brand-mark">t/f</span>thesisflow<span>.</span>
</a>
<div>
<span class="mini-label">RESEARCH, IN PROGRESS</span>
<h1>A little structure.<br>A lot of possibility.</h1>
<p>Bring students, advisors and meaningful feedback into one shared workflow.</p>
<div class="paper-stack">
<div>
<small>THESIS PROPOSAL</small>
<h2>From a good question<br>to a better answer.</h2>
<span>Research · Review · Refine</span>
</div>
</div>
</div>
<small>Made for thoughtful work.</small>
</section>
<section class="login-panel">
<form method="post" action="/?page=login">
<?= csrf_field() ?>
<span class="mini-label">YOUR RESEARCH WORKSPACE</span>
<h2>Pick up where you left off.</h2>
<p class="muted">Sign in to view proposals and feedback.</p>
<?php if (
    $error
): ?>
<p class="error" role="alert">
<?= e(
    $error,
) ?>
</p>
<?php endif; ?>
<label>Email<input type="email" name="email" value="advisor@example.test" autocomplete="username" required>
</label>
<label>Password<input type="password" name="password" value="DemoPass123!" autocomplete="current-password" required>
</label>
<button class="primary full">Open workspace →</button>
<div class="demo-box">
<b>Explore the demo</b>
<p>Use <code>student@example.test</code>, <code>advisor@example.test</code> or <code>admin@example.test</code>.<br>All use <code>DemoPass123!</code>.</p>
</div>
</form>
</section>
</div>
