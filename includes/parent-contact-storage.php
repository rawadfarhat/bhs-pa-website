<?php
declare(strict_types=1);

/** Public intake is append-only and never modifies a canonical contact or user. */
function savePublicParentContact(PDO $pdo, array $contact, array $children, string $source = 'public_website'): int
{
    if (!in_array($source, ['public_website', 'portal_registration'], true)) throw new InvalidArgumentException('Invalid contact source.');
    $payload = json_encode(['parent'=>$contact, 'children'=>$children], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $pdo->prepare('INSERT INTO public_parent_contact_submissions (source, submitted_payload) VALUES (?,?)')->execute([$source,$payload]);
    return (int)$pdo->lastInsertId();
}
