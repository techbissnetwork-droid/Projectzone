<?php
declare(strict_types=1);

namespace Techbiss\Core;

/**
 * Server-side validation. Frontend validation is a convenience only — every
 * submission is re-validated here before it is allowed near the database.
 */
final class Validator
{
    private array $data;
    /** @var array<string,string> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $clean = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    private function raw(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    private function fail(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function required(string $field, string $label, int $min = 1, int $max = 255): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($value === '') {
            $this->fail($field, $label . ' is required.');
            return $this;
        }
        if (mb_strlen($value) < $min) {
            $this->fail($field, $label . ' must be at least ' . $min . ' characters.');
            return $this;
        }
        if (mb_strlen($value) > $max) {
            $this->fail($field, $label . ' must be ' . $max . ' characters or fewer.');
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function optional(string $field, int $max = 255): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($value !== '' && mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function text(string $field, int $max = 20000, bool $required = false, string $label = 'This field'): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($required && $value === '') {
            $this->fail($field, $label . ' is required.');
            return $this;
        }
        if (mb_strlen($value) > $max) {
            $this->fail($field, $label . ' must be ' . $max . ' characters or fewer.');
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function email(string $field, string $label = 'Email', bool $required = true): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($value === '') {
            if ($required) {
                $this->fail($field, $label . ' is required.');
            } else {
                $this->clean[$field] = '';
            }
            return $this;
        }
        if (mb_strlen($value) > 190 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->fail($field, 'Please enter a valid email address.');
            return $this;
        }
        $this->clean[$field] = mb_strtolower($value);
        return $this;
    }

    public function phone(string $field, bool $required = false): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($value === '') {
            if ($required) {
                $this->fail($field, 'Phone number is required.');
            } else {
                $this->clean[$field] = '';
            }
            return $this;
        }
        if (!preg_match('/^[0-9+()\-.\s]{6,32}$/', $value)) {
            $this->fail($field, 'Please enter a valid phone number.');
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function url(string $field, bool $required = false): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($value === '') {
            if ($required) {
                $this->fail($field, 'A URL is required.');
            } else {
                $this->clean[$field] = '';
            }
            return $this;
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }
        if (!filter_var($value, FILTER_VALIDATE_URL) || mb_strlen($value) > 500) {
            $this->fail($field, 'Please enter a valid URL.');
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function int(string $field, ?int $min = null, ?int $max = null, string $label = 'Value'): self
    {
        $raw = $this->raw($field);
        if ($raw === null || $raw === '') {
            $this->clean[$field] = null;
            return $this;
        }
        if (!is_numeric($raw)) {
            $this->fail($field, $label . ' must be a number.');
            return $this;
        }
        $value = (int) $raw;
        if ($min !== null && $value < $min) {
            $this->fail($field, $label . ' must be at least ' . $min . '.');
            return $this;
        }
        if ($max !== null && $value > $max) {
            $this->fail($field, $label . ' must be at most ' . $max . '.');
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function decimal(string $field, string $label = 'Amount', float $min = 0.0, float $max = 99999999.99, bool $nullable = true): self
    {
        $raw = $this->raw($field);
        if ($raw === null || trim((string) $raw) === '') {
            if ($nullable) {
                $this->clean[$field] = null;
                return $this;
            }
            $this->fail($field, $label . ' is required.');
            return $this;
        }
        $raw = str_replace([',', ' '], '', (string) $raw);
        if (!is_numeric($raw)) {
            $this->fail($field, $label . ' must be a number.');
            return $this;
        }
        $value = round((float) $raw, 2);
        if ($value < $min || $value > $max) {
            $this->fail($field, $label . ' is out of range.');
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function in(string $field, array $allowed, string $label = 'Selection', bool $required = true): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($value === '' && !$required) {
            $this->clean[$field] = '';
            return $this;
        }
        if (!in_array($value, $allowed, true)) {
            $this->fail($field, 'Please choose a valid ' . strtolower($label) . '.');
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function boolean(string $field): self
    {
        $v = $this->raw($field);
        $this->clean[$field] = in_array($v, ['1', 1, true, 'on', 'true', 'yes'], true) ? 1 : 0;
        return $this;
    }

    public function date(string $field, bool $required = false, string $label = 'Date'): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($value === '') {
            if ($required) {
                $this->fail($field, $label . ' is required.');
            } else {
                $this->clean[$field] = null;
            }
            return $this;
        }
        $normalised = str_replace('T', ' ', $value);
        $ts = strtotime($normalised);
        if ($ts === false) {
            $this->fail($field, 'Please enter a valid date.');
            return $this;
        }
        $this->clean[$field] = date(strlen($normalised) > 10 ? 'Y-m-d H:i:s' : 'Y-m-d', $ts);
        return $this;
    }

    public function slug(string $field, string $fallbackField = 'title'): self
    {
        $value = trim((string) ($this->raw($field) ?? ''));
        if ($value === '') {
            $value = trim((string) ($this->raw($fallbackField) ?? ''));
        }
        $slug = Str::slug($value);
        if (mb_strlen($slug) > 190) {
            $slug = mb_substr($slug, 0, 190);
        }
        $this->clean[$field] = $slug;
        return $this;
    }

    /** Accept an array of scalars, keep only the allowed ones. */
    public function multi(string $field, ?array $allowed = null, int $maxItems = 40): self
    {
        $raw = $this->raw($field);
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $v = trim((string) $item);
                if ($v === '') {
                    continue;
                }
                if ($allowed !== null && !in_array($v, $allowed, true)) {
                    continue;
                }
                $out[] = $v;
                if (count($out) >= $maxItems) {
                    break;
                }
            }
        }
        $this->clean[$field] = array_values(array_unique($out));
        return $this;
    }

    /** Rich text from the admin editor, sanitised against a tag whitelist. */
    public function html(string $field, bool $required = false, string $label = 'Content'): self
    {
        $value = (string) ($this->raw($field) ?? '');
        if (trim(strip_tags($value)) === '' && $required) {
            $this->fail($field, $label . ' is required.');
            return $this;
        }
        $this->clean[$field] = Str::sanitizeHtml($value);
        return $this;
    }

    /** Honeypot: bots fill hidden fields, humans do not. */
    public function honeypot(string $field = 'website_url'): self
    {
        if (trim((string) ($this->raw($field) ?? '')) !== '') {
            $this->fail('__spam', 'Your submission could not be processed.');
        }
        return $this;
    }

    public function matches(string $field, string $otherField, string $label = 'Passwords'): self
    {
        if ((string) $this->raw($field) !== (string) $this->raw($otherField)) {
            $this->fail($field, $label . ' do not match.');
        }
        return $this;
    }

    public function password(string $field, string $label = 'Password', bool $required = true, int $min = 10): self
    {
        $value = (string) ($this->raw($field) ?? '');
        if ($value === '') {
            if ($required) {
                $this->fail($field, $label . ' is required.');
            }
            return $this;
        }
        if (strlen($value) < $min) {
            $this->fail($field, $label . ' must be at least ' . $min . ' characters.');
            return $this;
        }
        if (strlen($value) > 200) {
            $this->fail($field, $label . ' is too long.');
            return $this;
        }
        if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/[0-9]/', $value)) {
            $this->fail($field, $label . ' must contain both letters and numbers.');
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function addError(string $field, string $message): self
    {
        $this->fail($field, $message);
        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return $this->errors === [] ? '' : (string) reset($this->errors);
    }

    /** @return array<string,mixed> */
    public function valid(): array
    {
        return $this->clean;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->clean[$field] ?? $default;
    }

    /** @return array<string,mixed> only the listed keys, in order */
    public function only(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $this->clean)) {
                $out[$f] = $this->clean[$f];
            }
        }
        return $out;
    }
}
