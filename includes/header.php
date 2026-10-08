<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'BHS Parents Association';
$activePage = $activePage ?? '';
$publishedEvents = publicEvents();
$nav = [
    'home' => ['Home', 'index.php'],
    'members' => ['Our PA', 'pa-members.php'],
    'connect' => ['Stay Connected', 'subscribe.php'],
    'terms' => ['Terms', 'terms.php'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Broummana High School Parents Association — connecting families, supporting students, and strengthening our school community.">
    <meta name="theme-color" content="#123f4a">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e(url('assets/images/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/styles.css?v=2')) ?>">
    <script src="<?= e(url('assets/site.js?v=1')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="shell header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="BHS Parents Association home">
            <img src="<?= e(url('assets/images/pa-logo-h.png')) ?>" alt="" width="54" height="54">
            <span><strong>BHS</strong><small>Parents Association</small></span>
        </a>
        <button class="menu-toggle icon-button" type="button" aria-expanded="false" aria-controls="site-nav"><span class="sr-only">Toggle menu</span><span></span><span></span><span></span></button>
        <nav id="site-nav" class="site-nav" aria-label="Main navigation">
            <?php foreach ($nav as $key => [$label, $href]): ?>
                <a href="<?= e(url($href)) ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($publishedEvents !== []): ?><div class="nav-dropdown"><a href="<?= e(url('events.php')) ?>"<?= $activePage === 'events' ? ' aria-current="page"' : '' ?>>Events <span aria-hidden="true">▾</span></a><div class="nav-submenu"><?php foreach ($publishedEvents as $navEvent): ?><a href="<?= e(url('event.php?id=' . (int) $navEvent['id'])) ?>"><?= e($navEvent['name']) ?></a><?php endforeach; ?></div></div><?php endif; ?>
            <a class="button button-small" href="<?= e(url('subscribe.php')) ?>">Register your details</a>
        </nav>
    </div>
</header>
<main id="main-content">

