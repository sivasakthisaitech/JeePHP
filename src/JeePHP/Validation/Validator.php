<?php
declare(strict_types=1);

namespace JeePHP\Validation;

use InvalidArgumentException;
use JeePHP\Database\Database;

final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            if (!is_string($field) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
                throw new InvalidArgumentException('Validation fields must be simple names.');
            }
            $value = $data[$field] ?? null;
            $parts = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);
            foreach ($parts as $rule) {
                if (!is_string($rule) || $rule === '') {
                    continue;
                }
                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name !== 'required' && $name !== 'nullable' && ($value === null || $value === '')) {
                    if ($name === 'nullable') {
                        break;
                    }
                    continue;
                }

                $invalid = match ($name) {
                    'required' => $value === null || $value === '',
                    'nullable' => false,
                    'email' => !is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false,
                    'url' => !is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false,
                    'numeric' => !is_numeric($value),
                    'integer' => filter_var($value, FILTER_VALIDATE_INT) === false,
                    'boolean' => !in_array($value, [true, false, 0, 1, '0', '1'], true),
                    'phone' => !is_scalar($value) || preg_match('/^[0-9+(). -]{7,25}$/', (string) $value) !== 1,
                    'date' => !is_string($value) || strtotime($value) === false,
                    'min' => self::measureInvalid($value, $argument, true),
                    'max' => self::measureInvalid($value, $argument, false),
                    'confirmed' => !isset($data[$field . '_confirmation']) || $value !== $data[$field . '_confirmation'],
                    'same' => !is_string($argument) || !array_key_exists($argument, $data) || $value !== $data[$argument],
                    'in' => !is_string($argument) || !in_array((string) $value, explode(',', $argument), true),
                    'regex' => self::regexInvalid($value, $argument),
                    'unique' => self::databaseRuleInvalid($value, $argument, true),
                    'exists' => self::databaseRuleInvalid($value, $argument, false),
                    default => throw new InvalidArgumentException('Unknown validation rule: ' . $name),
                };
                if ($invalid) {
                    $errors[$field][] = self::message($field, $name, $argument);
                }
            }
        }
        return $errors;
    }

    private static function measureInvalid(mixed $value, ?string $limit, bool $minimum): bool
    {
        if ($limit === null || !is_numeric($limit)) {
            throw new InvalidArgumentException('min and max rules need a numeric limit.');
        }
        if (is_numeric($value)) {
            return $minimum ? (float) $value < (float) $limit : (float) $value > (float) $limit;
        }
        $length = mb_strlen((string) $value);
        return $minimum ? $length < (int) $limit : $length > (int) $limit;
    }

    private static function regexInvalid(mixed $value, ?string $pattern): bool
    {
        if ($pattern === null || strlen($pattern) > 200) {
            throw new InvalidArgumentException('regex rule needs a pattern shorter than 200 characters.');
        }
        $expression = '~' . str_replace('~', '\\~', $pattern) . '~u';
        $result = @preg_match($expression, (string) $value);
        if ($result === false) {
            throw new InvalidArgumentException('Invalid regex rule pattern.');
        }
        return $result !== 1;
    }

    private static function databaseRuleInvalid(mixed $value, ?string $arguments, bool $unique): bool
    {
        $parts = explode(',', (string) $arguments);
        $table = $parts[0] ?? '';
        $column = $parts[1] ?? '';
        $ignoreId = $parts[2] ?? null;
        foreach ([$table, $column] as $identifier) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
                throw new InvalidArgumentException('unique and exists need safe table,column names.');
            }
        }
        $sql = 'SELECT 1 FROM `' . $table . '` WHERE `' . $column . '` = :value';
        $parameters = ['value' => (string) $value];
        if ($unique && $ignoreId !== null && $ignoreId !== '') {
            if (filter_var($ignoreId, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('The optional unique ignore ID must be an integer.');
            }
            $sql .= ' AND `id` <> :ignore_id';
            $parameters['ignore_id'] = (int) $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        $found = $statement->fetchColumn() !== false;
        return $unique ? $found : !$found;
    }

    private static function message(string $field, string $rule, ?string $argument): string
    {
        $label = str_replace('_', ' ', $field);
        return match ($rule) {
            'required' => 'The ' . $label . ' field is required.',
            'email' => 'Enter a valid email address for ' . $label . '.',
            'url' => 'Enter a valid URL for ' . $label . '.',
            'numeric' => 'The ' . $label . ' field must be a number.',
            'integer' => 'The ' . $label . ' field must be a whole number.',
            'boolean' => 'The ' . $label . ' field must be true or false.',
            'phone' => 'Enter a valid phone number for ' . $label . '.',
            'date' => 'Enter a valid date for ' . $label . '.',
            'min' => 'The ' . $label . ' field must be at least ' . $argument . '.',
            'max' => 'The ' . $label . ' field must not exceed ' . $argument . '.',
            'confirmed' => 'The ' . $label . ' confirmation does not match.',
            'same' => 'The ' . $label . ' field must match ' . str_replace('_', ' ', (string) $argument) . '.',
            'in' => 'Choose an allowed value for ' . $label . '.',
            'regex' => 'The ' . $label . ' format is invalid.',
            'unique' => 'That ' . $label . ' is already in use.',
            'exists' => 'The selected ' . $label . ' does not exist.',
            default => 'The ' . $label . ' field is invalid.',
        };
    }
}
