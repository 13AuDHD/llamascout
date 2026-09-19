
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


<?php
if (
    in_array(
        $adminFooterScript,
        [
            'badges.php',
            'badge-admin.php',
        ],
        true
    )
    && function_exists(
        'llama_badge_threshold_metric_labels'
    )
):
    $badgeThresholdMetricLabels =
        llama_badge_threshold_metric_labels();

    $badgeThresholdData = [];

    if (
        $adminFooterScript === 'badges.php'
        && isset($definitions)
        && is_array($definitions)
    ) {
        foreach ($definitions as $definition) {
            $definitionId =
                (int) (
                    $definition['id']
                    ?? 0
                );

            if ($definitionId < 1) {
                continue;
            }

            $badgeThresholdData[
                (string) $definitionId
            ] = [
                'metric' =>
                    (string) (
                        $definition[
                            'threshold_metric'
                        ]
                        ?? ''
                    ),
                'threshold' =>
                    $definition[
                        'threshold_value'
                    ]
                    ?? null,
            ];
        }
    } elseif (
        $adminFooterScript === 'badge-admin.php'
        && isset($badge)
        && is_array($badge)
    ) {
        $definitionId =
            (int) (
                $badge['id']
                ?? 0
            );

        if ($definitionId > 0) {
            $badgeThresholdData[
                (string) $definitionId
            ] = [
                'metric' =>
                    (string) (
                        $badge[
                            'threshold_metric'
                        ]
                        ?? ''
                    ),
                'threshold' =>
                    $badge[
                        'threshold_value'
                    ]
                    ?? null,
            ];
        }
    }
?>

<script>
(() => {
    const metricLabels =
        <?= json_encode(
            $badgeThresholdMetricLabels,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        ) ?>;

    const savedThresholds =
        <?= json_encode(
            $badgeThresholdData,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        ) ?>;

    const numberFormatter =
        new Intl.NumberFormat();

    const formBadgeId = (form) => {
        const input =
            form.querySelector(
                'input[name="badge_id"]'
            );

        return input
            ? input.value.trim()
            : '';
    };

    document
        .querySelectorAll(
            '.admin-badge-definition-form'
        )
        .forEach((form) => {
            const awardType =
                form.querySelector(
                    'select[name="award_type"]'
                );

            const thresholdInput =
                form.querySelector(
                    'input[name="threshold_value"]'
                );

            if (!awardType || !thresholdInput) {
                return;
            }

            const thresholdField =
                thresholdInput.closest(
                    'label'
                );

            if (!thresholdField) {
                return;
            }

            const metricField =
                document.createElement(
                    'label'
                );

            metricField.className =
                'admin-badge-threshold-metric-field';

            const metricTitle =
                document.createElement(
                    'span'
                );

            metricTitle.textContent =
                'Threshold type';

            const metricSelect =
                document.createElement(
                    'select'
                );

            metricSelect.name =
                'threshold_metric';

            const blankOption =
                document.createElement(
                    'option'
                );

            blankOption.value =
                '';

            blankOption.textContent =
                'Special / legacy automatic logic';

            metricSelect.appendChild(
                blankOption
            );

            Object.entries(
                metricLabels
            ).forEach(
                ([value, label]) => {
                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value =
                        value;

                    option.textContent =
                        label;

                    metricSelect.appendChild(
                        option
                    );
                }
            );

            const help =
                document.createElement(
                    'small'
                );

            help.textContent =
                'Choose what this automatic badge counts.';

            metricField.append(
                metricTitle,
                metricSelect,
                help
            );

            thresholdField.before(
                metricField
            );

            const badgeId =
                formBadgeId(form);

            const saved =
                badgeId !== ''
                    ? savedThresholds[
                        badgeId
                    ]
                    : null;

            if (
                saved
                && typeof saved.metric
                    === 'string'
            ) {
                metricSelect.value =
                    saved.metric;
            }

            const updateVisibility = () => {
                const automatic =
                    awardType.value
                    === 'automatic';

                metricField.hidden =
                    !automatic;

                thresholdField.hidden =
                    !automatic;

                metricSelect.disabled =
                    !automatic;

                thresholdInput.disabled =
                    !automatic;
            };

            awardType.addEventListener(
                'change',
                updateVisibility
            );

            updateVisibility();
        });

    document
        .querySelectorAll(
            '.admin-badge-definition-card'
        )
        .forEach((card) => {
            const href =
                card.getAttribute('href')
                || '';

            const match =
                href.match(
                    /[?&]id=(\d+)/
                );

            if (!match) {
                return;
            }

            const saved =
                savedThresholds[
                    match[1]
                ];

            if (
                !saved
                || !saved.metric
                || !metricLabels[
                    saved.metric
                ]
                || !saved.threshold
            ) {
                return;
            }

            const stats =
                card.querySelector(
                    '.admin-badge-definition-stats'
                );

            if (!stats) {
                return;
            }

            const genericThreshold =
                Array.from(
                    stats.querySelectorAll(
                        'span'
                    )
                ).find(
                    (span) =>
                        span.textContent
                            .trim()
                            .toLowerCase()
                            .startsWith(
                                'threshold'
                            )
                );

            if (!genericThreshold) {
                return;
            }

            genericThreshold.textContent =
                metricLabels[
                    saved.metric
                ]
                + ' Â· '
                + numberFormatter.format(
                    Number(
                        saved.threshold
                    )
                );
        });
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
