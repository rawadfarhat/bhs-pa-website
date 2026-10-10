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
    // Public page links use clean routes; file and form endpoints keep their URLs.
    $path = preg_replace('~^(event|events|pa-members|subscribe|terms|festival-of-peace-2026)\.(?:php|html)(?=[?#]|$)~', '$1', $path);
    $path = preg_replace('~^index\.(?:php|html)(?=[?#]|$)~', '', $path);
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

function publicEvents(): array
{
    static $events;
    if (is_array($events)) return $events;
    try {
        $events = db()->query("SELECT id,name,event_date,description FROM events WHERE deleted_at IS NULL AND status='active' AND publish_to_website=1 ORDER BY event_date DESC,id DESC")->fetchAll();
    } catch (Throwable $error) {
        error_log('Public event query failed: ' . $error->getMessage());
        $events = [];
    }
    return $events;
}

function publicEvent(int $id): ?array
{
    try {
        $stmt = db()->prepare("SELECT id,name,event_date,description,events_page_content_html,publish_photos,data_collection_config FROM events WHERE id=:id AND deleted_at IS NULL AND status='active' AND publish_to_website=1 LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $error) {
        error_log('Public event lookup failed: ' . $error->getMessage());
        return null;
    }
}

function publicEventPhotos(int $eventId): array
{
    try {
        $stmt = db()->prepare("SELECT a.id,a.file_name,a.image_width,a.image_height,
            COALESCE((SELECT v.width FROM attachment_image_variants v WHERE v.attachment_id=a.id AND v.variant='medium'),a.image_width) display_width,
            COALESCE((SELECT v.height FROM attachment_image_variants v WHERE v.attachment_id=a.id AND v.variant='medium'),a.image_height) display_height
            FROM attachments a INNER JOIN events e ON e.id=a.entity_id
            WHERE a.entity_type='event_photo' AND a.entity_id=:event_id AND a.mime_type IN ('image/jpeg','image/png','image/webp')
              AND e.deleted_at IS NULL AND e.status='active' AND e.publish_to_website=1 AND e.publish_photos=1
            ORDER BY a.id ASC");
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    } catch (Throwable $error) {
        error_log('Public event photo query failed: ' . $error->getMessage());
        return [];
    }
}

function publicEventAttachments(int $eventId): array
{
    try {
        $stmt = db()->prepare("SELECT a.id,a.file_name,a.mime_type
            FROM attachments a INNER JOIN events e ON e.id=a.entity_id
            WHERE a.entity_type='event_attachment' AND a.entity_id=:event_id
              AND e.deleted_at IS NULL AND e.status='active' AND e.publish_to_website=1
            ORDER BY a.id ASC");
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    } catch (Throwable $error) {
        error_log('Public event attachment query failed: ' . $error->getMessage());
        return [];
    }
}

/** Replace only text nodes, never attributes or executable markup. */
function replaceEventText(string $html, string $pattern, callable $render): string
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div id="replacement-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('replacement-root');
    if (!$root) return '';
    $xpath = new DOMXPath($doc);
    foreach (iterator_to_array($xpath->query('.//text()', $root)) as $node) {
        $text = $node->nodeValue;
        if (!preg_match($pattern, $text)) continue;
        $replaced = preg_replace_callback($pattern, $render, htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $fragmentDoc = new DOMDocument();
        $fragmentDoc->loadHTML('<?xml encoding="utf-8" ?><div id="fragment-root">' . $replaced . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $fragmentRoot = $fragmentDoc->getElementById('fragment-root');
        if ($fragmentRoot) foreach (iterator_to_array($fragmentRoot->childNodes) as $child) $node->parentNode->insertBefore($doc->importNode($child, true), $node);
        $node->parentNode->removeChild($node);
    }
    $output = '';
    foreach ($root->childNodes as $child) $output .= $doc->saveHTML($child);
    return $output;
}
function replaceEventAttachmentPlaceholders(string $html, array $attachments): string
{
    foreach ($attachments as $attachment) {
        $name = (string) ($attachment['file_name'] ?? '');
        if ($name === '') continue;
        $safeName = str_replace(['{', '}'], '', $name);
        $filePatterns = array_unique([preg_quote($safeName, '/'), preg_quote(e($safeName), '/')]);
        $pattern = '/\{attachment:\s*(?:' . implode('|', $filePatterns) . ')\s*((?:;[^{}]*)?)\}/iu';
        $html = replaceEventText($html, $pattern, static function (array $match) use ($attachment, $name): string {
            $options = [];
            foreach (explode(';', html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $option) {
                $parts = explode(':', $option, 2);
                if (count($parts) === 2) $options[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            $label = ($options['name'] ?? '') ?: $name;
            $source = e(url('event-attachment.php?id=' . (int) $attachment['id']));
            $link = '<a href="' . $source . '&amp;download=1">' . e($label) . '</a>';
            if (($options['display'] ?? '') !== 'embed') return $link;
            $mime = strtolower((string) ($attachment['mime_type'] ?? ''));
            $preview = '';
            if (in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) $preview = '<img src="' . $source . '" alt="' . e($label) . '" loading="lazy">';
            elseif (in_array($mime, ['video/mp4','video/webm','video/ogg'], true)) $preview = '<video src="' . $source . '" controls preload="metadata" aria-label="' . e($label) . '"></video>';
            elseif ($mime === 'application/pdf') $preview = '<iframe src="' . $source . '" title="' . e($label) . '" loading="lazy"></iframe>';
            return $preview === '' ? $link : '<span class="event-attachment-embed">' . $preview . $link . '</span>';
        });
    }
    return $html;
}

function renderEventGallery(array $event, array $photos): string
{
    if ($photos === []) return '';
    ob_start();
    ?>
    <section class="event-gallery-block" aria-label="<?= e($event['name']) ?> photo gallery" data-event-gallery>
        <div class="event-gallery-heading"><h2>Photo gallery</h2><p>Scroll through the thumbnails or select one to open the gallery.</p></div>
        <div class="event-thumbnail-strip" aria-label="Event photo thumbnails">
            <?php foreach ($photos as $index => $photo): $base = 'event-photo.php?id=' . (int) $photo['id'] . '&variant='; ?>
                <button class="event-gallery-thumb" type="button" data-gallery-index="<?= $index ?>" data-medium="<?= e(url($base . 'medium')) ?>" data-large="<?= e(url($base . 'large')) ?>" data-alt="<?= e($event['name']) ?> photo <?= $index + 1 ?>" aria-label="Open <?= e($event['name']) ?> photo <?= $index + 1 ?>">
                    <img src="<?= e(url($base . 'thumbnail')) ?>" width="480" height="320" alt="<?= e($event['name']) ?> photo <?= $index + 1 ?>" loading="lazy" decoding="async">
                </button>
            <?php endforeach; ?>
        </div>
        <dialog class="event-gallery-dialog" aria-label="<?= e($event['name']) ?> expanded photo gallery">
            <div class="event-gallery-dialog-shell">
                <button class="event-gallery-close" type="button" data-gallery-close aria-label="Close photo gallery">×</button>
                <button class="event-gallery-nav event-gallery-previous" type="button" data-gallery-previous aria-label="Previous photo">‹</button>
                <figure><div class="event-gallery-photo"><img alt=""></div><figcaption aria-live="polite"></figcaption></figure>
                <button class="event-gallery-nav event-gallery-next" type="button" data-gallery-next aria-label="Next photo">›</button>
                <div class="event-gallery-dialog-thumbs" aria-label="Choose a photo">
                    <?php foreach ($photos as $index => $photo): $base = 'event-photo.php?id=' . (int) $photo['id'] . '&variant='; ?>
                        <button type="button" data-dialog-gallery-index="<?= $index ?>" aria-label="Show photo <?= $index + 1 ?>"><img src="<?= e(url($base . 'thumbnail')) ?>" width="120" height="80" alt="" loading="lazy" decoding="async"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </dialog>
    </section>
    <?php
    return (string) ob_get_clean();
}

/** @return array{html:string,gallery_placed:bool} */
function renderPublicEventContent(?string $html, array $attachments, string $galleryHtml): array
{
    $content = replaceEventAttachmentPlaceholders(safeEventHtml((string) $html), $attachments);
    $galleryPlaced = false;
    if ($galleryHtml !== '') {
        $content = preg_replace('/<p>\s*\{image_gallery\}\s*<\/p>|\{image_gallery\}/i', '__EVENT_IMAGE_GALLERY__', $content) ?? $content;
        if (str_contains($content, '__EVENT_IMAGE_GALLERY__')) {
            $content = preg_replace('/__EVENT_IMAGE_GALLERY__/', $galleryHtml, $content, 1) ?? $content;
            $content = str_replace('__EVENT_IMAGE_GALLERY__', '', $content);
            $galleryPlaced = true;
        }
    } else {
        $content = preg_replace('/<p>\s*\{image_gallery\}\s*<\/p>|\{image_gallery\}/i', '', $content) ?? $content;
    }
    return ['html' => $content, 'gallery_placed' => $galleryPlaced];
}

function safeEventHtml(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') return '';
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><div id="event-content">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $allowed = ['div','p','br','strong','b','em','i','u','ul','ol','li','h2','h3','h4','blockquote','table','thead','tbody','tr','th','td','a','span'];
    $nodes = $document->getElementsByTagName('*');
    for ($i = $nodes->length - 1; $i >= 0; $i--) {
        $node = $nodes->item($i);
        if (!$node instanceof DOMElement || $node->getAttribute('id') === 'event-content') continue;
        if (!in_array(strtolower($node->tagName), $allowed, true)) {
            $node->parentNode?->removeChild($node);
            continue;
        }
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if ($name === 'class') {
                $classes = array_intersect(preg_split('/\s+/', trim($attribute->value)), ['event-columns', 'event-stack', 'event-card', 'event-highlight', 'event-muted', 'event-accent', 'event-center', 'event-eyebrow', 'event-stat']);
                if ($classes) { $node->setAttribute('class', implode(' ', array_unique($classes))); continue; }
            }
            if ($node->tagName === 'a' && $name === 'href') {
                $href = html_entity_decode(trim($attribute->value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $scheme = parse_url($href, PHP_URL_SCHEME);
                if (!preg_match('/[\x00-\x1F\x7F]/', $href)
                    && !str_starts_with($href, '//')
                    && ($scheme === null || in_array(strtolower((string) $scheme), ['http', 'https', 'mailto'], true))) continue;
            }
            if ($node->tagName === 'a' && in_array($name, ['title'], true)) continue;
            $node->removeAttribute($attribute->name);
        }
        if ($node->tagName === 'a') $node->setAttribute('rel', 'noopener noreferrer');
    }
    $root = $document->getElementById('event-content');
    $output = '';
    if ($root) foreach ($root->childNodes as $child) $output .= $document->saveHTML($child);
    return $output;
}

