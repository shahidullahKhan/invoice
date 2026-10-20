<?php /** Shared page shell. @var string $content @var array $user */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>HAR Invoice Generator</title>
    <link rel="icon" href="https://content.harstatic.com/media/icons/clientportal/client_portal_logo.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6f8; }
        .table td, .table th { font-size: 14px; vertical-align: top; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        /* Brand lockup: HAR logo + product name */
        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .brand img { width: 40px; height: 40px; border-radius: 10px; }
        .brand .divider { width: 1px; height: 28px; background: #dfe3f0; }
        .brand .name { font-weight: 700; font-size: 18px; color: #0b1b4d; line-height: 1.1; }
        .brand .sub { font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #0738cd; }
        .app-nav .nav-link { color: #4a5268; font-weight: 500; border-radius: 8px; padding: 6px 12px; }
        .app-nav .nav-link:hover { background: #f0f3fb; color: #0738cd; }
        .app-nav .nav-link.active { background: #e8eefc; color: #0738cd; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand bg-white border-bottom mb-4 py-2">
    <div class="container flex-wrap gap-2">
        <a class="brand" href="<?= url() ?>">
            <img src="https://content.harstatic.com/media/icons/clientportal/client_portal_logo.svg" alt="HAR">
            <span class="divider"></span>
            <span>
                <span class="d-block sub">HAR.com</span>
                <span class="d-block name">Invoice Generator</span>
            </span>
        </a>
        <?php $current = $_GET['a'] ?? 'home'; ?>
        <div class="navbar-nav app-nav gap-1 ms-auto">
            <?php foreach (['home' => 'Home', 'new' => 'New Invoice', 'history' => 'History', 'settings' => 'Settings'] as $route => $label): ?>
                <a class="nav-link <?= $current === $route ? 'active' : '' ?>" href="<?= url($route) ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
<main class="container pb-5">
    <?php if ($f = flash()): ?>
        <div class="alert alert-<?= e($f[0]) ?>"><?= e($f[1]) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>
<script>
    // Eye buttons: toggle a password-style input, or reveal/hide a masked inline value
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-reveal-input], [data-reveal]');
        if (!btn) return;
        if (btn.hasAttribute('data-reveal-input')) {
            var input = btn.parentElement.querySelector('input');
            input.type = input.type === 'password' ? 'text' : 'password';
        } else {
            var val = btn.previousElementSibling, shown = val.textContent !== '••••';
            val.textContent = shown ? '••••' : val.dataset.value;
        }
        btn.classList.toggle('active');
    });
</script>
</body>
</html>
