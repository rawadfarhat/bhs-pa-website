<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$event = publicEvent($eventId);
if ($event === null) { http_response_code(404); $pageTitle = 'Event not found | BHS Parents Association'; $activePage = ''; require __DIR__ . '/includes/header.php'; echo '<section class="section"><div class="shell narrow"><h1>Event not found</h1><p>This event is unavailable or is no longer published.</p></div></section>'; require __DIR__ . '/includes/footer.php'; exit; }
$photos = !empty($event['publish_photos']) ? publicEventPhotos($eventId) : [];
$attachments = publicEventAttachments($eventId);
$galleryHtml = renderEventGallery($event, $photos);
$renderedContent = renderPublicEventContent($event['events_page_content_html'] ?? '', $attachments, $galleryHtml);
$content = $renderedContent['html'];
$galleryPlaced = $renderedContent['gallery_placed'];
$pageTitle = $event['name'] . ' | BHS Parents Association';
$activePage = 'events';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="shell"><p class="eyebrow"><?= !empty($event['event_date']) ? e(date('F j, Y', strtotime($event['event_date']))) : 'Community event' ?></p><h1><?= e($event['name']) ?></h1><?php if (!empty($event['description'])): ?><p><?= e($event['description']) ?></p><?php endif; ?></div></section>
<?php if ($content !== ''): ?><section class="section"><div class="shell narrow prose"><?= $content ?></div></section><?php endif; ?>
<?php if ($galleryHtml !== '' && !$galleryPlaced): ?><section class="section section-tint"><div class="shell"><?= $galleryHtml ?></div></section><?php endif; ?>
<script src="<?= e(url('assets/event-gallery.js?v=2')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
