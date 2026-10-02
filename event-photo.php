<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$variant = (string) ($_GET['variant'] ?? 'medium');
if (!in_array($variant, ['thumbnail','medium','large'], true)) { http_response_code(404); exit; }
try {
    $stmt = db()->prepare("SELECT a.id,a.file_name,a.file_blob source_blob,a.mime_type source_mime,a.created_at,v.file_blob variant_blob,v.mime_type variant_mime,v.byte_size,v.sha256
        FROM attachments a INNER JOIN events e ON e.id=a.entity_id
        LEFT JOIN attachment_image_variants v ON v.attachment_id=a.id AND v.variant=:variant
        WHERE a.id=:id AND a.entity_type='event_photo' AND a.mime_type IN ('image/jpeg','image/png','image/webp')
          AND e.deleted_at IS NULL AND e.status='active' AND e.publish_to_website=1 AND e.publish_photos=1 LIMIT 1");
    $stmt->execute(['variant' => $variant, 'id' => $id]);
    $photo = $stmt->fetch();
    if (!$photo) { http_response_code(404); exit; }
    $content = $photo['variant_blob'] ?? $photo['source_blob'];
    if (is_resource($content)) $content = stream_get_contents($content);
    if (!is_string($content) || $content === '') { http_response_code(404); exit; }
    $mime = $photo['variant_mime'] ?: $photo['source_mime'];
    $etag = '"' . ($photo['sha256'] ?: hash('sha256', $content)) . '"';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($content));
    header('Content-Disposition: inline; filename="' . rawurlencode(basename((string) $photo['file_name'])) . '"');
    header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime((string) $photo['created_at'])) . ' GMT');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    $ifModifiedSince = strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
    $modifiedAt = strtotime((string) $photo['created_at']);
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag || ($ifModifiedSince !== false && $ifModifiedSince >= $modifiedAt)) { http_response_code(304); exit; }
    echo $content;
} catch (Throwable $error) { error_log('Public event photo failed: ' . $error->getMessage()); http_response_code(404); }
