<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/event-data-collection-service.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed.']);
    exit;
}
$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT) ?: 0;
try {
    if (!publicEvent($eventId)) {
        http_response_code(404);
        echo json_encode(['message' => 'Event unavailable.']);
        exit;
    }
    echo json_encode(['data' => App\Services\EventDataCollectionService::totals(db(), $eventId)]);
} catch (Throwable $error) {
    error_log('Event collection totals failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['message' => 'Totals unavailable.']);
}
