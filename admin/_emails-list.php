<?php
$templatesByCategory = [];

foreach ($emailTemplates as $emailTemplate) {
    $category =
        (string) ($emailTemplate['category'] ?? 'Other');

    $templatesByCategory[$category][] =
        $emailTemplate;
}
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

    <?php foreach ($templatesByCategory as $category => $categoryTemplates): ?>

        <section class="email-template-group">

            <h3><?= moderation_e($category) ?></h3>

            <div class="email-template-list">

                <?php foreach ($categoryTemplates as $template): ?>
                    <?php
                    $key = (string) $template['template_key'];
                    $isSelected = $key === $selectedTemplateKey;
                    ?>

                    <a
                        class="email-template-row<?= $isSelected ? ' is-active' : '' ?>"
                        href="/emails.php?template=<?= rawurlencode($key) ?>"
                    >
                        <span class="email-template-icon">
                            <i
                                class="<?= match ($key) {
                                    'verify_email' => 'fa-solid fa-envelope-circle-check',
                                    'welcome' => 'fa-solid fa-hand-sparkles',
                                    'password_reset' => 'fa-solid fa-key',
                                    'goodbye' => 'fa-solid fa-door-open',
                                    default => 'fa-solid fa-envelope',
                                } ?>"
                                aria-hidden="true"
                            ></i>
                        </span>

                        <span class="email-template-copy">
                            <strong>
                                <?= moderation_e((string) $template['name']) ?>
                            </strong>

                            <small>
                                <?= moderation_e((string) $template['description']) ?>
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

        </section>

    <?php endforeach; ?>

</section>
