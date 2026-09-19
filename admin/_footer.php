
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


    <script>
        (() => {

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
