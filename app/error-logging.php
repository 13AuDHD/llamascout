<?php

declare(strict_types=1);

function llama_error_ensure_table(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS application_errors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reference_code VARCHAR(24) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'error',
            exception_class VARCHAR(190) NULL,
            message TEXT NOT NULL,
            action VARCHAR(190) NULL,
            request_method VARCHAR(12) NULL,
            request_path VARCHAR(500) NULL,
            user_id BIGINT UNSIGNED NULL,
            file_path VARCHAR(500) NULL,
            line_number INT UNSIGNED NULL,
            trace MEDIUMTEXT NULL,
            context_json MEDIUMTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_application_errors_reference (reference_code),
            KEY idx_application_errors_created (created_at),
            KEY idx_application_errors_user (user_id),
            KEY idx_application_errors_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function llama_error_reference(): string
{
    try {
        return 'LS-' . strtoupper(bin2hex(random_bytes(4)));
    } catch (Throwable $exception) {
        return 'LS-' . strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8));
    }
}

function llama_error_request_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    if ($uri === '') {
        return '';
    }

    $path = parse_url($uri, PHP_URL_PATH);

    return is_string($path)
        ? mb_substr($path, 0, 500)
        : '';
}

function llama_error_user_id(): ?int
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    return $userId > 0 ? $userId : null;
}

function llama_error_sanitize_context(array $context): array
{
    $blocked = [
        'password', 'passwd', 'token', 'secret', 'authorization',
        'cookie', 'session', 'csrf', 'card', 'cvc', 'stripe',
        'api_key', 'apikey', 'key',
    ];

    $clean = [];

    foreach ($context as $key => $value) {
        $keyString = (string) $key;
        $normalized = strtolower($keyString);

        $sensitive = false;
        foreach ($blocked as $needle) {
            if (str_contains($normalized, $needle)) {
                $sensitive = true;
                break;
            }
        }

        if ($sensitive) {
            $clean[$keyString] = '[redacted]';
            continue;
        }

        if (is_array($value)) {
            $clean[$keyString] = llama_error_sanitize_context($value);
        } elseif (is_scalar($value) || $value === null) {
            $clean[$keyString] = $value;
        } elseif ($value instanceof Stringable) {
            $clean[$keyString] = (string) $value;
        } else {
            $clean[$keyString] = '[' . get_debug_type($value) . ']';
        }
    }

    return $clean;
}

function llama_log_exception(
    Throwable $exception,
    ?string $action = null,
    array $context = [],
    string $severity = 'error'
): string {
    $reference = llama_error_reference();
    $method = mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 12);
    $path = llama_error_request_path();
    $userId = llama_error_user_id();
    $context = llama_error_sanitize_context($context);

    $serverLine = sprintf(
        '[%s] %s %s user=%s path=%s action=%s %s: %s in %s:%d',
        $severity,
        $reference,
        gmdate('c'),
        $userId !== null ? (string) $userId : '-',
        $path !== '' ? $path : '-',
        $action !== null && $action !== '' ? $action : '-',
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );

    error_log($serverLine);

    try {
        $db = db();
        llama_error_ensure_table($db);

        $stmt = $db->prepare(
            'INSERT INTO application_errors
                (reference_code, severity, exception_class, message, action,
                 request_method, request_path, user_id, file_path, line_number,
                 trace, context_json)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $contextJson = $context
            ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        $stmt->execute([
            $reference,
            mb_substr($severity, 0, 20),
            mb_substr(get_class($exception), 0, 190),
            $exception->getMessage(),
            $action !== null ? mb_substr($action, 0, 190) : null,
            $method !== '' ? $method : null,
            $path !== '' ? $path : null,
            $userId,
            mb_substr($exception->getFile(), 0, 500),
            max(0, $exception->getLine()),
            $exception->getTraceAsString(),
            $contextJson !== false ? $contextJson : null,
        ]);
    } catch (Throwable $loggingFailure) {
        error_log(
            '[error-logger-failure] ' . $reference . ' ' .
            get_class($loggingFailure) . ': ' . $loggingFailure->getMessage()
        );
    }

    return $reference;
}

function llama_error_public_response(string $reference): never
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Cache-Control: no-store, max-age=0');
    }

    $path = llama_error_request_path();
    $isApi = str_starts_with($path, '/api/');

    if ($isApi) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'error' => 'Something went wrong.',
            'reference' => $reference,
        ]);
        exit;
    }

    $safeReference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Something went wrong | Llama Scout</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#111;color:#f5f5f5;margin:0;padding:32px}'
        . 'main{max-width:640px;margin:10vh auto;background:#1c1c1c;border:1px solid #333;border-radius:16px;padding:28px}'
        . 'h1{margin-top:0}code{background:#0d0d0d;padding:4px 8px;border-radius:6px}</style></head><body><main>'
        . '<h1>Something went wrong.</h1>'
        . '<p>Llama Scout recorded the error so it can be investigated.</p>'
        . '<p>Error reference: <code>' . $safeReference . '</code></p>'
        . '</main></body></html>';

    exit;
}

function llama_error_register_handlers(): void
{
    static $registered = false;

    if ($registered) {
        return;
    }

    $registered = true;

    set_exception_handler(
        static function (Throwable $exception): void {
            $reference = llama_log_exception($exception, 'uncaught_exception');
            llama_error_public_response($reference);
        }
    );

    register_shutdown_function(
        static function (): void {
            $last = error_get_last();

            if (!$last) {
                return;
            }

            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

            if (!in_array((int) ($last['type'] ?? 0), $fatalTypes, true)) {
                return;
            }

            $exception = new ErrorException(
                (string) ($last['message'] ?? 'Fatal PHP error'),
                0,
                (int) ($last['type'] ?? E_ERROR),
                (string) ($last['file'] ?? ''),
                (int) ($last['line'] ?? 0)
            );

            $reference = llama_log_exception($exception, 'fatal_shutdown', [], 'fatal');

            if (!headers_sent()) {
                llama_error_public_response($reference);
            }
        }
    );
}
