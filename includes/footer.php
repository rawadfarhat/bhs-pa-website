<?php declare(strict_types=1); ?>
</main>
<footer class="site-footer">
    <div class="shell footer-grid">
        <div>
            <a class="brand brand-footer" href="<?= e(url('index.php')) ?>"><img src="<?= e(url('assets/images/pa-logo.png')) ?>" alt="" width="52" height="52"><span><strong>BHS</strong><small>Parents Association</small></span></a>
            <p>Parents working together for a connected, supportive BHS community.</p>
        </div>
        <div><h2>Explore</h2><nav aria-label="Footer navigation"><a href="<?= e(url('index.php')) ?>">Home</a><a href="<?= e(url('pa-members.php')) ?>">Our PA</a><a href="<?= e(url('subscribe.php')) ?>">Stay Connected</a><?php if (publicEvents() !== []): ?><a href="<?= e(url('events.php')) ?>">Events</a><?php endif; ?><a href="<?= e(url('terms.php')) ?>">Terms</a><a href="<?= e(url('privacy')) ?>">Privacy</a><a href="https://portal.bhs-pa.com" target="_blank" rel="noopener noreferrer" aria-label="Portal (opens in a new tab)">Portal <span aria-hidden="true">↗</span></a></nav></div>
        <div><h2>Contact</h2><a href="mailto:weserve@bhs-pa.com">weserve@bhs-pa.com</a><a href="https://wa.me/96179035755" target="_blank" rel="noopener">WhatsApp +961 79 035 755</a><a href="https://www.instagram.com/bhs.parentassociation/" target="_blank" rel="noopener">Instagram</a></div>
    </div>
    <div class="shell footer-bottom"><span>&copy; <?= date('Y') ?> BHS Parents Association</span><span>We serve, together.</span></div>
</footer>
</body>
</html>
