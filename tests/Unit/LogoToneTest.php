<?php

namespace Tests\Unit;

use App\Support\LogoTone;
use PHPUnit\Framework\TestCase;

/** A white logo on see-through gets a dark box on the attendee pages; nothing else does. */
class LogoToneTest extends TestCase
{
    private function png(array $fill, bool $clearAround): string
    {
        $img = imagecreatetruecolor(200, 100);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, $clearAround ? imagecolorallocatealpha($img, 0, 0, 0, 127) : imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 40, 30, 160, 70, imagecolorallocate($img, ...$fill));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    public function test_only_a_light_logo_on_see_through_counts_as_light(): void
    {
        $this->assertTrue(LogoTone::measure($this->png([255, 255, 255], true)), 'white wordmark, transparent around it');
        $this->assertFalse(LogoTone::measure($this->png([140, 20, 30], true)), 'a coloured logo');
        $this->assertFalse(LogoTone::measure($this->png([250, 250, 250], false)), 'a logo on its own white square');
        $this->assertFalse(LogoTone::measure('<svg/>'), 'what GD cannot read');
    }
}
