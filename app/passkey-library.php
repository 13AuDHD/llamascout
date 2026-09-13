<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   SHIPMONK PASSKEYS INTEGRATION

   The third-party library is installed into:
   vendor/shipmonk/passkeys/src

   Its source is pinned and installed from Basecamp by an Owner.
   ========================================================= */


const LLAMA_PASSKEY_LIBRARY_COMMIT =
    'b0427f5d3258df9970781d13b0d23a3ef6263578';

const LLAMA_PASSKEY_RP_ID =
    'llamascout.com';

const LLAMA_PASSKEY_RP_NAME =
    'Llama Scout';

const LLAMA_PASSKEY_ORIGIN =
    'https://account.llamascout.com';

const LLAMA_PASSKEY_PENDING_TTL =
    300;


function llama_passkey_library_root(): string
{
    return
        dirname(__DIR__)
        . '/vendor/shipmonk/passkeys/src';
}


function llama_passkey_register_autoloader(): void
{
    static $registered = false;

    if ($registered) {
        return;
    }

    $registered = true;

    spl_autoload_register(
        static function (
            string $class
        ): void {
            $prefix =
                'ShipMonk\\Passkeys\\';

            if (
                !str_starts_with(
                    $class,
                    $prefix
                )
            ) {
                return;
            }

            $relative =
                substr(
                    $class,
                    strlen($prefix)
                );

            if ($relative === false || $relative === '') {
                return;
            }

            $path =
                llama_passkey_library_root()
                . '/'
                . str_replace(
                    '\\',
                    '/',
                    $relative
                )
                . '.php';

            if (is_file($path)) {
                require_once $path;
            }
        }
    );
}


llama_passkey_register_autoloader();


function llama_passkey_library_ready(): bool
{
    if (
        PHP_VERSION_ID < 80400
        ||
        !extension_loaded('openssl')
    ) {
        return false;
    }

    return
        is_file(
            llama_passkey_library_root()
            . '/PasskeyFlow.php'
        )
        &&
        class_exists(
            'ShipMonk\\Passkeys\\PasskeyFlow'
        );
}


function llama_passkey_assert_library_ready(): void
{
    if (PHP_VERSION_ID < 80400) {
        throw new RuntimeException(
            'Passkeys require PHP 8.4 or newer.'
        );
    }

    if (!extension_loaded('openssl')) {
        throw new RuntimeException(
            'Passkeys require the PHP OpenSSL extension.'
        );
    }

    if (!llama_passkey_library_ready()) {
        throw new RuntimeException(
            'The passkey engine has not been installed yet.'
        );
    }
}


if (
    interface_exists(
        'ShipMonk\\Passkeys\\PendingCeremonyStore'
    )
    &&
    interface_exists(
        'ShipMonk\\Passkeys\\PasskeyStore'
    )
) {

final class LlamaPasskeyPendingStore
    implements \ShipMonk\Passkeys\PendingCeremonyStore
{
    private const MAX_PENDING = 8;


    public function rememberPendingAuthentication(
        \ShipMonk\Passkeys\PendingAuthentication $pending
    ): void {
        $this->remember(
            'llama_passkey_pending_authentication',
            $pending->challenge,
            $pending
        );
    }


    public function consumePendingAuthentication(
        string $challenge
    ): ?\ShipMonk\Passkeys\PendingAuthentication {
        $pending =
            $this->consume(
                'llama_passkey_pending_authentication',
                $challenge
            );

        return
            $pending instanceof
            \ShipMonk\Passkeys\PendingAuthentication
                ? $pending
                : null;
    }


    public function rememberPendingRegistration(
        \ShipMonk\Passkeys\PendingRegistration $pending
    ): void {
        $this->remember(
            'llama_passkey_pending_registration',
            $pending->challenge,
            $pending
        );
    }


    public function consumePendingRegistration(
        string $challenge
    ): ?\ShipMonk\Passkeys\PendingRegistration {
        $pending =
            $this->consume(
                'llama_passkey_pending_registration',
                $challenge
            );

        return
            $pending instanceof
            \ShipMonk\Passkeys\PendingRegistration
                ? $pending
                : null;
    }


    private function remember(
        string $bucket,
        string $challenge,
        object $pending
    ): void {
        $_SESSION[$bucket] ??= [];

        $_SESSION[$bucket][$challenge] = [
            'created_at' =>
                time(),
            'pending' =>
                $pending,
        ];

        while (
            count(
                $_SESSION[$bucket]
            )
            >
            self::MAX_PENDING
        ) {
            array_shift(
                $_SESSION[$bucket]
            );
        }
    }


    private function consume(
        string $bucket,
        string $challenge
    ): ?object {
        $items =
            $_SESSION[$bucket]
            ?? [];

        if (
            !is_array($items)
            ||
            !array_key_exists(
                $challenge,
                $items
            )
        ) {
            return null;
        }

        $entry =
            $items[$challenge];

        unset(
            $_SESSION[$bucket][$challenge]
        );

        if (!is_array($entry)) {
            return null;
        }

        $createdAt =
            (int) (
                $entry['created_at']
                ?? 0
            );

        if (
            $createdAt < 1
            ||
            $createdAt
            <
            (
                time()
                -
                LLAMA_PASSKEY_PENDING_TTL
            )
        ) {
            return null;
        }

        $pending =
            $entry['pending']
            ?? null;

        return
            is_object($pending)
                ? $pending
                : null;
    }
}


final class LlamaPasskeyStore
    implements \ShipMonk\Passkeys\PasskeyStore
{
    public function __construct(
        private readonly PDO $db
    ) {
    }


    public function findUserHandleByUsername(
        string $username
    ): ?string {
        $username =
            strtolower(
                trim(
                    $username
                )
            );

        if ($username === '') {
            return null;
        }

        $stmt =
            $this->db->prepare(
                '
                SELECT pi.user_handle

                FROM user_passkey_identities pi

                INNER JOIN users u
                  ON u.id = pi.user_id

                WHERE
                    (
                        LOWER(u.email) = ?
                        OR LOWER(u.username) = ?
                    )
                  AND u.status NOT IN
                    (
                        "suspended",
                        "disabled"
                    )
                  AND u.anonymized_at IS NULL

                LIMIT 1
                '
            );

        $stmt->execute([
            $username,
            $username,
        ]);

        $handle =
            $stmt->fetchColumn();

        return
            is_string($handle)
            && $handle !== ''
                ? $handle
                : null;
    }


    public function findCredentialByCredentialId(
        string $credentialId
    ): ?\ShipMonk\Passkeys\Ceremony\CredentialRecord {
        $stmt =
            $this->db->prepare(
                '
                SELECT
                    p.credential_id,
                    p.public_key,
                    p.signature_counter,
                    p.uv_initialized,
                    p.backup_eligible,
                    p.backup_state,
                    p.transports,
                    i.user_handle

                FROM user_passkeys p

                INNER JOIN user_passkey_identities i
                  ON i.user_id = p.user_id

                WHERE p.credential_id = ?
                  AND p.revoked_at IS NULL

                LIMIT 1
                '
            );

        $stmt->execute([
            $credentialId
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $this->recordFromRow($row)
                : null;
    }


    public function findCredentialsByUserHandle(
        string $userHandle
    ): array {
        $stmt =
            $this->db->prepare(
                '
                SELECT
                    p.credential_id,
                    p.public_key,
                    p.signature_counter,
                    p.uv_initialized,
                    p.backup_eligible,
                    p.backup_state,
                    p.transports,
                    i.user_handle

                FROM user_passkeys p

                INNER JOIN user_passkey_identities i
                  ON i.user_id = p.user_id

                WHERE i.user_handle = ?
                  AND p.revoked_at IS NULL

                ORDER BY
                    p.created_at ASC,
                    p.id ASC
                '
            );

        $stmt->execute([
            $userHandle
        ]);

        $records = [];

        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: []
            as
            $row
        ) {
            $records[] =
                $this->recordFromRow(
                    $row
                );
        }

        return $records;
    }


    public function findUserEntityByUserHandle(
        string $userHandle
    ): ?\ShipMonk\Passkeys\Options\PublicKeyCredentialUserEntity {
        $stmt =
            $this->db->prepare(
                '
                SELECT
                    i.user_handle,
                    u.email,
                    u.username,
                    u.display_name

                FROM user_passkey_identities i

                INNER JOIN users u
                  ON u.id = i.user_id

                WHERE i.user_handle = ?
                  AND u.status NOT IN
                    (
                        "suspended",
                        "disabled"
                    )
                  AND u.anonymized_at IS NULL

                LIMIT 1
                '
            );

        $stmt->execute([
            $userHandle
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return null;
        }

        $name =
            trim(
                (string) (
                    $row['email']
                    ?? ''
                )
            );

        if ($name === '') {
            return null;
        }

        $displayName =
            trim(
                (string) (
                    $row['display_name']
                    ?: $row['username']
                    ?: $name
                )
            );

        return
            new \ShipMonk\Passkeys\Options\PublicKeyCredentialUserEntity(
                id:
                    (string) $row['user_handle'],
                name:
                    $name,
                displayName:
                    $displayName
            );
    }


    public function saveCredential(
        \ShipMonk\Passkeys\RegisteredPasskey $passkey
    ): void {
        $record =
            $passkey->toCredentialRecord();

        $userId =
            llama_passkey_user_id_from_handle(
                $this->db,
                $record->userHandle
            );

        if ($userId < 1) {
            throw new RuntimeException(
                'The passkey account could not be resolved.'
            );
        }

        $authorization =
            llama_passkey_registration_authorization();

        if (
            !is_array($authorization)
            ||
            (int) (
                $authorization['user_id']
                ?? 0
            )
            !==
            $userId
        ) {
            throw new RuntimeException(
                'The passkey registration authorization is missing or expired.'
            );
        }

        $label =
            llama_passkey_validate_label(
                (string) (
                    $authorization['label']
                    ?? ''
                )
            );

        $transports =
            $record->transports === null
                ? null
                : json_encode(
                    $record->transports,
                    JSON_THROW_ON_ERROR
                );

        $attachment =
            $passkey
                ->authenticatorAttachment
                ?->value;

        $stmt =
            $this->db->prepare(
                '
                INSERT INTO user_passkeys
                (
                    user_id,
                    credential_id,
                    public_key,
                    signature_counter,
                    uv_initialized,
                    transports,
                    authenticator_attachment,
                    backup_eligible,
                    backup_state,
                    label,
                    created_at,
                    last_used_at,
                    revoked_at
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    NULL,
                    NULL
                )
                '
            );

        $stmt->execute([
            $userId,
            $record->credentialId,
            $record->publicKey->toBytes(),
            $record->signCount,
            $record->uvInitialized ? 1 : 0,
            $transports,
            $attachment,
            $record->backupEligible ? 1 : 0,
            $record->backupState ? 1 : 0,
            $label,
        ]);
    }


    public function updateCredential(
        \ShipMonk\Passkeys\Ceremony\AuthenticationResult $result
    ): void {
        $stmt =
            $this->db->prepare(
                '
                UPDATE user_passkeys

                SET
                    signature_counter = ?,
                    backup_state = ?,
                    uv_initialized =
                        GREATEST(
                            uv_initialized,
                            ?
                        ),
                    last_used_at =
                        UTC_TIMESTAMP()

                WHERE credential_id = ?
                  AND revoked_at IS NULL
                '
            );

        $stmt->execute([
            $result->newSignCount,
            $result->backupState ? 1 : 0,
            $result->userVerified ? 1 : 0,
            $result->credentialId,
        ]);
    }


    private function recordFromRow(
        array $row
    ): \ShipMonk\Passkeys\Ceremony\CredentialRecord {
        $transports = null;

        if (
            isset($row['transports'])
            &&
            trim(
                (string) $row['transports']
            )
            !== ''
        ) {
            $decoded =
                json_decode(
                    (string) $row['transports'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            $transports =
                is_array($decoded)
                    ? array_values(
                        array_map(
                            'strval',
                            $decoded
                        )
                    )
                    : null;
        }

        return
            new \ShipMonk\Passkeys\Ceremony\CredentialRecord(
                credentialId:
                    (string) $row['credential_id'],
                publicKey:
                    \ShipMonk\Passkeys\Cose\CoseKey::fromBytes(
                        (string) $row['public_key']
                    ),
                signCount:
                    (int) $row['signature_counter'],
                userHandle:
                    (string) $row['user_handle'],
                uvInitialized:
                    (bool) $row['uv_initialized'],
                backupEligible:
                    (bool) $row['backup_eligible'],
                backupState:
                    (bool) $row['backup_state'],
                transports:
                    $transports
            );
    }
}


}


function llama_passkey_flow(
    ?PDO $db = null
): \ShipMonk\Passkeys\PasskeyFlow {
    llama_passkey_assert_library_ready();

    $db =
        $db
        ?: db();

    return
        new \ShipMonk\Passkeys\PasskeyFlow(
            rpId:
                LLAMA_PASSKEY_RP_ID,
            rpName:
                LLAMA_PASSKEY_RP_NAME,
            origins:
                [
                    LLAMA_PASSKEY_ORIGIN,
                ],
            store:
                new LlamaPasskeyStore(
                    $db
                ),
            pendingCeremonyStore:
                new LlamaPasskeyPendingStore()
        );
}


function llama_passkey_user_id_from_handle(
    PDO $db,
    string $userHandle
): int {
    $stmt =
        $db->prepare(
            '
            SELECT user_id

            FROM user_passkey_identities

            WHERE user_handle = ?

            LIMIT 1
            '
        );

    $stmt->execute([
        $userHandle
    ]);

    return
        (int) (
            $stmt->fetchColumn()
            ?: 0
        );
}


function llama_passkey_validate_label(
    string $label
): string {
    $label =
        trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $label
            )
            ?? ''
        );

    if (
        mb_strlen(
            $label
        )
        <
        2
        ||
        mb_strlen(
            $label
        )
        >
        60
    ) {
        throw new InvalidArgumentException(
            'Passkey name must be 2 to 60 characters.'
        );
    }

    return $label;
}


function llama_passkey_begin_registration_authorization(
    int $userId,
    string $label
): void {
    $_SESSION[
        'llama_passkey_registration_authorization'
    ] = [
        'user_id' =>
            $userId,
        'label' =>
            llama_passkey_validate_label(
                $label
            ),
        'authorized_at' =>
            time(),
    ];
}


function llama_passkey_registration_authorization(): ?array
{
    $authorization =
        $_SESSION[
            'llama_passkey_registration_authorization'
        ]
        ?? null;

    if (!is_array($authorization)) {
        return null;
    }

    $authorizedAt =
        (int) (
            $authorization['authorized_at']
            ?? 0
        );

    if (
        $authorizedAt < 1
        ||
        $authorizedAt
        <
        (
            time()
            -
            LLAMA_PASSKEY_PENDING_TTL
        )
    ) {
        llama_passkey_clear_registration_authorization();

        return null;
    }

    return $authorization;
}


function llama_passkey_clear_registration_authorization(): void
{
    unset(
        $_SESSION[
            'llama_passkey_registration_authorization'
        ]
    );
}
