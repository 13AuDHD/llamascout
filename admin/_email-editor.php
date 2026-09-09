<?php if (!$selectedTemplate): ?>

    <section class="admin-panel email-editor-empty">
        <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>
        <h2>Select an email template</h2>
        <p>
            Choose an email from the template library to edit,
            preview, or send a test.
        </p>
    </section>

<?php else: ?>

    <?php
    $variables =
        is_array($selectedTemplate['variables'] ?? null)
            ? $selectedTemplate['variables']
            : [];

    $editorSubject =
        (string) ($_POST['subject'] ?? $selectedTemplate['subject']);

    $editorPreheader =
        (string) ($_POST['preheader'] ?? $selectedTemplate['preheader']);

    $editorText =
        (string) ($_POST['text_body'] ?? $selectedTemplate['text_body']);

    $editorHtml =
        (string) ($_POST['html_body'] ?? $selectedTemplate['html_body']);

    $editorEnabled =
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            ? !empty($_POST['is_enabled'])
            : !empty($selectedTemplate['enabled']);

    $previewTemplate =
        array_merge(
            $selectedTemplate,
            [
                'subject' => $editorSubject,
                'preheader' => $editorPreheader,
                'text_body' => $editorText,
                'html_body' => $editorHtml,
                'enabled' => $editorEnabled ? 1 : 0,
            ]
        );

    $previewRendered =
        llama_email_render_record(
            $previewTemplate,
            llama_email_sample_context(
                (string) $selectedTemplate['template_key']
            )
        );
    ?>

    <section class="admin-panel email-editor-panel">

        <header class="admin-panel-header">
            <div>
                <p><?= moderation_e((string) $selectedTemplate['category']) ?></p>
                <h2><?= moderation_e((string) $selectedTemplate['name']) ?></h2>
            </div>

            <span>
                <?= $editorEnabled ? 'Enabled' : 'Disabled' ?>
            </span>
        </header>

        <form method="post" class="email-editor-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= moderation_e(moderation_csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="template_key"
                value="<?= moderation_e((string) $selectedTemplate['template_key']) ?>"
            >

            <div class="email-editor-status-row">
                <label class="email-editor-toggle">
                    <input
                        type="checkbox"
                        name="is_enabled"
                        value="1"
                        <?= $editorEnabled ? 'checked' : '' ?>
                    >
                    <span>
                        <strong>Enabled</strong>
                        <small>
                            Disabled lifecycle templates will not send automatically.
                            Test messages can still be sent.
                        </small>
                    </span>
                </label>

                <div class="email-test-address">
                    Test recipient
                    <strong>dev@llamascout.com</strong>
                </div>
            </div>

            <label>
                <span>Subject</span>
                <input
                    type="text"
                    name="subject"
                    maxlength="190"
                    value="<?= moderation_e($editorSubject) ?>"
                    required
                >
            </label>

            <label>
                <span>Preview text</span>
                <input
                    type="text"
                    name="preheader"
                    maxlength="255"
                    value="<?= moderation_e($editorPreheader) ?>"
                >
            </label>

            <div class="email-variable-box">
                <strong>Available variables</strong>

                <div>
                    <?php foreach ($variables as $variable): ?>
                        <code>{{<?= moderation_e((string) $variable) ?>}}</code>
                    <?php endforeach; ?>
                </div>
            </div>

            <label>
                <span>HTML body</span>
                <textarea
                    name="html_body"
                    rows="20"
                    spellcheck="false"
                    required
                ><?= moderation_e($editorHtml) ?></textarea>
            </label>

            <label>
                <span>Plain-text fallback</span>
                <textarea
                    name="text_body"
                    rows="14"
                    required
                ><?= moderation_e($editorText) ?></textarea>
            </label>

            <div class="email-editor-actions">
                <button
                    class="admin-button"
                    type="submit"
                    name="email_action"
                    value="save"
                >
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    Save Email
                </button>

                <button
                    class="admin-button"
                    type="submit"
                    name="email_action"
                    value="test"
                >
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    Send Test to dev@llamascout.com
                </button>
            </div>

        </form>

    </section>


    <section class="admin-panel email-preview-panel">

        <header class="admin-panel-header">
            <div>
                <p>Preview</p>
                <h2>Rendered test email</h2>
            </div>

            <span>
                Sample member data
            </span>
        </header>

        <div class="email-preview-meta">
            <span>Subject</span>
            <strong><?= moderation_e($previewRendered['subject']) ?></strong>
        </div>

        <iframe
            class="email-preview-frame"
            title="Email preview"
            sandbox
            srcdoc="<?= moderation_e($previewRendered['html']) ?>"
        ></iframe>

    </section>

<?php endif; ?>
