<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\File\File;

class SafeFile extends File
{
    public function isReadable(): bool
    {
        if (parent::isReadable()) {
            return true;
        }

        $path = $this->getPathname();
        if (! is_file($path)) {
            return false;
        }

        $h = @fopen($path, 'rb');
        if ($h !== false) {
            fclose($h);

            return true;
        }

        return false;
    }
}

/**
 * Robust cross-platform filesystem safety helpers.
 *
 * Windows under restricted tokens / sandbox environments reports false
 * from is_readable() on temporary directory paths even when the file can
 * be read via fopen(). FileSafety handles this consistently so credentials,
 * storage downloads, and caches never fail due to false ACL negatives.
 */
class FileSafety
{
    /**
     * Cross-platform check if a file exists and is readable.
     */
    public static function isReadable(?string $path): bool
    {
        if (! $path || ! is_string($path)) {
            return false;
        }

        if (! file_exists($path) || ! is_file($path)) {
            return false;
        }

        if (is_readable($path)) {
            return true;
        }

        $handle = @fopen($path, 'rb');
        if ($handle !== false) {
            fclose($handle);

            return true;
        }

        return false;
    }

    /**
     * Cross-platform check if a directory or file is writable.
     */
    public static function isWritable(?string $path): bool
    {
        if (! $path || ! is_string($path)) {
            return false;
        }

        if (is_writable($path)) {
            return true;
        }

        if (is_dir($path)) {
            $testFile = rtrim($path, '/\\').DIRECTORY_SEPARATOR.'cb_probe_'.bin2hex(random_bytes(6)).'.tmp';
            $written = @file_put_contents($testFile, '1');
            if ($written !== false) {
                @unlink($testFile);

                return true;
            }

            return false;
        }

        if (file_exists($path)) {
            $handle = @fopen($path, 'a');
            if ($handle !== false) {
                fclose($handle);

                return true;
            }
        }

        return false;
    }

    /**
     * Return a File instance that works safely with Symfony's BinaryFileResponse
     * across all platforms.
     */
    public static function file(string $path): SafeFile
    {
        return new SafeFile($path);
    }
}
