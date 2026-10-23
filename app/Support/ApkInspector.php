<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Reads what an Android app file (.apk) says about itself, with no Android
 * tools on the server: the package name, versionCode and versionName from
 * its binary AndroidManifest.xml, and the SHA-256 of the certificate it is
 * signed with (APK Signature Scheme v3 or v2) — the value
 * `apksigner verify --print-certs` prints. Pure PHP (zlib only), so it works
 * on shared hosting without the zip extension.
 *
 * Anything that is not a well-formed, v2/v3-signed APK is refused with a
 * reason a person can act on.
 */
final class ApkInspector
{
    private const ATTR_VERSION_CODE = 0x0101021B;

    private const ATTR_VERSION_NAME = 0x0101021C;

    private const ATTR_MIN_SDK = 0x0101020C;

    private const SCHEME_V2 = 0x7109871A;

    private const SCHEME_V3 = 0xF05368C0;

    /**
     * @return array{package: string, version_code: int, version_name: string, min_sdk: ?int, cert_sha256: string}
     */
    public static function inspect(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('The file could not be read.');
        }

        try {
            $size = (int) fstat($handle)['size'];
            [$cdOffset, $cdSize] = self::endOfCentralDirectory($handle, $size);
            $manifest = self::entry($handle, $cdOffset, $cdSize, 'AndroidManifest.xml');
            $cert = self::signingCertificate($handle, $cdOffset);
        } finally {
            fclose($handle);
        }

        $info = self::manifest($manifest);
        if ($cert === null) {
            throw new InvalidArgumentException('This APK is not signed with APK Signature Scheme v2 or v3. Upload the release APK from build-native.sh.');
        }

        return $info + ['cert_sha256' => $cert];
    }

    /** @return array{0: int, 1: int} the central directory's offset and size */
    private static function endOfCentralDirectory($handle, int $size): array
    {
        if ($size < 22) {
            throw new InvalidArgumentException('This is not an APK (the file is too small).');
        }
        $tail = min($size, 65557);
        fseek($handle, $size - $tail);
        $data = (string) fread($handle, $tail);
        $at = strrpos($data, "PK\x05\x06");
        if ($at === false || strlen($data) - $at < 22) {
            throw new InvalidArgumentException('This is not an APK (no zip directory found).');
        }
        $eocd = unpack('Vsig/vdisk/vcddisk/ventries/vtotal/Vsize/Voffset', substr($data, $at, 20));
        if ($eocd['offset'] + $eocd['size'] > $size) {
            throw new InvalidArgumentException('This APK is damaged (the zip directory points outside the file).');
        }

        return [$eocd['offset'], $eocd['size']];
    }

    private static function entry($handle, int $cdOffset, int $cdSize, string $wanted): string
    {
        fseek($handle, $cdOffset);
        $cd = (string) fread($handle, $cdSize);
        $pos = 0;
        while ($pos + 46 <= strlen($cd) && substr($cd, $pos, 4) === "PK\x01\x02") {
            $h = unpack('vmethod/Vtime/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vint/Vext/Vlocal', substr($cd, $pos + 10, 36));
            $name = substr($cd, $pos + 46, $h['nlen']);
            if ($name === $wanted) {
                fseek($handle, $h['local']);
                $local = (string) fread($handle, 30);
                if (substr($local, 0, 4) !== "PK\x03\x04") {
                    throw new InvalidArgumentException('This APK is damaged ('.$wanted.' cannot be found).');
                }
                $l = unpack('vnlen/velen', substr($local, 26, 4));
                fseek($handle, $h['local'] + 30 + $l['nlen'] + $l['elen']);
                $raw = $h['csize'] > 0 ? (string) fread($handle, $h['csize']) : '';
                $body = match ($h['method']) {
                    0 => $raw,
                    8 => @gzinflate($raw),
                    default => false,
                };
                if ($body === false || strlen($body) !== $h['usize']) {
                    throw new InvalidArgumentException('This APK is damaged ('.$wanted.' cannot be unpacked).');
                }

                return $body;
            }
            $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
        }

        throw new InvalidArgumentException('This is not an Android app (no '.$wanted.').');
    }

    /** SHA-256 of the first signer's certificate in the APK Signing Block (v3 first, then v2), or null. */
    private static function signingCertificate($handle, int $cdOffset): ?string
    {
        if ($cdOffset < 32) {
            return null;
        }
        fseek($handle, $cdOffset - 24);
        $footer = (string) fread($handle, 24);
        if (substr($footer, 8) !== 'APK Sig Block 42') {
            return null;
        }
        $blockSize = self::uint64(substr($footer, 0, 8));
        if ($blockSize < 24 || $blockSize > $cdOffset - 8) {
            return null;
        }
        fseek($handle, $cdOffset - $blockSize - 8);
        $block = (string) fread($handle, $blockSize - 16);
        $pairs = [];
        $pos = 8;
        while ($pos + 12 <= strlen($block)) {
            $length = self::uint64(substr($block, $pos, 8));
            if ($length < 4 || $pos + 8 + $length > strlen($block)) {
                break;
            }
            $id = unpack('V', substr($block, $pos + 8, 4))[1];
            $pairs[$id] = substr($block, $pos + 12, $length - 4);
            $pos += 8 + $length;
        }

        foreach ([self::SCHEME_V3, self::SCHEME_V2] as $scheme) {
            if (isset($pairs[$scheme]) && ($cert = self::firstCertificate($pairs[$scheme])) !== null) {
                return hash('sha256', $cert);
            }
        }

        return null;
    }

    /** signers → first signer → signed data → digests, certificates → first certificate (DER). */
    private static function firstCertificate(string $value): ?string
    {
        $signers = self::prefixed($value, 0);
        $signer = $signers === null ? null : self::prefixed($signers, 0);
        $signedData = $signer === null ? null : self::prefixed($signer, 0);
        if ($signedData === null) {
            return null;
        }
        $digests = self::prefixed($signedData, 0);
        $certificates = $digests === null ? null : self::prefixed($signedData, 4 + strlen($digests));

        return $certificates === null ? null : self::prefixed($certificates, 0);
    }

    /** A uint32-length-prefixed slice starting at $at, or null when it does not fit. */
    private static function prefixed(string $data, int $at): ?string
    {
        if ($at + 4 > strlen($data)) {
            return null;
        }
        $length = unpack('V', substr($data, $at, 4))[1];

        return $at + 4 + $length <= strlen($data) ? substr($data, $at + 4, $length) : null;
    }

    private static function uint64(string $bytes): int
    {
        $parts = unpack('Vlow/Vhigh', $bytes);

        return $parts['high'] === 0 ? $parts['low'] : PHP_INT_MAX;
    }

    /**
     * The <manifest> and <uses-sdk> attributes from Android's binary XML.
     *
     * @return array{package: string, version_code: int, version_name: string, min_sdk: ?int}
     */
    private static function manifest(string $xml): array
    {
        if (strlen($xml) < 8 || unpack('v', substr($xml, 0, 2))[1] !== 0x0003) {
            throw new InvalidArgumentException('This APK is damaged (its manifest is not Android binary XML).');
        }
        $strings = [];
        $resourceIds = [];
        $found = [];
        $pos = unpack('v', substr($xml, 2, 2))[1];

        while ($pos + 8 <= strlen($xml)) {
            $chunk = unpack('vtype/vheader/Vsize', substr($xml, $pos, 8));
            if ($chunk['size'] < 8 || $pos + $chunk['size'] > strlen($xml)) {
                break;
            }
            $body = substr($xml, $pos, $chunk['size']);

            if ($chunk['type'] === 0x0001) {
                $strings = self::stringPool($body);
            } elseif ($chunk['type'] === 0x0180) {
                $resourceIds = array_values(unpack('V*', substr($body, 8)) ?: []);
            } elseif ($chunk['type'] === 0x0102 && strlen($body) >= 36) {
                $element = unpack('Vns/Vname/vstart/vsize/vcount', substr($body, 16, 14));
                $tag = $strings[$element['name']] ?? '';
                if ($tag === 'manifest' || $tag === 'uses-sdk') {
                    for ($i = 0; $i < $element['count']; $i++) {
                        $at = 16 + $element['start'] + $i * $element['size'];
                        if ($at + 20 > strlen($body)) {
                            break;
                        }
                        $a = unpack('Vns/Vname/Vraw/vtsize/Cres0/Ctype/Vdata', substr($body, $at, 20));
                        $name = $strings[$a['name']] ?? '';
                        $resId = $resourceIds[$a['name']] ?? 0;
                        $text = $a['raw'] !== 0xFFFFFFFF ? ($strings[$a['raw']] ?? null) : ($a['type'] === 0x03 ? ($strings[$a['data']] ?? null) : null);
                        $number = $a['type'] >= 0x10 && $a['type'] <= 0x1F ? $a['data'] : null;

                        if ($tag === 'manifest' && $name === 'package') {
                            $found['package'] = $text;
                        } elseif ($tag === 'manifest' && ($resId === self::ATTR_VERSION_CODE || $name === 'versionCode')) {
                            $found['version_code'] = $number ?? (is_numeric($text) ? (int) $text : null);
                        } elseif ($tag === 'manifest' && ($resId === self::ATTR_VERSION_NAME || $name === 'versionName')) {
                            $found['version_name'] = $text;
                        } elseif ($tag === 'uses-sdk' && ($resId === self::ATTR_MIN_SDK || $name === 'minSdkVersion')) {
                            $found['min_sdk'] = $number;
                        }
                    }
                }
            }
            $pos += $chunk['size'];
        }

        if (empty($found['package']) || empty($found['version_code']) || ! isset($found['version_name']) || $found['version_name'] === '') {
            throw new InvalidArgumentException('This APK has no package name or version in its manifest.');
        }

        return [
            'package' => (string) $found['package'],
            'version_code' => (int) $found['version_code'],
            'version_name' => (string) $found['version_name'],
            'min_sdk' => isset($found['min_sdk']) ? (int) $found['min_sdk'] : null,
        ];
    }

    /** @return list<string> */
    private static function stringPool(string $chunk): array
    {
        $h = unpack('vtype/vheader/Vsize/Vcount/Vstyles/Vflags/Vstart', substr($chunk, 0, 24));
        $utf8 = ($h['flags'] & 0x100) !== 0;
        $offsets = $h['count'] > 0 ? array_values(unpack('V'.$h['count'], substr($chunk, $h['header'], 4 * $h['count']))) : [];
        $strings = [];

        foreach ($offsets as $offset) {
            $at = $h['start'] + $offset;
            if ($utf8) {
                $at += (ord($chunk[$at] ?? "\0") & 0x80) ? 2 : 1; // length in UTF-16 units: skipped
                $length = ord($chunk[$at] ?? "\0");
                if ($length & 0x80) {
                    $length = (($length & 0x7F) << 8) | ord($chunk[$at + 1] ?? "\0");
                    $at += 2;
                } else {
                    $at += 1;
                }
                $strings[] = substr($chunk, $at, $length);
            } else {
                $length = unpack('v', substr($chunk, $at, 2))[1];
                $at += 2;
                if ($length & 0x8000) {
                    $length = (($length & 0x7FFF) << 16) | unpack('v', substr($chunk, $at, 2))[1];
                    $at += 2;
                }
                $strings[] = (string) mb_convert_encoding(substr($chunk, $at, $length * 2), 'UTF-8', 'UTF-16LE');
            }
        }

        return $strings;
    }
}
