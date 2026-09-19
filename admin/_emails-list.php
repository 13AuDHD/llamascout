<?php
$selectedCategoryTemplates =
    $templatesByCategory[
        $selectedCategory
    ]
    ?? [];
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

    <form
        class="email-template-picker"
        method="get"
        action="/emails.php"
    >
        <div class="email-template-picker-fields">

            <label class="email-template-picker-field">
                <span>Category</span>

                <select
                    name="category"
                    aria-label="Email category"
                    onchange="this.form.submit()"
                >
                    <?php foreach (
                        $templatesByCategory
                        as
                        $category => $categoryTemplates
                    ): ?>
                        <?php
                        $categoryCount =
                            count(
                                $categoryTemplates
                            );
                        ?>

                        <option
                            value="<?= moderation_e($category) ?>"
                            <?= $category === $selectedCategory ? 'selected' : '' ?>
                        >
                            <?= moderation_e($category) ?>
                            (<?= number_format($categoryCount) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="email-template-picker-field">
                <span>Email template</span>

                <select
                    name="template"
                    aria-label="Email template"
                    onchange="this.form.submit()"
                >
                    <?php foreach (
                        $selectedCategoryTemplates
                        as
                        $template
                    ): ?>
                        <?php
                        $templateKey =
                            (string) (
                                $template[
                                    'template_key'
                                ]
                                ?? ''
                            );
                        ?>

                        <option
                            value="<?= moderation_e($templateKey) ?>"
                            <?= $templateKey === $selectedTemplateKey ? 'selected' : '' ?>
                        >
                            <?= moderation_e(
                                (string) (
                                    $template['name']
                                    ?? $templateKey
                                )
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

        </div>

        <noscript>
            <button
                class="admin-button email-template-picker-submit"
                type="submit"
            >
                Open Email
            </button>
        </noscript>
    </form>

</section>
