(() => {
    'use strict';

    const STYLE_ID =
        'llama-place-report-presentation-css';

    const STYLE_URL =
        '/css/site/features/place-report-presentation.css';

    const loadStyles = () => {
        if (document.getElementById(STYLE_ID)) {
            return;
        }

        const link =
            document.createElement('link');

        link.id = STYLE_ID;
        link.rel = 'stylesheet';
        link.href = STYLE_URL;

        document.head.appendChild(link);
    };

    const findField = (
        form,
        key
    ) => {
        const escape =
            typeof CSS !== 'undefined'
            && typeof CSS.escape === 'function'
                ? CSS.escape(key)
                : key.replace(
                    /[^a-zA-Z0-9_-]/g,
                    '\\$&'
                );

        const control =
            form.querySelector(
                `[name="${escape}"], [name="${escape}[]"]`
            );

        return control
            ? control.closest(
                '.contribution-field'
            )
            : null;
    };

    const createQuestionGroup = (
        title,
        description
    ) => {
        const section =
            document.createElement(
                'section'
            );

        section.className =
            'place-report-question-group';

        const header =
            document.createElement(
                'header'
            );

        header.className =
            'place-report-question-group-header';

        const heading =
            document.createElement(
                'h4'
            );

        heading.textContent =
            title;

        header.appendChild(
            heading
        );

        if (description) {
            const copy =
                document.createElement(
                    'p'
                );

            copy.textContent =
                description;

            header.appendChild(
                copy
            );
        }

        const grid =
            document.createElement(
                'div'
            );

        grid.className =
            'contribution-grid place-report-question-group-grid';

        section.append(
            header,
            grid
        );

        return {
            section,
            grid,
        };
    };

    const groupSensoryFields = (
        form
    ) => {
        const sensory =
            form.querySelector(
                '[data-report-subsection="sensory_environment"]'
            );

        if (
            !sensory
            || sensory.dataset.presentationGrouped === '1'
        ) {
            return;
        }

        const body =
            sensory.querySelector(
                ':scope > .contribution-section-body'
            );

        const originalGrid =
            body?.querySelector(
                ':scope > .contribution-grid'
            );

        if (
            !body
            || !originalGrid
        ) {
            return;
        }

        const groups = [
            {
                title:
                    'Environment & setting',
                description:
                    'What surrounds the campsite and how exposed or sheltered it feels.',
                keys: [
                    'site_shade',
                    'tree_cover',
                    'site_open_sky',
                    'landscape_primary',
                    'landscape_details',
                    'landscape_views',
                    'environment_water_nearby',
                    'environment_wildlife',
                    'environment_bugs',
                    'environment_wind_exposure',
                    'environment_sun_exposure',
                    'environment_shade',
                    'environment_open_sky',
                    'critter_activity',
                ],
            },
            {
                title:
                    'Daytime sensory experience',
                description:
                    'Rate what someone is likely to experience here during the day.',
                keys: [
                    'daytime_noise',
                    'daytime_traffic',
                    'daytime_crowds',
                    'daytime_privacy',
                    'daytime_light_pollution',
                    'daytime_sensory_comfort',
                    'daytime_social_interaction',
                ],
            },
            {
                title:
                    'Nighttime sensory experience',
                description:
                    'Rate the same campsite after dark so daytime activity does not blur the overnight experience.',
                keys: [
                    'nighttime_noise',
                    'nighttime_traffic',
                    'nighttime_crowds',
                    'nighttime_privacy',
                    'nighttime_light_pollution',
                    'nighttime_sensory_comfort',
                    'nighttime_social_interaction',
                ],
            },
            {
                title:
                    'Specific sensory conditions',
                description:
                    'Call out recurring sounds, smells, visibility, dust, smoke, and other conditions that can matter even when the overall campsite feels comfortable.',
                keys: [
                    'sensory_dust_from_traffic',
                    'sensory_generator_noise',
                    'sensory_aircraft_noise',
                    'sensory_train_noise',
                    'sensory_dog_barking',
                    'sensory_road_noise',
                    'sensory_human_activity',
                    'sensory_wildlife_noise',
                    'sensory_wind_noise',
                    'sensory_smoke_risk',
                    'sensory_strong_odors',
                    'sensory_visual_exposure',
                    'sensory_predictability',
                ],
            },
            {
                title:
                    'Sensory summary',
                description:
                    'Use the narrative summary for context that the individual ratings cannot capture.',
                keys: [
                    'sensory_summary',
                ],
            },
        ];

        groups.forEach(
            (definition) => {
                const fields =
                    definition.keys
                        .map(
                            (key) =>
                                findField(
                                    form,
                                    key
                                )
                        )
                        .filter(Boolean);

                if (!fields.length) {
                    return;
                }

                const group =
                    createQuestionGroup(
                        definition.title,
                        definition.description
                    );

                fields.forEach(
                    (field) => {
                        group.grid.appendChild(
                            field
                        );
                    }
                );

                body.appendChild(
                    group.section
                );
            }
        );

        if (
            !originalGrid.querySelector(
                '.contribution-field'
            )
        ) {
            originalGrid.remove();
        }

        sensory.dataset.presentationGrouped =
            '1';
    };

    const movePhotosToTopLevel = (
        form
    ) => {
        const existingTopLevel =
            form.querySelector(
                '[data-report-main-section="photos"]'
            );

        if (existingTopLevel) {
            return;
        }

        const sitePhotoGroup =
            form.querySelector(
                '[data-report-subsection="site_photos"]'
            );

        const siteSection =
            form.querySelector(
                '[data-report-main-section="site"]'
            );

        if (
            !sitePhotoGroup
            || !siteSection
        ) {
            return;
        }

        const topLevel =
            document.createElement(
                'details'
            );

        topLevel.className =
            'contribution-section place-report-photos-main';

        topLevel.dataset.reportMainSection =
            'photos';

        const summary =
            document.createElement(
                'summary'
            );

        const label =
            document.createElement(
                'span'
            );

        label.className =
            'place-report-photos-main-label';

        label.textContent =
            'Photos';

        summary.appendChild(
            label
        );

        const body =
            document.createElement(
                'div'
            );

        body.className =
            'contribution-section-body';

        topLevel.append(
            summary,
            body
        );

        const groupSummary =
            sitePhotoGroup.querySelector(
                ':scope > summary > span:first-child'
            );

        if (groupSummary) {
            groupSummary.textContent =
                'Place Photos & Notes';
        }

        const nestedPhotoDetails =
            sitePhotoGroup.querySelector(
                '[data-photo-uploader]'
            )?.closest(
                'details.contribution-section'
            );

        if (nestedPhotoDetails) {
            const nestedSummary =
                nestedPhotoDetails.querySelector(
                    ':scope > summary'
                );

            const nestedLabel =
                nestedSummary?.querySelector(
                    ':scope > span:first-child'
                );

            const nestedHelp =
                nestedSummary?.querySelector(
                    ':scope > small'
                );

            if (nestedLabel) {
                /*
                 * Keep the existing camera icon node when present.
                 */
                const icon =
                    nestedLabel.querySelector(
                        'svg, i'
                    );

                nestedLabel.textContent =
                    '';

                if (icon) {
                    nestedLabel.appendChild(
                        icon
                    );
                    nestedLabel.append(
                        ' '
                    );
                }

                nestedLabel.append(
                    'Place photos'
                );
            }

            if (nestedHelp) {
                nestedHelp.textContent =
                    'Entrances, signs, gates, posted rules, campground layout, shared facilities, roads, and overall Place context';
            }
        }

        sitePhotoGroup.dataset.reportSubsection =
            'photos_information';

        body.appendChild(
            sitePhotoGroup
        );

        siteSection.insertAdjacentElement(
            'afterend',
            topLevel
        );
    };

    const initForm = (
        form
    ) => {
        loadStyles();

        groupSensoryFields(
            form
        );

        movePhotosToTopLevel(
            form
        );
    };

    const start = () => {
        document
            .querySelectorAll(
                '.place-report-form'
            )
            .forEach(
                initForm
            );
    };

    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            start,
            {
                once: true,
            }
        );
    } else {
        start();
    }
})();
