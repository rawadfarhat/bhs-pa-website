<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'BHS Parents Association';
$activePage = 'home';
$featuredMembers = paMembers(true);
$upcomingEvents = upcomingEvents(publicEvents());
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-image" role="img" aria-label="Broummana High School community"></div>
    <div class="shell hero-content"><p class="eyebrow">Broummana High School</p><h1>Stronger families.<br>A stronger school.</h1><p>We connect parents, support students, and turn shared ideas into meaningful action for the whole BHS community.</p><div class="button-row"><a class="button" href="<?= e(url('subscribe.php')) ?>">Stay connected</a><a class="button button-secondary" href="<?= e(url('pa-members.php')) ?>">Meet the PA</a></div></div>
</section>
<?php if ($upcomingEvents !== []): ?>
<section class="section" aria-labelledby="upcoming-events-heading"><div class="shell">
    <div class="section-heading"><div><p class="eyebrow">Community calendar</p><h2 id="upcoming-events-heading">Upcoming events</h2></div><a class="text-link" href="<?= e(url('events.php')) ?>">View all events <span aria-hidden="true">→</span></a></div>
    <div class="event-list-grid">
        <?php foreach ($upcomingEvents as $event): ?>
        <article class="event-card">
            <p class="eyebrow"><time datetime="<?= e(substr($event['event_date'], 0, 10)) ?>"><?= e(date('F j, Y', strtotime($event['event_date']))) ?></time></p>
            <h3><a href="<?= e(url('event.php?id=' . (int) $event['id'])) ?>"><?= e($event['name']) ?></a></h3>
            <?php if (!empty($event['description'])): ?><p class="event-description"><?= e($event['description']) ?></p><?php endif; ?>
            <a class="text-link" href="<?= e(url('event.php?id=' . (int) $event['id'])) ?>">View event <span aria-hidden="true">→</span></a>
        </article>
        <?php endforeach; ?>
    </div>
</div></section>
<?php endif; ?>
<section class="section intro-section"><div class="shell split"><div><p class="eyebrow">Who we are</p><h2>A parent voice with a practical purpose</h2></div><div class="lead"><p>The BHS Parents Association brings families and the school closer together. We listen, communicate, and help create initiatives that make school life richer for every student.</p><p>Our work is guided by openness, cooperation, and the Quaker values at the heart of Broummana High School.</p></div></div></section>
<section class="section section-tint"><div class="shell"><div class="section-heading"><div><p class="eyebrow">The team</p><h2>Meet your PA representatives</h2></div><a class="text-link" href="<?= e(url('pa-members.php')) ?>">View every member <span aria-hidden="true">→</span></a></div><div class="member-grid"><?php renderMemberCards($featuredMembers); ?></div></div></section>
<section class="section"><div class="shell action-panel"><div><p class="eyebrow">Stay in the loop</p><h2>Make sure PA updates reach your family</h2><p>Share or update your preferred contact details securely. It only takes a minute.</p></div><a class="button" href="<?= e(url('subscribe.php')) ?>">Register your details</a></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>

