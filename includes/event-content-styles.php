<?php
declare(strict_types=1);

const EVENT_NATIVE_CONTENT_CLASSES = [
    'event-columns', 'event-stack', 'event-card', 'event-highlight', 'event-muted',
    'event-accent', 'event-center', 'event-eyebrow', 'event-stat',
];
const EVENT_CONTENT_SCOPE = 'event-authored-content';

function eventContentClass(string $name): string
{
    $class = in_array($name, EVENT_NATIVE_CONTENT_CLASSES, true) ? $name : 'event-custom-' . $name;
    if (class_exists(App\Services\LoggingService::class)) App\Services\LoggingService::info('events', 'website.content.class.prepared', [], 10);
    return $class;
}

/** Retain presentation declarations without resource loads, browser extensions or page overlays. */
function eventContentDeclarations(string $css): string
{
    $log = class_exists(App\Services\LoggingService::class);
    if ($log) App\Services\LoggingService::info('events', 'website.content.declarations.start', [], 8);
    static $allowed = [
        'align-content','align-items','align-self','aspect-ratio','background','background-color','background-image',
        'background-position','background-repeat','background-size','border','border-block','border-bottom','border-collapse',
        'border-color','border-inline','border-left','border-radius','border-right','border-spacing','border-style',
        'border-top','border-width','box-shadow','box-sizing','caption-side','color','column-gap','display','flex',
        'flex-basis','flex-direction','flex-flow','flex-grow','flex-shrink','flex-wrap','font','font-family','font-size',
        'font-style','font-variant','font-weight','gap','grid','grid-area','grid-auto-columns','grid-auto-flow',
        'grid-auto-rows','grid-column','grid-row','grid-template','grid-template-areas','grid-template-columns',
        'grid-template-rows','height','justify-content','justify-items','justify-self','letter-spacing','line-height',
        'list-style-position','list-style-type','margin','margin-block','margin-bottom','margin-inline','margin-left',
        'margin-right','margin-top','max-height','max-width','min-height','min-width','object-fit','object-position',
        'opacity','overflow','overflow-wrap','overflow-x','overflow-y','padding','padding-block','padding-bottom',
        'padding-inline','padding-left','padding-right','padding-top','row-gap','table-layout','text-align',
        'text-decoration','text-indent','text-shadow','text-transform','vertical-align','white-space','width',
        'word-break','word-spacing',
    ];
    static $functions = [
        'calc','clamp','color','color-mix','hsl','hsla','hwb','lab','lch','linear-gradient','max','min','minmax',
        'oklab','oklch','radial-gradient','repeat','repeating-linear-gradient','rgb','rgba','var',
    ];
    $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? '';
    $declarations = [];
    foreach (explode(';', $css) as $declaration) {
        if ($log) App\Services\LoggingService::info('events', 'website.content.declarations.item', [], 10);
        [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
        $property = strtolower(trim($property));
        $value = trim($value);
        $custom = preg_match('/^--(?!theme-|app-component-scale$)[a-z][a-z0-9-]*$/D', $property) === 1;
        if (!$custom && !in_array($property, $allowed, true)) continue;
        if (!preg_match('~^[\w#.% ,()+*/\'"-]+(?:\s*!important)?$~iD', $value)) continue;
        preg_match_all('/([a-z][a-z0-9-]*)\s*\(/i', $value, $calls);
        if (array_diff(array_map('strtolower', $calls[1]), $functions)) continue;
        $value = preg_replace_callback('/\bvar\(\s*(--[a-z][a-z0-9-]*)/i', static function (array $match) use ($log): string {
            if ($log) App\Services\LoggingService::info('events', 'website.content.declarations.variable', [], 10);
            $name = strtolower($match[1]);
            if (str_starts_with($name, '--theme-') || $name === '--app-component-scale') return $match[0];
            return 'var(--event-custom-' . substr($name, 2);
        }, $value) ?? '';
        $declarations[] = ($custom ? '--event-custom-' . substr($property, 2) : $property) . ': ' . $value;
    }
    if ($log) App\Services\LoggingService::info('events', 'website.content.declarations.complete', [], 8);
    return implode('; ', $declarations);
}

function eventContentStylesheet(string $css, bool $allowMedia = true): string
{
    $log = class_exists(App\Services\LoggingService::class);
    if ($log) App\Services\LoggingService::info('events', 'website.content.stylesheet.start', [], 3);
    if (strlen($css) > 65536) {
        if ($log) App\Services\LoggingService::warning('events', 'website.content.stylesheet.too_large');
        return '';
    }
    $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? '';
    $output = '';
    $offset = 0;
    // Consume a whole rule at a time. A single nested level allows responsive
    // rules without accidentally publishing the contents of a forbidden at-rule.
    while (preg_match('/\G\s*(?:[^{};]+;|([^{};]+)\{((?:[^{}]++|\{[^{}]*\})*)\})/s', $css, $rule, 0, $offset)) {
        $offset += strlen($rule[0]);
        if ($log) App\Services\LoggingService::info('events', 'website.content.stylesheet.rule', [], 10);
        $prelude = trim($rule[1] ?? '');
        if ($allowMedia && preg_match('/^@media\s+([a-z0-9 ():.\/,-]+)$/iD', $prelude, $media)) {
            $nested = eventContentStylesheet($rule[2], false);
            if ($nested !== '') $output .= '@media ' . $media[1] . '{' . $nested . '}';
            continue;
        }
        if ($prelude === '' || str_contains($rule[2], '{')) continue;
        $declarations = eventContentDeclarations($rule[2]);
        if ($declarations === '') continue;
        $selectors = [];
        foreach (explode(',', $prelude) as $selector) {
            if ($log) App\Services\LoggingService::info('events', 'website.content.stylesheet.selector', [], 10);
            $selector = trim($selector);
            if (!preg_match('/^[a-z.#*:][a-z0-9_.#*: >+~()-]*$/iD', $selector)) continue;
            // Only the positional pseudo-classes may contain arguments. In particular,
            // functional selector lists cannot inject a second, unscoped selector.
            $simple = preg_replace('/:(?:nth-child|nth-last-child|nth-of-type|nth-last-of-type)\([a-z0-9 +\-]+\)/i', ':nth-child', $selector);
            if ($simple === null || strpbrk($simple, '()') !== false) continue;
            $selector = preg_replace_callback('/([.#])([a-z_][a-z0-9_-]*)/i', static function (array $match) use ($log): string {
                if ($log) App\Services\LoggingService::info('events', 'website.content.stylesheet.selector_name', [], 10);
                return $match[1] === '.' ? '.' . eventContentClass($match[2]) : '#event-custom-id-' . $match[2];
            }, $selector) ?? '';
            $scope = '#' . EVENT_CONTENT_SCOPE;
            if (in_array(strtolower($selector), ['html', 'body', ':root'], true)) $selectors[] = $scope;
            else {
                $selector = preg_replace('/^(?:html +body|html|body|:root) *(?=>)|^(?:html +body|html|body|:root) +(?=[a-z.#*:])/i', '', $selector) ?? '';
                $selectors[] = $scope . ' ' . $selector;
            }
        }
        if ($selectors) $output .= implode(', ', $selectors) . '{' . $declarations . '}';
    }
    if ($log) App\Services\LoggingService::info('events', 'website.content.stylesheet.complete', [], 3);
    return $output;
}
