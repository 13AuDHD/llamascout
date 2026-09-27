<?php

declare(strict_types=1);

require_once __DIR__ . '/cell-coverage-import.php';


/* =========================================================
   LLAMA SCOUT
   FCC CELL COVERAGE AUTOMATIC SYNC
   ========================================================= */

const LLAMA_FCC_PUBLIC_API_BASE =
    'https://bdc.fcc.gov/api/public/map';

const LLAMA_FCC_DOWNLOAD_FORMAT_GEOPACKAGE = 2;

const LLAMA_FCC_AUTO_IMPORT_BATCH = 10000;


/* =========================================================
   CONFIGURATION
   ========================================================= */

function llama_fcc_sync_config(): array
{
    $config =
        llama_config();

    $fcc =
        $config['fcc_bdc']
        ?? [];

    if (!is_array($fcc)) {
        $fcc = [];
    }

    return [
        'username' =>
            trim(
                (string) (
                    $fcc['username']
                    ?? ''
                )
            ),

        'hash_value' =>
            trim(
                (string) (
                    $fcc['hash_value']
                    ?? ''
                )
            ),

        'api_base' =>
            rtrim(
                trim(
                    (string) (
                        $fcc['api_base']
                        ?? LLAMA_FCC_PUBLIC_API_BASE
                    )
                ),
                '/'
            ),
    ];
}


function llama_fcc_sync_is_configured(): bool
{
    $config =
        llama_fcc_sync_config();

    return
        $config['username'] !== ''
        && $config['hash_value'] !== '';
}


/* =========================================================
   STATES + PROVIDERS
   ========================================================= */

function llama_fcc_sync_states(): array
{
    return [
        '01' => 'Alabama',
        '02' => 'Alaska',
        '04' => 'Arizona',
        '05' => 'Arkansas',
        '06' => 'California',
        '08' => 'Colorado',
        '09' => 'Connecticut',
        '10' => 'Delaware',
        '11' => 'District of Columbia',
        '12' => 'Florida',
        '13' => 'Georgia',
        '15' => 'Hawaii',
        '16' => 'Idaho',
        '17' => 'Illinois',
        '18' => 'Indiana',
        '19' => 'Iowa',
        '20' => 'Kansas',
        '21' => 'Kentucky',
        '22' => 'Louisiana',
        '23' => 'Maine',
        '24' => 'Maryland',
        '25' => 'Massachusetts',
        '26' => 'Michigan',
        '27' => 'Minnesota',
        '28' => 'Mississippi',
        '29' => 'Missouri',
        '30' => 'Montana',
        '31' => 'Nebraska',
        '32' => 'Nevada',
        '33' => 'New Hampshire',
        '34' => 'New Jersey',
        '35' => 'New Mexico',
        '36' => 'New York',
        '37' => 'North Carolina',
        '38' => 'North Dakota',
        '39' => 'Ohio',
        '40' => 'Oklahoma',
        '41' => 'Oregon',
        '42' => 'Pennsylvania',
        '44' => 'Rhode Island',
        '45' => 'South Carolina',
        '46' => 'South Dakota',
        '47' => 'Tennessee',
        '48' => 'Texas',
        '49' => 'Utah',
        '50' => 'Vermont',
        '51' => 'Virginia',
        '53' => 'Washington',
        '54' => 'West Virginia',
        '55' => 'Wisconsin',
        '56' => 'Wyoming',
    ];
}


function llama_fcc_sync_state_abbreviations(): array
{
    return [
        'AL' => '01',
        'AK' => '02',
        'AZ' => '04',
        'AR' => '05',
        'CA' => '06',
        'CO' => '08',
        'CT' => '09',
        'DE' => '10',
        'DC' => '11',
        'FL' => '12',
        'GA' => '13',
        'HI' => '15',
        'ID' => '16',
        'IL' => '17',
        'IN' => '18',
        'IA' => '19',
        'KS' => '20',
        'KY' => '21',
        'LA' => '22',
        'ME' => '23',
        'MD' => '24',
        'MA' => '25',
        'MI' => '26',
        'MN' => '27',
        'MS' => '28',
        'MO' => '29',
        'MT' => '30',
        'NE' => '31',
        'NV' => '32',
        'NH' => '33',
        'NJ' => '34',
        'NM' => '35',
        'NY' => '36',
        'NC' => '37',
        'ND' => '38',
        'OH' => '39',
        'OK' => '40',
        'OR' => '41',
        'PA' => '42',
        'RI' => '44',
        'SC' => '45',
        'SD' => '46',
        'TN' => '47',
        'TX' => '48',
        'UT' => '49',
        'VT' => '50',
        'VA' => '51',
        'WA' => '53',
        'WV' => '54',
        'WI' => '55',
        'WY' => '56',
    ];
}


function llama_fcc_sync_state_fips_from_row(
    array $row
): string {
    $direct =
        llama_fcc_sync_normalize_fips(
            llama_fcc_sync_row_value(
                $row,
                [
                    'state_fips',
                    'state_code',
                ]
            )
        );

    if ($direct !== '') {
        return $direct;
    }

    $abbr =
        strtoupper(
            trim(
                (string) (
                    llama_fcc_sync_row_value(
                        $row,
                        [
                            'state_abbr',
                            'state_usps',
                        ]
                    )
                    ?? ''
                )
            )
        );

    $byAbbr =
        llama_fcc_sync_state_abbreviations();

    if (
        $abbr !== ''
        && isset($byAbbr[$abbr])
    ) {
        return $byAbbr[$abbr];
    }

    $name =
        strtolower(
            trim(
                (string) (
                    llama_fcc_sync_row_value(
                        $row,
                        [
                            'state_name',
                        ]
                    )
                    ?? ''
                )
            )
        );

    if ($name !== '') {
        foreach (
            llama_fcc_sync_states()
            as $fips => $stateName
        ) {
            if (
                strtolower($stateName)
                === $name
            ) {
                return $fips;
            }
        }
    }

    return '';
}


function llama_fcc_sync_providers(): array
{
    return [
        130403 => [
            'key' => 'tmobile',
            'label' => 'T-Mobile',
        ],

        131425 => [
            'key' => 'verizon',
            'label' => 'Verizon',
        ],

        130077 => [
            'key' => 'att',
            'label' => 'AT&T',
        ],
    ];
}


/* =========================================================
   PRIVATE WORKING DIRECTORY + STATE FILE
   ========================================================= */

function llama_fcc_sync_directory(): string
{
    return llama_cell_import_directory();
}


function llama_fcc_sync_state_path(): string
{
    return
        llama_fcc_sync_directory()
        . '/fcc-cell-sync-state.json';
}


function llama_fcc_sync_load_state(): ?array
{
    $path =
        llama_fcc_sync_state_path();

    if (!is_file($path)) {
        return null;
    }

    $raw =
        file_get_contents($path);

    if (
        $raw === false
        || trim($raw) === ''
    ) {
        return null;
    }

    $state =
        json_decode(
            $raw,
            true
        );

    return is_array($state)
        ? $state
        : null;
}


function llama_fcc_sync_save_state(
    array $state
): void {
    $state['updated_at'] =
        gmdate('c');

    $path =
        llama_fcc_sync_state_path();

    $temp =
        $path . '.tmp';

    $json =
        json_encode(
            $state,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

    if ($json === false) {
        throw new RuntimeException(
            'The FCC sync state could not be encoded.'
        );
    }

    if (
        file_put_contents(
            $temp,
            $json,
            LOCK_EX
        ) === false
    ) {
        throw new RuntimeException(
            'The FCC sync state could not be written.'
        );
    }

    if (!rename($temp, $path)) {
        @unlink($temp);

        throw new RuntimeException(
            'The FCC sync state could not be finalized.'
        );
    }
}


function llama_fcc_sync_public_state(
    ?array $state = null
): array {
    $state ??=
        llama_fcc_sync_load_state();

    if (!$state) {
        return [
            'exists' => false,
            'status' => 'idle',
        ];
    }

    $queue =
        is_array(
            $state['queue']
            ?? null
        )
            ? $state['queue']
            : [];

    $index =
        max(
            0,
            (int) (
                $state['index']
                ?? 0
            )
        );

    $total =
        count($queue);

    $current =
        $queue[$index]
        ?? null;

    return [
        'exists' => true,
        'status' =>
            (string) (
                $state['status']
                ?? 'idle'
            ),

        'as_of_date' =>
            $state['as_of_date']
            ?? null,

        'created_at' =>
            $state['created_at']
            ?? null,

        'updated_at' =>
            $state['updated_at']
            ?? null,

        'last_check_at' =>
            $state['last_check_at']
            ?? null,

        'total' =>
            $total,

        'completed' =>
            min(
                $index,
                $total
            ),

        'remaining' =>
            max(
                0,
                $total - $index
            ),

        'phase' =>
            $state['phase']
            ?? null,

        'offset' =>
            (int) (
                $state['offset']
                ?? 0
            ),

        'current_total_rows' =>
            (int) (
                $state[
                    'current_total_rows'
                ]
                ?? 0
            ),

        'current_imported_rows' =>
            (int) (
                $state[
                    'current_imported_rows'
                ]
                ?? 0
            ),

        'current' =>
            is_array($current)
                ? [
                    'state_fips' =>
                        $current[
                            'state_fips'
                        ]
                        ?? null,

                    'state_name' =>
                        $current[
                            'state_name'
                        ]
                        ?? null,

                    'provider_key' =>
                        $current[
                            'provider_key'
                        ]
                        ?? null,

                    'provider_label' =>
                        $current[
                            'provider_label'
                        ]
                        ?? null,

                    'technology' =>
                        $current[
                            'technology'
                        ]
                        ?? null,

                    'file_name' =>
                        $current[
                            'file_name'
                        ]
                        ?? null,
                ]
                : null,

        'message' =>
            $state['message']
            ?? null,

        'error' =>
            $state['error']
            ?? null,

        'manifest_found' =>
            (int) (
                $state['manifest_found']
                ?? 0
            ),

        'manifest_missing' =>
            (int) (
                $state['manifest_missing']
                ?? 0
            ),

        'already_current' =>
            (int) (
                $state['already_current']
                ?? 0
            ),
    ];
}


/* =========================================================
   FCC HTTP
   ========================================================= */

function llama_fcc_sync_curl_base(
    string $url
): CurlHandle {
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL is required for automatic FCC downloads.'
        );
    }

    $config =
        llama_fcc_sync_config();

    if (
        $config['username'] === ''
        || $config['hash_value'] === ''
    ) {
        throw new RuntimeException(
            'FCC API credentials have not been configured.'
        );
    }

    $curl =
        curl_init($url);

    if (!$curl) {
        throw new RuntimeException(
            'The FCC API connection could not be initialized.'
        );
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                20,

            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'username: '
                    . $config['username'],
                'hash_value: '
                    . $config['hash_value'],
            ],

            /*
             * A clear user agent avoids generic HTTP clients
             * being filtered by the public FCC service.
             */
            CURLOPT_USERAGENT =>
                'LlamaScout/1.0 (+https://llamascout.com)',
        ]
    );

    return $curl;
}


function llama_fcc_sync_json_request(
    string $path
): array {
    $config =
        llama_fcc_sync_config();

    $url =
        $config['api_base']
        . '/'
        . ltrim($path, '/');

    $curl =
        llama_fcc_sync_curl_base(
            $url
        );

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_TIMEOUT =>
                120,
        ]
    );

    $body =
        curl_exec($curl);

    $status =
        (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

    $curlError =
        curl_error($curl);

    curl_close($curl);

    if (
        $body === false
        || $status < 200
        || $status >= 300
    ) {
        throw new RuntimeException(
            'FCC API request failed'
            . ($status > 0
                ? ' with HTTP '
                    . $status
                : '')
            . ($curlError !== ''
                ? ': '
                    . $curlError
                : '.')
        );
    }

    $decoded =
        json_decode(
            (string) $body,
            true
        );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'The FCC API returned invalid JSON.'
        );
    }

    if (
        isset($decoded['status'])
        && strtolower(
            (string) $decoded['status']
        ) === 'fail'
    ) {
        throw new RuntimeException(
            trim(
                (string) (
                    $decoded['message']
                    ?? 'FCC API request failed.'
                )
            )
        );
    }

    return $decoded;
}


/* =========================================================
   FCC CATALOG DISCOVERY
   ========================================================= */

function llama_fcc_sync_latest_date(): string
{
    $response =
        llama_fcc_sync_json_request(
            'listAsOfDates'
        );

    $rows =
        is_array(
            $response['data']
            ?? null
        )
            ? $response['data']
            : [];

    $dates = [];

    foreach ($rows as $row) {
        if (is_string($row)) {
            $candidate =
                trim($row);
        } elseif (is_array($row)) {
            $candidate =
                trim(
                    (string) (
                        $row['as_of_date']
                        ?? $row['date']
                        ?? ''
                    )
                );
        } else {
            continue;
        }

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}/',
                $candidate,
                $match
            )
        ) {
            $dates[] =
                $match[0];
        }
    }

    if (!$dates) {
        throw new RuntimeException(
            'The FCC API did not return an availability data date.'
        );
    }

    rsort(
        $dates,
        SORT_STRING
    );

    return $dates[0];
}

function llama_fcc_sync_manifest(
    string $asOfDate
): array {
    $response =
        llama_fcc_sync_json_request(
            'downloads/listAvailabilityData/'
            . rawurlencode($asOfDate)
    );

    return is_array(
        $response['data']
        ?? null
    )
        ? $response['data']
        : [];
}


function llama_fcc_sync_row_value(
    array $row,
    array $keys
): mixed {
    foreach ($keys as $key) {
        if (
            array_key_exists(
                $key,
                $row
            )
        ) {
            return $row[$key];
        }
    }

    return null;
}


function llama_fcc_sync_normalize_fips(
    mixed $value
): string {
    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return '';
    }

    if (ctype_digit($value)) {
        return str_pad(
            $value,
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    return '';
}


function llama_fcc_sync_manifest_candidate(
    array $row,
    string $asOfDate
): ?array {
    $providers =
        llama_fcc_sync_providers();

    $states =
        llama_fcc_sync_states();

    $providerId =
        (int) (
            llama_fcc_sync_row_value(
                $row,
                [
                    'provider_id',
                    'providerid',
                    'providerId',
                ]
            )
            ?? 0
        );

    $providerName =
        strtolower(
            trim(
                (string) (
                    llama_fcc_sync_row_value(
                        $row,
                        [
                            'provider_name',
                            'brand_name',
                            'brandname',
                        ]
                    )
                    ?? ''
                )
            )
        );

    $provider = null;

    if (isset($providers[$providerId])) {
        $provider =
            $providers[$providerId];
    } elseif (
        str_contains(
            $providerName,
            't-mobile'
        )
        || str_contains(
            $providerName,
            'tmobile'
        )
    ) {
        $provider = [
            'key' => 'tmobile',
            'label' => 'T-Mobile',
        ];
    } elseif (
        str_contains(
            $providerName,
            'verizon'
        )
    ) {
        $provider = [
            'key' => 'verizon',
            'label' => 'Verizon',
        ];
    } elseif (
        str_contains(
            $providerName,
            'at&t'
        )
        || str_contains(
            $providerName,
            'att mobility'
        )
    ) {
        $provider = [
            'key' => 'att',
            'label' => 'AT&T',
        ];
    }

    if (!$provider) {
        return null;
    }

    $stateFips =
        llama_fcc_sync_state_fips_from_row(
            $row
        );

    if (
        $stateFips === ''
        || !isset($states[$stateFips])
    ) {
        return null;
    }

    $fileId =
        trim(
            (string) (
                llama_fcc_sync_row_value(
                    $row,
                    [
                        'file_id',
                        'fileId',
                        'id',
                    ]
                )
                ?? ''
            )
        );

    if ($fileId === '') {
        return null;
    }

    $fileName =
        trim(
            (string) (
                llama_fcc_sync_row_value(
                    $row,
                    [
                        'file_name',
                        'filename',
                        'name',
                    ]
                )
                ?? ''
            )
        );

    $technologyText =
        strtolower(
            trim(
                (string) (
                    llama_fcc_sync_row_value(
                        $row,
                        [
                            'technology_type',
                            'technology_name',
                            'technology',
                            'technology_code_desc',
                        ]
                    )
                    ?? ''
                )
            )
        );

    $searchText =
        strtolower(
            implode(
                ' ',
                array_filter(
                    [
                        $fileName,
                        $technologyText,
                        (string) (
                            $row['category']
                            ?? ''
                        ),
                        (string) (
                            $row['subcategory']
                            ?? ''
                        ),
                        (string) (
                            $row['speed_tier']
                            ?? ''
                        ),
                        (string) (
                            $row['speed_tier_desc']
                            ?? ''
                        ),
                    ],
                    static fn(string $value): bool =>
                        trim($value) !== ''
                )
            )
        );

    /*
     * Provider manifests describe mobile products differently
     * across FCC vintages. Accept both explicit mobile wording
     * and the known mobile technology names.
     */
    $looksMobile =
        str_contains(
            $searchText,
            'mobile'
        )
        || str_contains(
            $searchText,
            '4g'
        )
        || str_contains(
            $searchText,
            'lte'
        )
        || str_contains(
            $searchText,
            '5g'
        );

    if (!$looksMobile) {
        return null;
    }

    $technologyCode = 0;

    $numericTechnology =
        llama_fcc_sync_row_value(
            $row,
            [
                'technology_code',
                'technologyCode',
            ]
        );

    if (is_numeric($numericTechnology)) {
        $numericTechnology =
            (int) $numericTechnology;

        if (
            $numericTechnology === 400
            || $numericTechnology === 500
        ) {
            $technologyCode =
                $numericTechnology;
        }
    }

    if ($technologyCode === 0) {
        if (
            str_contains(
                $searchText,
                '5g'
            )
        ) {
            $technologyCode = 500;
        } elseif (
            str_contains(
                $searchText,
                '4g'
            )
            || str_contains(
                $searchText,
                'lte'
            )
        ) {
            $technologyCode = 400;
        }
    }

    if ($technologyCode === 0) {
        return null;
    }

    /*
     * For 5G, Llama Scout wants the broader 7/1 product only.
     * Do not queue the separate 35/3 fast-5G product.
     */
    if (
        $technologyCode === 500
        && (
            str_contains(
                $searchText,
                '35/3'
            )
            || str_contains(
                $searchText,
                '35_3'
            )
            || str_contains(
                $searchText,
                '35-3'
            )
        )
    ) {
        return null;
    }

    $score = 100;

    if (
        $technologyCode === 500
        && (
            str_contains(
                $searchText,
                '7/1'
            )
            || str_contains(
                $searchText,
                '7_1'
            )
            || str_contains(
                $searchText,
                '7-1'
            )
        )
    ) {
        $score += 100;
    }

    /*
     * The manifest file ID identifies the coverage dataset.
     * The download endpoint's file_type=2 chooses GeoPackage.
     * Prefer entries explicitly labeled hexagon/H3 when the
     * FCC manifest exposes both raw and hex products.
     */
    $looksRaw =
        str_contains(
            $searchText,
            'raw coverage'
        );

    $looksHex =
        str_contains(
            $searchText,
            'hexagon'
        )
        || str_contains(
            $searchText,
            'h3'
        );

    if ($looksRaw && !$looksHex) {
        return null;
    }

    if ($looksHex) {
        $score += 40;
    }

    return [
        'file_id' =>
            $fileId,

        'file_name' =>
            $fileName !== ''
                ? $fileName
                : (
                    'fcc_'
                    . $stateFips
                    . '_'
                    . $provider['key']
                    . '_'
                    . $technologyCode
                ),

        'state_fips' =>
            $stateFips,

        'state_name' =>
            $states[$stateFips],

        'provider_id' =>
            $providerId,

        'provider_key' =>
            $provider['key'],

        'provider_label' =>
            $provider['label'],

        'technology_code' =>
            $technologyCode,

        'technology' =>
            $technologyCode === 500
                ? '5g'
                : '4g',

        'as_of_date' =>
            $asOfDate,

        'score' =>
            $score,
    ];
}

function llama_fcc_sync_select_catalog(
    array $manifest,
    string $asOfDate
): array {
    $best = [];

    foreach ($manifest as $row) {
        if (!is_array($row)) {
            continue;
        }

        $candidate =
            llama_fcc_sync_manifest_candidate(
                $row,
                $asOfDate
            );

        if (!$candidate) {
            continue;
        }

        $key =
            $candidate['state_fips']
            . ':'
            . $candidate['provider_key']
            . ':'
            . $candidate['technology'];

        if (
            !isset($best[$key])
            || (
                (int) $candidate['score']
                > (int) $best[$key]['score']
            )
        ) {
            $best[$key] =
                $candidate;
        }
    }

    foreach ($best as &$row) {
        unset($row['score']);
    }

    unset($row);

    ksort($best);

    return $best;
}


/* =========================================================
   LOCAL LEDGER COMPARISON
   ========================================================= */

function llama_fcc_sync_current_ledger(
    PDO $db
): array {
    $rows =
        $db
            ->query(
                'SELECT
                    state_fips,
                    provider_key,
                    technology,
                    fcc_as_of_date,
                    source_filename,
                    status
                 FROM cell_coverage_datasets
                 WHERE status = "current"'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

    $current = [];

    foreach ($rows as $row) {
        $key =
            trim(
                (string) (
                    $row['state_fips']
                    ?? ''
                )
            )
            . ':'
            . trim(
                (string) (
                    $row['provider_key']
                    ?? ''
                )
            )
            . ':'
            . trim(
                (string) (
                    $row['technology']
                    ?? ''
                )
            );

        $current[$key] =
            $row;
    }

    return $current;
}


function llama_fcc_sync_file_name_matches(
    string $installed,
    string $manifest
): bool {
    $installed =
        strtolower(
            pathinfo(
                trim($installed),
                PATHINFO_FILENAME
            )
        );

    $manifest =
        strtolower(
            pathinfo(
                trim($manifest),
                PATHINFO_FILENAME
            )
        );

    return
        $installed !== ''
        && $manifest !== ''
        && $installed === $manifest;
}


function llama_fcc_sync_create_plan(): array
{
    if (!llama_fcc_sync_is_configured()) {
        throw new RuntimeException(
            'FCC API credentials have not been configured.'
        );
    }

    $asOfDate =
        llama_fcc_sync_latest_date();

    $manifest =
        llama_fcc_sync_manifest(
            $asOfDate
        );

    $catalog =
        llama_fcc_sync_select_catalog(
            $manifest,
            $asOfDate
        );

    $current =
        llama_fcc_sync_current_ledger(
            cell_db()
        );

    $queue = [];
    $alreadyCurrent = 0;

    foreach (
        $catalog
        as $key => $dataset
    ) {
        $installed =
            $current[$key]
            ?? null;

        $needsSync = true;

        if (is_array($installed)) {
            $installedDate =
                trim(
                    (string) (
                        $installed[
                            'fcc_as_of_date'
                        ]
                        ?? ''
                    )
                );

            $installedFile =
                trim(
                    (string) (
                        $installed[
                            'source_filename'
                        ]
                        ?? ''
                    )
                );

            if (
                $installedDate > $asOfDate
            ) {
                $needsSync = false;
            } elseif (
                $installedDate === $asOfDate
            ) {
                /*
                 * Treat the installed filing vintage as current.
                 * FCC can refresh a filing-period snapshot later,
                 * and a future catalog-revision field can be used
                 * to detect that without forcing every same-date
                 * dataset to reinstall on each check.
                 */
                $needsSync = false;
            }
        }

        if ($needsSync) {
            $queue[] =
                $dataset;
        } else {
            $alreadyCurrent++;
        }
    }

    $expected =
        count(
            llama_fcc_sync_states()
        )
        * 3
        * 2;

    $state = [
        'version' => 1,

        'sync_id' =>
            bin2hex(
                random_bytes(8)
            ),

        'status' =>
            $queue
                ? 'running'
                : 'complete',

        'phase' =>
            $queue
                ? 'download'
                : 'complete',

        'as_of_date' =>
            $asOfDate,

        'created_at' =>
            gmdate('c'),

        'last_check_at' =>
            gmdate('c'),

        'queue' =>
            array_values($queue),

        'index' =>
            0,

        'offset' =>
            0,

        'current_total_rows' =>
            0,

        'current_imported_rows' =>
            0,

        'already_current' =>
            $alreadyCurrent,

        'manifest_found' =>
            count($catalog),

        'manifest_missing' =>
            max(
                0,
                $expected
                - count($catalog)
            ),

        'message' =>
            $queue
                ? 'FCC sync plan created.'
                : 'All available FCC coverage is current.',

        'error' =>
            null,
    ];

    llama_fcc_sync_save_state(
        $state
    );

    return $state;
}


/* =========================================================
   DOWNLOAD + EXTRACTION
   ========================================================= */

function llama_fcc_sync_download_to_file(
    string $url,
    string $destination
): int {
    $stream =
        fopen(
            $destination,
            'wb'
        );

    if (!$stream) {
        throw new RuntimeException(
            'The temporary FCC download file could not be created.'
        );
    }

    $curl =
        llama_fcc_sync_curl_base(
            $url
        );

    $config =
        llama_fcc_sync_config();

    curl_setopt_array(
        $curl,
        [
            CURLOPT_HTTPHEADER => [
                'Accept: application/octet-stream',
                'username: ' . $config['username'],
                'hash_value: ' . $config['hash_value'],
            ],

            CURLOPT_FILE =>
                $stream,

            CURLOPT_TIMEOUT =>
                1800,

            CURLOPT_LOW_SPEED_LIMIT =>
                1024,

            CURLOPT_LOW_SPEED_TIME =>
                90,
        ]
    );

    $ok =
        curl_exec($curl);

    $status =
        (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

    $error =
        curl_error($curl);

    curl_close($curl);
    fclose($stream);

    if (
        $ok === false
        || $status < 200
        || $status >= 300
    ) {
        @unlink($destination);

        throw new RuntimeException(
            'FCC file download failed'
            . ($status > 0
                ? ' with HTTP '
                    . $status
                : '')
            . ($error !== ''
                ? ': '
                    . $error
                : '.')
        );
    }

    return
        is_file($destination)
            ? (int) filesize(
                $destination
            )
            : 0;
}


function llama_fcc_sync_download_dataset(
    array $dataset,
    string $syncId,
    int $index
): string {
    $config =
        llama_fcc_sync_config();

    $fileId =
        rawurlencode(
            (string) (
                $dataset['file_id']
                ?? ''
            )
        );

    if ($fileId === '') {
        throw new RuntimeException(
            'The FCC catalog entry is missing a file ID.'
        );
    }

    $prefix =
        'fccsync_'
        . preg_replace(
            '/[^a-z0-9]/i',
            '',
            $syncId
        )
        . '_'
        . $index;

    $directory =
        llama_fcc_sync_directory();

    $download =
        $directory
        . '/'
        . $prefix
        . '.download';

    @unlink($download);

    $url =
        $config['api_base']
        . '/downloads/downloadFile/availability/'
        . $fileId
        . '/'
        . LLAMA_FCC_DOWNLOAD_FORMAT_GEOPACKAGE;

    llama_fcc_sync_download_to_file(
        $url,
        $download
    );

    $head =
        file_get_contents(
            $download,
            false,
            null,
            0,
            16
        );

    if (
        is_string($head)
        && str_starts_with(
            $head,
            'SQLite format 3'
        )
    ) {
        $gpkgName =
            $prefix . '.gpkg';

        $gpkgPath =
            $directory
            . '/'
            . $gpkgName;

        @unlink($gpkgPath);

        if (!rename(
            $download,
            $gpkgPath
        )) {
            @unlink($download);

            throw new RuntimeException(
                'The downloaded GeoPackage could not be finalized.'
            );
        }

        return $gpkgName;
    }

    if (
        !is_string($head)
        || !str_starts_with(
            $head,
            'PK'
        )
    ) {
        @unlink($download);

        throw new RuntimeException(
            'The FCC download was not a GeoPackage or ZIP archive.'
        );
    }

    if (!class_exists('ZipArchive')) {
        @unlink($download);

        throw new RuntimeException(
            'PHP ZipArchive is required for automatic FCC downloads.'
        );
    }

    $extractDirectory =
        $directory
        . '/'
        . $prefix
        . '_extract';

    llama_fcc_sync_remove_tree(
        $extractDirectory
    );

    if (!mkdir(
        $extractDirectory,
        0750,
        true
    )) {
        @unlink($download);

        throw new RuntimeException(
            'The FCC ZIP extraction directory could not be created.'
        );
    }

    $zip =
        new ZipArchive();

    $opened =
        $zip->open($download);

    if ($opened !== true) {
        @unlink($download);
        llama_fcc_sync_remove_tree(
            $extractDirectory
        );

        throw new RuntimeException(
            'The FCC ZIP archive could not be opened.'
        );
    }

    $zip->extractTo(
        $extractDirectory
    );

    $zip->close();

    /*
     * Delete the compressed file immediately so we never retain
     * hundreds of large FCC source archives.
     */
    @unlink($download);

    $gpkgPath = null;

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $extractDirectory,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {
        if (
            $file->isFile()
            && strtolower(
                $file->getExtension()
            ) === 'gpkg'
        ) {
            $gpkgPath =
                $file->getPathname();

            break;
        }
    }

    if (!$gpkgPath) {
        llama_fcc_sync_remove_tree(
            $extractDirectory
        );

        throw new RuntimeException(
            'The FCC ZIP archive did not contain a GeoPackage.'
        );
    }

    $gpkgName =
        $prefix . '.gpkg';

    $finalPath =
        $directory
        . '/'
        . $gpkgName;

    @unlink($finalPath);

    if (!rename(
        $gpkgPath,
        $finalPath
    )) {
        llama_fcc_sync_remove_tree(
            $extractDirectory
        );

        throw new RuntimeException(
            'The extracted FCC GeoPackage could not be moved.'
        );
    }

    llama_fcc_sync_remove_tree(
        $extractDirectory
    );

    return $gpkgName;
}


function llama_fcc_sync_remove_tree(
    string $path
): void {
    if ($path === '') {
        return;
    }

    if (is_file($path)) {
        @unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir(
                $file->getPathname()
            );
        } else {
            @unlink(
                $file->getPathname()
            );
        }
    }

    @rmdir($path);
}


function llama_fcc_sync_cleanup_dataset(
    array $state
): void {
    $filename =
        basename(
            (string) (
                $state['local_filename']
                ?? ''
            )
        );

    if ($filename !== '') {
        @unlink(
            llama_fcc_sync_directory()
            . '/'
            . $filename
        );
    }
}


/* =========================================================
   SYNC WORKER
   ========================================================= */

function llama_fcc_sync_update_ledger_source_name(
    array $dataset
): void {
    $stmt =
        cell_db()->prepare(
            'UPDATE cell_coverage_datasets
             SET source_filename = ?
             WHERE state_fips = ?
               AND provider_key = ?
               AND technology = ?
               AND fcc_as_of_date = ?'
        );

    $stmt->execute([
        (string) (
            $dataset['file_name']
            ?? ''
        ),
        (string) (
            $dataset['state_fips']
            ?? ''
        ),
        (string) (
            $dataset['provider_key']
            ?? ''
        ),
        (string) (
            $dataset['technology']
            ?? ''
        ),
        (string) (
            $dataset['as_of_date']
            ?? ''
        ),
    ]);
}


function llama_fcc_sync_resume(): array
{
    $state =
        llama_fcc_sync_load_state();

    if (!$state) {
        return llama_fcc_sync_create_plan();
    }

    if (
        ($state['status'] ?? '')
        === 'complete'
    ) {
        return $state;
    }

    $state['status'] =
        'running';

    $state['error'] =
        null;

    $state['message'] =
        'FCC sync resumed.';

    llama_fcc_sync_save_state(
        $state
    );

    return $state;
}


function llama_fcc_sync_step(): array
{
    $state =
        llama_fcc_sync_load_state();

    if (!$state) {
        $state =
            llama_fcc_sync_create_plan();
    }

    if (
        ($state['status'] ?? '')
        === 'complete'
    ) {
        return $state;
    }

    if (
        ($state['status'] ?? '')
        === 'error'
    ) {
        return $state;
    }

    $queue =
        is_array(
            $state['queue']
            ?? null
        )
            ? $state['queue']
            : [];

    $index =
        (int) (
            $state['index']
            ?? 0
        );

    if (!isset($queue[$index])) {
        $state['status'] =
            'complete';

        $state['phase'] =
            'complete';

        $state['message'] =
            'FCC cell coverage sync complete.';

        llama_fcc_sync_save_state(
            $state
        );

        return $state;
    }

    $dataset =
        $queue[$index];

    try {
        $phase =
            (string) (
                $state['phase']
                ?? 'download'
            );

        if ($phase === 'download') {
            $state['message'] =
                'Downloading '
                . ($dataset['state_name'] ?? '')
                . ' '
                . ($dataset['provider_label'] ?? '')
                . ' '
                . strtoupper(
                    (string) (
                        $dataset['technology']
                        ?? ''
                    )
                )
                . '...';

            llama_fcc_sync_save_state(
                $state
            );

            $localFilename =
                llama_fcc_sync_download_dataset(
                    $dataset,
                    (string) (
                        $state['sync_id']
                        ?? 'sync'
                    ),
                    $index
                );

            $state['local_filename'] =
                $localFilename;

            $state['phase'] =
                'import';

            $state['offset'] =
                0;

            $state[
                'current_total_rows'
            ] = 0;

            $state[
                'current_imported_rows'
            ] = 0;

            $state['message'] =
                'Download complete. Preparing import.';

            llama_fcc_sync_save_state(
                $state
            );

            return $state;
        }

        if ($phase !== 'import') {
            $state['phase'] =
                'download';

            llama_fcc_sync_save_state(
                $state
            );

            return $state;
        }

        $localFilename =
            basename(
                (string) (
                    $state['local_filename']
                    ?? ''
                )
            );

        if (
            $localFilename === ''
            || !is_file(
                llama_fcc_sync_directory()
                . '/'
                . $localFilename
            )
        ) {
            $state['phase'] =
                'download';

            $state['offset'] =
                0;

            llama_fcc_sync_save_state(
                $state
            );

            return $state;
        }

        $result =
            llama_cell_import_batch(
                cell_db(),
                $localFilename,
                (string) (
                    $dataset['state_fips']
                    ?? ''
                ),
                (string) (
                    $dataset['as_of_date']
                    ?? ''
                ),
                (int) (
                    $state['offset']
                    ?? 0
                ),
                LLAMA_FCC_AUTO_IMPORT_BATCH
            );

        $state['offset'] =
            (int) (
                $result['next_offset']
                ?? 0
            );

        $state[
            'current_total_rows'
        ] =
            (int) (
                $result['total_rows']
                ?? 0
            );

        $state[
            'current_imported_rows'
        ] =
            (int) (
                $state[
                    'current_imported_rows'
                ]
                ?? 0
            )
            + (int) (
                $result['imported']
                ?? 0
            );

        if (
            !empty(
                $result['done']
            )
        ) {
            llama_fcc_sync_update_ledger_source_name(
                $dataset
            );

            llama_fcc_sync_cleanup_dataset(
                $state
            );

            $state['index'] =
                $index + 1;

            $state['phase'] =
                isset(
                    $queue[
                        $index + 1
                    ]
                )
                    ? 'download'
                    : 'complete';

            $state['offset'] =
                0;

            $state[
                'current_total_rows'
            ] = 0;

            $state[
                'current_imported_rows'
            ] = 0;

            unset(
                $state[
                    'local_filename'
                ]
            );

            if (
                $state['phase']
                === 'complete'
            ) {
                $state['status'] =
                    'complete';

                $state['message'] =
                    'FCC cell coverage sync complete.';
            } else {
                $state['message'] =
                    'Dataset installed. Moving to the next FCC dataset.';
            }
        } else {
            $state['message'] =
                'Importing '
                . ($dataset['state_name'] ?? '')
                . ' '
                . ($dataset['provider_label'] ?? '')
                . ' '
                . strtoupper(
                    (string) (
                        $dataset['technology']
                        ?? ''
                    )
                )
                . '...';
        }

        llama_fcc_sync_save_state(
            $state
        );

        return $state;

    } catch (Throwable $e) {
        $state['status'] =
            'error';

        $state['error'] =
            $e->getMessage();

        $state['message'] =
            'FCC sync stopped on an error.';

        llama_fcc_sync_save_state(
            $state
        );

        return $state;
    }
}


function llama_fcc_sync_worker(
    int $timeBudgetSeconds = 240
): array {
    $started =
        microtime(true);

    $state =
        llama_fcc_sync_load_state();

    if (!$state) {
        $state =
            llama_fcc_sync_create_plan();
    }

    if (
        ($state['status'] ?? '')
        === 'error'
    ) {
        $state =
            llama_fcc_sync_resume();
    }

    while (
        (microtime(true) - $started)
        < max(
            20,
            $timeBudgetSeconds
        )
    ) {
        $state =
            llama_fcc_sync_step();

        if (
            in_array(
                (string) (
                    $state['status']
                    ?? ''
                ),
                [
                    'complete',
                    'error',
                ],
                true
            )
        ) {
            break;
        }
    }

    return $state;
}
