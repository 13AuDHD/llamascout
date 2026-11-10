<?php

declare(strict_types=1);

/**
 * One public projection for Place contact details and related links.
 *
 * The existing official facility bundle remains read-only. Approved Scout
 * Approved Scout Report corrections supersede official values while source
 * records and their attribution remain intact. This function is pure so public rendering and tests agree.
 *
 * @return array{items: array<string, array{value: string, icon: string, kind: string}>, links: list<array{url: string, title: string, label: string, icon: string}>}
 */
/**
 * Source-backed defaults for editable Place contact fields. Official records
 * remain intact; a later approved answer may supersede an individual value.
 * Only an explicitly identified homepage may populate the Website field.
 *
 * @return array<string,string>
 */
function llama_place_report_official_contact_baselines(array $official): array
{
    $urlValid = static fn (string $url): bool =>
        (bool) filter_var($url, FILTER_VALIDATE_URL)
        && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);

    $links = [];
    $homepage = '';
    foreach ((array) ($official['links'] ?? []) as $link) {
        if (!is_array($link)) {
            continue;
        }
        $url = trim((string) ($link['url'] ?? ''));
        if (!$urlValid($url)) {
            continue;
        }
        $title = trim((string) ($link['title'] ?? ''));
        if ($homepage === '' && preg_match('/\\b(official website|website|home\\s?page|homepage)\\b/i', $title)) {
            $homepage = $url;
        }
        $links[$url] = true;
    }

    // A crowded imported link list is still displayed publicly, but must not
    // be silently cut down to eight when it appears in an editable field.
    $editableLinks = count($links) <= 8
        ? implode("\n", array_keys($links))
        : '';

    return [
        'contact_mailing_address' => trim((string) ($official['address'] ?? '')),
        'contact_phone' => trim((string) ($official['phone'] ?? '')),
        'contact_email' => trim((string) ($official['email'] ?? '')),
        'contact_website_url' => $homepage,
        'contact_reservations_url' => trim((string) ($official['reservation_url'] ?? '')),
        'contact_related_links' => $editableLinks,
    ];
}

/**
 * Show the same effective values in Admin that appear publicly. The values
 * are display defaults only, not new Scout answers or a source data import.
 */
function llama_place_report_contact_editor_projection(
    array $currentValues,
    array $official,
    array $explicitUnknowns = []
): array {
    $values = $currentValues;
    $sources = [];
    $baselines = llama_place_report_official_contact_baselines($official);
    $provider = trim((string) ($official['source_provider'] ?? ''));
    $date = trim((string) ($official['source_updated_at'] ?? ''));
    $sourceLabel = $provider !== '' ? $provider : 'Official source';
    if ($date !== '') {
        $sourceLabel .= ' · ' . $date;
    }

    foreach ($baselines as $key => $baseline) {
        $existing = trim((string) ($currentValues[$key] ?? ''));
        if ($existing !== '' && !in_array($key, $explicitUnknowns, true)) {
            $sources[$key] = 'Approved Scout Report answer';
        } elseif (in_array($key, $explicitUnknowns, true)) {
            $sources[$key] = 'Explicit Unknown answer';
        } elseif ($baseline !== '') {
            $values[$key] = $baseline;
            $sources[$key] = $sourceLabel;
        }
    }

    return ['values' => $values, 'sources' => $sources, 'baselines' => $baselines];
}

/**
 * An unchanged prefilled official fact must not be re-saved as a Scout answer
 * when Admin saves other fields. Actual changes are normal canonical answers.
 */
function llama_place_report_contact_strip_unchanged_official(
    array $submitted,
    array $current,
    array $official
): array {
    foreach (llama_place_report_official_contact_baselines($official) as $key => $baseline) {
        if ($baseline === '' || !array_key_exists($key, $submitted)) {
            continue;
        }
        $existing = trim((string) ($current[$key] ?? ''));
        if ($existing !== '' || (string) $submitted[$key] !== $baseline) {
            continue;
        }
        unset($submitted[$key]);
    }
    return $submitted;
}

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

    $unknowns = (array) ($report['_answer_state'] ?? []);
    $effective = static function (string $key, string $officialValue, string $reportValue) use ($unknowns): string {
        if (in_array($key, $unknowns, true)) {
            return '';
        }
        return $reportValue !== '' ? $reportValue : $officialValue;
    };
    $phone = $effective('contact_phone', $string($official, 'phone'), $string($contact, 'phone'));
    $email = $effective('contact_email', $string($official, 'email'), $string($contact, 'email'));
    $address = $effective('contact_mailing_address', $string($official, 'address'), $string($contact, 'mailing_address'));
    $officialDefaults = llama_place_report_official_contact_baselines($official);

    $items = [
        'Official Address' => [
            'value' => $address,
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
        $effective('contact_website_url', $officialDefaults['contact_website_url'], $string($contact, 'website_url')),
        'globe'
    );
    $appendUrlCard(
        'Official Map',
        $string($details, 'facility_map_url'),
        'map'
    );
    $reservations = $effective('contact_reservations_url', $string($official, 'reservation_url'), $string($contact, 'reservations_url'));
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

    // A reviewed edit to the link list is the effective set. Otherwise the
    // named official links stay authoritative, including their original names.
    $related = $string($contact, 'related_links');
    $overrideLinks = $related !== '' || in_array('contact_related_links', $unknowns, true);
    $originalLabels = [];
    foreach ((array) ($official['links'] ?? []) as $link) {
        if (!is_array($link)) {
            continue;
        }
        $url = $string($link, 'url');
        if ($validUrl($url)) {
            $originalLabels[strtolower(rtrim($url, '/'))] = $string($link, 'title');
            if (!$overrideLinks) {
                $appendLink($url, $string($link, 'title'), 'Official Links');
            }
        }
    }
    if ($overrideLinks) {
        foreach (array_slice(preg_split('/\\r\\n|\\r|\\n/', $related) ?: [], 0, 8) as $url) {
            $url = trim($url);
            if (!$validUrl($url)) {
                continue;
            }
            $originalTitle = $originalLabels[strtolower(rtrim($url, '/'))] ?? '';
            $title = $originalTitle !== '' ? $originalTitle : (string) parse_url($url, PHP_URL_HOST);
            $appendLink($url, $title, $originalTitle !== '' ? 'Official Links' : 'Related Link');
        }
    }

    return [
        'items' => $items,
        'links' => $links,
    ];
}
