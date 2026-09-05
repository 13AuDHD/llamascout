<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT PROMOTION BANNER

   Reads the currently active website promotion from the
   membership promotion calendar.

   Campaign timestamps are stored in UTC.
   ========================================================= */

function llama_active_website_promotion(PDO $db): ?array
{
    try {
        $table = $db->query(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'membership_promotions'"
        );

        if (!$table || (int) $table->fetchColumn() < 1) {
            return null;
        }

        $requiredColumns = [
            'show_site_banner',
            'show_countdown',
            'banner_text',
            'landing_url',
        ];

        $columnStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'membership_promotions'
               AND column_name = ?"
        );

        foreach ($requiredColumns as $column) {
            $columnStmt->execute([$column]);

            if ((int) $columnStmt->fetchColumn() < 1) {
                return null;
            }
        }

        $stmt = $db->query(
            "SELECT
                id,
                name,
                public_label,
                public_description,
                starts_at,
                ends_at,
                show_site_banner,
                show_countdown,
                banner_text,
                landing_url,
                promotion_code,
                campaign_type
             FROM membership_promotions
             WHERE is_enabled = 1
               AND show_site_banner = 1
               AND starts_at <= UTC_TIMESTAMP()
               AND ends_at > UTC_TIMESTAMP()
             ORDER BY starts_at DESC, id DESC
             LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

        return $promotion ?: null;
    } catch (Throwable $exception) {
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception(
                $exception,
                'promotion_banner.read'
            );
        }

        return null;
    }
}


function llama_promotion_banner_text(array $promotion): string
{
    $bannerText = trim((string) ($promotion['banner_text'] ?? ''));

    if ($bannerText !== '') {
        return $bannerText;
    }

    $label = trim((string) ($promotion['public_label'] ?? ''));

    if ($label !== '') {
        return $label;
    }

    return trim((string) ($promotion['name'] ?? 'Limited-time membership offer'));
}


function llama_promotion_banner_url(
    array $promotion,
    string $siteUrl
): string {
    $url = trim((string) ($promotion['landing_url'] ?? ''));

    if ($url === '') {
        return rtrim($siteUrl, '/') . '/membership';
    }

    if (str_starts_with($url, '/')) {
        return rtrim($siteUrl, '/') . $url;
    }

    if (
        preg_match(
            '#^https?://#i',
            $url
        )
    ) {
        return $url;
    }

    return rtrim($siteUrl, '/') . '/' . ltrim($url, '/');
}


function llama_promotion_banner_end_iso(?string $utcDateTime): string
{
    $value = trim((string) $utcDateTime);

    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC')
        ))->format('Y-m-d\TH:i:s\Z');
    } catch (Throwable) {
        return '';
    }
}
