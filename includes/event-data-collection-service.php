<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;

/** Public form contract; keep the website's includes/event-data-collection-service.php in sync. */
final class EventDataCollectionService
{
    public const DEFAULT_SUCCESS_MESSAGE = 'Thank you for submitting your information';
    public const FIELDS = [
        'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
        'number_01' => 'Number 01', 'number_02' => 'Number 02', 'text_01' => 'Text 01', 'text_02' => 'Text 02',
        'number_03' => 'Number 03', 'text_03' => 'Text 03',
        'checkbox_01' => 'Checkbox 01', 'checkbox_02' => 'Checkbox 02', 'checkbox_03' => 'Checkbox 03',
        'radio_01' => 'Radio group',
        'dropdown_01' => 'Dropdown 01', 'dropdown_02' => 'Dropdown 02', 'dropdown_03' => 'Dropdown 03',
        'phone_01' => 'Phone 01', 'phone_02' => 'Phone 02', 'phone_03' => 'Phone 03',
        'textarea_01' => 'Text area 01', 'textarea_02' => 'Text area 02',
    ];

    public const CHOICE_FIELDS = ['radio_01', 'dropdown_01', 'dropdown_02', 'dropdown_03'];
    public const PHONE_FIELDS = ['phone', 'phone_01', 'phone_02', 'phone_03'];
    public const MULTILINE_FIELDS = ['text_01', 'text_02', 'textarea_01', 'textarea_02'];
    public const NUMBER_FIELDS = ['number_01', 'number_02', 'number_03'];

    /** Display-only attendee ceiling; null/blank/invalid values leave totals unchanged. */
    public static function displayTotals(array $totals, mixed $maximum, mixed $maximum02 = null): array
    {
        $log = class_exists(LoggingService::class);
        if ($log) LoggingService::info('event-data-collection', 'displayTotals.start', [], 3);
        foreach (['number_01' => $maximum, 'number_02' => $maximum02] as $field => $ceiling) {
            if ($log) LoggingService::info('event-data-collection', 'displayTotals.field', [], 10);
            $maximum = $ceiling;
            $max = is_scalar($maximum) ? trim((string) $maximum) : '';
            if (!preg_match('/^\d+(?:\.\d{1,6})?$/D', $max)) {
                if ($log) LoggingService::info('event-data-collection', 'displayTotals.unconfigured', [], 10);
                continue;
            }
            // Compare decimal strings exactly, without converting large totals to floats.
            $normalize = static function (string $value): array {
                if (class_exists(LoggingService::class)) LoggingService::info('event-data-collection', 'displayTotals.normalize', [], 8);
                $parts = explode('.', $value, 2);
                return [ltrim($parts[0], '0') ?: '0', str_pad($parts[1] ?? '', 6, '0')];
            };
            $actual = (string) ($totals[$field] ?? '0');
            if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $actual)) {
                if ($log) LoggingService::info('event-data-collection', 'displayTotals.compare', [], 10);
                [$a, $af] = $normalize($actual); [$b, $bf] = $normalize($max);
                if (strlen($a) > strlen($b) || (strlen($a) === strlen($b) && (strcmp($a, $b) > 0 || ($a === $b && strcmp($af, $bf) >= 0)))) {
                    if ($log) LoggingService::info('event-data-collection', 'displayTotals.ceiling', [], 10);
                    $totals[$field] = $b . (($fraction = rtrim($bf, '0')) !== '' ? '.' . $fraction : '');
                }
            }
        }
        if ($log) LoggingService::info('event-data-collection', 'displayTotals.complete', [], 3);
        return $totals;
    }

    public static function totals(PDO $pdo, int $eventId): array
    {
        $query = $pdo->prepare('SELECT COUNT(*) AS count, COALESCE(SUM(number_01),0) AS number_01, COALESCE(SUM(number_02),0) AS number_02, COALESCE(SUM(number_03),0) AS number_03 FROM event_data_collection WHERE event_id=?');
        $query->execute([$eventId]);
        $totals = $query->fetch(PDO::FETCH_ASSOC);
        foreach ($totals as $key => $value) {
            // Keep DECIMAL precision, including totals too large for a float.
            $text = (string) $value;
            if (str_contains($text, '.')) $text = rtrim(rtrim($text, '0'), '.');
            $totals[$key] = $text === '-0' ? '0' : $text;
        }
        return $totals;
    }

    public static function configuration(mixed $input): array
    {
        $log = class_exists(LoggingService::class);
        if ($log) LoggingService::info('event-data-collection', 'configuration.start', [], 1);
        if (is_string($input)) $input = json_decode($input, true);
        if (!is_array($input)) $input = [];
        $result = ['enabled' => !empty($input['enabled']), 'consent' => !empty($input['consent']), 'submit_label' => self::text($input['submit_label'] ?? 'Submit', 100) ?: 'Submit', 'subtext' => self::text($input['subtext'] ?? '', 2000), 'fields' => []];
        $result['success_message'] = self::text($input['success_message'] ?? '', 2000) ?: self::DEFAULT_SUCCESS_MESSAGE;
        $result['field_order'] = self::fieldOrder($input['field_order'] ?? []);
        foreach ($result['field_order'] as $key) {
            if ($log) LoggingService::info('event-data-collection', 'configuration.field', [], 10);
            $label = self::FIELDS[$key];
            $field = is_array($input['fields'][$key] ?? null) ? $input['fields'][$key] : [];
            $result['fields'][$key] = ['enabled' => !empty($field['enabled']), 'required' => !empty($field['enabled']) && !empty($field['required']), 'label' => self::text($field['label'] ?? $label, 150) ?: $label];
            if (in_array($key, self::CHOICE_FIELDS, true)) {
                $result['fields'][$key]['options'] = self::options($field['options'] ?? []);
                if ($result['enabled'] && $result['fields'][$key]['enabled'] && !$result['fields'][$key]['options']) {
                    if ($log) LoggingService::warning('event-data-collection', 'configuration.choices.missing');
                    throw new InvalidArgumentException($label . ' needs at least one option.');
                }
            }
            if (str_starts_with($key, 'number_')) {
                if (class_exists(LoggingService::class)) LoggingService::info('event-data-collection', 'limits.configuration.start', [], 3);
                foreach (['min', 'max'] as $bound) {
                    if (class_exists(LoggingService::class)) LoggingService::info('event-data-collection', 'limits.configuration.bound', [], 10);
                    $value = self::text($field[$bound] ?? '', 190);
                    if ($value !== '' && !preg_match('/^-?\d{1,15}(?:\.\d{1,6})?$/D', $value)) {
                    if (class_exists(LoggingService::class)) LoggingService::warning('event-data-collection', 'limits.rejected');
                    throw new InvalidArgumentException($label . ' limits must be numbers (up to six decimal places).');
                }
                    $result['fields'][$key][$bound] = $value === '' ? null : $value;
                }
                if (class_exists(LoggingService::class)) LoggingService::info('event-data-collection', 'limits.configuration.prepared', [], 3);
                $limits = $result['fields'][$key];
                if ($limits['min'] !== null && $limits['max'] !== null && self::compareNumbers($limits['min'], $limits['max']) > 0) {
                    if (class_exists(LoggingService::class)) LoggingService::warning('event-data-collection', 'limits.rejected');
                    throw new InvalidArgumentException($label . ' minimum must not exceed its maximum.');
                }
            }
        }
        if ($result['enabled'] && !array_filter($result['fields'], fn($field) => $field['enabled'])) {
            if ($log) LoggingService::warning('event-data-collection', 'configuration.fields.missing');
            throw new InvalidArgumentException('Select at least one field to collect.');
        }
        if ($log) LoggingService::info('event-data-collection', 'configuration.complete', [], 1);
        return $result;
    }

    private static function fieldOrder(mixed $input): array
    {
        $log = class_exists(LoggingService::class);
        if ($log) LoggingService::info('event-data-collection', 'fieldOrder.start', [], 8);
        $order = [];
        if (!is_array($input) || !array_is_list($input)) {
            if ($log) LoggingService::warning('event-data-collection', 'fieldOrder.invalid');
            $input = [];
        }
        foreach (array_slice($input, 0, count(self::FIELDS)) as $key) {
            if ($log) LoggingService::info('event-data-collection', 'fieldOrder.field', [], 10);
            if (is_string($key) && isset(self::FIELDS[$key])) $order[$key] = true;
        }
        if ($log) LoggingService::info('event-data-collection', 'fieldOrder.complete', [], 8);
        return array_keys($order + self::FIELDS);
    }

    /** Option text is also the stored value, so older answers remain readable after edits. */
    private static function options(mixed $input): array
    {
        $log = class_exists(LoggingService::class);
        if ($log) LoggingService::info('event-data-collection', 'options.start', [], 8);
        if (!is_array($input) || !array_is_list($input) || count($input) > 50) {
            if ($log) LoggingService::warning('event-data-collection', 'options.list.rejected');
            throw new InvalidArgumentException('Use up to 50 options per choice field.');
        }
        $options = [];
        foreach ($input as $option) {
            if ($log) LoggingService::info('event-data-collection', 'options.item', [], 10);
            if (!is_string($option) || preg_match('/[\r\n\x00]/', $option)) {
                if ($log) LoggingService::warning('event-data-collection', 'options.item.rejected');
                throw new InvalidArgumentException('Each option must be one line of text.');
            }
            $option = self::text($option, 190);
            if ($option === '') continue;
            if (in_array($option, $options, true)) {
                if ($log) LoggingService::warning('event-data-collection', 'options.duplicate.rejected');
                throw new InvalidArgumentException('Use distinct options in each choice field.');
            }
            $options[] = $option;
        }
        if ($log) LoggingService::info('event-data-collection', 'options.complete', [], 8);
        return $options;
    }

    /** Compare signed decimals exactly at database precision. */
    private static function compareNumbers(string $a, string $b): int
    {
        if (class_exists(LoggingService::class)) LoggingService::info('event-data-collection', 'limits.compare.start', [], 8);
        $negativeA = str_starts_with($a, '-') && trim($a, '-0.') !== '';
        $negativeB = str_starts_with($b, '-') && trim($b, '-0.') !== '';
        $partsA = explode('.', ltrim($a, '-'), 2);
        $partsB = explode('.', ltrim($b, '-'), 2);
        $left = str_pad($partsA[0], 15, '0', STR_PAD_LEFT) . str_pad($partsA[1] ?? '', 6, '0');
        $right = str_pad($partsB[0], 15, '0', STR_PAD_LEFT) . str_pad($partsB[1] ?? '', 6, '0');
        $result = $negativeA !== $negativeB ? ($negativeA ? -1 : 1) : (strcmp($left, $right) <=> 0) * ($negativeA ? -1 : 1);
        if (class_exists(LoggingService::class)) LoggingService::info('event-data-collection', 'limits.compare.complete', [], 8);
        return $result;
    }

    private static function text(mixed $value, int $limit): string
    {
        if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException('Invalid field value.');
        $text = trim((string) $value);
        if (mb_strlen($text) > $limit) throw new InvalidArgumentException('A field exceeds its maximum length.');
        return $text;
    }

    public static function phone(string $raw, string $country, array $countries): string
    {
        if (!isset($countries[$country])) throw new InvalidArgumentException('Choose a phone country.');
        if (!preg_match('/^\+?[\d\s().-]+$/D', trim($raw))) throw new InvalidArgumentException('Enter a valid phone number.');
        $digits = preg_replace('/\D/', '', $raw);
        $metadata = $countries[$country];
        if (str_starts_with(trim($raw), '+')) $international = true;
        elseif (preg_match('~^(?:' . $metadata['international_prefix'] . ')~', $digits, $prefix)) {
            $digits = substr($digits, strlen($prefix[0])); $international = true;
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2); $international = true;
        } else $international = false;
        if ($international) {
            $matches = array_filter($countries, fn($item) => str_starts_with($digits, $item['code']));
            if (!$matches) throw new InvalidArgumentException('Enter a valid country calling code.');
            if (isset($matches[$country])) $matches = [$country => $metadata] + $matches;
        } else $matches = [$country => $metadata];
        foreach ($matches as $candidateMetadata) {
            $national = $international ? substr($digits, strlen($candidateMetadata['code'])) : $digits;
            $normalized = self::nationalPhone($national, $candidateMetadata);
            if ($normalized !== null) return $normalized;
        }
        throw new InvalidArgumentException('Enter a valid phone number for the selected country.');
    }

    private static function nationalPhone(string $digits, array $metadata): ?string
    {
        $valid = static fn($number) => in_array(strlen($number), $metadata['lengths'], true) && preg_match('~^(?:' . $metadata['pattern'] . ')$~D', $number);
        if ($metadata['prefix'] !== '' && preg_match('~^(?:' . $metadata['prefix'] . ')~', $digits, $prefix)) {
            $candidate = substr($digits, strlen($prefix[0]));
            if ($metadata['transform'] !== '' && count($prefix) > 1 && end($prefix) !== '') {
                $replacement = str_replace(['\\1','\\2','\\3','\\4','\\5'], ['$1','$2','$3','$4','$5'], $metadata['transform']);
                $candidate = preg_replace('~^(?:' . $metadata['prefix'] . ')~', $replacement, $digits, 1);
            }
            if ($valid($candidate)) $digits = $candidate;
        }
        $normalized = $metadata['code'] . $digits;
        if (!$valid($digits) || !preg_match('/^[1-9]\d{6,14}$/D', $normalized)) return null;
        return '+' . $normalized;
    }

    public static function values(array $configuration, array $input, array $countries): array
    {
        $log = class_exists(LoggingService::class);
        if ($log) LoggingService::info('event-data-collection', 'values.start', [], 1);
        $values = [];
        foreach ($configuration['fields'] as $key => $field) {
            if ($log) LoggingService::info('event-data-collection', 'values.field', [], 10);
            if (!$field['enabled']) continue;
            $value = self::text($input[$key] ?? '', in_array($key, self::MULTILINE_FIELDS, true) ? 2000 : 190);
            if (str_starts_with($key, 'checkbox_') && $value !== '') {
                if (!in_array($value, ['0', '1'], true) || ($field['required'] && $value !== '1')) {
                    if ($log) LoggingService::warning('event-data-collection', 'values.checkbox.rejected');
                    throw new InvalidArgumentException($field['required'] ? $field['label'] . ' must be checked.' : 'Invalid checkbox value.');
                }
            }
            if ($value === '') {
                if ($field['required']) throw new InvalidArgumentException($field['label'] . ' is required.');
                continue; // Blank optional fields never erase existing values.
            }
            if ($key === 'email') {
                $value = strtolower($value);
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
            }
            if (in_array($key, self::PHONE_FIELDS, true)) {
                if ($log) LoggingService::info('event-data-collection', 'values.phone.normalize', [], 8);
                $value = self::phone($value, self::text($input[$key . '_country'] ?? 'LB', 2), $countries);
            }
            if (in_array($key, self::CHOICE_FIELDS, true) && !in_array($value, $field['options'], true)) {
                if ($log) LoggingService::warning('event-data-collection', 'values.choice.rejected');
                throw new InvalidArgumentException('Choose a listed option for ' . $field['label'] . '.');
            }
            if ($key === 'text_03' && preg_match('/[\r\n]/', $value)) {
                if ($log) LoggingService::warning('event-data-collection', 'values.single_line.rejected');
                throw new InvalidArgumentException($field['label'] . ' must be one line.');
            }
            if (str_starts_with($key, 'number_') && !preg_match('/^-?\d{1,15}(?:\.\d{1,6})?$/D', $value)) throw new InvalidArgumentException($field['label'] . ' must be a number (up to six decimal places).');
            if (str_starts_with($key, 'number_')) {
                foreach (['min' => -1, 'max' => 1] as $bound => $direction) {
                    if (isset($field[$bound]) && self::compareNumbers($value, $field[$bound]) === $direction) {
                    if (class_exists(LoggingService::class)) LoggingService::warning('event-data-collection', 'limits.rejected');
                    throw new InvalidArgumentException($field['label'] . ($bound === 'min' ? ' must be at least ' : ' must be at most ') . $field[$bound] . '.');
                }
                }
            }
            $values[$key] = $value;
        }
        if ($configuration['consent'] && ($input['consent'] ?? '') !== '1') throw new InvalidArgumentException('Please agree to the use of your information.');
        if ($configuration['consent']) $values['consented_at'] = gmdate('Y-m-d H:i:s');
        if ($log) LoggingService::info('event-data-collection', 'values.complete', [], 1);
        return $values;
    }

    /** Caller locks and checks the published event in the same transaction. */
    public static function save(PDO $pdo, int $eventId, array $values): void
    {
        $key = isset($values['email']) ? 'email' : (isset($values['phone']) ? 'phone' : null);
        $id = false;
        if ($key !== null) {
            $find = $pdo->prepare("SELECT id FROM event_data_collection WHERE event_id=? AND {$key}=? ORDER BY id LIMIT 1 FOR UPDATE");
            $find->execute([$eventId, $values[$key]]);
            $id = $find->fetchColumn();
        }
        $values = array_intersect_key($values, array_flip([...array_keys(self::FIELDS), 'consented_at']));
        if ($id) {
            $set = implode(',', array_map(fn($key) => "`{$key}`=?", array_keys($values)));
            $pdo->prepare("UPDATE event_data_collection SET {$set},updated_at=UTC_TIMESTAMP() WHERE id=? AND event_id=?")->execute([...array_values($values), $id, $eventId]);
        } else {
            $columns = implode(',', array_map(fn($key) => "`{$key}`", array_keys($values)));
            $marks = implode(',', array_fill(0, count($values) + 1, '?'));
            $pdo->prepare('INSERT INTO event_data_collection (event_id' . ($columns ? ',' . $columns : '') . ") VALUES ({$marks})")->execute([$eventId, ...array_values($values)]);
        }
    }
}
