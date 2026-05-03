<?php
declare(strict_types=1);

/**
 * Upload one or more product images. Validates by mime sniff (not extension),
 * generates unique filename, moves into /uploads/, inserts product_images row.
 *
 * @param int $productId
 * @param array $files     re-indexed multi-file array (see normalize_files_array)
 * @param int $startSort   sort_order to start incrementing from
 * @return array{saved: string[], errors: string[]}
 */
function handle_image_upload(int $productId, array $files, int $startSort = 0): array {
    $cfg = config('uploads');
    $maxSize = (int)($cfg['max_size'] ?? 10485760);
    $allowed = $cfg['allowed_mimes'] ?? ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    $uploadDir = realpath(__DIR__ . '/../uploads');
    if ($uploadDir === false) {
        @mkdir(__DIR__ . '/../uploads', 0755, true);
        $uploadDir = realpath(__DIR__ . '/../uploads');
    }

    $errors = [];
    $saved = [];
    $sort = $startSort;

    foreach ($files as $file) {
        if (!is_array($file)) continue;
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error code $err for " . ($file['name'] ?? 'file');
            continue;
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
            $errors[] = "File too large: " . ($file['name'] ?? '?');
            continue;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);
        if (!$mime || !in_array($mime, $allowed, true)) {
            $errors[] = "Unsupported image type: " . ($mime ?: 'unknown');
            continue;
        }

        // Move into a scratch path inside /uploads — required by PHP for uploaded files.
        $scratch = $uploadDir . DIRECTORY_SEPARATOR . '_tmp_' . bin2hex(random_bytes(8));
        if (!move_uploaded_file($file['tmp_name'], $scratch)) {
            $errors[] = "Failed to save " . ($file['name'] ?? '?');
            continue;
        }

        // All output is JPEG, regardless of source format.
        $newName   = bin2hex(random_bytes(8)) . '.jpg';
        $thumbName = image_thumb_filename($newName);
        $displayPath = $uploadDir . DIRECTORY_SEPARATOR . $newName;
        $thumbPath   = $uploadDir . DIRECTORY_SEPARATOR . $thumbName;

        $okDisplay = image_resize($scratch, $displayPath, IMAGE_DISPLAY_LONG_EDGE, IMAGE_DISPLAY_QUALITY);
        $okThumb   = image_resize($scratch, $thumbPath,   IMAGE_THUMB_LONG_EDGE,   IMAGE_THUMB_QUALITY);

        @unlink($scratch); // discard original

        if (!$okDisplay) {
            $errors[] = "Couldn't process image: " . ($file['name'] ?? '?');
            @unlink($displayPath);
            @unlink($thumbPath);
            continue;
        }
        if (!$okThumb) {
            error_log("Thumb generation failed for $newName — display fallback will be used");
        }

        @chmod($displayPath, 0644);
        if ($okThumb) @chmod($thumbPath, 0644);

        $stmt = db()->prepare(
            'INSERT INTO product_images (product_id, filename, sort_order) VALUES (:pid, :fn, :so)'
        );
        $stmt->execute([
            ':pid' => $productId,
            ':fn'  => $newName,
            ':so'  => $sort++,
        ]);
        $saved[] = $newName;
    }

    return ['saved' => $saved, 'errors' => $errors];
}

/**
 * PHP collapses <input name="files[]"> into a column-major array. Re-pivot.
 */
function normalize_files_array(array $field): array {
    if (empty($field['name'])) return [];
    if (!is_array($field['name'])) {
        return [$field];
    }
    $count = count($field['name']);
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[] = [
            'name'     => $field['name'][$i],
            'type'     => $field['type'][$i],
            'tmp_name' => $field['tmp_name'][$i],
            'error'    => $field['error'][$i],
            'size'     => $field['size'][$i],
        ];
    }
    return $out;
}

function delete_image_file(string $filename): void {
    $safe = basename($filename);
    $dir = realpath(__DIR__ . '/../uploads');
    if (!$dir) return;
    $main  = $dir . DIRECTORY_SEPARATOR . $safe;
    $thumb = $dir . DIRECTORY_SEPARATOR . image_thumb_filename($safe);
    if (is_file($main))  @unlink($main);
    if (is_file($thumb)) @unlink($thumb);
}
