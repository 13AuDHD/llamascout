<?php
$templatesByCategory = [];

foreach ($emailTemplates as $emailTemplate) {
    $category =
        (string) (
            $emailTemplate['category']
            ?? 'Other'
        );

    $templatesByCategory[$category][] =
        $emailTemplate;
}

$emailTemplateIcons = [
    'verify_email' => 'mail-check',
    'welcome' => 'user-circle',
    'password_reset' => 'key',
    'goodbye' => 'logout',
    'order_confirmation' => 'receipt',
    'order_shipped' => 'truck-delivery',
    'order_delivered' => 'package',
    'refund_confirmation' => 'credit-card',
    'membership_started' => 'circle-check',
    'membership_cancel_scheduled' => 'calendar-event',
    'membership_payment_failed' => 'credit-card',
    'membership_ended' => 'circle-minus',
    'complimentary_started' => 'gift-card',
    'complimentary_ending' => 'hourglass-empty',
    'complimentary_invitation' => 'mail-check',
];
?>

<section class="admin-panel email-template-library">

    <header class="admin-panel-header">
        <div>
            <p>Template Library</p>
            <h2>Emails Llama Scout can send</h2>
        </div>

        <span>
            <?= count($emailTemplates) ?> templates
        </span>
    </header>

    <div class="email-template-groups">

        <?php foreach (
            $templatesByCategory
            as
            $category => $categoryTemplates
        ): ?>

            <?php
            $categoryTemplateCount =
                count($categoryTemplates);

            $categoryEnabledCount =
                count(
                    array_filter(
                        $categoryTemplates,
                        static fn (array $template): bool =>
                            !empty($template['enabled'])
                    )
                );
            ?>

            <details class="email-template-group">

                <summary class="email-template-group-summary">

                    <span class="email-template-group-copy">
                        <strong>
                            <?= moderation_e($category) ?>
                        </strong>

                        <small>
                            <?= number_format($categoryTemplateCount) ?>
                            <?= $categoryTemplateCount === 1 ? 'template' : 'templates' ?>
                            &middot;
                            <?= number_format($categoryEnabledCount) ?>
                            enabled
                        </small>
                    </span>

                    <span
                        class="email-template-group-chevron"
                        aria-hidden="true"
                    >
                        <?= llama_icon('chevron-down') ?>
                    </span>

                </summary>

                <div class="email-template-list">

                    <?php foreach (
                        $categoryTemplates
                        as
                        $template
                    ): ?>
                        <?php
                        $key =
                            (string)
                            $template['template_key'];

                        $isSelected =
                            $key
                            === $selectedTemplateKey;

                        $icon =
                            $emailTemplateIcons[$key]
                            ?? 'mail';
                        ?>

                        <a
                            class="email-template-row<?= $isSelected ? ' is-active' : '' ?>"
                            href="/emails.php?template=<?= rawurlencode($key) ?>"
                        >
                            <span class="email-template-icon">
                                <i aria-hidden="true">
                                    <?= llama_icon($icon) ?>
                                </i>
                            </span>

                            <span class="email-template-copy">
                                <strong>
                                    <?= moderation_e(
                                        (string)
                                        $template['name']
                                    ) ?>
                                </strong>

                                <small>
                                    <?= moderation_e(
                                        (string)
                                        $template['description']
                                    ) ?>
                                </small>
                            </span>

                            <span
                                class="email-template-status<?= !empty($template['enabled']) ? ' is-enabled' : '' ?>"
                            >
                                <?= !empty($template['enabled']) ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </a>

                    <?php endforeach; ?>

                </div>

            </details>

        <?php endforeach; ?>

    </div>

</section>
