<?php

declare(strict_types=1);

/**
 * One public projection for Place contact details and related links.
 *
 * The existing official facility bundle remains read-only. Approved Scout
 * Report contact answers supply missing information without modifying source
 * records. This function is pure so public rendering and tests agree.
 *
 * @return array{items: array<string, array{value: string, icon: string, kind: string}>, links: list<array{url: string, title: string, label: string, icon: string}>}
 */
function llama_place_report_public_contact_cards(
    array $official,
    array $report
): array {
    $string = static function (array $source, string $key): string {
        $value = $source[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    };
    $validUrl = static function (string $url): bool {
        return $url !== ''
            && (bool) filter_var($url, FILTER_VALIDATE_URL)
            && in_array(
                strtolower((string) parse_url($url, PHP_URL_SCHEME)),
                ['http', 'https'],
                true
            );
    };

    $contact = is_array($report['contact'] ?? null) ? $report['contact'] : [];
    $details = is_array($report['details'] ?? null) ? $report['details'] : [];

    $phone = $string($official, 'phone');
    if ($phone === '') {
        $phone = $string($contact, 'phone');
    }
    $email = $string($official, 'email');
    if ($email === '') {
        $email = $string($contact, 'email');
    }

    $items = [
        'Official Address' => [
            'value' => $string($official, 'address'),
            'icon' => 'map-pin',
            'kind' => 'text',
        ],
        'Phone Number' => [
            'value' => $phone,
            'icon' => 'phone',
            'kind' => 'phone',
        ],
        'Email Address' => [
            'value' => $email,
            'icon' => 'mail',
            'kind' => 'email',
        ],
        'Published Fees' => [
            'value' => $string($official, 'fee_description'),
            'icon' => 'receipt',
            'kind' => 'text',
        ],
    ];

    $seenUrls = [];
    $appendUrlCard = static function (
        string $label,
        string $url,
        string $icon
    ) use (&$items, &$seenUrls, $validUrl): void {
        if (!$validUrl($url)) {
            return;
        }
        $dedupeKey = strtolower(rtrim($url, '/'));
        if (isset($seenUrls[$dedupeKey])) {
            return;
        }
        $seenUrls[$dedupeKey] = true;
        $items[$label] = [
            'value' => $url,
            'icon' => $icon,
            'kind' => 'url',
        ];
    };

    $appendUrlCard(
        'Website',
        $string($contact, 'website_url'),
        'globe'
    );
    $appendUrlCard(
        'Official Map',
        $string($details, 'facility_map_url'),
        'map'
    );
    $reservations = $string($official, 'reservation_url');
    if ($reservations === '') {
        $reservations = $string($contact, 'reservations_url');
    }
    $appendUrlCard('Reservations', $reservations, 'calendar');

    $links = [];
    $appendLink = static function (
        string $url,
        string $title,
        string $label
    ) use (&$links, &$seenUrls, $validUrl): void {
        if (!$validUrl($url)) {
            return;
        }
        $dedupeKey = strtolower(rtrim($url, '/'));
        if (isset($seenUrls[$dedupeKey])) {
            return;
        }
        $seenUrls[$dedupeKey] = true;
        $links[] = [
            'url' => $url,
            'title' => $title !== '' ? $title : $url,
            'label' => $label,
            'icon' => 'external-link',
        ];
    };

    // Official provider links are shown before community-submitted extras.
    foreach ((array) ($official['links'] ?? []) as $link) {
        if (!is_array($link)) {
            continue;
        }
        $appendLink(
            $string($link, 'url'),
            $string($link, 'title'),
            'Official Links'
        );
    }

    // Older records are validated here too, not only at submission.
    $related = $string($contact, 'related_links');
    foreach (array_slice(preg_split('/\\r\\n|\\r|\\n/', $related) ?: [], 0, 8) as $url) {
        $url = trim($url);
        $host = $validUrl($url)
            ? (string) parse_url($url, PHP_URL_HOST)
            : '';
        $appendLink($url, $host, 'Related Link');
    }

    return [
        'items' => $items,
        'links' => $links,
    ];
}
