<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Checks form input and collects friendly, per-field messages.
 *
 *   $v = new Validator($request->body, ['room_id' => 'Room', 'model' => 'Model']);
 *   $data = $v->validate([
 *       'room_id' => ['required', 'int', 'exists:rooms'],
 *       'model'   => ['max:100'],
 *   ]);
 *   if ($v->fails()) { ... $v->errors() ... }
 *
 * Values are trimmed; empty optional values become null.
 */
final class Validator
{
    /** @var array<string, string> field => first error */
    private array $errors = [];

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $labels field => label used in messages
     */
    public function __construct(private array $input, private array $labels = [])
    {
    }

    /**
     * @param array<string, list<string>> $rules
     * @return array<string, mixed> cleaned values for every field in $rules
     */
    public function validate(array $rules): array
    {
        $data = [];
        foreach ($rules as $field => $fieldRules) {
            $raw = $this->input[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : null;
            if ($value === '') {
                $value = null;
            }

            if ($value === null) {
                if (in_array('required', $fieldRules, true)) {
                    $this->errors[$field] = $this->requiredMessage($field, $fieldRules);
                }
                $data[$field] = null;
                continue;
            }

            foreach ($fieldRules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($name, $arg, $value, $field);
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
            $data[$field] = in_array('int', $fieldRules, true) && ctype_digit($value) ? (int) $value : $value;
        }
        return $data;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Add an error found outside the rules (e.g. a duplicate serial number). */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    private function check(string $rule, ?string $arg, string $value, string $field): ?string
    {
        $label = $this->label($field);
        return match ($rule) {
            'required' => null,
            'max' => mb_strlen($value) > (int) $arg ? "$label is too long ($arg characters at most)." : null,
            'int' => ctype_digit($value) ? null : "$label must be a whole number.",
            'min_value' => ctype_digit($value) && (int) $value >= (int) $arg ? null : "$label must be at least $arg.",
            'max_value' => ctype_digit($value) && (int) $value <= (int) $arg ? null : "$label must be $arg or less.",
            'in' => in_array($value, explode(',', (string) $arg), true) ? null : 'Choose a valid ' . $this->lower($label) . '.',
            'ip' => filter_var($value, FILTER_VALIDATE_IP) !== false ? null : "$label must be an IP address such as 192.168.1.20.",
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : "$label must be an email address such as name@pribadidepok.sch.id.",
            'exists' => $this->exists((string) $arg, $value) ? null : 'That ' . $this->lower($label) . ' no longer exists. Choose another one.',
            default => null,
        };
    }

    /** @param list<string> $rules */
    private function requiredMessage(string $field, array $rules): string
    {
        $label = $this->label($field);
        // Pick-from-a-list fields read better as "Choose a room."
        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'exists:') || str_starts_with($rule, 'in:')) {
                return 'Choose a ' . $this->lower($label) . '.';
            }
        }
        return ucfirst($label) . ' is required.';
    }

    /** "Room" -> "room" for mid-sentence use, but acronyms stay ("SD card size", "IP address"). */
    private function lower(string $label): string
    {
        return preg_match('/^[A-Z]{2}/', $label) ? $label : lcfirst($label);
    }

    private function exists(string $table, string $value): bool
    {
        if (!preg_match('/^\w+$/', $table) || !ctype_digit($value)) {
            return false;
        }
        return db()->value("SELECT 1 FROM `$table` WHERE id = ?", [(int) $value]) !== null;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? str_replace('_', ' ', $field);
    }
}
