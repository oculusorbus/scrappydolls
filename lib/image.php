<?php
declare(strict_types=1);

/**
 * Image resize / re-encode for product uploads.
 *
 * Two backends:
 *   - Imagick (preferred) — better quality, native EXIF auto-orient
 *   - GD       (fallback)  — universally available
 *
 * Always writes JPEG output (universal compatibility, smallest reliable
 * size for photos). Strips EXIF metadata after baking in orientation.
 */

const IMAGE_DISPLAY_LONG_EDGE = 1600;
const IMAGE_DISPLAY_QUALITY   = 88;
const IMAGE_THUMB_LONG_EDGE   = 400;
const IMAGE_THUMB_QUALITY     = 82;

/**
 * Resize $src into $dst (always JPEG). Preserves aspect ratio, downscales
 * only — never upscales. Auto-orients based on EXIF before resizing.
 *
 * Returns true on success.
 */
function image_resize(string $src, string $dst, int $maxLongEdge, int $quality = 85): bool {
    if (!is_file($src)) return false;
    if (extension_loaded('imagick')) {
        if (_image_resize_imagick($src, $dst, $maxLongEdge, $quality)) return true;
        // fall through to GD if Imagick failed unexpectedly
    }
    if (extension_loaded('gd')) {
        return _image_resize_gd($src, $dst, $maxLongEdge, $quality);
    }
    error_log('image_resize: neither Imagick nor GD is available — copying original');
    return @copy($src, $dst);
}

function _image_resize_imagick(string $src, string $dst, int $max, int $quality): bool {
    try {
        $im = new Imagick($src);
        if (method_exists($im, 'autoOrient')) {
            $im->autoOrient();
        } else {
            // Fallback for older Imagick: emulate autoOrient
            switch ($im->getImageOrientation()) {
                case Imagick::ORIENTATION_BOTTOMRIGHT: $im->rotateImage('#000', 180); break;
                case Imagick::ORIENTATION_RIGHTTOP:    $im->rotateImage('#000',  90); break;
                case Imagick::ORIENTATION_LEFTBOTTOM:  $im->rotateImage('#000', -90); break;
            }
            $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        }

        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        if (max($w, $h) > $max) {
            if ($w >= $h) $im->resizeImage($max, 0, Imagick::FILTER_LANCZOS, 1);
            else          $im->resizeImage(0, $max, Imagick::FILTER_LANCZOS, 1);
        }

        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality($quality);
        $im->setImageInterlaceScheme(Imagick::INTERLACE_PLANE); // progressive JPEG
        $im->stripImage(); // remove all EXIF/IPTC after orientation baked in

        $ok = $im->writeImage($dst);
        $im->clear();
        return (bool)$ok;
    } catch (Throwable $e) {
        error_log('Imagick resize failed: ' . $e->getMessage());
        return false;
    }
}

function _image_resize_gd(string $src, string $dst, int $max, int $quality): bool {
    $info = @getimagesize($src);
    if (!$info) return false;
    [$w, $h] = $info;
    $type = $info[2];

    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
        default:             return false;
    }
    if (!$img) return false;

    // EXIF auto-orient (JPEG only)
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src);
        $orient = is_array($exif) ? (int)($exif['Orientation'] ?? 0) : 0;
        if ($orient >= 3) {
            $rotated = null;
            if ($orient === 3) {
                $rotated = imagerotate($img, 180, 0);
            } elseif ($orient === 6) {
                $rotated = imagerotate($img, -90, 0);
                [$w, $h] = [$h, $w];
            } elseif ($orient === 8) {
                $rotated = imagerotate($img, 90, 0);
                [$w, $h] = [$h, $w];
            }
            if ($rotated) {
                imagedestroy($img);
                $img = $rotated;
            }
        }
    }

    if (max($w, $h) > $max) {
        if ($w >= $h) { $newW = $max;             $newH = (int)round($h * ($max / $w)); }
        else          { $newH = $max;             $newW = (int)round($w * ($max / $h)); }
        $resized = imagecreatetruecolor($newW, $newH);
        // Solid white fill (in case of transparent PNGs being saved as JPEG)
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefilledrectangle($resized, 0, 0, $newW, $newH, $white);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    // Progressive JPEG for nicer perceived load
    if (function_exists('imageinterlace')) imageinterlace($img, true);
    $ok = imagejpeg($img, $dst, $quality);
    imagedestroy($img);
    return (bool)$ok;
}

/**
 * Convert a base filename like "abc123.jpg" into "abc123-thumb.jpg".
 */
function image_thumb_filename(string $filename): string {
    $dot = strrpos($filename, '.');
    if ($dot === false) return $filename . '-thumb';
    return substr($filename, 0, $dot) . '-thumb' . substr($filename, $dot);
}
