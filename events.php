<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$events = publicEvents();
$pageTitle = 'Events | BHS Parents Association';
$activePage = 'events';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="shell"><p class="eyebrow">Community calendar</p><h1>Events</h1><p>Discover events published by the BHS Parents Association.</p></div></section>
<section class="section"><div class="shell event-list-grid"><?php foreach ($events as $event): ?><article class="event-card"><p class="eyebrow"><?= !empty($event['event_date']) ? e(date('F j, Y', strtotime($event['event_date']))) : 'Community event' ?></p><h2><a href="<?= e(url('event.php?id=' . (int) $event['id'])) ?>"><?= e($event['name']) ?></a></h2><?php if (!empty($event['description'])): ?><p><?= e($event['description']) ?></p><?php endif; ?><a class="text-link" href="<?= e(url('event.php?id=' . (int) $event['id'])) ?>">View event <span aria-hidden="true">→</span></a></article><?php endforeach; ?></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
