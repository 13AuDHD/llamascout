<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/knowledge-base.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode(
        [
            'ok' => false,
            'message' => 'POST required.',
        ]
    );
    exit;
}

$contentType =
    strtolower(
        trim(
            (string) (
                $_SERVER['CONTENT_TYPE']
                ?? ''
            )
        )
    );

$input = $_POST;

if (str_starts_with($contentType, 'application/json')) {
    $raw =
        file_get_contents('php://input');

    $decoded =
        is_string($raw)
            ? json_decode($raw, true)
            : null;

    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$token =
    trim(
        (string) (
            $input['csrf_token']
            ?? ''
        )
    );

if (!llama_kb_public_verify_feedback_token($token)) {
    http_response_code(403);

    echo json_encode(
        [
            'ok' => false,
            'message' =>
                'Your session token expired. Reload the article and try again.',
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
    exit;
}

$articleId =
    max(
        0,
        (int) ($input['article_id'] ?? 0)
    );

$helpfulRaw =
    $input['helpful']
    ?? null;

$helpful =
    filter_var(
        $helpfulRaw,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );

if ($helpful === null) {
    http_response_code(422);

    echo json_encode(
        [
            'ok' => false,
            'message' =>
                'Choose whether the article was helpful.',
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
    exit;
}

$reason =
    trim(
        (string) (
            $input['reason']
            ?? ''
        )
    );

try {
    $feedbackId =
        llama_kb_public_submit_feedback(
            db(),
            $articleId,
            $helpful,
            $reason
        );

    echo json_encode(
        [
            'ok' => true,
            'feedback_id' => $feedbackId,
            'helpful' => $helpful,
            'message' =>
                $helpful
                    ? 'Thanks. This llama appreciates useful data.'
                    : 'Thanks. That helps us improve this article.',
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode(
        [
            'ok' => false,
            'message' => $exception->getMessage(),
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode(
        [
            'ok' => false,
            'message' =>
                'Feedback could not be saved right now.',
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
}
