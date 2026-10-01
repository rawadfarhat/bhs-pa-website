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

function replaceLinkedUserChildren(PDO $pdo, int $userId, array $children): void
{
    if ($userId <= 0) return;

    $pdo->prepare("DELETE FROM user_children WHERE user_id=:user_id AND source_type='parent_contacts_cleanup'")
        ->execute(['user_id' => $userId]);
    $insert = $pdo->prepare("INSERT INTO user_children (user_id,student_name,class_grade,source_type,created_at,updated_at) VALUES (:user_id,:student_name,:class_grade,'parent_contacts_cleanup',NOW(),NOW())");
    foreach ($children as $child) {
        $insert->execute(['user_id' => $userId, 'student_name' => $child['name'], 'class_grade' => $child['grade']]);
    }
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

$studentNames = implode("\n", array_column($children, 'name'));
$grades = implode("\n", array_column($children, 'grade'));
$diagnostics = json_encode(['source' => 'public_website', 'submitted_at' => gmdate(DATE_ATOM), 'child_count' => count($children)], JSON_UNESCAPED_UNICODE);
$lockName = 'bhs_pa_public_contact_' . hash('sha256', $email . '|' . $bhsId);

try {
    $pdo = db();
    $lock = $pdo->prepare('SELECT GET_LOCK(:name, 5)');
    $lock->execute(['name' => $lockName]);
    if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Contact lock unavailable.');
    $pdo->beginTransaction();
    // Only update website-originated rows. Legacy rows are rebuilt by the
    // portal's Re-clean action and would otherwise overwrite newer form data.
    $find = $pdo->prepare("SELECT id,linked_user_id FROM parent_contacts_clean WHERE source_contact_id IS NULL AND (LOWER(clean_email)=:email OR UPPER(clean_bhs_id)=:bhs_id) ORDER BY CASE WHEN UPPER(clean_bhs_id)=:bhs_id2 THEN 0 ELSE 1 END,id LIMIT 1 FOR UPDATE");
    $find->execute(['email' => $email, 'bhs_id' => $bhsId, 'bhs_id2' => $bhsId]);
    $existing = $find->fetch() ?: null;
    $existingId = $existing['id'] ?? null;
    $linkedUserId = (int) ($existing['linked_user_id'] ?? 0);
    if ($linkedUserId <= 0) {
        $findUser = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=:email AND deleted_at IS NULL ORDER BY id LIMIT 1 FOR UPDATE');
        $findUser->execute(['email' => $email]);
        $linkedUserId = (int) ($findUser->fetchColumn() ?: 0);
    }
    $params = [
        'raw_parent_name'=>$name,'raw_first_name'=>$firstName,'raw_last_name'=>$lastName,'raw_email'=>$email,'raw_mobile'=>$phone,
        'raw_student_names'=>$studentNames,'raw_class_grade'=>$grades,'raw_preferred_channel'=>$channel,'raw_consent'=>1,
        'clean_full_name'=>$name,'clean_first_name'=>$firstName,'clean_last_name'=>$lastName,'clean_email'=>$email,'clean_phone'=>$phone,
        'clean_bhs_id'=>$bhsId,'clean_student_names'=>$studentNames,'clean_class_grade'=>$grades,'clean_preferred_channel'=>$channel,
        'validation_errors'=>json_encode(['errors'=>[]]),'merge_diagnostics'=>$diagnostics,'linked_user_id'=>$linkedUserId > 0 ? $linkedUserId : null,
    ];
    if ($existingId) {
        $params['id'] = (int) $existingId;
        $sql = "UPDATE parent_contacts_clean SET raw_parent_name=:raw_parent_name,raw_first_name=:raw_first_name,raw_last_name=:raw_last_name,raw_email=:raw_email,raw_mobile=:raw_mobile,raw_student_names=:raw_student_names,raw_class_grade=:raw_class_grade,raw_preferred_channel=:raw_preferred_channel,raw_consent=:raw_consent,raw_created_at=NOW(),clean_full_name=:clean_full_name,clean_first_name=:clean_first_name,clean_last_name=:clean_last_name,clean_email=:clean_email,clean_phone=:clean_phone,clean_bhs_id=:clean_bhs_id,clean_student_names=:clean_student_names,clean_class_grade=:clean_class_grade,clean_preferred_channel=:clean_preferred_channel,validation_status='valid',validation_errors=:validation_errors,merge_diagnostics=:merge_diagnostics,is_ready_for_user=1,linked_user_id=:linked_user_id,updated_at=NOW() WHERE id=:id";
    } else {
        $sql = "INSERT INTO parent_contacts_clean (source_contact_id,raw_parent_name,raw_first_name,raw_last_name,raw_email,raw_mobile,raw_student_names,raw_class_grade,raw_preferred_channel,raw_consent,raw_created_at,clean_full_name,clean_first_name,clean_last_name,clean_email,clean_phone,clean_bhs_id,clean_student_names,clean_class_grade,clean_preferred_channel,validation_status,validation_errors,merge_diagnostics,is_ready_for_user,linked_user_id,created_at,updated_at) VALUES (NULL,:raw_parent_name,:raw_first_name,:raw_last_name,:raw_email,:raw_mobile,:raw_student_names,:raw_class_grade,:raw_preferred_channel,:raw_consent,NOW(),:clean_full_name,:clean_first_name,:clean_last_name,:clean_email,:clean_phone,:clean_bhs_id,:clean_student_names,:clean_class_grade,:clean_preferred_channel,'valid',:validation_errors,:merge_diagnostics,1,:linked_user_id,NOW(),NOW())";
    }
    $pdo->prepare($sql)->execute($params);
    replaceLinkedUserChildren($pdo, $linkedUserId, $children);
    $pdo->commit();
    $pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => $lockName]);
    respond(200, 'Thank you. Your information has been received.');
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($pdo) && $pdo instanceof PDO) { try { $pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => $lockName]); } catch (Throwable) {} }
    error_log('Public contact submission failed: ' . $error->getMessage());
    respond(500, 'We could not save your information right now. Please try again later.');
}

