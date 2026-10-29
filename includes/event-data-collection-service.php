<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;

/** Public form contract; keep the website's includes/event-data-collection-service.php in sync. */
final class EventDataCollectionService
{
    public const DEFAULT_SUCCESS_MESSAGE = 'Thank you for submitting your information';
    public const FIELDS = ['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'number_01' => 'Number 01', 'number_02' => 'Number 02', 'text_01' => 'Text 01', 'text_02' => 'Text 02'];

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
        $query = $pdo->prepare('SELECT COUNT(*) AS count, COALESCE(SUM(number_01),0) AS number_01, COALESCE(SUM(number_02),0) AS number_02 FROM event_data_collection WHERE event_id=?');
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
        if (is_string($input)) $input = json_decode($input, true);
        if (!is_array($input)) $input = [];
        $result = ['enabled' => !empty($input['enabled']), 'consent' => !empty($input['consent']), 'submit_label' => self::text($input['submit_label'] ?? 'Submit', 100) ?: 'Submit', 'subtext' => self::text($input['subtext'] ?? '', 2000), 'fields' => []];
        $result['success_message'] = self::text($input['success_message'] ?? '', 2000) ?: self::DEFAULT_SUCCESS_MESSAGE;
        foreach (self::FIELDS as $key => $label) {
            $field = is_array($input['fields'][$key] ?? null) ? $input['fields'][$key] : [];
            $result['fields'][$key] = ['enabled' => !empty($field['enabled']), 'required' => !empty($field['enabled']) && !empty($field['required']), 'label' => self::text($field['label'] ?? $label, 150) ?: $label];
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
        if ($result['enabled'] && !array_filter($result['fields'], fn($field) => $field['enabled'])) throw new InvalidArgumentException('Select at least one field to collect.');
        return $result;
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
        $values = [];
        foreach ($configuration['fields'] as $key => $field) {
            if (!$field['enabled']) continue;
            $value = self::text($input[$key] ?? '', str_starts_with($key, 'text_') ? 2000 : 190);
            if ($value === '') {
                if ($field['required']) throw new InvalidArgumentException($field['label'] . ' is required.');
                continue; // Blank optional fields never erase existing values.
            }
            if ($key === 'email') {
                $value = strtolower($value);
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
            }
            if ($key === 'phone') $value = self::phone($value, self::text($input['phone_country'] ?? 'LB', 2), $countries);
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
