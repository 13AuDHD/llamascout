<?php

declare(strict_types=1);

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/admin-users.php';
require_once __DIR__ . '/scout-ranks.php';
require_once __DIR__ . '/stripe.php';


const LLAMA_SCOUT_INVITE_DAYS = 30;
const LLAMA_SCOUT_TRAINING_VERSION = '1';


function llama_scout_onboarding_profile(
    PDO $db,
    int $userId
): ?array {
    $stmt = $db->prepare(
        'SELECT
            sp.*,
            COALESCE(
                NULLIF(i.display_name, ""),
                NULLIF(i.username, ""),
                CASE
                    WHEN sp.invited_by IS NULL THEN NULL
                    ELSE CONCAT("User #", sp.invited_by)
                END
            ) AS inviter_name,
            COALESCE(
                NULLIF(a.display_name, ""),
                NULLIF(a.username, ""),
                CASE
                    WHEN sp.approved_by IS NULL THEN NULL
                    ELSE CONCAT("User #", sp.approved_by)
                END
            ) AS approver_name
         FROM scout_profiles sp
         LEFT JOIN users i
            ON i.id = sp.invited_by
         LEFT JOIN users a
            ON a.id = sp.approved_by
         WHERE sp.user_id = ?
         LIMIT 1'
    );

    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function llama_scout_onboarding_application(
    PDO $db,
    int $profileId,
    int $userId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM scout_applications
         WHERE scout_profile_id = ?
           AND user_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $profileId,
        $userId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function llama_scout_onboarding_training(
    PDO $db,
    int $profileId,
    int $userId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM scout_training
         WHERE scout_profile_id = ?
           AND user_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $profileId,
        $userId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function llama_scout_onboarding_status_label(
    string $status
): string {
    return match ($status) {
        'invited' => 'Scout Invitation',
        'application_started' => 'About You',
        'application_submitted' => 'Application Complete',
        'training' => 'Scout Training',
        'pending_approval' => 'Awaiting Approval',
        'active' => 'Active Scout',
        'inactive' => 'Inactive Scout',
        'declined' => 'Declined',
        'removed' => 'Removed',
        default => ucwords(
            str_replace(
                ['_', '-'],
                ' ',
                $status
            )
        ),
    };
}


function llama_scout_onboarding_step(
    string $status
): int {
    return match ($status) {
        'invited' => 1,
        'application_started' => 2,
        'application_submitted', 'training' => 3,
        'pending_approval' => 4,
        'active' => 5,
        default => 0,
    };
}


function llama_scout_onboarding_href(
    string $status
): string {
    return match ($status) {
        'invited', 'declined' => '/scout-invite.php',
        'application_started' => '/scout-application.php',
        'application_submitted', 'training', 'pending_approval' =>
            '/scout-training.php',
        'active', 'inactive' => '/scout.php',
        default => '/',
    };
}


function llama_scout_invitation_expired(
    array $profile
): bool {
    if (
        (string) ($profile['status'] ?? '')
        !== 'invited'
    ) {
        return false;
    }

    $expires = trim(
        (string) (
            $profile['invitation_expires_at']
            ?? ''
        )
    );

    if ($expires === '') {
        return false;
    }

    $timestamp = strtotime($expires);

    return
        $timestamp !== false
        && $timestamp < time();
}


function llama_scout_onboarding_csrf_token(): string {
    start_llama_session();

    if (
        empty(
            $_SESSION['scout_onboarding_csrf']
        )
    ) {
        $_SESSION['scout_onboarding_csrf'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return
        (string)
        $_SESSION['scout_onboarding_csrf'];
}


function llama_scout_onboarding_verify_csrf(
    string $submitted
): bool {
    $expected =
        llama_scout_onboarding_csrf_token();

    return
        $submitted !== ''
        && hash_equals(
            $expected,
            $submitted
        );
}


function llama_scout_send_invitation_email(
    array $candidate
): bool {
    $email = trim(
        (string) (
            $candidate['email']
            ?? ''
        )
    );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $name = trim(
        (string) (
            $candidate['display_name']
            ?: $candidate['username']
            ?: 'there'
        )
    );

    $url =
        'https://account.llamascout.com/scout-invite.php';

    $subject =
        'You are invited to become a Llama Scout';

    $text =
        "Hi {$name},\n\n"
        . "You've been invited to join the Llama Scout team as a Scout.\n\n"
        . "Scout invitations are offered to community members whose contributions suggest they may be a good fit for the Scout team.\n\n"
        . "Active Scouts receive Scout tools and complimentary full Llama Scout access while their Scout status remains active.\n\n"
        . "Becoming a Scout is optional. You can review the invitation and Scout expectations before deciding.\n\n"
        . "Your invitation expires "
        . LLAMA_SCOUT_INVITE_DAYS
        . " days after it was sent.\n\n"
        . "Review your invitation:\n"
        . $url
        . "\n\n"
        . "Llama Scout\n"
        . "Know the place before you go.";

    $safeName =
        htmlspecialchars(
            $name,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeUrl =
        htmlspecialchars(
            $url,
            ENT_QUOTES,
            'UTF-8'
        );

    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Llama Scout Invitation</title>
</head>
<body style="margin:0;padding:0;background:#f4efe6;font-family:Arial,Helvetica,sans-serif;color:#172822;">
  <div style="max-width:600px;margin:0 auto;padding:40px 20px;">
    <div style="background:#ffffff;border-radius:14px;padding:32px;">
      <p style="margin:0 0 8px;font-size:13px;font-weight:bold;text-transform:uppercase;letter-spacing:.08em;color:#667069;">
        Llama Scout Invitation
      </p>
      <h1 style="margin:0 0 18px;font-size:28px;">You're invited to become a Scout</h1>
      <p>Hi {$safeName},</p>
      <p style="line-height:1.6;">You've been invited to join the Llama Scout team as a Scout.</p>
      <p style="line-height:1.6;">Active Scouts receive Scout tools and complimentary full Llama Scout access while their Scout status remains active.</p>
      <p style="margin:30px 0;">
        <a href="{$safeUrl}" style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:8px;text-decoration:none;font-weight:bold;">
          Review Scout Invitation
        </a>
      </p>
      <p style="color:#667069;font-size:14px;line-height:1.6;">
        Becoming a Scout is optional. Review the invitation and Scout expectations before deciding.
        This invitation expires 30 days after it was sent.
      </p>
      <hr style="border:0;border-top:1px solid #e4e4e0;margin:28px 0;">
      <p style="margin:0;font-size:14px;color:#667069;">Llama Scout<br>Know the place before you go.</p>
    </div>
  </div>
</body>
</html>
HTML;

    return send_llama_mail(
        $email,
        $subject,
        $text,
        $html
    );
}


function llama_scout_admin_eligible_candidates(
    PDO $db
): array {
    return
        $db->query(
            'SELECT
                u.id,
                u.email,
                u.username,
                u.display_name,
                u.email_verified_at,
                sp.id AS scout_profile_id,
                sp.status AS scout_status
             FROM users u
             LEFT JOIN scout_profiles sp
                ON sp.user_id = u.id
             WHERE u.status = "active"
               AND u.email_verified_at IS NOT NULL
               AND (
                    sp.id IS NULL
                    OR sp.status IN (
                        "declined",
                        "invited"
                    )
               )
             ORDER BY
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    u.email
                ) ASC,
                u.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}


function llama_scout_admin_invite(
    PDO $db,
    int $actorUserId,
    int $candidateId
): array {
    if ($candidateId < 1) {
        throw new RuntimeException(
            'Choose a member to invite.'
        );
    }

    $candidateStmt = $db->prepare(
        'SELECT
            id,
            email,
            username,
            display_name,
            status,
            email_verified_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $candidateStmt->execute([
        $candidateId,
    ]);

    $candidate =
        $candidateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {
        throw new RuntimeException(
            'Member account not found.'
        );
    }

    if (
        (string) $candidate['status']
        !== 'active'
    ) {
        throw new RuntimeException(
            'That account cannot receive a Scout invitation.'
        );
    }

    if (
        empty(
            $candidate['email_verified_at']
        )
    ) {
        throw new RuntimeException(
            'The member must verify their email before receiving a Scout invitation.'
        );
    }

    $existing =
        llama_scout_onboarding_profile(
            $db,
            $candidateId
        );

    if (
        $existing
        && !in_array(
            (string) $existing['status'],
            [
                'invited',
                'declined',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'This member already has an active Scout onboarding or Scout record.'
        );
    }

    $db->beginTransaction();

    try {
        if ($existing) {
            $stmt = $db->prepare(
                'UPDATE scout_profiles
                 SET
                    status = "invited",
                    invited_at = CURRENT_TIMESTAMP,
                    invited_by = ?,
                    invitation_expires_at =
                        DATE_ADD(
                            CURRENT_TIMESTAMP,
                            INTERVAL 30 DAY
                        ),
                    application_started_at = NULL,
                    application_submitted_at = NULL,
                    training_started_at = NULL,
                    training_completed_at = NULL,
                    approved_at = NULL,
                    approved_by = NULL,
                    scout_started_at = NULL,
                    active_through = NULL,
                    inactive_at = NULL,
                    removed_at = NULL,
                    removed_by = NULL,
                    removal_reason = NULL,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND user_id = ?'
            );

            $stmt->execute([
                $actorUserId,
                (int) $existing['id'],
                $candidateId,
            ]);

            $profileId =
                (int) $existing['id'];
        } else {
            $stmt = $db->prepare(
                'INSERT INTO scout_profiles (
                    user_id,
                    status,
                    invited_at,
                    invited_by,
                    invitation_expires_at
                 ) VALUES (
                    ?,
                    "invited",
                    CURRENT_TIMESTAMP,
                    ?,
                    DATE_ADD(
                        CURRENT_TIMESTAMP,
                        INTERVAL 30 DAY
                    )
                 )'
            );

            $stmt->execute([
                $candidateId,
                $actorUserId,
            ]);

            $profileId =
                (int) $db->lastInsertId();
        }

        /*
         * A re-invitation starts the application and training
         * cleanly while preserving the Scout profile history.
         */
        $db->prepare(
            'DELETE FROM scout_applications
             WHERE scout_profile_id = ?
               AND user_id = ?'
        )->execute([
            $profileId,
            $candidateId,
        ]);

        $db->prepare(
            'DELETE FROM scout_training
             WHERE scout_profile_id = ?
               AND user_id = ?'
        )->execute([
            $profileId,
            $candidateId,
        ]);

        admin_users_audit(
            $db,
            $actorUserId,
            $candidateId,
            'scout.invited',
            'Invited a member to become a Llama Scout.',
            [
                'scout_profile_id' =>
                    $profileId,

                'expires_days' =>
                    LLAMA_SCOUT_INVITE_DAYS,
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    $sent =
        llama_scout_send_invitation_email(
            $candidate
        );

    return [
        'profile_id' =>
            $profileId,

        'mail_sent' =>
            $sent,

        'candidate' =>
            $candidate,
    ];
}


function llama_scout_accept_invitation(
    PDO $db,
    int $userId
): void {
    $profile =
        llama_scout_onboarding_profile(
            $db,
            $userId
        );

    if (!$profile) {
        throw new RuntimeException(
            'Your Scout invitation could not be found.'
        );
    }

    if (
        (string) $profile['status']
        !== 'invited'
    ) {
        throw new RuntimeException(
            'This invitation has already been responded to.'
        );
    }

    if (
        llama_scout_invitation_expired(
            $profile
        )
    ) {
        throw new RuntimeException(
            'This Scout invitation has expired.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE scout_profiles
         SET
            status = "application_started",
            application_started_at =
                COALESCE(
                    application_started_at,
                    CURRENT_TIMESTAMP
                ),
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND user_id = ?
           AND status = "invited"
           AND (
                invitation_expires_at IS NULL
                OR invitation_expires_at >= CURRENT_TIMESTAMP
           )'
    );

    $stmt->execute([
        (int) $profile['id'],
        $userId,
    ]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException(
            'The invitation could not be accepted. Reload and try again.'
        );
    }
}


function llama_scout_decline_invitation(
    PDO $db,
    int $userId
): void {
    $stmt = $db->prepare(
        'UPDATE scout_profiles
         SET
            status = "declined",
            updated_at = CURRENT_TIMESTAMP
         WHERE user_id = ?
           AND status = "invited"'
    );

    $stmt->execute([
        $userId,
    ]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException(
            'This invitation can no longer be declined.'
        );
    }
}


function llama_scout_save_application(
    PDO $db,
    int $userId,
    array $data
): void {
    $profile =
        llama_scout_onboarding_profile(
            $db,
            $userId
        );

    if (
        !$profile
        || (string) $profile['status']
            !== 'application_started'
    ) {
        throw new RuntimeException(
            'Your Scout application is not currently available.'
        );
    }

    $requiredText = static function (
        string $key,
        string $label,
        int $max
    ) use ($data): string {
        $value = trim(
            (string) (
                $data[$key]
                ?? ''
            )
        );

        if ($value === '') {
            throw new RuntimeException(
                $label .
                ' is required.'
            );
        }

        if (mb_strlen($value) > $max) {
            throw new RuntimeException(
                $label .
                ' is too long.'
            );
        }

        return $value;
    };

    $optionalText = static function (
        string $key,
        int $max
    ) use ($data): ?string {
        $value = trim(
            (string) (
                $data[$key]
                ?? ''
            )
        );

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw new RuntimeException(
                'One of your responses is too long.'
            );
        }

        return $value;
    };

    $legalName =
        $requiredText(
            'legal_name',
            'Legal name',
            150
        );

    $address1 =
        $requiredText(
            'address_line_1',
            'Address',
            150
        );

    $city =
        $requiredText(
            'city',
            'City',
            100
        );

    $stateRegion =
        $requiredText(
            'state_region',
            'State / region',
            100
        );

    $postalCode =
        $requiredText(
            'postal_code',
            'Postal code',
            30
        );

    $country =
        $requiredText(
            'country',
            'Country',
            100
        );

    $agreesAccuracy =
        isset(
            $data['agrees_accuracy']
        );

    $agreesSafety =
        isset(
            $data['agrees_safety']
        );

    $agreesConduct =
        isset(
            $data['agrees_conduct']
        );

    if (
        !$agreesAccuracy
        || !$agreesSafety
        || !$agreesConduct
    ) {
        throw new RuntimeException(
            'All Scout commitments must be acknowledged before submitting.'
        );
    }

    $existing =
        llama_scout_onboarding_application(
            $db,
            (int) $profile['id'],
            $userId
        );

    $values = [
        $legalName,
        $address1,
        $optionalText(
            'address_line_2',
            150
        ),
        $city,
        $stateRegion,
        $postalCode,
        $country,
        $optionalText(
            'phone',
            40
        ),
        $optionalText(
            'why_scout',
            4000
        ),
        $optionalText(
            'travel_experience',
            4000
        ),
        $optionalText(
            'field_experience',
            4000
        ),
        $optionalText(
            'accessibility_experience',
            4000
        ),
        $optionalText(
            'sensory_experience',
            4000
        ),
        1,
        1,
        1,
    ];

    $db->beginTransaction();

    try {
        if ($existing) {
            $stmt = $db->prepare(
                'UPDATE scout_applications
                 SET
                    legal_name = ?,
                    address_line_1 = ?,
                    address_line_2 = ?,
                    city = ?,
                    state_region = ?,
                    postal_code = ?,
                    country = ?,
                    phone = ?,
                    why_scout = ?,
                    travel_experience = ?,
                    field_experience = ?,
                    accessibility_experience = ?,
                    sensory_experience = ?,
                    agrees_accuracy = ?,
                    agrees_safety = ?,
                    agrees_conduct = ?,
                    submitted_at =
                        COALESCE(
                            submitted_at,
                            CURRENT_TIMESTAMP
                        ),
                    reviewed_at = NULL,
                    reviewed_by = NULL,
                    review_notes = NULL,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND scout_profile_id = ?
                   AND user_id = ?'
            );

            $stmt->execute([
                ...$values,
                (int) $existing['id'],
                (int) $profile['id'],
                $userId,
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO scout_applications (
                    scout_profile_id,
                    user_id,
                    legal_name,
                    address_line_1,
                    address_line_2,
                    city,
                    state_region,
                    postal_code,
                    country,
                    phone,
                    why_scout,
                    travel_experience,
                    field_experience,
                    accessibility_experience,
                    sensory_experience,
                    agrees_accuracy,
                    agrees_safety,
                    agrees_conduct,
                    submitted_at
                 ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    CURRENT_TIMESTAMP
                 )'
            );

            $stmt->execute([
                (int) $profile['id'],
                $userId,
                ...$values,
            ]);
        }

        $db->prepare(
            'UPDATE scout_profiles
             SET
                status = "application_submitted",
                application_submitted_at =
                    COALESCE(
                        application_submitted_at,
                        CURRENT_TIMESTAMP
                    ),
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND user_id = ?
               AND status = "application_started"'
        )->execute([
            (int) $profile['id'],
            $userId,
        ]);

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}


function llama_scout_begin_training(
    PDO $db,
    int $userId
): array {
    $profile =
        llama_scout_onboarding_profile(
            $db,
            $userId
        );

    if (
        !$profile
        || !in_array(
            (string) $profile['status'],
            [
                'application_submitted',
                'training',
                'pending_approval',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Scout training is not currently available.'
        );
    }

    $training =
        llama_scout_onboarding_training(
            $db,
            (int) $profile['id'],
            $userId
        );

    if (
        !$training
        && (string) $profile['status']
            !== 'pending_approval'
    ) {
        $stmt = $db->prepare(
            'INSERT INTO scout_training (
                scout_profile_id,
                user_id,
                training_version,
                video_started_at
             ) VALUES (?, ?, ?, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            (int) $profile['id'],
            $userId,
            LLAMA_SCOUT_TRAINING_VERSION,
        ]);

        $training =
            llama_scout_onboarding_training(
                $db,
                (int) $profile['id'],
                $userId
            );
    }

    if (
        (string) $profile['status']
        === 'application_submitted'
    ) {
        $db->prepare(
            'UPDATE scout_profiles
             SET
                status = "training",
                training_started_at =
                    COALESCE(
                        training_started_at,
                        CURRENT_TIMESTAMP
                    ),
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND user_id = ?
               AND status = "application_submitted"'
        )->execute([
            (int) $profile['id'],
            $userId,
        ]);
    }

    return
        $training
        ?: [];
}


function llama_scout_complete_training(
    PDO $db,
    int $userId,
    array $data
): void {
    $profile =
        llama_scout_onboarding_profile(
            $db,
            $userId
        );

    if (
        !$profile
        || (string) $profile['status']
            !== 'training'
    ) {
        throw new RuntimeException(
            'Scout training is not currently ready to complete.'
        );
    }

    foreach (
        [
            'acknowledged_tools' =>
                'Scout tools and access',
            'acknowledged_accuracy' =>
                'accuracy expectations',
            'acknowledged_safety' =>
                'safety expectations',
            'acknowledged_privacy' =>
                'privacy expectations',
        ]
        as
        $key => $label
    ) {
        if (
            !isset(
                $data[$key]
            )
        ) {
            throw new RuntimeException(
                'Acknowledge the ' .
                $label .
                ' before finishing training.'
            );
        }
    }

    $training =
        llama_scout_onboarding_training(
            $db,
            (int) $profile['id'],
            $userId
        );

    if (!$training) {
        throw new RuntimeException(
            'Your Scout training record could not be found.'
        );
    }

    $db->beginTransaction();

    try {
        /*
         * The v2 onboarding uses the complete written orientation
         * below instead of the unfinished legacy training video.
         * The historical video fields are satisfied at completion
         * so the existing schema and readiness checks stay valid.
         */
        $db->prepare(
            'UPDATE scout_training
             SET
                training_version = ?,
                video_started_at =
                    COALESCE(
                        video_started_at,
                        CURRENT_TIMESTAMP
                    ),
                video_completed_at =
                    COALESCE(
                        video_completed_at,
                        CURRENT_TIMESTAMP
                    ),
                acknowledged_tools = 1,
                acknowledged_accuracy = 1,
                acknowledged_safety = 1,
                acknowledged_privacy = 1,
                completed_at =
                    COALESCE(
                        completed_at,
                        CURRENT_TIMESTAMP
                    ),
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND scout_profile_id = ?
               AND user_id = ?'
        )->execute([
            LLAMA_SCOUT_TRAINING_VERSION,
            (int) $training['id'],
            (int) $profile['id'],
            $userId,
        ]);

        $db->prepare(
            'UPDATE scout_profiles
             SET
                status = "pending_approval",
                training_started_at =
                    COALESCE(
                        training_started_at,
                        CURRENT_TIMESTAMP
                    ),
                training_completed_at =
                    COALESCE(
                        training_completed_at,
                        CURRENT_TIMESTAMP
                    ),
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND user_id = ?
               AND status = "training"'
        )->execute([
            (int) $profile['id'],
            $userId,
        ]);

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}


function llama_scout_admin_review(
    PDO $db,
    int $actorUserId,
    int $profileId,
    string $action,
    string $notes
): void {
    $notes = trim($notes);

    if (
        !in_array(
            $action,
            [
                'approve',
                'return',
                'decline',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Invalid Scout review action.'
        );
    }

    if (
        in_array(
            $action,
            [
                'return',
                'decline',
            ],
            true
        )
        && $notes === ''
    ) {
        throw new RuntimeException(
            'Add a review note before returning or declining this onboarding.'
        );
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM scout_profiles
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $profileId,
    ]);

    $profile =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        throw new RuntimeException(
            'Scout profile not found.'
        );
    }

    $userId =
        (int) $profile['user_id'];

    $application =
        llama_scout_onboarding_application(
            $db,
            $profileId,
            $userId
        );

    $training =
        llama_scout_onboarding_training(
            $db,
            $profileId,
            $userId
        );

    $accountStmt =
        $db->prepare(
            'SELECT
                membership_status,
                membership_started_at,
                membership_ends_at,
                stripe_subscription_id
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

    $accountStmt->execute([
        $userId,
    ]);

    $account =
        $accountStmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new RuntimeException(
            'Scout account not found.'
        );
    }

    $hasPaidSubscription =
        trim(
            (string) (
                $account['stripe_subscription_id']
                ?? ''
            )
        ) !== ''
        &&
        in_array(
            strtolower(
                trim(
                    (string) (
                        $account['membership_status']
                        ?? ''
                    )
                )
            ),
            [
                'active',
                'trialing',
                'past_due',
            ],
            true
        );

    $billingTransitionNeeded =
        false;

    $db->beginTransaction();

    try {
        if ($action === 'approve') {
            if (
                (string) $profile['status']
                !== 'pending_approval'
            ) {
                throw new RuntimeException(
                    'This Scout is not awaiting approval.'
                );
            }

            if (
                !$application
                || empty(
                    $application['submitted_at']
                )
                || !$training
                || empty(
                    $training['completed_at']
                )
                || empty(
                    $training['acknowledged_tools']
                )
                || empty(
                    $training['acknowledged_accuracy']
                )
                || empty(
                    $training['acknowledged_safety']
                )
                || empty(
                    $training['acknowledged_privacy']
                )
            ) {
                throw new RuntimeException(
                    'Application and training must be complete before approval.'
                );
            }

            $periodMonths =
                llama_scout_policy_int(
                    $db,
                    'scout_period_months'
                );

            $activeThrough =
                (new DateTimeImmutable('now'))
                ->modify(
                    '+' .
                    $periodMonths .
                    ' months'
                )
                ->format(
                    'Y-m-d H:i:s'
                );

            $profileUpdate =
                $db->prepare(
                    'UPDATE scout_profiles
                     SET
                        status = "active",
                        approved_at = CURRENT_TIMESTAMP,
                        approved_by = ?,
                        scout_started_at =
                            COALESCE(
                                scout_started_at,
                                CURRENT_TIMESTAMP
                            ),
                        active_through = ?,
                        inactive_at = NULL,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?
                       AND user_id = ?
                       AND status = "pending_approval"'
                );

            $profileUpdate->execute([
                $actorUserId,
                $activeThrough,
                $profileId,
                $userId,
            ]);

            if ($profileUpdate->rowCount() !== 1) {
                throw new RuntimeException(
                    'The Scout profile changed before approval could be completed.'
                );
            }

            /*
             * One rank authority:
             * current role + permanent initial approval history.
             */
            llama_change_scout_rank(
                $db,
                $userId,
                LLAMA_SCOUT_RANK_SCOUT,
                LLAMA_RANK_REASON_INITIAL_APPROVAL,
                $actorUserId,
                null,
                $notes !== ''
                    ? $notes
                    : 'Initial Llama Scout approval.'
            );

            /*
             * A non-paying Scout is represented as complimentary
             * through the same active-through date.
             *
             * Paid Stripe billing stays truthful through the
             * already-paid period. Active Scout access is provided
             * independently by app/access.php, then Stripe renewal
             * is scheduled to stop after the DB approval commits.
             */
            if (!$hasPaidSubscription) {
                $db->prepare(
                    'UPDATE users
                     SET
                        membership_status = "complimentary",
                        membership_interval = NULL,
                        membership_started_at =
                            COALESCE(
                                membership_started_at,
                                CURRENT_TIMESTAMP
                            ),
                        membership_ends_at = ?
                     WHERE id = ?'
                )->execute([
                    $activeThrough,
                    $userId,
                ]);
            } else {
                $billingTransitionNeeded =
                    true;
            }

            if ($application) {
                $db->prepare(
                    'UPDATE scout_applications
                     SET
                        reviewed_at = CURRENT_TIMESTAMP,
                        reviewed_by = ?,
                        review_notes = ?
                     WHERE id = ?'
                )->execute([
                    $actorUserId,
                    $notes !== ''
                        ? $notes
                        : null,
                    (int) $application['id'],
                ]);
            }

            $auditAction =
                'scout.onboarding_approved';

            $summary =
                'Approved Scout onboarding and activated Scout access.';

        } elseif ($action === 'return') {
            if (
                !in_array(
                    (string) $profile['status'],
                    [
                        'application_submitted',
                        'training',
                        'pending_approval',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'This Scout onboarding cannot be returned for changes.'
                );
            }

            $db->prepare(
                'UPDATE scout_profiles
                 SET
                    status = "application_started",
                    application_submitted_at = NULL,
                    training_started_at = NULL,
                    training_completed_at = NULL,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND user_id = ?'
            )->execute([
                $profileId,
                $userId,
            ]);

            if ($application) {
                $db->prepare(
                    'UPDATE scout_applications
                     SET
                        submitted_at = NULL,
                        reviewed_at = CURRENT_TIMESTAMP,
                        reviewed_by = ?,
                        review_notes = ?
                     WHERE id = ?'
                )->execute([
                    $actorUserId,
                    $notes,
                    (int) $application['id'],
                ]);
            }

            if ($training) {
                $db->prepare(
                    'UPDATE scout_training
                     SET
                        video_started_at = NULL,
                        video_completed_at = NULL,
                        acknowledged_tools = 0,
                        acknowledged_accuracy = 0,
                        acknowledged_safety = 0,
                        acknowledged_privacy = 0,
                        completed_at = NULL,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([
                    (int) $training['id'],
                ]);
            }

            $auditAction =
                'scout.onboarding_returned';

            $summary =
                'Returned Scout onboarding for changes.';

        } else {
            if (
                !in_array(
                    (string) $profile['status'],
                    [
                        'invited',
                        'application_started',
                        'application_submitted',
                        'training',
                        'pending_approval',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'This Scout onboarding cannot be declined.'
                );
            }

            $db->prepare(
                'UPDATE scout_profiles
                 SET
                    status = "declined",
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND user_id = ?'
            )->execute([
                $profileId,
                $userId,
            ]);

            if ($application) {
                $db->prepare(
                    'UPDATE scout_applications
                     SET
                        reviewed_at = CURRENT_TIMESTAMP,
                        reviewed_by = ?,
                        review_notes = ?
                     WHERE id = ?'
                )->execute([
                    $actorUserId,
                    $notes,
                    (int) $application['id'],
                ]);
            }

            $auditAction =
                'scout.onboarding_declined';

            $summary =
                'Declined Scout onboarding.';
        }

        admin_users_audit(
            $db,
            $actorUserId,
            $userId,
            $auditAction,
            $summary,
            [
                'scout_profile_id' =>
                    $profileId,

                'notes' =>
                    $notes !== ''
                        ? $notes
                        : null,
            ]
        );

        $db->commit();

        if (
            $action === 'approve'
            && $billingTransitionNeeded
        ) {
            try {
                $billingResult =
                    llama_schedule_subscription_end_for_scout(
                        $db,
                        $userId
                    );

                admin_users_audit(
                    $db,
                    $actorUserId,
                    $userId,
                    'scout.billing_transition',
                    'Processed paid membership transition after Scout approval.',
                    [
                        'scout_profile_id' =>
                            $profileId,

                        'result' =>
                            $billingResult,
                    ]
                );

            } catch (Throwable $billingException) {
                error_log(
                    'Llama Scout Scout billing transition error: '
                    .
                    $billingException->getMessage()
                );

                admin_users_audit(
                    $db,
                    $actorUserId,
                    $userId,
                    'scout.billing_transition_failed',
                    'Scout approval completed, but paid subscription renewal could not be stopped automatically.',
                    [
                        'scout_profile_id' =>
                            $profileId,

                        'error' =>
                            $billingException->getMessage(),
                    ]
                );

                throw new RuntimeException(
                    'Scout approval completed, but Stripe renewal could not be stopped automatically. Review this member\'s billing.'
                );
            }
        }

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
