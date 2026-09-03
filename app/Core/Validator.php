<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Small rule-based validator: "required|email|max:180".
 */
final class Validator
{
    private array $errors = [];
    private array $valid = [];

    public function __construct(private array $data)
    {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data);
        foreach ($rules as $field => $ruleset) {
            $validator->apply($field, $ruleset, $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)));
        }
        return $validator;
    }

    private function apply(string $field, string $ruleset, string $label): void
    {
        $raw = $this->data[$field] ?? null;
        $value = is_string($raw) ? trim($raw) : $raw;
        $rules = array_filter(explode('|', $ruleset));
        $isRequired = in_array('required', $rules, true);

        if (($value === null || $value === '' || $value === []) && !$isRequired) {
            $this->valid[$field] = $value;
            return;
        }

        foreach ($rules as $rule) {
            [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
            $failed = match ($name) {
                'required' => $value === null || $value === '' || $value === [],
                'email' => !filter_var((string) $value, FILTER_VALIDATE_EMAIL),
                'url' => !filter_var((string) $value, FILTER_VALIDATE_URL),
                'numeric' => !is_numeric($value),
                'integer' => filter_var($value, FILTER_VALIDATE_INT) === false,
                'min' => is_numeric($value) ? (float) $value < (float) $parameter : mb_strlen((string) $value) < (int) $parameter,
                'max' => is_numeric($value) ? (float) $value > (float) $parameter : mb_strlen((string) $value) > (int) $parameter,
                'in' => !in_array((string) $value, explode(',', (string) $parameter), true),
                'slug' => !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value),
                'alpha_dash' => !preg_match('/^[A-Za-z0-9_\-\.]+$/', (string) $value),
                'phone' => !preg_match('/^[\d\s\+\-\(\)\.]{6,24}$/', (string) $value),
                'confirmed' => ($this->data[$field . '_confirmation'] ?? null) !== $value,
                'accepted' => !in_array($value, ['1', 1, true, 'on', 'yes', 'true'], true),
                default => false,
            };

            if ($failed) {
                $this->errors[$field] ??= $this->message($name, $label, $parameter, $value);
                return;
            }
        }

        $this->valid[$field] = $value;
    }

    private function message(string $rule, string $label, ?string $parameter, mixed $value): string
    {
        return match ($rule) {
            'required' => "{$label} is required.",
            'email' => "Enter a valid email address.",
            'url' => "Enter a valid URL, including https://",
            'numeric', 'integer' => "{$label} must be a number.",
            'min' => is_numeric($value)
                ? "{$label} must be at least {$parameter}."
                : "{$label} must be at least {$parameter} characters.",
            'max' => is_numeric($value)
                ? "{$label} may not be greater than {$parameter}."
                : "{$label} may not exceed {$parameter} characters.",
            'in' => "{$label} is not a valid option.",
            'slug' => "{$label} must be lowercase letters, numbers and hyphens.",
            'alpha_dash' => "{$label} may only contain letters, numbers, dots, dashes and underscores.",
            'phone' => "Enter a valid phone number.",
            'confirmed' => "{$label} confirmation does not match.",
            'accepted' => "Please accept the {$label}.",
            default => "{$label} is invalid.",
        };
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->valid;
    }
}
