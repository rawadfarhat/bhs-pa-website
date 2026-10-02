<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
try {
    $stmt = db()->prepare("SELECT a.file_name,a.file_blob,a.file_path,a.mime_type,a.created_at
        FROM attachments a INNER JOIN events e ON e.id=a.entity_id
        WHERE a.id=:id AND a.entity_type='event_attachment'
          AND e.deleted_at IS NULL AND e.status='active' AND e.publish_to_website=1 LIMIT 1");
    $stmt->execute(['id' => $id]);
    $attachment = $stmt->fetch();
    if (!$attachment) { http_response_code(404); exit; }
    $content = $attachment['file_blob'];
    if (is_resource($content)) $content = stream_get_contents($content);
    if ((!is_string($content) || $content === '') && !empty($attachment['file_path'])) {
        $root = realpath((string) config('portal.root_path', dirname(__DIR__) . '/BHS-PA-Cashbox/bhs-pa-cashbox'));
        $path = $root === false ? false : realpath($root . '/' . ltrim(str_replace('\\', '/', (string) $attachment['file_path']), '/'));
        if ($path !== false && str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR)) && is_file($path)) $content = file_get_contents($path);
    }
    if (!is_string($content) || $content === '') { http_response_code(404); exit; }
    $mime = preg_replace('/[^a-zA-Z0-9.+\/-]/', '', (string) ($attachment['mime_type'] ?: 'application/octet-stream'));
    $etag = '"' . hash('sha256', $content) . '"';
    $downloadName = str_replace(['"', "\r", "\n"], '', basename((string) $attachment['file_name']));
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($content));
    header('Content-Disposition: inline; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Cache-Control: public, max-age=3600');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'self'; sandbox");
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
    echo $content;
} catch (Throwable $error) { error_log('Public event attachment failed: ' . $error->getMessage()); http_response_code(404); }
