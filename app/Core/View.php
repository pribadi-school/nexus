<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Plain PHP templates. Escape every value with e().
 *
 *   'dashboard/index'      -> views/dashboard/index.php
 *   'Dashboard::index'     -> app/Modules/Dashboard/views/index.php
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app', ?string $title = null): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, ['content' => $content, 'title' => $title ?? ''] + $data);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        $file = self::resolve($template);

        // Isolated scope: the template only sees $data's keys.
        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };

        ob_start();
        try {
            $render($file, $data);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    private static function resolve(string $template): string
    {
        if (str_contains($template, '::')) {
            [$module, $name] = explode('::', $template, 2);
            $file = BASE_PATH . "/app/Modules/$module/views/$name.php";
        } else {
            $file = BASE_PATH . "/views/$template.php";
        }
        if (!is_file($file)) {
            throw new RuntimeException("View not found: $template");
        }
        return $file;
    }
}
