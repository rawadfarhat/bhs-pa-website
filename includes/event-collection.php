<?php
declare(strict_types=1);
require_once __DIR__ . '/event-data-collection-service.php';

function eventCollectionSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['use_strict_mode' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    }
    $_SESSION['event_collection_csrf'] ??= bin2hex(random_bytes(32));
}
function eventPhoneCountries(): array
{
    return json_decode((string) file_get_contents(__DIR__ . '/phone-countries.json'), true, 512, JSON_THROW_ON_ERROR);
}
function replaceEventFormPlaceholder(string $html, array $configuration): string
{
    return replaceEventText($html, '/\{event_form(?:;\s*label:([^{}]*))?\}/iu', static function (array $match) use ($configuration): string {
        if (!$configuration['enabled']) return '';
        $label = html_entity_decode(trim($match[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: 'Register';
        return '<button type="button" class="button event-collection-open" data-event-collection-open>' . e($label) . '</button>';
    });
}
function renderEventCollectionTotals(string $html, array $totals): string
{
    $html = replaceEventText($html, '/\{progress\s*;\s*field\s*:\s*(count|number_01|number_02)\s*;\s*max\s*:\s*(\d+(?:\.\d+)?)\s*(?:;\s*label\s*:([^{}]*))?\}/iu', static function (array $match) use ($totals): string {
        $key = strtolower($match[1]);
        $maximum = (float) $match[2];
        if (!is_finite($maximum) || $maximum <= 0) return e($match[0]);
        $raw = (string) ($totals[$key] ?? '0');
        $value = max(0, min($maximum, (float) $raw));
        $label = html_entity_decode(trim($match[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: 'Progress';
        return '<span class="event-progress" data-event-progress="' . e($key) . '"><span class="event-progress-heading"><span>' . e($label) . '</span><span><strong data-event-collection-stat="' . e($key) . '">' . e($raw) . '</strong> / ' . e($match[2]) . '</span></span><progress max="' . e($match[2]) . '" value="' . e((string) $value) . '" aria-label="' . e($label) . '" aria-valuetext="' . e($raw . ' of ' . $match[2]) . '"></progress></span>';
    });
    return replaceEventText($html, '/\{count\}|\{sum\s*;\s*field\s*:\s*(number_01|number_02)\s*\}/iu', static function (array $match) use ($totals): string {
        $key = isset($match[1]) ? strtolower($match[1]) : 'count';
        return '<span data-event-collection-stat="' . e($key) . '">' . e((string) ($totals[$key] ?? '0')) . '</span>';
    });
}
function renderEventCollectionForm(array $event, array $configuration): string
{
    ob_start(); ?>
<dialog id="event-collection-dialog" class="event-collection-dialog" aria-labelledby="event-collection-title">
  <button type="button" class="event-collection-close" aria-label="Close form">×</button>
  <h2 id="event-collection-title"><?= e($event['name']) ?></h2>
  <?php if ($configuration['subtext'] !== ''): ?><p class="event-description" data-event-collection-intro><?= e($configuration['subtext']) ?></p><?php endif; ?>
  <form action="<?= e(url('submit-event-information.php')) ?>" method="post" id="event-collection-form" data-totals-url="<?= e(url('event-collection-totals.php?event_id=' . (int) $event['id'])) ?>">
    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['event_collection_csrf']) ?>">
    <label class="event-collection-trap" aria-hidden="true">Leave empty<input name="website" tabindex="-1" autocomplete="off"></label>
    <?php foreach ($configuration['fields'] as $key => $field): if (!$field['enabled']) continue; ?>
    <label for="collect-<?= e($key) ?>"><?= e($field['label']) ?> <?= $field['required'] ? '(required)' : '(optional)' ?></label>
    <?php if ($key === 'phone'): ?>
    <div class="event-phone-input">
      <div class="event-country-picker">
        <input type="hidden" name="phone_country" value="LB">
        <button type="button" class="event-country-toggle" aria-label="Phone country: Lebanon +961" aria-haspopup="listbox" aria-expanded="false" aria-controls="event-country-options"><img data-phone-country-flag src="<?= e(url('assets/flags/lb.svg')) ?>" alt="" width="24" height="18"><span data-phone-country-code>+961</span><span aria-hidden="true">▾</span></button>
        <div class="event-country-panel" hidden>
          <input type="search" class="event-country-search" aria-label="Search phone countries" placeholder="Search countries" autocomplete="off">
          <div id="event-country-options" role="listbox" aria-label="Phone country and calling code">
            <?php foreach (eventPhoneCountries() as $iso => $country): ?>
            <button type="button" role="option" tabindex="-1" aria-selected="<?= $iso === 'LB' ? 'true' : 'false' ?>" data-country="<?= e($iso) ?>" data-code="<?= e($country['code']) ?>" data-name="<?= e($country['name']) ?>"><img src="<?= e(url('assets/flags/' . strtolower($iso) . '.svg')) ?>" alt="" width="24" height="18" loading="lazy"><span><?= e($country['name']) ?></span><span>+<?= e($country['code']) ?></span></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <input id="collect-phone" name="phone" type="tel" autocomplete="tel-national" maxlength="40" <?= $field['required'] ? 'required' : '' ?>>
    </div>
    <?php elseif (str_starts_with($key, 'text_')): ?>
    <textarea id="collect-<?= e($key) ?>" name="<?= e($key) ?>" maxlength="2000" rows="3" <?= $field['required'] ? 'required' : '' ?>></textarea>
    <?php else: $type = $key === 'email' ? 'email' : (str_starts_with($key, 'number_') ? 'number' : 'text'); ?>
    <input id="collect-<?= e($key) ?>" name="<?= e($key) ?>" type="<?= $type ?>" <?= $type === 'number' ? 'step="any"' : 'maxlength="190"' ?> <?= in_array($key, ['email','name'], true) ? 'autocomplete="' . $key . '"' : '' ?> <?= $field['required'] ? 'required' : '' ?>>
    <?php endif; endforeach; ?>
    <?php if ($configuration['consent']): ?><label class="event-collection-consent"><input type="checkbox" name="consent" value="1" required> <span>I agree to the use of this information as described in the <a href="<?= e(url('terms')) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</span></label><?php endif; ?>
    <p class="event-collection-privacy">Your information is used to administer this event and communicate about it. Individual submissions are accessible only to authorized PA staff, including administrators. Read our <a href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener">Privacy Policy</a> and <a href="<?= e(url('terms')) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>. For correction or deletion requests, contact <a href="mailto:weserve@bhs-pa.com?subject=Event%20data%20privacy%20request">weserve@bhs-pa.com</a>.</p>
    <button type="submit" class="button"><?= e($configuration['submit_label']) ?></button>
  </form>
  <p id="event-collection-message" role="status" aria-live="polite" tabindex="-1"></p>
</dialog>
<?php return (string) ob_get_clean();
}
