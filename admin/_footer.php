
<?php
$adminFooterScript =
    basename(
        (string) (
            $_SERVER['SCRIPT_NAME']
            ?? ''
        )
    );

if (
    $adminFooterScript === 'user.php'
    && isset($user, $userId, $actorUserId)
): ?>

    <link
        rel="stylesheet"
        href="https://llamascout.com/css/admin/pages/user-email-verification.css"
    >

    <div
        id="admin-user-email-verification-staging"
        hidden
    >
        <?php
        require __DIR__
            . '/_user-email-verification-panel.php';
        ?>
    </div>

    <script>
        (() => {
            const staging =
                document.getElementById(
                    'admin-user-email-verification-staging'
                );

            const panel =
                staging?.querySelector(
                    '[data-user-email-verification-panel]'
                );

            if (!staging || !panel) {
                return;
            }

            const danger =
                document.querySelector(
                    '.admin-danger-panel'
                );

            const auditHeading = [
                ...document.querySelectorAll(
                    '.admin-panel-header h2'
                )
            ].find(
                (heading) =>
                    heading.textContent.trim()
                    === 'Admin Activity'
            );

            const auditPanel =
                auditHeading?.closest(
                    '.admin-panel'
                );

            if (danger?.parentNode) {
                danger.parentNode.insertBefore(
                    panel,
                    danger
                );
            } else if (auditPanel?.parentNode) {
                auditPanel.parentNode.insertBefore(
                    panel,
                    auditPanel
                );
            } else {
                staging.parentNode?.insertBefore(
                    panel,
                    staging
                );
            }

            staging.remove();

            const timezoneInput =
                document.querySelector(
                    '.admin-user-form input[name="timezone"]'
                );

            if (timezoneInput) {
                const timezoneSelect =
                    document.createElement(
                        'select'
                    );

                timezoneSelect.name =
                    'timezone';

                timezoneSelect.required =
                    true;

                timezoneSelect.className =
                    timezoneInput.className;

                const timezoneOptions =
                    <?= json_encode(
                        llama_timezones(),
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ) ?>;

                const currentTimezone =
                    timezoneInput.value.trim();

                Object.entries(
                    timezoneOptions
                ).forEach(
                    ([value, label]) => {
                        const option =
                            document.createElement(
                                'option'
                            );

                        option.value =
                            value;

                        option.textContent =
                            `${label} (${value})`;

                        if (
                            value
                            === currentTimezone
                        ) {
                            option.selected =
                                true;
                        }

                        timezoneSelect.appendChild(
                            option
                        );
                    }
                );

                /*
                 * A legacy value not present in the controlled timezone list
                 * remains visible rather than silently changing it.
                 */
                if (
                    currentTimezone !== ''
                    && !Object.prototype.hasOwnProperty.call(
                        timezoneOptions,
                        currentTimezone
                    )
                ) {
                    const legacy =
                        document.createElement(
                            'option'
                        );

                    legacy.value =
                        currentTimezone;

                    legacy.textContent =
                        `${currentTimezone} (Legacy value)`;

                    legacy.selected =
                        true;

                    timezoneSelect.prepend(
                        legacy
                    );
                }

                timezoneInput.replaceWith(
                    timezoneSelect
                );
            }
        })();
    </script>

<?php endif; ?>

        </main>

    </div>

</div>

<script src="https://llamascout.com/js/admin.js"></script>

<?php if (!empty($adminNeedsPhotoUploader)): ?>
    <script src="https://llamascout.com/js/photo-uploader.js"></script>
<?php endif; ?>


</body>
</html>
