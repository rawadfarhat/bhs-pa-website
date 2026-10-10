<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/event-collection.php';
use App\Services\EventDataCollectionService;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function collectionResponse(int $status, string $message): never {
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') collectionResponse(405, 'Method not allowed.');
eventCollectionSession();
$csrf = $_POST['csrf'] ?? null;
if (!is_string($csrf) || !hash_equals($_SESSION['event_collection_csrf'], $csrf)) collectionResponse(403, 'Please refresh the page and try again.');
$attempts = array_filter($_SESSION['event_collection_attempts'] ?? [], fn($time) => $time > time() - 60);
if (count($attempts) >= 20) collectionResponse(429, 'Please wait a moment before submitting again.');
$attempts[] = time();
$_SESSION['event_collection_attempts'] = $attempts;
session_write_close();
if (!empty($_POST['website'])) collectionResponse(200, 'Thank you for submitting your information');
$eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
if (!$eventId || $eventId < 1) collectionResponse(422, 'Invalid event.');
$pdo = null;
try {
    $pdo = db();
    $pdo->beginTransaction();
    // Serialize submissions per event, including requests whose email/phone has no row yet.
    $query = $pdo->prepare("SELECT data_collection_config FROM events WHERE id=? AND deleted_at IS NULL AND status='active' AND publish_to_website=1 FOR UPDATE");
    $query->execute([$eventId]);
    $event = $query->fetch();
    if (!$event) { $pdo->rollBack(); collectionResponse(404, 'This form is unavailable.'); }
    $configuration = EventDataCollectionService::configuration($event['data_collection_config']);
    if (!$configuration['enabled']) { $pdo->rollBack(); collectionResponse(404, 'This form is unavailable.'); }
    $values = EventDataCollectionService::values($configuration, $_POST, eventPhoneCountries());
    EventDataCollectionService::save($pdo, (int) $eventId, $values);
    $pdo->commit();
    collectionResponse(200, $configuration['success_message']);
} catch (InvalidArgumentException $error) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    collectionResponse(422, $error->getMessage());
} catch (Throwable $error) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Event information submission failed: ' . $error->getMessage());
    collectionResponse(503, 'Unable to submit at the moment. Please try again.');
}
