<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   AUTHENTICATION
   app/auth.php

   Authentication rules:
   - Member / Scout accounts may use normal session and
     Remember Me authentication.
   - Owner / Admin accounts require MFA.
   - Privileged Remember Me tokens never restore a session.
   - A role promotion immediately invalidates any ordinary
     session on the next authenticated request unless MFA
     has been completed for that session.
   ========================================================= */


require_once
    __DIR__
    . '/database.php';

require_once
    __DIR__
    . '/mfa.php';

require_once
    __DIR__
    . '/presence.php';


/* =========================================================
   CONSTANTS
   ========================================================= */


const LLAMA_REMEMBER_DAYS =
    30;


const LLAMA_REMEMBER_COOKIE =
    'llamascout_remember';


/* =========================================================
   SESSION SETUP
   ========================================================= */


function start_llama_session(): void {

    if (
        session_status()
        ===
        PHP_SESSION_ACTIVE
    ) {

        return;
    }


    session_name(
        'llamascout_session'
    );


    session_set_cookie_params([
        'lifetime' =>
            0,

        'path' =>
            '/',

        'domain' =>
            '.llamascout.com',

        'secure' =>
            true,

        'httponly' =>
            true,

        'samesite' =>
            'Lax',
    ]);


    session_start();
}


/* =========================================================
   REMEMBER ME
   ========================================================= */


function create_remember_token(
    int $userId
): void {

    if (
        $userId < 1
    ) {

        return;
    }


    /*
     * Privileged accounts must complete MFA whenever a new
     * authenticated browser session is established.
     *
     * We therefore do not create long-lived Remember Me
     * authentication for Owner/Admin accounts.
     */

    if (
        llama_mfa_role_requires_mfa(
            $userId
        )
    ) {

        return;
    }


    $selector =
        bin2hex(
            random_bytes(
                16
            )
        );


    $validator =
        bin2hex(
            random_bytes(
                32
            )
        );


    $tokenHash =
        password_hash(
            $validator,
            PASSWORD_DEFAULT
        );


    if (
        !is_string(
            $tokenHash
        )
        ||
        $tokenHash === ''
    ) {

        throw new RuntimeException(
            'Remember Me token could not be secured.'
        );
    }


    $expires =
        time()
        +
        (
            LLAMA_REMEMBER_DAYS
            *
            86400
        );


    $expiresSql =
        date(
            'Y-m-d H:i:s',
            $expires
        );


    $cleanup =
        db()->prepare(
            '
            DELETE FROM user_remember_tokens

            WHERE user_id = ?
              AND expires_at < CURRENT_TIMESTAMP
            '
        );


    $cleanup->execute([
        $userId
    ]);


    $stmt =
        db()->prepare(
            '
            INSERT INTO user_remember_tokens
            (
                user_id,
                selector,
                token_hash,
                expires_at
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
            '
        );


    $stmt->execute([
        $userId,
        $selector,
        $tokenHash,
        $expiresSql,
    ]);


    setcookie(
        LLAMA_REMEMBER_COOKIE,
        $selector
        .
        ':'
        .
        $validator,
        [
            'expires' =>
                $expires,

            'path' =>
                '/',

            'domain' =>
                '.llamascout.com',

            'secure' =>
                true,

            'httponly' =>
                true,

            'samesite' =>
                'Lax',
        ]
    );
}


/* =========================================================
   CLEAR REMEMBER ME
   ========================================================= */


function clear_remember_cookie(): void {

    if (
        !empty(
            $_COOKIE[
                LLAMA_REMEMBER_COOKIE
            ]
        )
    ) {

        $cookie =
            (string)
            $_COOKIE[
                LLAMA_REMEMBER_COOKIE
            ];


        $parts =
            explode(
                ':',
                $cookie,
                2
            );


        if (
            count(
                $parts
            )
            ===
            2
        ) {

            $selector =
                $parts[0];


            if (
                $selector !== ''
            ) {

                $stmt =
                    db()->prepare(
                        '
                        DELETE FROM user_remember_tokens

                        WHERE selector = ?
                        '
                    );


                $stmt->execute([
                    $selector
                ]);
            }
        }
    }


    setcookie(
        LLAMA_REMEMBER_COOKIE,
        '',
        [
            'expires' =>
                time()
                -
                3600,

            'path' =>
                '/',

            'domain' =>
                '.llamascout.com',

            'secure' =>
                true,

            'httponly' =>
                true,

            'samesite' =>
                'Lax',
        ]
    );


    unset(
        $_COOKIE[
            LLAMA_REMEMBER_COOKIE
        ]
    );
}


/* =========================================================
   REMEMBERED LOGIN
   ========================================================= */


function attempt_remembered_login(): bool {

    if (
        empty(
            $_COOKIE[
                LLAMA_REMEMBER_COOKIE
            ]
        )
    ) {

        return false;
    }


    $cookie =
        (string)
        $_COOKIE[
            LLAMA_REMEMBER_COOKIE
        ];


    $parts =
        explode(
            ':',
            $cookie,
            2
        );


    if (
        count(
            $parts
        )
        !==
        2
    ) {

        clear_remember_cookie();

        return false;
    }


    [
        $selector,
        $validator
    ] =
        $parts;


    if (
        $selector === ''
        ||
        $validator === ''
    ) {

        clear_remember_cookie();

        return false;
    }


    $stmt =
        db()->prepare(
            '
            SELECT
                rt.id,
                rt.user_id,
                rt.token_hash,
                rt.expires_at,
                u.status

            FROM user_remember_tokens rt

            INNER JOIN users u
              ON u.id = rt.user_id

            WHERE rt.selector = ?

            LIMIT 1
            '
        );


    $stmt->execute([
        $selector
    ]);


    $remember =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$remember) {

        clear_remember_cookie();

        return false;
    }


    $userId =
        (int)
        $remember[
            'user_id'
        ];


    if (
        strtotime(
            (string)
            $remember[
                'expires_at'
            ]
        )
        <=
        time()
    ) {

        clear_remember_cookie();

        return false;
    }


    if (
        in_array(
            (string)
            $remember[
                'status'
            ],
            [
                'suspended',
                'disabled',
            ],
            true
        )
    ) {

        clear_remember_cookie();

        return false;
    }


    if (
        !password_verify(
            $validator,
            (string)
            $remember[
                'token_hash'
            ]
        )
    ) {

        clear_remember_cookie();

        return false;
    }


    /*
     * A token created while an account was still an ordinary
     * member must never become an MFA bypass after the account
     * is promoted to Admin or Owner.
     */

    if (
        llama_mfa_role_requires_mfa(
            $userId
        )
    ) {

        llama_mfa_invalidate_remember_tokens(
            $userId
        );


        clear_remember_cookie();


        return false;
    }


    start_llama_session();


    session_regenerate_id(
        true
    );


    $_SESSION[
        'user_id'
    ] =
        $userId;


    $_SESSION[
        'logged_in_at'
    ] =
        time();


    $update =
        db()->prepare(
            '
            UPDATE user_remember_tokens

            SET last_used_at =
                CURRENT_TIMESTAMP

            WHERE id = ?
            '
        );


    $update->execute([
        $remember[
            'id'
        ]
    ]);


    return true;
}


/* =========================================================
   CURRENT USER
   ========================================================= */


function current_user(): ?array {

    start_llama_session();


    $userId =
        (int) (
            $_SESSION[
                'user_id'
            ]
            ?? 0
        );


    if (
        $userId < 1
        &&
        attempt_remembered_login()
    ) {

        $userId =
            (int) (
                $_SESSION[
                    'user_id'
                ]
                ?? 0
            );
    }


    if (
        $userId < 1
    ) {

        return null;
    }


    $stmt =
        db()->prepare(
            '
            SELECT
                id,
                email,
                username,
                display_name,
                timezone,
                status,
                email_verified_at,
                created_at

            FROM users

            WHERE id = ?

            LIMIT 1
            '
        );


    $stmt->execute([
        $userId
    ]);


    $user =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$user) {

        logout_user();

        return null;
    }


    if (
        in_array(
            (string)
            $user[
                'status'
            ],
            [
                'suspended',
                'disabled',
            ],
            true
        )
    ) {

        logout_user();

        return null;
    }


    /*
     * GLOBAL MFA ENFORCEMENT
     *
     * This catches:
     * - an old pre-MFA Owner/Admin session
     * - an account promoted to Admin while already signed in
     * - any code path that accidentally establishes an
     *   ordinary authenticated session for a privileged user
     */

    if (
        llama_mfa_role_requires_mfa(
            $userId
        )
        &&
        (
            !llama_mfa_is_enabled(
                $userId
            )
            ||
            !llama_mfa_session_is_verified(
                $userId
            )
        )
    ) {

        unset(
            $_SESSION[
                'user_id'
            ],
            $_SESSION[
                'logged_in_at'
            ]
        );


        llama_mfa_invalidate_remember_tokens(
            $userId
        );


        clear_remember_cookie();


        return null;
    }


    /*
     * Authenticated presence.
     *
     * This is approximate activity, not a persistent "logged in"
     * flag. Closed tabs and sleeping devices naturally age out.
     * Presence failure must never break authentication.
     */
    try {
        llama_presence_touch(
            db(),
            $userId
        );
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout presence update error: '
            .
            $exception->getMessage()
        );
    }


    /*
     * Run throttled application maintenance only after the
     * authenticated account has passed status and MFA checks.
     *
     * Scout maintenance has its own database-backed interval,
     * so normal requests only do meaningful work when due.
     * A maintenance failure must never lock a member out.
     */
    static $maintenanceAttempted =
        false;

    if (!$maintenanceAttempted) {
        $maintenanceAttempted =
            true;

        try {
            require_once
                __DIR__
                . '/scout-maintenance.php';

            llama_run_scout_renewal_maintenance(
                db()
            );
        } catch (Throwable $exception) {
            error_log(
                'Llama Scout authenticated maintenance error: '
                .
                $exception->getMessage()
            );
        }
    }


    return $user;
}


/* =========================================================
   LOGIN STATUS
   ========================================================= */


function is_logged_in(): bool {

    return
        current_user()
        !==
        null;
}


/* =========================================================
   LOGIN
   ========================================================= */


function attempt_login_result(
    string $login,
    string $password,
    bool $remember = false
): string {

    $login =
        strtolower(
            trim(
                $login
            )
        );


    $stmt =
        db()->prepare(
            '
            SELECT *

            FROM users

            WHERE LOWER(email) = ?
               OR LOWER(username) = ?

            LIMIT 1
            '
        );


    $stmt->execute([
        $login,
        $login,
    ]);


    $user =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$user) {

        return
            'invalid_credentials';
    }


    if (
        !password_verify(
            $password,
            (string)
            $user[
                'password_hash'
            ]
        )
    ) {

        return
            'invalid_credentials';
    }


    $status =
        (string) (
            $user[
                'status'
            ]
            ?? ''
        );


    if (
        $status ===
        'suspended'
    ) {

        return
            'suspended';
    }


    if (
        $status ===
        'disabled'
    ) {

        return
            'disabled';
    }


    $userId =
        (int)
        $user[
            'id'
        ];


    /*
     * Defense in depth:
     * no caller may accidentally complete a password-only
     * login for an Owner/Admin account.
     */

    if (
        llama_mfa_role_requires_mfa(
            $userId
        )
    ) {

        start_llama_session();


        llama_mfa_clear_session_state();


        llama_mfa_begin_login_challenge(
            $userId,
            $remember,
            null
        );


        llama_mfa_invalidate_remember_tokens(
            $userId
        );


        return
            'mfa_required';
    }


    start_llama_session();


    session_regenerate_id(
        true
    );


    $_SESSION[
        'user_id'
    ] =
        $userId;


    $_SESSION[
        'logged_in_at'
    ] =
        time();


    $loginStmt =
        db()->prepare(
            '
            UPDATE users

            SET
                last_login_at =
                    CURRENT_TIMESTAMP,

                dormancy_notice_sent_at =
                    NULL

            WHERE id = ?
            '
        );


    $loginStmt->execute([
        $userId
    ]);


    if ($remember) {

        create_remember_token(
            $userId
        );
    }


    return
        'success';
}


function attempt_login(
    string $login,
    string $password,
    bool $remember = false
): bool {

    return
        attempt_login_result(
            $login,
            $password,
            $remember
        )
        ===
        'success';
}


/* =========================================================
   LOGOUT
   ========================================================= */


function logout_user(): void {

    start_llama_session();


    clear_remember_cookie();


    llama_mfa_clear_session_state();


    $_SESSION =
        [];


    if (
        ini_get(
            'session.use_cookies'
        )
    ) {

        $params =
            session_get_cookie_params();


        setcookie(
            session_name(),
            '',
            [
                'expires' =>
                    time()
                    -
                    42000,

                'path' =>
                    $params[
                        'path'
                    ],

                'domain' =>
                    $params[
                        'domain'
                    ],

                'secure' =>
                    (bool)
                    $params[
                        'secure'
                    ],

                'httponly' =>
                    (bool)
                    $params[
                        'httponly'
                    ],

                'samesite' =>
                    $params[
                        'samesite'
                    ]
                    ?? 'Lax',
            ]
        );
    }


    session_destroy();
}


/* =========================================================
   SAFE LOGIN RETURN URL
   ========================================================= */


function llama_safe_return_url(
    ?string $url
): ?string {

    $url =
        trim(
            (string)
            $url
        );


    if (
        $url === ''
    ) {

        return null;
    }


    $parts =
        parse_url(
            $url
        );


    if (
        !is_array(
            $parts
        )
    ) {

        return null;
    }


    $scheme =
        strtolower(
            (string) (
                $parts[
                    'scheme'
                ]
                ?? ''
            )
        );


    $host =
        strtolower(
            (string) (
                $parts[
                    'host'
                ]
                ?? ''
            )
        );


    if (
        $scheme !==
        'https'
    ) {

        return null;
    }


    if (
        $host !==
        'llamascout.com'
        &&
        !str_ends_with(
            $host,
            '.llamascout.com'
        )
    ) {

        return null;
    }


    return
        $url;
}


function llama_current_request_url(): string {

    $host =
        trim(
            (string) (
                $_SERVER[
                    'HTTP_HOST'
                ]
                ?? 'llamascout.com'
            )
        );


    /*
     * Prevent a malicious Host header from influencing an
     * authentication redirect.
     */

    $hostLower =
        strtolower(
            $host
        );


    if (
        $hostLower !==
        'llamascout.com'
        &&
        !str_ends_with(
            $hostLower,
            '.llamascout.com'
        )
    ) {

        $host =
            'llamascout.com';
    }


    $uri =
        (string) (
            $_SERVER[
                'REQUEST_URI'
            ]
            ?? '/'
        );


    return
        'https://'
        .
        $host
        .
        $uri;
}


/* =========================================================
   REQUIRE LOGIN
   ========================================================= */


function require_login(): void {

    if (
        is_logged_in()
    ) {

        return;
    }


    $returnUrl =
        llama_current_request_url();


    header(
        'Location: https://account.llamascout.com/login.php?return='
        .
        rawurlencode(
            $returnUrl
        )
    );


    exit;
}


/* =========================================================
   EMAIL VERIFICATION
   ========================================================= */


function is_email_verified(
    ?array $user = null
): bool {

    if (
        $user ===
        null
    ) {

        $user =
            current_user();
    }


    if (!$user) {

        return false;
    }


    return
        !empty(
            $user[
                'email_verified_at'
            ]
        );
}


function require_verified_email(): void {

    require_login();


    $user =
        current_user();


    if (
        is_email_verified(
            $user
        )
    ) {

        return;
    }


    header(
        'Location: https://account.llamascout.com/verify-email.php'
    );


    exit;
}


/* =========================================================
   USER ROLES
   ========================================================= */


function user_roles(
    ?int $userId = null
): array {

    if (
        $userId ===
        null
    ) {

        $user =
            current_user();


        if (!$user) {

            return [];
        }


        $userId =
            (int)
            $user[
                'id'
            ];
    }


    if (
        $userId < 1
    ) {

        return [];
    }


    $stmt =
        db()->prepare(
            '
            SELECT
                r.slug

            FROM roles r

            INNER JOIN user_roles ur
              ON ur.role_id = r.id

            WHERE ur.user_id = ?

            ORDER BY r.slug
            '
        );


    $stmt->execute([
        $userId
    ]);


    return
        array_column(
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            ),
            'slug'
        );
}


/* =========================================================
   ROLE CHECK
   ========================================================= */


function user_has_role(
    string $role,
    ?int $userId = null
): bool {

    $roles =
        user_roles(
            $userId
        );


    if (
        in_array(
            $role,
            $roles,
            true
        )
    ) {

        return true;
    }


    /*
     * Master Scout legacy slug compatibility.
     */

    if (
        (
            $role ===
            'master-scout'
            &&
            in_array(
                'master_scout',
                $roles,
                true
            )
        )
        ||
        (
            $role ===
            'master_scout'
            &&
            in_array(
                'master-scout',
                $roles,
                true
            )
        )
    ) {

        return true;
    }


    /*
     * Owner inherits Admin.
     */

    if (
        $role ===
        'admin'
        &&
        in_array(
            'owner',
            $roles,
            true
        )
    ) {

        return true;
    }


    /*
     * Master Scout inherits Scout.
     */

    if (
        $role ===
        'scout'
        &&
        (
            in_array(
                'master-scout',
                $roles,
                true
            )
            ||
            in_array(
                'master_scout',
                $roles,
                true
            )
        )
    ) {

        return true;
    }


    return false;
}


/* =========================================================
   OWNER / ADMIN CHECKS
   ========================================================= */


function user_is_owner(
    ?int $userId = null
): bool {

    return
        user_has_role(
            'owner',
            $userId
        );
}


function user_is_admin(
    ?int $userId = null
): bool {

    return
        user_has_role(
            'admin',
            $userId
        );
}


/* =========================================================
   REQUIRE ROLE
   ========================================================= */


function require_role(
    string $role
): void {

    require_login();


    if (
        user_has_role(
            $role
        )
    ) {

        return;
    }


    header(
        'Location: https://llamascout.com/safety.php?reason=permission'
    );


    exit;
}
