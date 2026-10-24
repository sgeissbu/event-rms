<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Kleine Helfer für Formulare in Templates (Fehlerklassen, Meldungen, alte Werte).
 * Verwendung:
 *   $f = new Form($errors, $old);
 *   <input class="form-control<?= $f->invalid('name') ?>" name="name" value="<?= $f->value('name') ?>">
 *   <?= $f->feedback('name') ?>
 */
final class Form
{
    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    public function __construct(private array $errors = [], private array $old = [])
    {
    }

    public function invalid(string $key): string
    {
        return isset($this->errors[$key]) ? ' is-invalid' : '';
    }

    public function feedback(string $key): string
    {
        return isset($this->errors[$key]) ? '<div class="invalid-feedback">' . e($this->errors[$key]) . '</div>' : '';
    }

    /** Escaped für Attribute/Text. */
    public function value(string $key, string $default = ''): string
    {
        $value = $this->old[$key] ?? $default;
        return e(is_scalar($value) ? (string) $value : $default);
    }

    public function selected(string $key, string $option): string
    {
        return (string) ($this->old[$key] ?? '') === $option ? ' selected' : '';
    }

    public function checked(string $key): string
    {
        return !empty($this->old[$key]) ? ' checked' : '';
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function error(string $key): ?string
    {
        return $this->errors[$key] ?? null;
    }
}
