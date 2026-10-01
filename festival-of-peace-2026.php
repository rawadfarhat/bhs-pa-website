<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Festival of Peace 2026 | BHS Parents Association';
$activePage = 'events';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="shell"><p class="eyebrow">Community event</p><h1>Festival of Peace 2026</h1><p>Event information and posters from the BHS community.</p></div></section>
<section class="section"><div class="shell"><div class="poster-grid"><?php for ($i=1;$i<=8;$i++): $file=sprintf('Poster-%02d.jpg',$i); ?><a href="<?= e(url('assets/images/festival-of-peace-2026/'.$file)) ?>" target="_blank"><img src="<?= e(url('assets/images/festival-of-peace-2026/'.$file)) ?>" alt="Festival of Peace 2026 poster <?= $i ?>" loading="lazy"></a><?php endfor; ?></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
