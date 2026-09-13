<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

if (
    !admin_users_current_is_owner(
        $db,
        $actorUserId
    )
) {
    http_response_code(403);
    exit('Owner access required.');
}

$notice = '';
$error = '';

$commit =
    LLAMA_PASSKEY_LIBRARY_COMMIT;

$manifest = [
        'LICENSE.md' => '6669b0e0e89828e9676f30b655197fdb767f1f54',
        'src/Base64/Base64.php' => 'aa8397675a2a8b6a7a8db3fe49b5aa2c69e2bd48',
        'src/Base64/InvalidBase64Exception.php' => '05cf734c53ea2a7cde7860c1fe5e812dbeeb4cd2',
        'src/Binary/BytesReader.php' => '3223e7bfb8fadcf4067caa6e3be898847c79b8bf',
        'src/Binary/BytesReaderException.php' => '23610e59de0d9ee580f1b612220fee2f1a7ff83e',
        'src/Cbor/CborDecoder.php' => '4968dee9fd62b2c9c4d5b142242cda131b704ba4',
        'src/Cbor/CborEncoder.php' => 'f69657fa6869c72474145d8b6666d786f2780cf4',
        'src/Cbor/CborMap.php' => '84baaf29e3e78a74bc06db89e03ed2533f861d0f',
        'src/Cbor/CborMapException.php' => '06a2c724bfeebde03d38ea5525faebb6cdfc33d2',
        'src/Cbor/InvalidCborException.php' => '4b83adddecc248d7cb929a4e648c5f7b8842d209',
        'src/Ceremony/AuthenticationExpectations.php' => 'e3820ff8e0b588b9996dcb7a21c4f94a6aadc9d6',
        'src/Ceremony/AuthenticationResult.php' => 'f28698992471c1c8db91cdd700e32a0a196a52dd',
        'src/Ceremony/CredentialRecord.php' => '7e61935a3e887f02b48609358ba57e3be4742df2',
        'src/Ceremony/CredentialStore.php' => '61c7cc77a32836956950c97b07b083f10a2d4832',
        'src/Ceremony/RegistrationExpectations.php' => 'ebbe18376e286bbd85b7c17f24e6918d6796881c',
        'src/Ceremony/RegistrationResult.php' => 'ec8772c78dea6307fbc734abcffcdd9f1e6645b7',
        'src/Ceremony/RelyingParty.php' => '0d1648367e62ccfd838e690fd8cbc900fa54fe5f',
        'src/Ceremony/VerificationException.php' => '87706ad3eba168d827269ffd5b7cefdc5d07fe04',
        'src/Cose/CoseAlgorithmIdentifier.php' => '53c1a243a66f9dc50d92af4628340f0159cd6775',
        'src/Cose/CoseEc2Key.php' => 'b59fa68cdba6e325c6d38a4ba6a33c4b6df9790a',
        'src/Cose/CoseKey.php' => 'a67c98c087922ab67c872811d4ed23b577a9f5e1',
        'src/Cose/CoseKeyException.php' => '742713727d74dbb79de4b67a182688ff7cac6d10',
        'src/Cose/CoseKeyLoadException.php' => '0d05108ae18c960e1bc8dd8fc4b5d24fc9856fcd',
        'src/Cose/CoseOkpKey.php' => '394d1bc486e248125e27d2b9a856e38b684c7db6',
        'src/Cose/CoseRsaKey.php' => '3ec8e0f3edb2f3a4a92bf5d854bc7d276e99571f',
        'src/Credential/AttestationObject.php' => 'a1332cc2cc88272059b7ffa64a62ce83c3c8797d',
        'src/Credential/AttestedCredentialData.php' => '2d2af82dfcf769a06d06ccd161004f7a7aaa4855',
        'src/Credential/AuthenticatorAssertionResponse.php' => 'a1574264c9ad3f740f35dbe32f402edf0e347f42',
        'src/Credential/AuthenticatorAttestationResponse.php' => 'f7de479239ecf297ebf654d62886fb8a1ed45d25',
        'src/Credential/AuthenticatorData.php' => 'd740fcee9cbe2220c1ecad55121577847b754b79',
        'src/Credential/AuthenticatorResponse.php' => 'b8f242aba8fb115cc4ae9c1ce9f6cdb033dca540',
        'src/Credential/CollectedClientData.php' => '7e788cbe85678fb774da7b6ff4864bbe2c3dcb23',
        'src/Credential/MalformedDataException.php' => '190f12a3087b18246a6801e8e0332b5c646cfbfb',
        'src/Credential/PublicKeyCredential.php' => '395c978f23dbbd24b03e08846243bce1fc5e0db3',
        'src/Enum/AuthenticatorAttachment.php' => 'c6a6d7688485f6debe6d0e7066a024d3e97bcd05',
        'src/Enum/AuthenticatorTransport.php' => '149b6843c9abdeefb82ebe8573424228a264f621',
        'src/Enum/PublicKeyCredentialHint.php' => '2e079b6f3161867de05e1a8f66994184d21387b5',
        'src/Enum/PublicKeyCredentialType.php' => '8e60e38484c2a6ee47b069560dc1e813ec068ab0',
        'src/Enum/ResidentKeyRequirement.php' => 'b2d59a03c4e8ba12b8800ea86af3c88c4e6d8312',
        'src/Enum/UserVerificationRequirement.php' => '34c2691a138637509abaa2c09bc04885dd40d81b',
        'src/Json/JsonObject.php' => '0d931e3e4b379bf1da3df3fc68960e7af78f9280',
        'src/Json/JsonObjectException.php' => 'bdbabebc468f3b4e3a330ba089ff3588544fb6ba',
        'src/Options/AuthenticatorSelectionCriteria.php' => '85e4d452d069c7c0ed8d8cd5e955558302b42af3',
        'src/Options/PublicKeyCredentialCreationOptions.php' => 'cb84de3268d46961ef97a260a4fa540b6649da46',
        'src/Options/PublicKeyCredentialDescriptor.php' => '71a225e4761130cdf359b5b05dcdb6cba4c49f2d',
        'src/Options/PublicKeyCredentialEntity.php' => '80bbe5c8563110d09da993d386931be1eb8f3292',
        'src/Options/PublicKeyCredentialParameters.php' => '88210f4013f756e9e715d064a4880eeaeb5c888b',
        'src/Options/PublicKeyCredentialRequestOptions.php' => 'a66462d64e5ee662064962a5e115da4af38237dc',
        'src/Options/PublicKeyCredentialRpEntity.php' => '665da4349149fb96cbf824ec4403f39bc715f2bc',
        'src/Options/PublicKeyCredentialUserEntity.php' => 'eccf05228ed03e12ef810cc8555649c93df66cc7',
        'src/PasskeyFlow.php' => 'c4c2d350d63892566e82424e2b18a799b08c1898',
        'src/PasskeyStore.php' => '74cce14aadbc22fc2f61fdcb49dade4163a05814',
        'src/PendingAuthentication.php' => '87aa7f86025f4592244f7c83496809da6510790f',
        'src/PendingCeremonyStore.php' => 'ec75cd2727a6f64f36af4f2cc540d2cba9afeeef',
        'src/PendingRegistration.php' => '6ede9b318da7990055d036163117c7b6a09b5746',
        'src/RegisteredPasskey.php' => 'd58cab3df8ba3969ccd7dc55364032b7821c4a60',
        'src/Signal/AllAcceptedCredentialsSignal.php' => '7955b731e1af538b290456f90eeee53bb2780397',
        'src/Signal/CurrentUserDetailsSignal.php' => '4e3805a486d52fcc0a520f3ba1b097571eee8b6c',
];

function llama_passkey_installer_git_blob_sha(
    string $content
): string {
    return sha1(
        'blob '
        . strlen($content)
        . "\0"
        . $content
    );
}

function llama_passkey_installer_download(
    string $url,
    string $destination
): void {
    if (function_exists('curl_init')) {
        $handle =
            curl_init(
                $url
            );

        if ($handle === false) {
            throw new RuntimeException(
                'Could not initialize the download.'
            );
        }

        $file =
            fopen(
                $destination,
                'wb'
            );

        if ($file === false) {
            curl_close($handle);

            throw new RuntimeException(
                'Could not open the temporary download file.'
            );
        }

        curl_setopt_array(
            $handle,
            [
                CURLOPT_FILE =>
                    $file,
                CURLOPT_FOLLOWLOCATION =>
                    true,
                CURLOPT_MAXREDIRS =>
                    5,
                CURLOPT_CONNECTTIMEOUT =>
                    15,
                CURLOPT_TIMEOUT =>
                    60,
                CURLOPT_USERAGENT =>
                    'Llama-Scout-Passkey-Installer/1.0',
                CURLOPT_FAILONERROR =>
                    true,
                CURLOPT_PROTOCOLS =>
                    CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS =>
                    CURLPROTO_HTTPS,
            ]
        );

        $ok =
            curl_exec(
                $handle
            );

        $curlError =
            curl_error(
                $handle
            );

        curl_close(
            $handle
        );

        fclose(
            $file
        );

        if (!$ok) {
            @unlink(
                $destination
            );

            throw new RuntimeException(
                'The passkey library download failed: '
                . $curlError
            );
        }

        return;
    }

    $context =
        stream_context_create(
            [
                'http' => [
                    'timeout' =>
                        60,
                    'follow_location' =>
                        1,
                    'max_redirects' =>
                        5,
                    'user_agent' =>
                        'Llama-Scout-Passkey-Installer/1.0',
                ],
            ]
        );

    $content =
        @file_get_contents(
            $url,
            false,
            $context
        );

    if (!is_string($content) || $content === '') {
        throw new RuntimeException(
            'The server could not download the passkey library.'
        );
    }

    if (
        file_put_contents(
            $destination,
            $content,
            LOCK_EX
        )
        ===
        false
    ) {
        throw new RuntimeException(
            'The downloaded passkey library could not be saved temporarily.'
        );
    }
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    $csrf =
        (string) (
            $_POST['csrf_token']
            ?? ''
        );

    if (!moderation_verify_csrf($csrf)) {
        $error =
            'Your session token expired. Reload the page and try again.';
    } else {
        try {
            if (PHP_VERSION_ID < 80400) {
                throw new RuntimeException(
                    'The server must be running PHP 8.4 or newer.'
                );
            }

            if (!extension_loaded('openssl')) {
                throw new RuntimeException(
                    'The PHP OpenSSL extension is required.'
                );
            }

            if (!class_exists('ZipArchive')) {
                throw new RuntimeException(
                    'The PHP Zip extension is required for the one-time installer.'
                );
            }

            $temporaryZip =
                tempnam(
                    sys_get_temp_dir(),
                    'llama-passkeys-'
                );

            if (!is_string($temporaryZip)) {
                throw new RuntimeException(
                    'Could not create a temporary installer file.'
                );
            }

            $url =
                'https://github.com/shipmonk-rnd/passkeys/archive/'
                . rawurlencode($commit)
                . '.zip';

            llama_passkey_installer_download(
                $url,
                $temporaryZip
            );

            $zip =
                new ZipArchive();

            $opened =
                $zip->open(
                    $temporaryZip
                );

            if ($opened !== true) {
                @unlink(
                    $temporaryZip
                );

                throw new RuntimeException(
                    'The downloaded passkey archive is not a valid ZIP file.'
                );
            }

            $vendorRoot =
                dirname(__DIR__)
                . '/vendor/shipmonk/passkeys';

            $archiveRoot =
                'passkeys-'
                . $commit
                . '/';

            foreach (
                $manifest
                as
                $relativePath =>
                $expectedSha
            ) {
                $archivePath =
                    $archiveRoot
                    . $relativePath;

                $index =
                    $zip->locateName(
                        $archivePath,
                        ZipArchive::FL_NOCASE
                    );

                if ($index === false) {
                    throw new RuntimeException(
                        'Required library file is missing from the archive: '
                        . $relativePath
                    );
                }

                $content =
                    $zip->getFromIndex(
                        $index
                    );

                if (!is_string($content)) {
                    throw new RuntimeException(
                        'Could not read library file: '
                        . $relativePath
                    );
                }

                $actualSha =
                    llama_passkey_installer_git_blob_sha(
                        $content
                    );

                if (
                    !hash_equals(
                        $expectedSha,
                        $actualSha
                    )
                ) {
                    throw new RuntimeException(
                        'Library integrity verification failed for '
                        . $relativePath
                    );
                }

                $destination =
                    $vendorRoot
                    . '/'
                    . $relativePath;

                $directory =
                    dirname(
                        $destination
                    );

                if (
                    !is_dir($directory)
                    &&
                    !mkdir(
                        $directory,
                        0755,
                        true
                    )
                    &&
                    !is_dir($directory)
                ) {
                    throw new RuntimeException(
                        'Could not create the passkey library directory.'
                    );
                }

                $temporaryFile =
                    $destination
                    . '.tmp';

                if (
                    file_put_contents(
                        $temporaryFile,
                        $content,
                        LOCK_EX
                    )
                    ===
                    false
                ) {
                    throw new RuntimeException(
                        'Could not write library file: '
                        . $relativePath
                    );
                }

                if (
                    !rename(
                        $temporaryFile,
                        $destination
                    )
                ) {
                    @unlink(
                        $temporaryFile
                    );

                    throw new RuntimeException(
                        'Could not activate library file: '
                        . $relativePath
                    );
                }

                @chmod(
                    $destination,
                    0644
                );
            }

            $zip->close();

            @unlink(
                $temporaryZip
            );

            clearstatcache();

            if (!llama_passkey_library_ready()) {
                throw new RuntimeException(
                    'The library files were installed but could not be loaded.'
                );
            }

            $notice =
                'Passkey engine installed and verified successfully.';

        } catch (Throwable $exception) {
            if (
                isset($zip)
                &&
                $zip instanceof ZipArchive
            ) {
                @$zip->close();
            }

            if (
                isset($temporaryZip)
                &&
                is_string($temporaryZip)
            ) {
                @unlink(
                    $temporaryZip
                );
            }

            $error =
                $exception->getMessage();
        }
    }
}

$installed =
    llama_passkey_library_ready();

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' =>
        $stats['new_places'],
    'updates' =>
        $stats['updates'],
    'reports' =>
        $stats['reports'],
    'orders' =>
        $stats['orders'],
    'scout_reviews' =>
        $stats['scout_reviews'],
];

$adminPageTitle =
    'Passkey Engine';

$adminPageEyebrow =
    'System';

$adminActiveNav =
    'system';

require __DIR__ . '/_header.php';
?>

<?php if ($notice !== ''): ?>
    <div class="admin-user-notice is-success">
        <?= moderation_e($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>


<section class="admin-panel">

    <header class="admin-panel-header">
        <div>
            <p>WebAuthn</p>
            <h2>Passkey Engine</h2>
        </div>

        <span class="admin-status-pill">
            <?= $installed
                ? 'Installed'
                : 'Not installed' ?>
        </span>
    </header>

    <div class="admin-user-action-box">

        <p>
            Llama Scout uses the zero-dependency ShipMonk Passkeys
            library for WebAuthn verification. This installer is pinned
            to commit
            <code><?= moderation_e($commit) ?></code>.
        </p>

        <p>
            Every downloaded source file is checked against its expected
            Git blob SHA before it is installed.
        </p>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(moderation_csrf_token()) ?>"
            >

            <button
                class="admin-button"
                type="submit"
            >
                <?= $installed
                    ? 'Verify / reinstall engine'
                    : 'Install passkey engine' ?>
            </button>
        </form>

        <?php if ($installed): ?>
            <p>
                <a
                    class="admin-button is-muted"
                    href="https://account.llamascout.com/passkeys.php"
                >
                    Open Passkeys
                </a>
            </p>
        <?php endif; ?>

    </div>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
