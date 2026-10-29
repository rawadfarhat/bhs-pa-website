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
$introduction = renderEventIntroduction($event, $bannerId);
$photos = !empty($event['publish_photos']) ? publicEventPhotos($eventId) : [];
$attachments = publicEventAttachments($eventId);
$galleryHtml = renderEventGallery($event, $photos);
$renderedContent = renderPublicEventContent($event['events_page_content_html'] ?? '', $attachments, $galleryHtml);
$collection = App\Services\EventDataCollectionService::configuration($event['data_collection_config'] ?? null);
$content = replaceEventFormPlaceholder($renderedContent['html'], $collection);
if (preg_match('/\{count\}|\{sum\s*;\s*field\s*:\s*number_0[12]\s*\}|\{progress\s*;/iu', $content)) {
    try {
        $content = renderEventCollectionTotals($content, eventCollectionDisplayTotals(App\Services\EventDataCollectionService::totals(db(), $eventId)));
    } catch (Throwable $error) {
        if (class_exists(App\Services\LoggingService::class)) App\Services\LoggingService::caught('event-data-collection', 'website.event.totals_unavailable', __LINE__, 'error', $error);
        // Keep the event and its form usable; never reveal raw totals when ceiling settings cannot be read.
        $content = renderEventCollectionTotals($content, ['count' => '—', 'number_01' => '—', 'number_02' => '—']);
    }
}
$galleryPlaced = $renderedContent['gallery_placed'];
$pageTitle = $event['name'] . ' | BHS Parents Association';
$activePage = 'events';
require __DIR__ . '/includes/header.php';
?>
<?= $introduction['banner'] ?>
<?php if ($introduction['body'] !== '' || $content !== ''): ?><section class="section"><div class="shell narrow prose"><?= $introduction['body'] ?><?= $content ?></div></section><?php endif; ?>
<?php if ($galleryHtml !== '' && !$galleryPlaced): ?><section class="section section-tint"><div class="shell"><?= $galleryHtml ?></div></section><?php endif; ?>
<?php if ($collection['enabled']): ?><?= renderEventCollectionForm($event, $collection) ?><script src="<?= e(url('assets/event-collection.js?v=6')) ?>" defer></script><?php endif; ?>
<script src="<?= e(url('assets/event-gallery.js?v=2')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
