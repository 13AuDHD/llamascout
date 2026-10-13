<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb-schema.php';

/**
 * Cross-check observed RIDB JSON field paths against the fields the current
 * Llama Scout importer explicitly understands or intentionally preserves.
 *
 * Unknown observed paths are never discarded. They are reported for review.
 */

function llama_ridb_coverage_scopes(): array
{
    return [
        'facility' =>
            'Facilities',

        'campsite' =>
            'Campsites',

        'address' =>
            'Facility addresses',

        'media' =>
            'Media',

        'link' =>
            'Links',

        'activity' =>
            'Activities',
    ];
}

function llama_ridb_coverage_rules(): array
{
    return [
        'facility' => [
            'FacilityID' => [
                'status' => 'imported',
                'target' => 'RIDB source identity',
            ],
            'FacilityName' => [
                'status' => 'imported',
                'target' => 'Place name / source facts',
            ],
            'FacilityTypeDescription' => [
                'status' => 'imported',
                'target' => 'Facility/source metadata',
            ],
            'FacilityDescription' => [
                'status' => 'imported',
                'target' => 'Place description / source facts',
            ],
            'FacilityDirections' => [
                'status' => 'preserved',
                'target' => 'Canonical RIDB source-attributed detail',
            ],
            'FacilityLatitude' => [
                'status' => 'imported',
                'target' => 'Place coordinates',
            ],
            'FacilityLongitude' => [
                'status' => 'imported',
                'target' => 'Place coordinates',
            ],
            'FacilityPhone' => [
                'status' => 'imported',
                'target' => 'place_facility_facts',
            ],
            'FacilityEmail' => [
                'status' => 'imported',
                'target' => 'place_facility_facts',
            ],
            'FacilityReservationURL' => [
                'status' => 'imported',
                'target' => 'place_facility_facts',
            ],
            'FacilityUseFeeDescription' => [
                'status' => 'imported',
                'target' => 'place_facility_facts',
            ],
            'Reservable' => [
                'status' => 'preserved',
                'target' => 'RIDB source record / catalog',
            ],
            'Enabled' => [
                'status' => 'preserved',
                'target' => 'RIDB source record / catalog',
            ],
            'ParentRecAreaID' => [
                'status' => 'preserved',
                'target' => 'RIDB source relationship',
            ],
            'LastUpdatedDate' => [
                'status' => 'imported',
                'target' => 'Source update timestamp',
            ],
            'GEOJSON' => [
                'status' => 'preserved',
                'target' => 'RIDB source geometry',
            ],
        ],

        'campsite' => [
            'CampsiteID' => [
                'status' => 'imported',
                'target' => 'place_campsites source identity',
            ],
            'FacilityID' => [
                'status' => 'imported',
                'target' => 'Parent Place relationship',
            ],
            'CampsiteName' => [
                'status' => 'imported',
                'target' => 'place_campsites',
            ],
            'CampsiteType' => [
                'status' => 'imported',
                'target' => 'place_campsites',
            ],
            'CampsiteTypeDescription' => [
                'status' => 'imported',
                'target' => 'place_campsites',
            ],
            'CampsiteAccessible' => [
                'status' => 'imported',
                'target' => 'place_campsites',
            ],
            'CampsiteLatitude' => [
                'status' => 'imported',
                'target' => 'place_campsites',
            ],
            'CampsiteLongitude' => [
                'status' => 'imported',
                'target' => 'place_campsites',
            ],
            'CampsiteReservable' => [
                'status' => 'imported',
                'target' => 'Source-scoped campsite feature',
            ],
            'Loop' => [
                'status' => 'imported',
                'target' => 'Source-scoped campsite feature',
            ],
            'TypeOfUse' => [
                'status' => 'imported',
                'target' => 'Source-scoped campsite feature',
            ],
            'CreatedDate' => [
                'status' => 'imported',
                'target' => 'Source-scoped campsite feature',
            ],
            'LastUpdatedDate' => [
                'status' => 'imported',
                'target' => 'Source-scoped campsite feature / provenance',
            ],
            'ATTRIBUTES' => [
                'status' => 'imported',
                'target' => 'Campsite facts/features',
            ],
            'ATTRIBUTES[]' => [
                'status' => 'imported',
                'target' => 'Campsite facts/features',
            ],
            'ATTRIBUTES[].AttributeID' => [
                'status' => 'preserved',
                'target' => 'RIDB attribute provenance',
            ],
            'ATTRIBUTES[].AttributeName' => [
                'status' => 'imported',
                'target' => 'Normalization mapping',
            ],
            'ATTRIBUTES[].AttributeValue' => [
                'status' => 'imported',
                'target' => 'Campsite facts/features',
            ],
            'PERMITTEDEQUIPMENT' => [
                'status' => 'imported',
                'target' => 'Permitted equipment',
            ],
            'PERMITTEDEQUIPMENT[]' => [
                'status' => 'imported',
                'target' => 'Permitted equipment',
            ],
            'PERMITTEDEQUIPMENT[].EquipmentName' => [
                'status' => 'imported',
                'target' => 'Permitted equipment',
            ],
            'PERMITTEDEQUIPMENT[].MaxLength' => [
                'status' => 'imported',
                'target' => 'Permitted equipment maximum length',
            ],
            'ENTITYMEDIA' => [
                'status' => 'imported',
                'target' => 'place_campsite_images',
            ],
            'ENTITYMEDIA[]' => [
                'status' => 'imported',
                'target' => 'place_campsite_images',
            ],
        ],

        'address' => [
            'FacilityAddressID' => [
                'status' => 'preserved',
                'target' => 'RIDB address provenance',
            ],
            'FacilityID' => [
                'status' => 'preserved',
                'target' => 'Facility relationship',
            ],
            'FacilityStreetAddress1' => [
                'status' => 'imported',
                'target' => 'Official facility address',
            ],
            'FacilityStreetAddress2' => [
                'status' => 'imported',
                'target' => 'Official facility address',
            ],
            'City' => [
                'status' => 'imported',
                'target' => 'Official facility address',
            ],
            'AddressStateCode' => [
                'status' => 'imported',
                'target' => 'Official facility address',
            ],
            'PostalCode' => [
                'status' => 'imported',
                'target' => 'Official facility address',
            ],
            'AddressCountryCode' => [
                'status' => 'preserved',
                'target' => 'RIDB address source',
            ],
        ],

        'media' => [
            'EntityMediaID' => [
                'status' => 'imported',
                'target' => 'Media provenance',
            ],
            'MediaID' => [
                'status' => 'imported',
                'target' => 'Media provenance',
            ],
            'EntityID' => [
                'status' => 'imported',
                'target' => 'Place/campsite relationship',
            ],
            'EntityType' => [
                'status' => 'imported',
                'target' => 'Place/campsite relationship',
            ],
            'MediaType' => [
                'status' => 'imported',
                'target' => 'Image filtering / provenance',
            ],
            'Title' => [
                'status' => 'imported',
                'target' => 'Image alt/title metadata',
            ],
            'Subtitle' => [
                'status' => 'preserved',
                'target' => 'RIDB source media record',
            ],
            'Description' => [
                'status' => 'imported',
                'target' => 'Image caption where supported',
            ],
            'URL' => [
                'status' => 'imported',
                'target' => 'Place/site gallery source',
            ],
            'Credits' => [
                'status' => 'imported',
                'target' => 'Site image credit / source',
            ],
            'EmbedCode' => [
                'status' => 'preserved',
                'target' => 'RIDB source media record',
            ],
            'Height' => [
                'status' => 'preserved',
                'target' => 'RIDB source media record',
            ],
            'Width' => [
                'status' => 'preserved',
                'target' => 'RIDB source media record',
            ],
        ],

        'link' => [
            'LinkID' => [
                'status' => 'imported',
                'target' => 'Official link provenance',
            ],
            'EntityID' => [
                'status' => 'preserved',
                'target' => 'RIDB relationship',
            ],
            'EntityType' => [
                'status' => 'preserved',
                'target' => 'RIDB relationship',
            ],
            'Title' => [
                'status' => 'imported',
                'target' => 'Official link title',
            ],
            'LinkTitle' => [
                'status' => 'imported',
                'target' => 'Official link title',
            ],
            'LinkType' => [
                'status' => 'imported',
                'target' => 'Official link title/type',
            ],
            'URL' => [
                'status' => 'imported',
                'target' => 'Official facility links',
            ],
        ],

        'activity' => [
            'ActivityID' => [
                'status' => 'imported',
                'target' => 'Activity provenance',
            ],
            'ActivityName' => [
                'status' => 'imported',
                'target' => 'Place facility feature',
            ],
            'EntityID' => [
                'status' => 'preserved',
                'target' => 'RIDB relationship',
            ],
            'EntityType' => [
                'status' => 'preserved',
                'target' => 'RIDB relationship',
            ],
        ],
    ];
}

function llama_ridb_coverage_match(
    string $scope,
    string $path
): array {
    $rules =
        llama_ridb_coverage_rules();

    $scopeRules =
        $rules[$scope]
        ?? [];

    if (isset($scopeRules[$path])) {
        return $scopeRules[$path];
    }

    /*
     * For campsite embedded media, retain every child path as source-backed
     * even when only a subset is promoted into gallery columns today.
     */
    if (
        $scope === 'campsite'
        && str_starts_with(
            $path,
            'ENTITYMEDIA[].'
        )
    ) {
        return [
            'status' => 'preserved',
            'target' => 'Lossless campsite media source record',
        ];
    }

    /*
     * Unknown campsite attribute object fields stay losslessly preserved even
     * if the importer does not yet have a canonical target for that field.
     */
    if (
        $scope === 'campsite'
        && str_starts_with(
            $path,
            'ATTRIBUTES[].'
        )
    ) {
        return [
            'status' => 'preserved',
            'target' => 'Lossless campsite attribute source record',
        ];
    }

    if (
        $scope === 'campsite'
        && str_starts_with(
            $path,
            'PERMITTEDEQUIPMENT[].'
        )
    ) {
        return [
            'status' => 'preserved',
            'target' => 'Lossless permitted-equipment source record',
        ];
    }

    return [
        'status' => 'review',
        'target' => 'No explicit production mapping registered yet',
    ];
}

function llama_ridb_coverage_report(
    PDO $ridbDb,
    string $scope
): array {
    $scopes =
        llama_ridb_coverage_scopes();

    if (!isset($scopes[$scope])) {
        $scope = 'facility';
    }

    $dataset =
        llama_ridb_schema_dataset(
            $ridbDb,
            $scope
        );

    $rows = [];
    $counts = [
        'imported' => 0,
        'preserved' => 0,
        'review' => 0,
    ];

    foreach (
        (array) (
            $dataset['fields']
            ?? []
        )
        as $field
    ) {
        $path =
            (string) (
                $field['path']
                ?? ''
            );

        $coverage =
            llama_ridb_coverage_match(
                $scope,
                $path
            );

        $status =
            (string) (
                $coverage['status']
                ?? 'review'
            );

        if (!isset($counts[$status])) {
            $status = 'review';
        }

        $counts[$status]++;

        $rows[] =
            array_merge(
                $field,
                [
                    'coverage_status' =>
                        $status,
                    'target' =>
                        (string) (
                            $coverage['target']
                            ?? ''
                        ),
                ]
            );
    }

    usort(
        $rows,
        static function (
            array $a,
            array $b
        ): int {
            $rank = [
                'review' => 0,
                'imported' => 1,
                'preserved' => 2,
            ];

            $aRank =
                $rank[
                    $a['coverage_status']
                    ?? 'review'
                ]
                ?? 9;

            $bRank =
                $rank[
                    $b['coverage_status']
                    ?? 'review'
                ]
                ?? 9;

            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            return strnatcasecmp(
                (string) (
                    $a['path']
                    ?? ''
                ),
                (string) (
                    $b['path']
                    ?? ''
                )
            );
        }
    );

    return [
        'scope' =>
            $scope,
        'label' =>
            $scopes[$scope],
        'record_count' =>
            (int) (
                $dataset['record_count']
                ?? 0
            ),
        'field_count' =>
            (int) (
                $dataset['field_count']
                ?? 0
            ),
        'imported_count' =>
            $counts['imported'],
        'preserved_count' =>
            $counts['preserved'],
        'review_count' =>
            $counts['review'],
        'rows' =>
            $rows,
        'scopes' =>
            $scopes,
    ];
}

function llama_ridb_coverage_status_label(
    string $status
): string {
    return match ($status) {
        'imported' =>
            'Mapped into Llama Scout',

        'preserved' =>
            'Preserved / source-backed',

        default =>
            'Needs review',
    };
}
