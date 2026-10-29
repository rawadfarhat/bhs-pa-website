<?php
declare(strict_types=1);

/** Public rendering only; publication and media eligibility remain in their queries. */
function renderEventIntroduction(array $event, int $bannerId): array
{
    $logger = class_exists(App\Services\LoggingService::class);
    if ($logger) App\Services\LoggingService::info('events', 'website.introduction.start', [], 3);
    $placement = $event['description_placement'] ?? 'banner';
    if (!in_array($placement, ['hidden', 'banner', 'body'], true)) {
        if ($logger) App\Services\LoggingService::warning('events', 'website.introduction.invalid_placement');
        $placement = 'banner';
    }
    $text = '';
    if ($placement !== 'hidden') {
        if ($logger) App\Services\LoggingService::info('events', 'website.introduction.text', [], 10);
        $date = !empty($event['event_date']) ? date('F j, Y', strtotime($event['event_date'])) : 'Community event';
        $text = '<p class="eyebrow">' . e($date) . '</p><h1>' . e($event['name']) . '</h1>';
        if (!empty($event['description'])) $text .= '<p class="event-description">' . e($event['description']) . '</p>';
    }
    $banner = '';
    if ($bannerId > 0 || $placement === 'banner') {
        if ($logger) App\Services\LoggingService::info('events', 'website.introduction.banner', [], 10);
        $background = '';
        if ($bannerId > 0) {
            $overlay = $placement === 'banner' ? 'linear-gradient(rgba(0,0,0,.55),rgba(0,0,0,.55)),' : '';
            $background = ' style="background-image:' . $overlay . 'url(\'' . e(url('event-photo.php?id=' . $bannerId . '&variant=large')) . '\')"';
        }
        $banner = '<section class="page-hero' . ($bannerId > 0 ? ' page-hero-photo' : '') . ($placement !== 'banner' ? ' event-banner-only' : '') . '"' . $background . '>'
            . ($placement === 'banner' ? '<div class="shell">' . $text . '</div>' : '') . '</section>';
    }
    $body = $placement === 'body' ? '<header class="event-body-introduction">' . $text . '</header>' : '';
    if ($logger) App\Services\LoggingService::info('events', 'website.introduction.complete', [], 3);
    return ['banner' => $banner, 'body' => $body];
}
