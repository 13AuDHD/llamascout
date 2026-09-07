<?php

declare(strict_types=1);

function llama_timezones(): array
{
    return [
        'America/Denver' => 'Mountain Time',
        'America/Phoenix' => 'Arizona Time',
        'America/Los_Angeles' => 'Pacific Time',
        'America/Chicago' => 'Central Time',
        'America/New_York' => 'Eastern Time',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time',
        'America/Toronto' => 'Toronto / Eastern Canada',
        'America/Vancouver' => 'Vancouver / Pacific Canada',
        'Europe/London' => 'London',
        'Europe/Paris' => 'Central Europe',
        'Australia/Sydney' => 'Sydney',
        'Pacific/Auckland' => 'Auckland',
    ];
}

function llama_default_timezone(): string
{
    return 'America/Denver';
}

function llama_timezone_is_valid(?string $timezone): bool
{
    $timezone = trim((string) $timezone);
    return $timezone !== '' && array_key_exists($timezone, llama_timezones());
}

function llama_user_timezone(?array $user): string
{
    $timezone = trim((string) ($user['timezone'] ?? ''));
    return llama_timezone_is_valid($timezone)
        ? $timezone
        : llama_default_timezone();
}

function llama_reset_viewer_timezone_cache(): void
{
    unset($GLOBALS['llama_viewer_timezone_cache']);
}

function llama_viewer_timezone(?array $user = null): string
{
    if ($user !== null) {
        return llama_user_timezone($user);
    }

    $cached = $GLOBALS['llama_viewer_timezone_cache'] ?? null;
    if (is_string($cached) && llama_timezone_is_valid($cached)) {
        return $cached;
    }

    $timezone = llama_default_timezone();

    if (function_exists('current_user')) {
        try {
            $viewer = current_user();
            if (is_array($viewer)) {
                $timezone = llama_user_timezone($viewer);
            }
        } catch (Throwable) {
            // Formatting must never break a page if viewer lookup fails.
        }
    }

    $GLOBALS['llama_viewer_timezone_cache'] = $timezone;
    return $timezone;
}

function llama_format_datetime(
    ?string $date,
    ?string $timezone = null,
    string $format = 'Y-m-d H:i:s T'
): string {
    $date = trim((string) $date);
    if ($date === '') {
        return 'Never';
    }

    $timezone = llama_timezone_is_valid($timezone)
        ? (string) $timezone
        : llama_default_timezone();

    try {
        $value = new DateTimeImmutable($date, new DateTimeZone('UTC'));
        return $value
            ->setTimezone(new DateTimeZone($timezone))
            ->format($format);
    } catch (Throwable) {
        return $date;
    }
}

function llama_format_user_datetime(
    ?string $date,
    ?array $user,
    string $format = 'Y-m-d H:i:s T'
): string {
    return llama_format_datetime($date, llama_user_timezone($user), $format);
}

function llama_format_viewer_datetime(
    ?string $date,
    string $format = 'Y-m-d H:i:s T'
): string {
    return llama_format_datetime($date, llama_viewer_timezone(), $format);
}

function llama_format_date(
    ?string $date,
    ?string $timezone = null,
    string $format = 'Y-m-d'
): string {
    return llama_format_datetime($date, $timezone, $format);
}

function llama_format_viewer_date(
    ?string $date,
    string $format = 'Y-m-d'
): string {
    return llama_format_datetime($date, llama_viewer_timezone(), $format);
}
