<?php

declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Website configuration is not available.');
}

$config = require $configFile;

function config(string $path, mixed $default = null): mixed
{
    global $config;
    $value = $config;
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        config('db.host'),
        config('db.port', 3306),
        config('db.database'),
        config('db.charset', 'utf8mb4')
    );
    $pdo = new PDO($dsn, (string) config('db.username'), (string) config('db.password'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $configuredBasePath = trim((string) config('app.base_path', ''));

    if ($configuredBasePath !== '') {
        $basePath = '/' . trim($configuredBasePath, '/');
    } else {
        // Keep links working whether the site is hosted at the domain root or
        // from a subdirectory such as /bhs-pa-website/ in local development.
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
    }

    return ($basePath === '' ? '' : $basePath) . '/' . ltrim($path, '/');
}

function paMembers(bool $mainOnly = false): array
{
    $sql = "SELECT p.user_id,p.first_name,p.last_name,p.pa_title,p.biography_html,
                   p.main_listing,p.display_order,p.profile_picture_attachment_id
            FROM pa_member_profiles p
            INNER JOIN users u ON u.id=p.user_id
            WHERE u.is_pa_member=1 AND u.deleted_at IS NULL";
    if ($mainOnly) {
        $sql .= ' AND p.main_listing=1';
    }
    $sql .= ' ORDER BY p.display_order IS NULL,p.display_order,p.first_name,p.last_name,p.user_id';
    try {
        return db()->query($sql)->fetchAll();
    } catch (Throwable $error) {
        error_log('Public PA member query failed: ' . $error->getMessage());
        return [];
    }
}

function renderMemberCards(array $members): void
{
    if ($members === []) {
        echo '<p class="empty-state">Member profiles are being updated. Please check back soon.</p>';
        return;
    }
    foreach ($members as $member) {
        $name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
        $photo = !empty($member['profile_picture_attachment_id'])
            ? url('member-photo.php?id=' . (int) $member['profile_picture_attachment_id'])
            : url('assets/images/pa-logo.png');
        echo '<article class="member-card">';
        echo '<img class="member-photo" src="' . e($photo) . '" alt="Portrait of ' . e($name) . '" loading="lazy">';
        echo '<div class="member-copy"><h3>' . e($name) . '</h3>';
        if (!empty($member['pa_title'])) {
            echo '<p class="member-title">' . e($member['pa_title']) . '</p>';
        }
        echo '<div class="member-bio">' . ($member['biography_html'] ?? '') . '</div></div></article>';
    }
}

