<?php
function slugify($text) {
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-');
  $text = preg_replace('~-+~', '-', $text);
  return strtolower($text ?: 'rezept-' . time());
}

/**
 * Speichert Upload-Bild als komprimiertes WebP
 * - Resize auf maxDim
 * - Qualität 0-100
 * - Unterstützt JPEG, PNG, WebP als Input
 */
function compressAndSaveImageAsWebp(string $tmpFile, string $destFile, int $maxDim = 1600, int $quality = 80): bool
{
    if (!function_exists('imagewebp')) {
        return false;
    }

    $info = @getimagesize($tmpFile);
    if (!$info || empty($info['mime'])) return false;

    switch ($info['mime']) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($tmpFile);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($tmpFile);
            break;
        case 'image/webp':
            $src = @imagecreatefromwebp($tmpFile);
            break;
        default:
            return false;
    }

    if (!$src) return false;

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= 0 || $h <= 0) { imagedestroy($src); return false; }

    $scale = min($maxDim / $w, $maxDim / $h, 1.0);
    $newW = (int)round($w * $scale);
    $newH = (int)round($h * $scale);

    $dst = imagecreatetruecolor($newW, $newH);

    imagealphablending($dst, false);
    imagesavealpha($dst, true);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $dir = dirname($destFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ok = imagewebp($dst, $destFile, $quality);

    imagedestroy($src);
    imagedestroy($dst);

    return $ok;
}
