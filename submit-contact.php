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

function cleanText(mixed $value, int $limit = 190): string
{
    $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
}

function cleanPhone(mixed $value): string
{
    $raw = trim((string) $value);
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
    if (str_starts_with($digits, '961')) return '+' . $digits;
    if (preg_match('/^0\d{7,8}$/', $digits)) return '+961' . substr($digits, 1);
    if (preg_match('/^(3\d{6}|[14789]\d{7})$/', $digits)) return '+961' . $digits;
    if (preg_match('/^[1-9]\d{7,14}$/', $digits)) return '+' . $digits;
    return '';
}

function cleanBhsId(mixed $value): string
{
    $value = strtoupper(cleanText($value, 40));
    return preg_match('/^[A-Z0-9-]{3,40}$/', $value) === 1 ? $value : '';
}

function canonicalGrade(mixed $value): string
{
    $value = strtoupper(cleanText($value, 20));
    if (preg_match('/^GRADE\s*(1[0-2]|[1-9])$/', $value, $matches) === 1) {
        $value = 'G' . $matches[1];
    }
    $allowed = ['KG1','KG2','KG3','G1','G2','G3','G4','G5','G6','G7','G8','G9','G10','G11','G12'];
    return in_array($value, $allowed, true) ? $value : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, 'Method not allowed.');
if (trim((string) ($_POST['website'] ?? '')) !== '') respond(200, 'Thank you. Your information has been received.');

$name = cleanText($_POST['name'] ?? '', 150);
$email = strtolower(cleanText($_POST['email'] ?? '', 150));
$bhsId = cleanBhsId($_POST['parentBHSID'] ?? '');
$phone = cleanPhone($_POST['phone'] ?? '');
$channel = cleanText($_POST['preferred_channel'] ?? 'Email', 50);
$consent = isset($_POST['gdpr']) ? 1 : 0;
$nameParts = preg_split('/\s+/u', $name, 2) ?: [];
$firstName = $nameParts[0] ?? '';
$lastName = $nameParts[1] ?? '';
$childNames = is_array($_POST['child_names'] ?? null) ? $_POST['child_names'] : [];
$childGrades = is_array($_POST['child_grades'] ?? null) ? $_POST['child_grades'] : [];
$children = [];
$seenChildren = [];
if (count($childNames) !== count($childGrades) || count($childNames) > 10) {
    respond(422, 'Please provide between one and ten complete child records.');
}
foreach ($childNames as $index => $childName) {
    $childName = cleanText($childName);
    $grade = canonicalGrade($childGrades[$index] ?? '');
    if ($childName === '' || $grade === '') respond(422, 'Please provide a valid name and grade for every child.');
    $dedupeKey = strtolower($childName) . '|' . strtolower($grade);
    if (isset($seenChildren[$dedupeKey])) continue;
    $seenChildren[$dedupeKey] = true;
    $children[] = ['name' => $childName, 'grade' => $grade];
}

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $bhsId === '' || $phone === '' || !$consent || $children === []) {
    respond(422, 'Please complete all required fields and try again.');
}
if (!in_array($channel, ['Email', 'Phone / WhatsApp'], true)) $channel = 'Email';

require __DIR__ . '/includes/parent-contact-storage.php';
try {
    savePublicParentContact(db(), [
        'name' => $name, 'first_name' => $firstName, 'last_name' => $lastName,
        'email' => $email, 'bhs_id' => $bhsId, 'phone' => $phone, 'channel' => $channel,
    ], $children);
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
