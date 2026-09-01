<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT
   SHARED STAGED PHOTO WORKFLOW

   Upload first, preview/edit/remove immediately, then commit
   only when the parent form succeeds.
   ========================================================= */

function llama_photo_csrf_token(): string
{
    if (empty($_SESSION['llama_photo_csrf'])) {
        $_SESSION['llama_photo_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['llama_photo_csrf'];
}

function llama_photo_verify_csrf(string $token): bool
{
    $stored = (string) ($_SESSION['llama_photo_csrf'] ?? '');

    return $stored !== ''
        && $token !== ''
        && hash_equals($stored, $token);
}

function llama_photo_contexts(): array
{
    return [
        'add-place' => ['max' => 10, 'label' => 'Place photos'],
        'update-place' => ['max' => 5, 'label' => 'Update photos'],
        'place-report' => ['max' => 5, 'label' => 'Report photos'],
        'profile-images' => ['max' => 5, 'label' => 'Profile images'],
        'shop-products' => ['max' => 20, 'label' => 'Product photos'],
        'badges' => ['max' => 1, 'label' => 'Badge image'],
    ];
}

function llama_photo_context_config(string $context): array
{
    $context = strtolower(trim($context));
    $contexts = llama_photo_contexts();

    if (!isset($contexts[$context])) {
        throw new InvalidArgumentException('Invalid photo upload context.');
    }

    return $contexts[$context];
}

function llama_photo_context_allowed(string $context, int $userId): bool
{
    if ($userId < 1) {
        return false;
    }

    llama_photo_context_config($context);

    if (!in_array(
        $context,
        [
            'shop-products',
            'badges',
        ],
        true
    )) {
        return true;
    }

    $roles = function_exists('user_roles') ? user_roles($userId) : [];

    return (bool) array_intersect(
        ['owner', 'admin', 'administrator'],
        array_map('strtolower', $roles)
    );
}

function llama_photo_stage_token(?string $token = null): string
{
    $token = strtolower(trim((string) $token));

    if ($token === '') {
        return bin2hex(random_bytes(16));
    }

    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        throw new InvalidArgumentException('Invalid photo staging token.');
    }

    return $token;
}

function llama_photo_public_root(): string
{
    $config = function_exists('llama_config') ? llama_config() : [];

    return rtrim(
        (string) ($config['app']['url'] ?? 'https://llamascout.com'),
        '/'
    );
}

function llama_photo_public_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return llama_photo_public_root() . '/' . ltrim($path, '/');
}

function llama_photo_stage_relative_dir(
    string $context,
    int $userId,
    string $token
): string {
    llama_photo_context_config($context);

    if ($userId < 1) {
        throw new InvalidArgumentException('A valid uploader is required.');
    }

    $token = llama_photo_stage_token($token);

    return '/uploads/staging/'
        . $context
        . '/user-'
        . $userId
        . '/'
        . $token;
}

function llama_photo_stage_absolute_dir(
    string $context,
    int $userId,
    string $token
): string {
    return dirname(__DIR__)
        . llama_photo_stage_relative_dir($context, $userId, $token);
}

function llama_photo_manifest_path(
    string $context,
    int $userId,
    string $token
): string {
    return llama_photo_stage_absolute_dir($context, $userId, $token)
        . '/manifest.json';
}

function llama_photo_read_manifest(
    string $context,
    int $userId,
    string $token
): array {
    $path = llama_photo_manifest_path($context, $userId, $token);

    if (!is_file($path)) {
        return [];
    }

    $json = @file_get_contents($path);
    if (!is_string($json) || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

function llama_photo_write_manifest(
    string $context,
    int $userId,
    string $token,
    array $photos
): void {
    $directory = llama_photo_stage_absolute_dir($context, $userId, $token);

    if (
        !is_dir($directory)
        && !mkdir($directory, 0755, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException('The temporary photo directory could not be created.');
    }

    $payload = json_encode(
        array_values($photos),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );

    if (@file_put_contents($directory . '/manifest.json', $payload, LOCK_EX) === false) {
        throw new RuntimeException('The temporary photo list could not be saved.');
    }
}

function llama_photo_process_badge_png(
    string $source,
    string $destination,
    int $maxDimension = 2400
): array {
    if (
        extension_loaded('imagick')
        && class_exists('Imagick')
    ) {
        $image = new Imagick();

        try {
            $image->readImage($source);

            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
            }

            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            $image->stripImage();
            $image->setImageAlphaChannel(
                Imagick::ALPHACHANNEL_ACTIVATE
            );

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();

            [$newWidth, $newHeight] =
                llama_photo_resized_dimensions(
                    $width,
                    $height,
                    $maxDimension
                );

            if (
                $newWidth !== $width
                || $newHeight !== $height
            ) {
                $image->thumbnailImage(
                    $newWidth,
                    $newHeight,
                    true,
                    true
                );
            }

            $image->setImageFormat('png');
            $image->setOption(
                'png:compression-level',
                '8'
            );
            $image->stripImage();

            if (!$image->writeImage($destination)) {
                throw new RuntimeException(
                    'The transparent badge image could not be saved.'
                );
            }

            return [
                'width' => $image->getImageWidth(),
                'height' => $image->getImageHeight(),
            ];
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    if (
        !extension_loaded('gd')
        || !function_exists('imagecreatefrompng')
        || !function_exists('imagepng')
    ) {
        throw new RuntimeException(
            'This server cannot currently preserve transparent PNG badge images.'
        );
    }

    $sourceImage =
        @imagecreatefrompng($source);

    if (!$sourceImage) {
        throw new RuntimeException(
            'The PNG badge image could not be decoded.'
        );
    }

    try {
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);

        [$newWidth, $newHeight] =
            llama_photo_resized_dimensions(
                $width,
                $height,
                $maxDimension
            );

        $output = imagecreatetruecolor(
            $newWidth,
            $newHeight
        );

        if (!$output) {
            throw new RuntimeException(
                'The transparent badge image could not be prepared.'
            );
        }

        try {
            imagealphablending($output, false);
            imagesavealpha($output, true);

            $transparent =
                imagecolorallocatealpha(
                    $output,
                    0,
                    0,
                    0,
                    127
                );

            imagefilledrectangle(
                $output,
                0,
                0,
                $newWidth,
                $newHeight,
                $transparent
            );

            imagecopyresampled(
                $output,
                $sourceImage,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $width,
                $height
            );

            if (!imagepng($output, $destination, 8)) {
                throw new RuntimeException(
                    'The transparent badge image could not be saved.'
                );
            }
        } finally {
            imagedestroy($output);
        }

        return [
            'width' => $newWidth,
            'height' => $newHeight,
        ];
    } finally {
        imagedestroy($sourceImage);
    }
}


function llama_photo_stage_upload(
    array $files,
    int $userId,
    string $context,
    string $token
): array {
    $config = llama_photo_context_config($context);
    $token = llama_photo_stage_token($token);
    $existing = llama_photo_read_manifest($context, $userId, $token);
    $uploads = llama_photo_normalize_uploads($files);

    if (!$uploads) {
        throw new InvalidArgumentException('Choose at least one photo.');
    }

    $maxPhotos = (int) $config['max'];

    if (count($existing) + count($uploads) > $maxPhotos) {
        throw new InvalidArgumentException(
            'You can include up to ' . $maxPhotos . ' photos here.'
        );
    }

    $directory = llama_photo_stage_absolute_dir($context, $userId, $token);
    $relativeDirectory = llama_photo_stage_relative_dir($context, $userId, $token);

    if (
        !is_dir($directory)
        && !mkdir($directory, 0755, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException('The temporary photo directory could not be created.');
    }

    $created = [];

    try {
        foreach ($uploads as $upload) {
            if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the photos did not upload correctly.');
            }

            if ((int) $upload['size'] < 1) {
                throw new RuntimeException('One of the selected photos is empty.');
            }

            if ((int) $upload['size'] > 15728640) {
                throw new RuntimeException('Each photo must be 15 MB or smaller.');
            }

            $tmp = (string) $upload['tmp_name'];

            if (!is_uploaded_file($tmp)) {
                throw new RuntimeException('One of the uploaded files could not be verified.');
            }

            $format = llama_photo_detect_image($tmp);

            if ($format === null) {
                throw new RuntimeException('One of the selected files is not a supported image.');
            }

            $preserveBadgePng =
                $context === 'badges'
                && $format === 'png';

            $extension =
                $preserveBadgePng
                    ? 'png'
                    : 'jpg';

            $mimeType =
                $preserveBadgePng
                    ? 'image/png'
                    : 'image/jpeg';

            $filename =
                'photo-' .
                bin2hex(random_bytes(16)) .
                '.' .
                $extension;

            $absolutePath =
                $directory .
                '/' .
                $filename;

            $relativePath =
                $relativeDirectory .
                '/' .
                $filename;

            if ($preserveBadgePng) {
                $dimensions =
                    llama_photo_process_badge_png(
                        $tmp,
                        $absolutePath,
                        2400
                    );
            } elseif (llama_photo_imagick_can_read($format)) {
                $dimensions = llama_photo_process_imagick(
                    $tmp,
                    $absolutePath,
                    2400,
                    84
                );
            } elseif (in_array($format, ['jpeg', 'png', 'webp'], true)) {
                $dimensions = llama_photo_process_gd(
                    $tmp,
                    $format,
                    $absolutePath,
                    2400,
                    84
                );
            } else {
                throw new RuntimeException(
                    'This server cannot currently convert that phone photo format.'
                );
            }

            if (!is_file($absolutePath) || filesize($absolutePath) < 1) {
                throw new RuntimeException('A processed photo was not saved correctly.');
            }

            $created[] = $absolutePath;

            $existing[] = [
                'path' => $relativePath,
                'url' => llama_photo_public_url($relativePath),
                'filename' => $filename,
                'original_name' => (string) ($upload['name'] ?? ''),
                'mime_type' => $mimeType,
                'width' => (int) ($dimensions['width'] ?? 0),
                'height' => (int) ($dimensions['height'] ?? 0),
                'size' => (int) filesize($absolutePath),
                'alt' => '',
            ];
        }

        llama_photo_write_manifest($context, $userId, $token, $existing);
    } catch (Throwable $exception) {
        foreach ($created as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        throw $exception;
    }

    return array_values($existing);
}

function llama_photo_stage_update_metadata(
    string $context,
    int $userId,
    string $token,
    array $submittedPhotos
): array {
    $manifest = llama_photo_read_manifest($context, $userId, $token);
    $byPath = [];

    foreach ($submittedPhotos as $photo) {
        if (!is_array($photo)) {
            continue;
        }

        $path = trim((string) ($photo['path'] ?? ''));
        if ($path !== '') {
            $byPath[$path] = $photo;
        }
    }

    foreach ($manifest as &$photo) {
        $path = (string) ($photo['path'] ?? '');
        if (isset($byPath[$path])) {
            $photo['alt'] = mb_substr(
                trim((string) ($byPath[$path]['alt'] ?? '')),
                0,
                300
            );
        }
    }
    unset($photo);

    llama_photo_write_manifest($context, $userId, $token, $manifest);

    return $manifest;
}

function llama_photo_stage_delete(
    string $context,
    int $userId,
    string $token,
    string $relativePath
): array {
    $manifest = llama_photo_read_manifest($context, $userId, $token);
    $requiredPrefix = llama_photo_stage_relative_dir($context, $userId, $token) . '/';

    if (!str_starts_with($relativePath, $requiredPrefix)) {
        throw new InvalidArgumentException('That staged photo does not belong to this upload.');
    }

    $next = [];
    $found = false;

    foreach ($manifest as $photo) {
        if ((string) ($photo['path'] ?? '') === $relativePath) {
            $found = true;
            $absolute = dirname(__DIR__) . $relativePath;
            if (is_file($absolute)) {
                @unlink($absolute);
            }
            continue;
        }

        $next[] = $photo;
    }

    if (!$found) {
        return $manifest;
    }

    llama_photo_write_manifest($context, $userId, $token, $next);

    return array_values($next);
}

function llama_photo_remove_tree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . '/' . $item;

        if (is_dir($path)) {
            llama_photo_remove_tree($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($directory);
}

function llama_photo_stage_abandon(
    string $context,
    int $userId,
    string $token
): void {
    llama_photo_context_config($context);
    $token = llama_photo_stage_token($token);
    llama_photo_remove_tree(llama_photo_stage_absolute_dir($context, $userId, $token));
}

function llama_photo_decode_form_photos(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter($decoded, 'is_array'));
}

function llama_photo_commit_stage(
    string $context,
    int $userId,
    string $token,
    array $submittedPhotos,
    string $destinationRelativeDirectory
): array {
    if (!$submittedPhotos) {
        llama_photo_stage_abandon($context, $userId, $token);
        return [];
    }

    $manifest = llama_photo_stage_update_metadata(
        $context,
        $userId,
        $token,
        $submittedPhotos
    );

    $submittedPaths = [];
    foreach ($submittedPhotos as $photo) {
        $path = trim((string) ($photo['path'] ?? ''));
        if ($path !== '') {
            $submittedPaths[$path] = true;
        }
    }

    $destinationRelativeDirectory = '/' . trim($destinationRelativeDirectory, '/');
    $destinationAbsoluteDirectory = dirname(__DIR__) . $destinationRelativeDirectory;

    if (
        !is_dir($destinationAbsoluteDirectory)
        && !mkdir($destinationAbsoluteDirectory, 0755, true)
        && !is_dir($destinationAbsoluteDirectory)
    ) {
        throw new RuntimeException('The permanent photo directory could not be created.');
    }

    $committed = [];
    $movedPaths = [];

    try {
        foreach ($manifest as $photo) {
            $sourceRelative = (string) ($photo['path'] ?? '');

            if (!isset($submittedPaths[$sourceRelative])) {
                continue;
            }

            $sourceAbsolute = dirname(__DIR__) . $sourceRelative;
            if (!is_file($sourceAbsolute)) {
                throw new RuntimeException('A staged photo is missing. Please upload it again.');
            }

            $filename = basename((string) ($photo['filename'] ?? $sourceRelative));
            $destinationAbsolute = $destinationAbsoluteDirectory . '/' . $filename;
            $destinationRelative = $destinationRelativeDirectory . '/' . $filename;

            if (!@rename($sourceAbsolute, $destinationAbsolute)) {
                if (!@copy($sourceAbsolute, $destinationAbsolute) || !@unlink($sourceAbsolute)) {
                    throw new RuntimeException('A photo could not be moved into permanent storage.');
                }
            }

            $movedPaths[] = $destinationAbsolute;

            $committed[] = [
                'path' => $destinationRelative,
                'url' => llama_photo_public_url($destinationRelative),
                'filename' => $filename,
                'original_name' => (string) ($photo['original_name'] ?? ''),
                'mime_type' => (string) ($photo['mime_type'] ?? 'image/jpeg'),
                'width' => (int) ($photo['width'] ?? 0),
                'height' => (int) ($photo['height'] ?? 0),
                'size' => (int) ($photo['size'] ?? 0),
                'alt' => mb_substr(trim((string) ($photo['alt'] ?? '')), 0, 300),
            ];
        }

        llama_photo_stage_abandon($context, $userId, $token);
    } catch (Throwable $exception) {
        foreach ($movedPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        throw $exception;
    }

    return $committed;
}

function llama_photo_delete_owned_permanent_path(
    string $relativePath,
    array $allowedPrefixes
): bool {
    $relativePath = '/' . ltrim(trim($relativePath), '/');

    $allowed = false;
    foreach ($allowedPrefixes as $prefix) {
        $prefix = '/' . trim((string) $prefix, '/') . '/';
        if (str_starts_with($relativePath, $prefix)) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed || str_contains($relativePath, '..')) {
        return false;
    }

    $absolute = dirname(__DIR__) . $relativePath;

    return !is_file($absolute) || @unlink($absolute);
}

function llama_photo_cleanup_abandoned_staging(int $olderThanSeconds = 86400): void
{
    $now = time();
    $last = (int) ($_SESSION['llama_photo_cleanup_at'] ?? 0);

    if ($last > 0 && ($now - $last) < 3600) {
        return;
    }

    $_SESSION['llama_photo_cleanup_at'] = $now;

    $root = dirname(__DIR__) . '/uploads/staging';
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();

        if ($item->isFile()) {
            if (($now - $item->getMTime()) > $olderThanSeconds) {
                @unlink($path);
            }
            continue;
        }

        $contents = @scandir($path);
        if (is_array($contents) && count($contents) === 2) {
            @rmdir($path);
        }
    }
}
