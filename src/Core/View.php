<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rendert PHP-Templates aus templates/ – optional eingebettet in ein Layout.
 */
final class View
{
    /** @param array<string, mixed> $vars */
    public static function render(string $template, array $vars = [], ?string $layout = 'layout'): void
    {
        $content = self::capture($template, $vars);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture($layout, $vars + ['content' => $content]);
    }

    /** @param array<string, mixed> $vars */
    public static function capture(string $template, array $vars = []): string
    {
        $file = APP_ROOT . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template nicht gefunden: ' . $template);
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
