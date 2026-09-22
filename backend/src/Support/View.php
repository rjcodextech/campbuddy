<?php

declare(strict_types=1);

namespace CampBuddy\Support;

/**
 * Deliberately tiny — plain PHP templates, no templating engine dependency
 * for what is a single small admin area.
 */
final class View
{
    public function __construct(private readonly string $viewsPath)
    {
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function render(string $template, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $this->viewsPath . '/' . $template . '.php';
        return (string) ob_get_clean();
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
