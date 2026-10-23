<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The apps reach the server by its domain (campbuddy.club), never by an IP address, so moving to another
 * host only means pointing the domain (Cloudflare DNS) at it: no app update needed. Development addresses
 * (10.0.2.2, 127.0.0.1) may appear only in the debug build's network config and in comments.
 */
class AppUsesDomainTest extends TestCase
{
    public function test_release_builds_talk_to_the_domain(): void
    {
        $gradle = file_get_contents(base_path('native-app/androidApp/build.gradle.kts'));
        $this->assertSame(2, substr_count($gradle, 'buildConfigField("String", "API_BASE", "\"https://campbuddy.club/api/v1\"")'), 'debug and release API_BASE');

        $ios = file_get_contents(base_path('native-app/shared/src/iosMain/kotlin/club/campbuddy/shared/PlatformIos.kt'));
        $this->assertStringContainsString('?: "https://campbuddy.club/api/v1"', $ios);
    }

    public function test_no_ip_address_in_app_code(): void
    {
        $dirs = ['native-app/androidApp/src/main', 'native-app/androidApp/src/direct', 'native-app/androidApp/src/play', 'native-app/shared/src'];
        $found = [];
        foreach ($dirs as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! in_array($file->getExtension(), ['kt', 'kts', 'xml', 'json', 'swift', 'plist'], true)) {
                    continue;
                }
                foreach (file($file->getPathname()) as $n => $line) {
                    $code = trim($line);
                    if (str_starts_with($code, '*') || str_starts_with($code, '//') || str_starts_with($code, '/*') || str_starts_with($code, '<!--')) {
                        continue;
                    }
                    if (preg_match('/(?<![\d.])(?:\d{1,3}\.){3}\d{1,3}(?![\d.])/', $code)) {
                        $found[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.($n + 1);
                    }
                }
            }
        }

        $this->assertSame([], $found, 'An IP address in app code (use the domain): '.implode(', ', $found));
    }
}
