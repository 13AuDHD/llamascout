<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';
require_once __DIR__ . '/place-campsites.php';

/**
 * Import facility-level official links without disturbing existing
 * RIDB activities or any Scout-owned data.
 */
function llama_ridb_import_facility_links(
    PDO $mainDb,
    PDO $ridbDb,
    int $placeId,
    string $facilityId
): int {
    if ($placeId < 1 || trim($facilityId) === '') {
        return 0;
    }

    $response =
        llama_ridb_facility_links(
            $facilityId
        );

    $links =
        $response['records']
        ?? [];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_links',
        $facilityId,
        $links,
        'LinkID'
    );

    $delete =
        $mainDb->prepare(
            'DELETE FROM place_facility_features
             WHERE place_id = ?
               AND source_provider = ?
               AND feature_key = ?'
        );

    $delete->execute([
        $placeId,
        'Recreation.gov / RIDB',
        'source_link',
    ]);

    if (!$links) {
        return 0;
    }

    $insert =
        $mainDb->prepare(
            'INSERT INTO place_facility_features
            (
                place_id,
                feature_key,
                feature_value,
                qualifier,
                source_provider,
                source_external_id,
                sort_order,
                is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );

    $count = 0;
    $sort = 1000;

    foreach ($links as $index => $link) {
        if (!is_array($link)) {
            continue;
        }

        $url =
            trim(
                (string) llama_ridb_record_value(
                    $link,
                    [
                        'URL',
                        'Url',
                        'LinkURL',
                        'linkURL',
                        'url',
                    ],
                    ''
                )
            );

        if (
            $url === ''
            || preg_match(
                '#^https?://#i',
                $url
            ) !== 1
        ) {
            continue;
        }

        $title =
            trim(
                (string) llama_ridb_record_value(
                    $link,
                    [
                        'Title',
                        'LinkTitle',
                        'LinkType',
                        'linkType',
                        'title',
                    ],
                    ''
                )
            );

        $externalId =
            trim(
                (string) llama_ridb_record_value(
                    $link,
                    [
                        'LinkID',
                        'linkID',
                        'id',
                    ],
                    ''
                )
            );

        if ($externalId === '') {
            $externalId =
                $facilityId
                . ':link:'
                . ($index + 1);
        }

        $insert->execute([
            $placeId,
            'source_link',
            $url,
            $title !== ''
                ? $title
                : null,
            'Recreation.gov / RIDB',
            $externalId,
            $sort,
        ]);

        $sort += 10;
        $count++;
    }

    return $count;
}

/**
 * Build the public facility-level official information bundle.
 */
function llama_place_facility_public_data(
    PDO $db,
    int $placeId
): array {
    if ($placeId < 1) {
        return [];
    }

    $facts =
        llama_place_facility_facts(
            $db,
            $placeId
        );

    $features =
        llama_place_facility_features(
            $db,
            $placeId
        );

    $activities = [];
    $links = [];

    foreach ($features as $feature) {
        $key =
            trim(
                (string) (
                    $feature['feature_key']
                    ?? ''
                )
            );

        if ($key === 'activity') {
            $value =
                trim(
                    (string) (
                        $feature['feature_value']
                        ?? ''
                    )
                );

            if ($value !== '') {
                $activities[] =
                    $value;
            }

            continue;
        }

        if ($key !== 'source_link') {
            continue;
        }

        $url =
            trim(
                (string) (
                    $feature['feature_value']
                    ?? ''
                )
            );

        if (
            $url === ''
            || preg_match(
                '#^https?://#i',
                $url
            ) !== 1
        ) {
            continue;
        }

        $title =
            trim(
                (string) (
                    $feature['qualifier']
                    ?? ''
                )
            );

        $links[] = [
            'url' =>
                $url,
            'title' =>
                $title !== ''
                    ? $title
                    : 'Official link',
        ];
    }

    $activities =
        array_values(
            array_unique(
                $activities
            )
        );

    $seenLinks = [];
    $uniqueLinks = [];

    foreach ($links as $link) {
        $key =
            strtolower(
                $link['url']
            );

        if (isset($seenLinks[$key])) {
            continue;
        }

        $seenLinks[$key] =
            true;

        $uniqueLinks[] =
            $link;
    }

    $addressParts =
        array_values(
            array_filter(
                [
                    trim(
                        (string) (
                            $facts['official_address_1']
                            ?? ''
                        )
                    ),
                    trim(
                        (string) (
                            $facts['official_address_2']
                            ?? ''
                        )
                    ),
                    trim(
                        implode(
                            ', ',
                            array_filter(
                                [
                                    trim(
                                        (string) (
                                            $facts['official_city']
                                            ?? ''
                                        )
                                    ),
                                    trim(
                                        (string) (
                                            $facts['official_state']
                                            ?? ''
                                        )
                                    ),
                                ],
                                static fn (
                                    string $value
                                ): bool =>
                                    $value !== ''
                            )
                        )
                    ),
                    trim(
                        (string) (
                            $facts['official_postal_code']
                            ?? ''
                        )
                    ),
                ],
                static fn (
                    string $value
                ): bool =>
                    $value !== ''
            )
        );

    return [
        'address' =>
            implode(
                ' Â· ',
                $addressParts
            ),
        'phone' =>
            trim(
                (string) (
                    $facts['official_phone']
                    ?? ''
                )
            ),
        'email' =>
            trim(
                (string) (
                    $facts['official_email']
                    ?? ''
                )
            ),
        'reservation_url' =>
            trim(
                (string) (
                    $facts['reservation_url']
                    ?? ''
                )
            ),
        'fee_description' =>
            trim(
                (string) (
                    $facts['fee_description']
                    ?? ''
                )
            ),
        'source_provider' =>
            trim(
                (string) (
                    $facts['source_provider']
                    ?? ''
                )
            ),
        'source_external_id' =>
            trim(
                (string) (
                    $facts['source_external_id']
                    ?? ''
                )
            ),
        'source_updated_at' =>
            trim(
                (string) (
                    $facts['source_updated_at']
                    ?? ''
                )
            ),
        'activities' =>
            $activities,
        'links' =>
            $uniqueLinks,
    ];
}
