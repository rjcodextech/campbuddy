<?php

/*
 * The CampBuddy × WPSimplified lockup with the brand guideline's clear space (CAMPBUDDY-BRAND-GUIDELINES.md §2:
 * ≈ 28% of the logo height on every side). The source files are cropped to the artwork, so a partner's site
 * put it edge to edge with other logos. This writes, next to them in public/media:
 *
 *   campbuddy-wpsimplified-logo-clear.png         web (1600px artwork), transparent
 *   campbuddy-wpsimplified-logo-clear-900dpi.png  print, from the 900 DPI master, 900 DPI tagged
 *   campbuddy-wpsimplified-logo-clear.svg         the same SVG with the clear space in its viewBox
 *
 *   php tools/logo-clear-space.php
 */

const CLEAR = 0.28;
$media = __DIR__.'/../public/media';

function padded(GdImage $src, int $artWidth, string $out, ?int $dpi = null): void
{
    $artHeight = (int) round(imagesy($src) * $artWidth / imagesx($src));
    $pad = (int) round(CLEAR * $artHeight);
    $canvas = imagecreatetruecolor($artWidth + 2 * $pad, $artHeight + 2 * $pad);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $src, $pad, $pad, 0, 0, $artWidth, $artHeight, imagesx($src), imagesy($src));
    imagealphablending($canvas, false);
    if ($dpi !== null) {
        imageresolution($canvas, $dpi, $dpi);
    }
    imagepng($canvas, $out, 9);
    echo basename($out).': '.imagesx($canvas).'×'.imagesy($canvas).", clear space {$pad}px\n";
}

$master = imagecreatefrompng("{$media}/campbuddy-wpsimplified-logo-900dpi.png");
imagesavealpha($master, true);
padded($master, 1600, "{$media}/campbuddy-wpsimplified-logo-clear.png");
padded($master, imagesx($master), "{$media}/campbuddy-wpsimplified-logo-clear-900dpi.png", 900);

$svg = file_get_contents("{$media}/campbuddy-wpsimplified-logo.svg");
if (! preg_match('/viewBox="0 0 (\d+) (\d+)" width="\d+" height="\d+"/', $svg, $m)) {
    fwrite(STDERR, "The SVG's viewBox is not the expected 0 0 W H.\n");
    exit(1);
}
[$w, $h] = [(int) $m[1], (int) $m[2]];
$pad = (int) round(CLEAR * $h);
$svg = str_replace($m[0], sprintf('viewBox="%d %d %d %d" width="%d" height="%d"', -$pad, -$pad, $w + 2 * $pad, $h + 2 * $pad, $w + 2 * $pad, $h + 2 * $pad), $svg);
file_put_contents("{$media}/campbuddy-wpsimplified-logo-clear.svg", $svg);
echo "campbuddy-wpsimplified-logo-clear.svg: viewBox with {$pad} units of clear space\n";
