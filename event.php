<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/event-collection.php';
eventCollectionSession();
$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$event = publicEvent($eventId);
if ($event === null) { http_response_code(404); $pageTitle = 'Event not found | BHS Parents Association'; $activePage = ''; require __DIR__ . '/includes/header.php'; echo '<section class="section"><div class="shell narrow"><h1>Event not found</h1><p>This event is unavailable or is no longer published.</p></div></section>'; require __DIR__ . '/includes/footer.php'; exit; }
$bannerStmt = db()->prepare("SELECT id FROM attachments WHERE entity_type='event_banner' AND entity_id=? AND mime_type IN ('image/jpeg','image/png','image/webp') ORDER BY id DESC LIMIT 1");
$bannerStmt->execute([$eventId]);
$bannerId = (int) ($bannerStmt->fetchColumn() ?: 0);
$photos = !empty($event['publish_photos']) ? publicEventPhotos($eventId) : [];
$attachments = publicEventAttachments($eventId);
$galleryHtml = renderEventGallery($event, $photos);
$renderedContent = renderPublicEventContent($event['events_page_content_html'] ?? '', $attachments, $galleryHtml);
$collection = App\Services\EventDataCollectionService::configuration($event['data_collection_config'] ?? null);
$content = replaceEventFormPlaceholder($renderedContent['html'], $collection);
if (preg_match('/\{count\}|\{sum\s*;\s*field\s*:\s*number_0[12]\s*\}|\{progress\s*;/iu', $content)) {
    $content = renderEventCollectionTotals($content, App\Services\EventDataCollectionService::totals(db(), $eventId));
}
$galleryPlaced = $renderedContent['gallery_placed'];
$pageTitle = $event['name'] . ' | BHS Parents Association';
$activePage = 'events';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero<?= $bannerId ? ' page-hero-photo' : '' ?>"<?php if ($bannerId): ?> style="background-image:linear-gradient(rgba(0,0,0,.55),rgba(0,0,0,.55)),url('<?= e(url('event-photo.php?id=' . $bannerId . '&variant=large')) ?>')"<?php endif; ?>><div class="shell"><p class="eyebrow"><?= !empty($event['event_date']) ? e(date('F j, Y', strtotime($event['event_date']))) : 'Community event' ?></p><h1><?= e($event['name']) ?></h1><?php if (!empty($event['description'])): ?><p class="event-description"><?= e($event['description']) ?></p><?php endif; ?></div></section>
<?php if ($content !== ''): ?><section class="section"><div class="shell narrow prose"><?= $content ?></div></section><?php endif; ?>
<?php if ($galleryHtml !== '' && !$galleryPlaced): ?><section class="section section-tint"><div class="shell"><?= $galleryHtml ?></div></section><?php endif; ?>
<?php if ($collection['enabled']): ?><?= renderEventCollectionForm($event, $collection) ?><script src="<?= e(url('assets/event-collection.js?v=6')) ?>" defer></script><?php endif; ?>
<script src="<?= e(url('assets/event-gallery.js?v=2')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
