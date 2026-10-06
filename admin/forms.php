<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

$notice = '';
$error = '';

$settingDefinitions = [
    'place_report_description_min_characters' => [
        'label' => 'Description minimum',
        'description' =>
            'Minimum characters required before Description counts as answered for Place Report completion.',
        'default' => 1500,
    ],

    'place_report_access_summary_min_characters' => [
        'label' => 'Access Summary minimum',
        'description' =>
            'Minimum characters required before Access Summary counts as answered for Place Report completion.',
        'default' => 1500,
    ],

    'place_report_sensory_summary_min_characters' => [
        'label' => 'Sensory Summary minimum',
        'description' =>
            'Minimum characters required before Sensory Summary counts as answered for Place Report completion.',
        'default' => 1500,
    ],
];

function admin_forms_read_setting(
    PDO $db,
    string $key,
    int $default
): int {
    try {
        $statement =
            $db->prepare(
                'SELECT setting_value
                 FROM site_settings
                 WHERE setting_key = ?
                 LIMIT 1'
            );

        $statement->execute([$key]);

        $value = $statement->fetchColumn();

        if (
            $value !== false
            && is_numeric($value)
        ) {
            return
                max(
                    0,
                    min(
                        10000,
                        (int) $value
                    )
                );
        }
    } catch (Throwable) {
        // Fall back to the canonical default below.
    }

    return $default;
}

$before = [];

foreach (
    $settingDefinitions
    as
    $settingKey => $definition
) {
    $before[$settingKey] =
        admin_forms_read_setting(
            $db,
            $settingKey,
            (int) $definition['default']
        );
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
        === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $submitted =
                (array) (
                    $_POST['requirements']
                    ?? []
                );

            $after = [];

            foreach (
                $settingDefinitions
                as
                $settingKey => $definition
            ) {
                $raw =
                    trim(
                        (string) (
                            $submitted[$settingKey]
                            ?? ''
                        )
                    );

                if (
                    $raw === ''
                    || !preg_match(
                        '/^\d+$/',
                        $raw
                    )
                ) {
                    throw new RuntimeException(
                        (string) $definition['label']
                        . ' must be a whole number from 0 to 10,000.'
                    );
                }

                $value = (int) $raw;

                if (
                    $value < 0
                    || $value > 10000
                ) {
                    throw new RuntimeException(
                        (string) $definition['label']
                        . ' must be between 0 and 10,000.'
                    );
                }

                $after[$settingKey] =
                    $value;
            }

            $db->beginTransaction();

            try {
                foreach (
                    $after
                    as
                    $settingKey => $value
                ) {
                    llama_set_site_setting(
                        $db,
                        $settingKey,
                        (string) $value
                    );
                }

                admin_users_audit(
                    $db,
                    $actorUserId,
                    null,
                    'system.form_requirements_updated',
                    'Updated Place Report form requirements.',
                    [
                        'before' => $before,
                        'after' => $after,
                    ]
                );

                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                throw $exception;
            }

            $before = $after;

            $notice =
                'Place Report form requirements saved.';
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

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
    'Forms';

$adminPageEyebrow =
    'Configuration';

$adminActiveNav =
    'forms';

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

<section class="admin-panel admin-forms-intro">
    <header class="admin-panel-header">
        <div>
            <p>Place Report</p>
            <h2>Completion Requirements</h2>
        </div>
    </header>

    <div class="admin-user-action-box">
        <p>
            These values control when narrative questions count as answered
            for the Place Report completion percentage. Every completion item
            remains equal in value. Set a minimum to 0 to remove the character
            threshold for that field.
        </p>
    </div>
</section>

<form method="post">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(
            moderation_csrf_token()
        ) ?>"
    >

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p>Narratives</p>
                <h2>Character Minimums</h2>
            </div>
        </header>

        <div class="admin-forms-grid">
            <?php foreach (
                $settingDefinitions
                as
                $settingKey => $definition
            ): ?>
                <label class="admin-forms-row">
                    <span>
                        <strong>
                            <?= moderation_e(
                                (string) $definition['label']
                            ) ?>
                        </strong>

                        <small>
                            <?= moderation_e(
                                (string) $definition['description']
                            ) ?>
                        </small>
                    </span>

                    <input
                        type="number"
                        min="0"
                        max="10000"
                        step="1"
                        inputmode="numeric"
                        name="requirements[<?= moderation_e(
                            $settingKey
                        ) ?>]"
                        value="<?= number_format(
                            (int) (
                                $before[$settingKey]
                                ?? $definition['default']
                            ),
                            0,
                            '.',
                            ''
                        ) ?>"
                    >
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="admin-forms-savebar">
        <p>
            Saving changes immediately affects Add Place, moderation,
            browser completion tracking, and server-side completion checks.
            Existing answers are not rewritten.
        </p>

        <button
            class="admin-button"
            type="submit"
        >
            Save form requirements
        </button>
    </div>
</form>

<?php require __DIR__ . '/_footer.php'; ?>
