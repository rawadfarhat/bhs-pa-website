<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, 'Method not allowed.');
if (trim((string) ($_POST['website'] ?? '')) !== '') respond(200, 'Thank you. Your information has been received.');

require __DIR__ . '/includes/parent-contact-validation.php';
try {
    $submission = validatePublicParentContact($_POST);
} catch (InvalidArgumentException $error) {
    respond(422, $error->getMessage());
}

require __DIR__ . '/includes/parent-contact-storage.php';
try {
    savePublicParentContact(db(), $submission['contact'], $submission['children']);
    respond(200, 'Thank you. Your information has been received.');
} catch (RuntimeException $error) {
    $message = $error->getMessage();
    if (str_starts_with($message, 'Multiple contacts') || str_starts_with($message, 'This contact is linked')) respond(409, $message);
    error_log('Public contact submission failed: ' . $message);
    respond(500, 'We could not save your information right now. Please try again later.');
} catch (Throwable $error) {
    error_log('Public contact submission failed: ' . $error->getMessage());
    respond(500, 'We could not save your information right now. Please try again later.');
}
