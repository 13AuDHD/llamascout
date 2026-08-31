<?php

declare(strict_types=1);

function llama_photo_endpoint_respond(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

function llama_photo_run_endpoint(): never
{
    require_verified_email();

    $user = current_user();
    $userId = (int) ($user['id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        llama_photo_endpoint_respond(405, [
            'success' => false,
            'message' => 'Photo changes must use POST.',
        ]);
    }

    $csrf = (string) ($_POST['csrf_token'] ?? '');
    $context = strtolower(trim((string) ($_POST['context'] ?? '')));
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    if (!llama_photo_verify_csrf($csrf)) {
        llama_photo_endpoint_respond(403, [
            'success' => false,
            'message' => 'Your photo upload session expired. Refresh the page and try again.',
        ]);
    }

    try {
        if (!llama_photo_context_allowed($context, $userId)) {
            llama_photo_endpoint_respond(403, [
                'success' => false,
                'message' => 'You do not have permission to use this photo uploader.',
            ]);
        }

        llama_photo_cleanup_abandoned_staging();

        $token = llama_photo_stage_token((string) ($_POST['token'] ?? ''));

        if ($action === 'list') {
            $photos = llama_photo_read_manifest($context, $userId, $token);

            llama_photo_endpoint_respond(200, [
                'success' => true,
                'token' => $token,
                'photos' => $photos,
            ]);
        }

        if ($action === 'upload') {
            $files = $_FILES['photos'] ?? null;

            if (!is_array($files)) {
                throw new InvalidArgumentException('Choose at least one photo.');
            }

            $photos = llama_photo_stage_upload(
                $files,
                $userId,
                $context,
                $token
            );

            llama_photo_endpoint_respond(200, [
                'success' => true,
                'token' => $token,
                'photos' => $photos,
            ]);
        }

        if ($action === 'delete') {
            $path = trim((string) ($_POST['path'] ?? ''));

            $photos = llama_photo_stage_delete(
                $context,
                $userId,
                $token,
                $path
            );

            llama_photo_endpoint_respond(200, [
                'success' => true,
                'token' => $token,
                'photos' => $photos,
            ]);
        }

        if ($action === 'sync') {
            $photos = llama_photo_decode_form_photos($_POST['photos_json'] ?? '[]');
            $photos = llama_photo_stage_update_metadata(
                $context,
                $userId,
                $token,
                $photos
            );

            llama_photo_endpoint_respond(200, [
                'success' => true,
                'token' => $token,
                'photos' => $photos,
            ]);
        }

        if ($action === 'abandon') {
            llama_photo_stage_abandon($context, $userId, $token);

            llama_photo_endpoint_respond(200, [
                'success' => true,
                'token' => $token,
                'photos' => [],
            ]);
        }

        throw new InvalidArgumentException('Invalid photo action.');
    } catch (InvalidArgumentException | RuntimeException $exception) {
        llama_photo_endpoint_respond(422, [
            'success' => false,
            'message' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        error_log('Llama Scout photo endpoint error: ' . $exception->getMessage());

        llama_photo_endpoint_respond(500, [
            'success' => false,
            'message' => 'The photo upload could not be completed. Please try again.',
        ]);
    }
}
