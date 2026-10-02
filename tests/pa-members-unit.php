<?php

declare(strict_types=1);

$bootstrap = file_get_contents(__DIR__ . '/../includes/bootstrap.php');
if (!is_string($bootstrap) || !str_contains($bootstrap, 'p.profile_picture_attachment_id')) {
    throw new RuntimeException('The public member list must use the photo stored on the PA member profile.');
}

$photoEndpoint = file_get_contents(__DIR__ . '/../member-photo.php');
if (!is_string($photoEndpoint) || !str_contains($photoEndpoint, "entity_type='pa_member_profile'")) {
    throw new RuntimeException('The public member photo endpoint must read PA member profile attachments.');
}

if (str_contains($photoEndpoint, "entity_type='user_profile'")) {
    throw new RuntimeException('The public member photo endpoint must not read user profile photos.');
}

echo "PA member website checks passed\n";
