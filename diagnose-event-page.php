<?php
declare(strict_types=1);

// Read-only cPanel Terminal diagnostic. Never expose database details over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/event-collection.php';

$id = filter_var($argv[1] ?? '7', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { fwrite(STDERR, "Usage: php diagnose-event-page.php EVENT_ID\n"); exit(1); }
function describeEventMarkup(string $label, string $html): void {
    preg_match_all('/<\s*(p|h[234]|div|strong|ul|li)\b/i', $html, $tags);
    echo $label . ': ' . json_encode([
        'bytes' => strlen($html), 'formatting_tags' => count($tags[0]),
        'has_columns' => str_contains($html, 'event-columns'),
        'has_cards' => str_contains($html, 'event-card'),
        'escaped_tags' => (bool) preg_match('/&lt;\s*(p|h[234]|div)\b/i', $html),
    ], JSON_UNESCAPED_SLASHES) . "\n";
}
try {
    echo 'PHP: ' . PHP_VERSION . '; libxml: ' . LIBXML_DOTTED_VERSION . "\n";
    $event = publicEvent((int) $id);
    if (!$event) { fwrite(STDERR, "Published active event not found.\n"); exit(1); }
    $raw = (string) ($event['events_page_content_html'] ?? '');
    describeEventMarkup('Database HTML', $raw);
    describeEventMarkup('Sanitized HTML', safeEventHtml($raw));
    $probe = safeEventHtml('<div class="event-columns"><div class="event-card"><h2>Test</h2><p>Paragraph</p></div></div>');
    describeEventMarkup('Built-in layout probe', $probe);
    $rendered = renderPublicEventContent($raw, publicEventAttachments((int) $id), '')['html'];
    $rendered = replaceEventFormPlaceholder($rendered, App\Services\EventDataCollectionService::configuration($event['data_collection_config'] ?? null));
    describeEventMarkup('After placeholders', renderEventCollectionTotals($rendered, ['count'=>'0','number_01'=>'0','number_02'=>'0']));
    foreach (['includes/bootstrap.php','includes/event-collection.php','event.php','assets/styles.css','assets/event-collection.js'] as $file) echo $file . ' SHA256: ' . hash_file('sha256', __DIR__ . '/' . $file) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Diagnostic failed (' . get_class($error) . "). Review the private PHP error log.\n");
    error_log('Event page diagnostic: ' . $error->getMessage());
    exit(1);
}
