<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enforces that event branding colors must never drop text/background
 * contrast below WCAG AA (4.5:1) against white, since CampBuddy renders
 * white text on the primary/accent color for buttons and badges.
 */
class MeetsColorContrast implements ValidationRule
{
    private const MIN_CONTRAST_RATIO = 4.5;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            $fail("The {$attribute} must be a hex color like #1a2b3c.");

            return;
        }

        $ratio = $this->contrastRatio($value, '#ffffff');

        if ($ratio < self::MIN_CONTRAST_RATIO) {
            $fail(sprintf(
                'The %s (%s) has a %.1f:1 contrast ratio against white text — WCAG AA requires at least %.1f:1. Choose a darker shade.',
                $attribute,
                $value,
                $ratio,
                self::MIN_CONTRAST_RATIO
            ));
        }
    }

    private function contrastRatio(string $hexA, string $hexB): float
    {
        $lumA = $this->relativeLuminance($hexA);
        $lumB = $this->relativeLuminance($hexB);

        $lighter = max($lumA, $lumB);
        $darker = min($lumA, $lumB);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = array_map(
            fn (int $channel) => $this->linearize($channel / 255),
            sscanf($hex, '#%02x%02x%02x')
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private function linearize(float $channel): float
    {
        return $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
