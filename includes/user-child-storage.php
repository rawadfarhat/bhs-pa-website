<?php
declare(strict_types=1);

/** Keep child IDs stable because portal complaint history references them. */
function syncPublicContactUserChildren(PDO $pdo, int $userId, array $children): void
{
    $source='parent_contacts_cleanup';
    $history='parent_contacts_cleanup_history';
    $stmt=$pdo->prepare('SELECT id,student_name,class_grade,source_type FROM user_children WHERE user_id=? AND source_type IN (?,?) ORDER BY id FOR UPDATE');
    $stmt->execute([$userId,$source,$history]);
    $existing=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $used=[]; $seen=[];
    $update=$pdo->prepare('UPDATE user_children SET student_name=?,class_grade=?,source_type=?,updated_at=NOW() WHERE id=? AND user_id=?');
    $insert=$pdo->prepare('INSERT INTO user_children(user_id,student_name,class_grade,source_type,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
    foreach ($children as $child) {
        $key=strtolower($child['student_name']).'|'.strtolower((string)$child['class_grade']);
        if (isset($seen[$key])) continue;
        $seen[$key]=true;
        $match=null;
        foreach ([true,false] as $exactGrade) {
            foreach ($existing as $candidate) {
                if (isset($used[$candidate['id']]) || mb_strtolower(trim($candidate['student_name']))!==mb_strtolower(trim($child['student_name']))) continue;
                if ($exactGrade && $candidate['class_grade']!==$child['class_grade']) continue;
                $match=$candidate; break 2;
            }
        }
        if ($match) {
            $used[$match['id']]=true;
            $update->execute([$child['student_name'],$child['class_grade'],$source,$match['id'],$userId]);
        } else $insert->execute([$userId,$child['student_name'],$child['class_grade'],$source]);
    }
    $archive=$pdo->prepare('UPDATE user_children SET source_type=?,updated_at=NOW() WHERE id=? AND user_id=?');
    foreach ($existing as $candidate) if (!isset($used[$candidate['id']]) && $candidate['source_type']===$source) $archive->execute([$history,$candidate['id'],$userId]);
}
