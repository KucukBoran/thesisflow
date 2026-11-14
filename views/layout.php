<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#223c36">
<title>ThesisFlow · Research, in progress</title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<?php if ($user): ?>
<div class="app-shell">
<aside>
<a href="/" class="brand">
<span class="brand-mark">t/f</span>thesisflow<span>.</span>
</a>
<p class="workspace-label">ACADEMIC WORKSPACE</p>
<a class="navigation" href="/">▦ <span>Thesis workspace</span>
</a>
<div class="sidebar-note">
<span class="mini-label">FROM IDEA TO APPROVAL</span>
<h2>Good research<br>takes a process.</h2>
<p>A shared space for proposals, feedback and progress.</p>
<span class="line-art">↗︎</span>
</div>
<div class="account">
<div class="avatar">
<?= e(
    mb_substr($user["name"], 0, 1),
) ?>
</div>
<div>
<b>
<?= e($user["name"]) ?>
</b>
<small>
<?= e(
    ucfirst($user["role"]),
) ?>
</small>
</div>
</div>
<form method="post" action="/?page=logout">
<?= csrf_field() ?>
<button class="signout">Sign out ↗︎</button>
</form>
</aside>
<main>
<header class="topbar">
<span>Academic workspace <span class="separator">/</span>
<b>Overview</b>
</span>
<span class="demo-pill">● Portfolio demo</span>
<form class="mobile-logout" method="post" action="/?page=logout">
<?= csrf_field() ?>
<button>Sign out</button>
</form>
</header>
<div class="main-content">
<?php if (isset($_SESSION["flash"])): ?>
<div class="flash" role="status">
<?= e(
    $_SESSION["flash"],
) ?>
</div>
<?php unset($_SESSION["flash"]);endif; ?>
<?= $content ?>
</div>
<footer class="page-footer">ThesisFlow · Fictional academic records · Local demo</footer>
</main>
</div>
<?php else: ?>
<?= $content ?>
<?php endif; ?>
</body>
</html>
