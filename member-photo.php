<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit; }
try {
    $stmt = db()->prepare("SELECT file_blob,mime_type,file_path FROM attachments WHERE id=:id AND entity_type='pa_member_profile' LIMIT 1");
    $stmt->execute(['id' => $id]);
    $photo = $stmt->fetch();
    if (!$photo) { throw new RuntimeException('Not found'); }
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    if (!empty($photo['file_blob'])) {
        header('Content-Type: ' . (in_array($photo['mime_type'], ['image/jpeg','image/png','image/webp'], true) ? $photo['mime_type'] : 'image/jpeg'));
        echo $photo['file_blob'];
        exit;
    }
    $path = (string) ($photo['file_path'] ?? '');
    if ($path !== '' && is_file($path)) {
        header('Content-Type: ' . ($photo['mime_type'] ?: 'image/jpeg'));
        readfile($path);
        exit;
    }
} catch (Throwable $error) {
    error_log('Public member photo failed: ' . $error->getMessage());
}
http_response_code(404);

