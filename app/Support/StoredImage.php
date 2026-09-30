<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class StoredImage
{
    public const PHOTO = 1600;

    public const AVATAR = 800;

    public const LOGO = 512;

    /**
     * Resizes the uploaded JPEG or PNG in place so the longest side fits $maxSide.
     * Without GD the file is kept as sent.
     */
    public static function shrink(UploadedFile $file, int $maxSide = self::PHOTO): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }

        $path = $file->getRealPath();
        $info = $path === false ? false : @getimagesize($path);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return;
        }

        $jpeg = $info[2] === IMAGETYPE_JPEG;
        $turn = $jpeg ? self::rotation($path) : 0;

        if (max($info[0], $info[1]) <= $maxSide && $turn === 0) {
            return;
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            return;
        }

        if ($turn !== 0) {
            $rotated = imagerotate($source, $turn, 0);
            if ($rotated === false) {
                return;
            }
            $source = $rotated;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $resized = $scale < 1
            ? imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)))
            : $source;

        if ($resized === false) {
            return;
        }

        if ($jpeg) {
            imagejpeg($resized, $path, 82);
        } else {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagepng($resized, $path, 6);
        }

        clearstatcache(true, $path);
    }

    private static function rotation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 0;
        }

        $exif = @exif_read_data($path);

        return match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
    }
}
