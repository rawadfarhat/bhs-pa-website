<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function websiteEventAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$header = file_get_contents(__DIR__ . '/../includes/header.php');
$detail = file_get_contents(__DIR__ . '/../event.php');
$endpoint = file_get_contents(__DIR__ . '/../event-photo.php');
$attachmentEndpoint = file_get_contents(__DIR__ . '/../event-attachment.php');
$galleryScript = file_get_contents(__DIR__ . '/../assets/event-gallery.js');
$bootstrap = file_get_contents(__DIR__ . '/../includes/bootstrap.php');
websiteEventAssert(str_contains($header, '$publishedEvents !== []'), 'Events menu should be hidden when there are no published events.');
websiteEventAssert(str_contains($header, 'nav-submenu') && str_contains($header, 'event.php?id='), 'Published events need a navigation submenu.');
websiteEventAssert(str_contains($bootstrap, "status='active'") && str_contains($bootstrap, 'publish_to_website=1'), 'Public event queries must enforce visibility.');
websiteEventAssert(str_contains($detail, 'renderPublicEventContent') && str_contains($detail, 'renderEventGallery'), 'The image gallery placeholder must be replaced at its content position.');
websiteEventAssert(str_contains($endpoint, "publish_photos=1") && str_contains($endpoint, "publish_to_website=1") && str_contains($endpoint, "status='active'"), 'Photo delivery must enforce public event authorization.');
websiteEventAssert(str_contains($endpoint, "['thumbnail','medium','large']") && str_contains($endpoint, 'HTTP_IF_NONE_MATCH') && str_contains($endpoint, 'http_response_code(304)'), 'Photo delivery needs a fixed variant allowlist and conditional caching.');
websiteEventAssert(str_contains($endpoint, '$photo[\'variant_blob\'] ?? $photo[\'source_blob\']'), 'Existing photos need source compatibility fallback.');
websiteEventAssert(str_contains($attachmentEndpoint, "entity_type='event_attachment'") && str_contains($attachmentEndpoint, 'publish_to_website=1'), 'Attachment delivery must be scoped to public event attachments.');
$gallery = renderEventGallery(['name' => 'Autumn Fair'], [['id' => 11], ['id' => 12]]);
websiteEventAssert(str_contains($gallery, 'event-thumbnail-strip') && str_contains($gallery, 'variant=thumbnail') && str_contains($gallery, 'loading="lazy"') && str_contains($gallery, 'decoding="async"'), 'The initial gallery must be a scrollable lazy thumbnail gallery.');
websiteEventAssert(str_contains($gallery, 'event-gallery-dialog') && str_contains($gallery, 'data-gallery-previous') && str_contains($gallery, 'data-gallery-next'), 'The expanded gallery needs previous and next carousel controls.');
websiteEventAssert(str_contains($galleryScript, 'ArrowLeft') && str_contains($galleryScript, 'ArrowRight') && str_contains($galleryScript, 'showModal()'), 'The carousel must support keyboard navigation and a modal viewer.');
$attachmentContent = replaceEventAttachmentPlaceholders('<p>{attachment:Agenda.pdf}</p>', [['id' => 42, 'file_name' => 'Agenda.pdf']]);
websiteEventAssert(str_contains($attachmentContent, 'event-attachment.php?id=42') && !str_contains($attachmentContent, '{attachment:'), 'Attachment placeholders must become public attachment links.');
$renamedAttachment = replaceEventAttachmentPlaceholders('<p>{attachment:Agenda.pdf;name: File 01}</p>', [['id' => 42, 'file_name' => 'Agenda.pdf']]);
websiteEventAssert(str_contains($renamedAttachment, '>File 01</a>') && !str_contains($renamedAttachment, '>Agenda.pdf</a>'), 'Attachment placeholders should support optional custom display text.');
$emptyAttachmentName = replaceEventAttachmentPlaceholders('<p>{attachment:Agenda.pdf;name: }</p>', [['id' => 42, 'file_name' => 'Agenda.pdf']]);
websiteEventAssert(str_contains($emptyAttachmentName, '>Agenda.pdf</a>'), 'Empty attachment display text should fall back to the filename.');
$escapedAttachmentName = replaceEventAttachmentPlaceholders('<p>{attachment:Agenda.pdf;name:&lt;Agenda &amp; Notes&gt;}</p>', [['id' => 42, 'file_name' => 'Agenda.pdf']]);
websiteEventAssert(str_contains($escapedAttachmentName, '&lt;Agenda &amp; Notes&gt;') && !str_contains($escapedAttachmentName, '<Agenda'), 'Custom attachment display text must remain HTML-safe.');
$renderedContent = renderPublicEventContent('<p>Before</p><p>{image_gallery}</p><p>{attachment:Agenda.pdf}</p>', [['id' => 42, 'file_name' => 'Agenda.pdf']], '<section id="rendered-gallery"></section>');
websiteEventAssert($renderedContent['gallery_placed'], 'The gallery placeholder must be detected.');
websiteEventAssert(str_contains($renderedContent['html'], 'rendered-gallery'), 'The gallery must render at the placeholder.');
websiteEventAssert(str_contains($renderedContent['html'], 'event-attachment.php?id=42'), 'The attachment must render at its placeholder.');
websiteEventAssert(!str_contains($renderedContent['html'], '{image_gallery}'), 'Rendered event content must not retain the gallery placeholder.');
$safeHtml = safeEventHtml('<p onclick="bad()">Welcome <strong>families</strong><script>alert(1)</script><a href="javascript:bad()">link</a></p>');
websiteEventAssert(str_contains($safeHtml, '<strong>families</strong>') && !str_contains($safeHtml, 'onclick') && !str_contains($safeHtml, '<script') && !str_contains($safeHtml, 'javascript:'), 'Published event HTML must be sanitized.');
fwrite(STDOUT, "public event website checks passed\n");
