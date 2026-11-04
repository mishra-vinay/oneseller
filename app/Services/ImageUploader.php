<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded images on the public disk, downscaled so the longest side
 * is at most MAX_SIDE pixels. Uses GD, which shared hosts ship with PHP.
 */
class ImageUploader
{
    private const MAX_SIDE = 1600;

    public function store(UploadedFile $file, string $directory): string
    {
        $path = trim($directory, '/').'/'.Str::random(32).'.jpg';
        $resized = $this->downscale($file->getRealPath());

        if ($resized === null) {
            // GD could not read it (e.g. no WebP support); keep the validated original.
            return $file->store($directory, 'public');
        }

        Storage::disk('public')->put($path, $resized);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function downscale(string $source): ?string
    {
        $info = @getimagesize($source);

        if (! $info) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };

        if (! $image) {
            return null;
        }

        [$width, $height] = [$info[0], $info[1]];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // White background so transparent PNGs do not turn black as JPEG.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);

        return ob_get_clean() ?: null;
    }
}
