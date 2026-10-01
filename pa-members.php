<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Our PA | BHS Parents Association';
$activePage = 'members';
$members = paMembers(false);
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="shell"><p class="eyebrow">Our representatives</p><h1>Meet the Parents Association</h1><p>Parents volunteering their experience, energy, and time to serve the BHS community.</p></div></section>
<section class="section"><div class="shell"><div class="member-grid member-grid-all"><?php renderMemberCards($members); ?></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>

