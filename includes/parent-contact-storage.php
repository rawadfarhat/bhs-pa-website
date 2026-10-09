<?php
declare(strict_types=1);
require_once __DIR__ . '/user-child-storage.php';

/** Save contact and child pairs atomically in the portal database. */
function savePublicParentContact(PDO $pdo, array $contact, array $children): int
{
    // A shared lock also serializes submissions that change email but share a
    // BHS ID. A lock on email+BHS alone would not protect those overlaps.
    $lockName = 'bhs_pa_public_parent_contacts';
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Contact lock unavailable.');
    try {
        $pdo->beginTransaction();
        $find = $pdo->prepare('SELECT * FROM parent_contacts_clean WHERE deleted_at IS NULL AND LOWER(TRIM(clean_email))=? ORDER BY (linked_user_id IS NOT NULL) DESC,id FOR UPDATE');
        $find->execute([$contact['email']]);
        $matches = $find->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) > 1) throw new RuntimeException('Multiple contacts use this email. Please contact the Parents Association to correct them.');
        $existing = $matches[0] ?? null;
        if (!$existing) {
            // A family ID alone is never a parent identity. Permit changed-email
            // updates only when the parent name also identifies a single row.
            $find = $pdo->prepare('SELECT * FROM parent_contacts_clean WHERE deleted_at IS NULL AND UPPER(clean_bhs_id)=? AND LOWER(TRIM(clean_full_name))=? ORDER BY id FOR UPDATE');
            $find->execute([$contact['bhs_id'], strtolower($contact['name'])]);
            $matches = $find->fetchAll(PDO::FETCH_ASSOC);
            if (count($matches) > 1) throw new RuntimeException('Multiple contacts match this parent. Please contact the Parents Association to correct them.');
            $existing = $matches[0] ?? null;
        }

        $findUser = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=? AND deleted_at IS NULL ORDER BY id LIMIT 1 FOR UPDATE');
        $findUser->execute([$contact['email']]);
        $emailUserId = (int) ($findUser->fetchColumn() ?: 0);
        $linkedUserId = (int) ($existing['linked_user_id'] ?? 0);
        if ($linkedUserId > 0) {
            $active = $pdo->prepare('SELECT id FROM users WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $active->execute([$linkedUserId]);
            if (!$active->fetchColumn()) $linkedUserId = 0;
        }
        if ($linkedUserId > 0 && $emailUserId > 0 && $linkedUserId !== $emailUserId) {
            throw new RuntimeException('This contact is linked to a different user. Please contact the Parents Association.');
        }
        $linkedUserId = $linkedUserId ?: $emailUserId;
        $studentNames = implode(', ', array_column($children, 'name'));
        $grades = implode(', ', array_column($children, 'grade'));
        $payload = json_encode(['parent' => $contact, 'children' => $children], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $diagnostics = json_encode(['source' => 'public_website', 'submitted_at' => gmdate(DATE_ATOM), 'child_count' => count($children)], JSON_THROW_ON_ERROR);
        $values = [$contact['name'],$contact['first_name'],$contact['last_name'],$contact['email'],$contact['phone'],$studentNames,$grades,$contact['channel'],
            $contact['name'],$contact['first_name'],$contact['last_name'],$contact['email'],$contact['phone'],$contact['bhs_id'],$studentNames,$grades,$contact['channel'],$diagnostics,$linkedUserId ?: null];
        if ($existing) {
            $id = (int) $existing['id'];
            $pdo->prepare("UPDATE parent_contacts_clean SET raw_parent_name=?,raw_first_name=?,raw_last_name=?,raw_email=?,raw_mobile=?,raw_student_names=?,raw_class_grade=?,raw_preferred_channel=?,raw_consent=1,raw_created_at=NOW(),clean_full_name=?,clean_first_name=?,clean_last_name=?,clean_email=?,clean_phone=?,clean_bhs_id=?,clean_student_names=?,clean_class_grade=?,clean_preferred_channel=?,merge_diagnostics=?,linked_user_id=?,validation_status='valid',validation_errors=JSON_OBJECT('errors',JSON_ARRAY()),is_ready_for_user=1,is_locally_managed=1,children_initialized=1,updated_at=NOW() WHERE id=?")
                ->execute([...$values, $id]);
        } else {
            $pdo->prepare("INSERT INTO parent_contacts_clean (raw_parent_name,raw_first_name,raw_last_name,raw_email,raw_mobile,raw_student_names,raw_class_grade,raw_preferred_channel,clean_full_name,clean_first_name,clean_last_name,clean_email,clean_phone,clean_bhs_id,clean_student_names,clean_class_grade,clean_preferred_channel,merge_diagnostics,linked_user_id,raw_consent,raw_created_at,validation_status,validation_errors,is_ready_for_user,is_locally_managed,children_initialized,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),'valid',JSON_OBJECT('errors',JSON_ARRAY()),1,1,1,NOW(),NOW())")
                ->execute($values);
            $id = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('INSERT INTO parent_contacts_website_sources (clean_contact_id,submitted_payload,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE submitted_payload=VALUES(submitted_payload),updated_at=NOW()')->execute([$id,$payload]);
        $source = $pdo->prepare('SELECT id FROM parent_contacts_website_sources WHERE clean_contact_id=?');
        $source->execute([$id]);
        $websiteSourceId = (int) $source->fetchColumn();
        // Preserve existing legacy source IDs and attach the website submission
        // as another source. Allocate a primary source ID for new contacts.
        $pdo->prepare('UPDATE parent_contacts_clean SET source_contact_id=COALESCE(source_contact_id,?) WHERE id=?')->execute([$websiteSourceId,$id]);
        $pdo->prepare('INSERT INTO parent_contacts_clean_sources(clean_contact_id,source_contact_id,created_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE clean_contact_id=VALUES(clean_contact_id)')->execute([$id,$websiteSourceId]);

        $pdo->prepare('UPDATE parent_contacts_clean SET raw_bhs_id=?,raw_children_initialized=1 WHERE id=?')->execute([$contact['bhs_id'],$id]);
        $pdo->prepare('DELETE FROM parent_contacts_raw_children WHERE clean_contact_id=?')->execute([$id]);
        $rawInsert = $pdo->prepare('INSERT INTO parent_contacts_raw_children(clean_contact_id,line_no,student_name,class_grade) VALUES (?,?,?,?)');
        foreach ($children as $index => $child) $rawInsert->execute([$id,$index+1,$child['name'],$child['grade']]);
        $pdo->prepare('DELETE FROM parent_contacts_clean_children WHERE clean_contact_id=?')->execute([$id]);
        $insert = $pdo->prepare('INSERT INTO parent_contacts_clean_children(clean_contact_id,line_no,student_name,class_grade) VALUES (?,?,?,?)');
        foreach ($children as $index => $child) $insert->execute([$id,$index+1,$child['name'],$child['grade']]);
        if ($linkedUserId > 0) {
            $pairs = $pdo->prepare('SELECT cc.student_name,cc.class_grade FROM parent_contacts_clean_children cc JOIN parent_contacts_clean pc ON pc.id=cc.clean_contact_id WHERE pc.linked_user_id=? AND pc.deleted_at IS NULL ORDER BY pc.id,cc.line_no');
            $pairs->execute([$linkedUserId]);
            syncPublicContactUserChildren($pdo,$linkedUserId,$pairs->fetchAll(PDO::FETCH_ASSOC));
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}
