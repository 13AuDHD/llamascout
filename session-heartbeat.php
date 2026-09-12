<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';


$origin =
    trim(
        (string) (
            $_SERVER['HTTP_ORIGIN']
            ?? ''
        )
    );

$allowedOrigins = [
    'https://llamascout.com',
    'https://www.llamascout.com',
    'https://account.llamascout.com',
];

if (
    $origin !== ''
    && in_array(
        $origin,
        $allowedOrigins,
        true
    )
) {
    header(
        'Access-Control-Allow-Origin: '
        . $origin
    );

    header(
        'Access-Control-Allow-Credentials: true'
    );

    header(
        'Vary: Origin'
    );
}

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);


if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    === 'OPTIONS'
) {
    http_response_code(204);
    exit;
}


$user =
    current_user();


echo json_encode(
    [
        'authenticated' =>
            $user !== null,

        'user_id' =>
            $user !== null
                ? (int) ($user['id'] ?? 0)
                : 0,

        'checked_at' =>
            gmdate('c'),
    ],
    JSON_UNESCAPED_SLASHES
);
