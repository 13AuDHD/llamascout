<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$adminUser =
    moderation_require_admin();

$config =
    llama_config();

$fcc =
    is_array(
        $config['fcc_bdc']
        ?? null
    )
        ? $config['fcc_bdc']
        : [];

$username =
    trim(
        (string) (
            $fcc['username']
            ?? ''
        )
    );

$hashValue =
    trim(
        (string) (
            $fcc['hash_value']
            ?? ''
        )
    );

$adminPageTitle =
    'FCC API Diagnostic';

$adminPageEyebrow =
    'Map Data';

$adminActiveNav =
    'cell-coverage';


function fcc_diag_mask_username(
    string $username
): string {
    if ($username === '') {
        return '(missing)';
    }

    $at =
        strpos(
            $username,
            '@'
        );

    if ($at === false) {
        return
            substr($username, 0, 2)
            . str_repeat(
                '•',
                max(
                    1,
                    strlen($username) - 4
                )
            )
            . substr($username, -2);
    }

    $local =
        substr(
            $username,
            0,
            $at
        );

    $domain =
        substr(
            $username,
            $at + 1
        );

    return
        substr($local, 0, 2)
        . str_repeat(
            '•',
            max(
                1,
                strlen($local) - 2
            )
        )
        . '@'
        . $domain;
}


function fcc_diag_request(
    string $url,
    string $username,
    string $hashValue,
    string $userAgent
): array {
    if (!function_exists('curl_init')) {
        return [
            'url' => $url,
            'user_agent' => $userAgent,
            'http_code' => 0,
            'effective_url' => '',
            'redirect_count' => 0,
            'curl_error' =>
                'PHP cURL is not enabled.',
            'body' => '',
        ];
    }

    $curl =
        curl_init($url);

    if (!$curl) {
        return [
            'url' => $url,
            'user_agent' => $userAgent,
            'http_code' => 0,
            'effective_url' => '',
            'redirect_count' => 0,
            'curl_error' =>
                'cURL could not be initialized.',
            'body' => '',
        ];
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_MAXREDIRS =>
                5,

            CURLOPT_CONNECTTIMEOUT =>
                20,

            CURLOPT_TIMEOUT =>
                45,

            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'username: '
                    . $username,
                'hash_value: '
                    . $hashValue,
            ],

            CURLOPT_USERAGENT =>
                $userAgent,
        ]
    );

    $body =
        curl_exec($curl);

    $result = [
        'url' =>
            $url,

        'user_agent' =>
            $userAgent,

        'http_code' =>
            (int) curl_getinfo(
                $curl,
                CURLINFO_RESPONSE_CODE
            ),

        'effective_url' =>
            (string) curl_getinfo(
                $curl,
                CURLINFO_EFFECTIVE_URL
            ),

        'redirect_count' =>
            (int) curl_getinfo(
                $curl,
                CURLINFO_REDIRECT_COUNT
            ),

        'curl_error' =>
            curl_error($curl),

        'body' =>
            is_string($body)
                ? mb_substr(
                    trim($body),
                    0,
                    2000
                )
                : '',
    ];

    curl_close($curl);

    return $result;
}


$tests = [];

if (
    $username !== ''
    && $hashValue !== ''
) {
    $tests[] =
        fcc_diag_request(
            'https://bdc.fcc.gov/api/public/map/listAsOfDates',
            $username,
            $hashValue,
            'LlamaScout/1.0 (+https://llamascout.com)'
        );

    $tests[] =
        fcc_diag_request(
            'https://bdc.fcc.gov/api/public/map/listAsOfDates',
            $username,
            $hashValue,
            'play/0.0.0'
        );

    $tests[] =
        fcc_diag_request(
            'https://broadbandmap.fcc.gov/api/public/map/listAsOfDates',
            $username,
            $hashValue,
            'play/0.0.0'
        );
}

require __DIR__ . '/_header.php';
?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Authentication Test</p>
            <h2>FCC Public Data API</h2>
        </div>
    </header>

    <p>
        This page does not display the FCC token.
        It only verifies what PHP loaded from
        <code>private/config.php</code>
        and tests the FCC authentication endpoint directly.
    </p>

    <table class="admin-table">
        <tbody>
            <tr>
                <th>Configured username</th>
                <td>
                    <?= moderation_e(
                        fcc_diag_mask_username(
                            $username
                        )
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Username length</th>
                <td>
                    <?= number_format(
                        strlen($username)
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Token present</th>
                <td>
                    <?= $hashValue !== ''
                        ? 'Yes'
                        : 'No' ?>
                </td>
            </tr>

            <tr>
                <th>Token length</th>
                <td>
                    <?= number_format(
                        strlen($hashValue)
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Leading/trailing whitespace</th>
                <td>
                    <?php
                    $rawHash =
                        (string) (
                            $fcc['hash_value']
                            ?? ''
                        );

                    echo strlen($rawHash)
                        === strlen($hashValue)
                            ? 'No'
                            : 'Yes';
                    ?>
                </td>
            </tr>
        </tbody>
    </table>
</section>


<?php foreach ($tests as $index => $test): ?>

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>
                    Test <?= $index + 1 ?>
                </p>

                <h2>
                    <?= moderation_e(
                        parse_url(
                            $test['url'],
                            PHP_URL_HOST
                        )
                        ?: $test['url']
                    ) ?>
                </h2>
            </div>

            <span>
                HTTP
                <?= number_format(
                    (int) $test[
                        'http_code'
                    ]
                ) ?>
            </span>
        </header>

        <table class="admin-table">
            <tbody>
                <tr>
                    <th>Request URL</th>
                    <td>
                        <code>
                            <?= moderation_e(
                                $test['url']
                            ) ?>
                        </code>
                    </td>
                </tr>

                <tr>
                    <th>User-Agent</th>
                    <td>
                        <code>
                            <?= moderation_e(
                                $test[
                                    'user_agent'
                                ]
                            ) ?>
                        </code>
                    </td>
                </tr>

                <tr>
                    <th>Effective URL</th>
                    <td>
                        <code>
                            <?= moderation_e(
                                $test[
                                    'effective_url'
                                ]
                            ) ?>
                        </code>
                    </td>
                </tr>

                <tr>
                    <th>Redirects</th>
                    <td>
                        <?= number_format(
                            (int) $test[
                                'redirect_count'
                            ]
                        ) ?>
                    </td>
                </tr>

                <tr>
                    <th>cURL error</th>
                    <td>
                        <?= moderation_e(
                            $test[
                                'curl_error'
                            ] !== ''
                                ? $test[
                                    'curl_error'
                                ]
                                : 'None'
                        ) ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <p>
            <strong>FCC response:</strong>
        </p>

        <pre style="white-space:pre-wrap;overflow-wrap:anywhere"><code><?= moderation_e(
            $test['body'] !== ''
                ? $test['body']
                : '(empty response body)'
        ) ?></code></pre>
    </section>

<?php endforeach; ?>


<?php if (!$tests): ?>
    <section class="admin-panel">
        <div class="admin-alert is-danger">
            FCC credentials are missing from
            <code>private/config.php</code>.
        </div>
    </section>
<?php endif; ?>

<?php
require __DIR__ . '/_footer.php';
