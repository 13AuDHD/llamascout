<?php

declare(strict_types=1);

// Admin Site policies come from the same canonical Site-eligible catalog.
// Inputs are namespaced so editing a Site cannot edit the parent Place.
// Place-layout questions also eligible for Site scope get their own
        // Site-only controls. They cannot share the parent Place inputs.
        $adminSiteSharedFields = array_intersect_key(
            llama_report_questions_for_scope('site', true),
            array_fill_keys(admin_place_site_shared_question_keys(), true)
        );
        $adminSiteSharedGroups = llama_report_group_selected_questions($adminSiteSharedFields);
        ?>
        <?php if ($adminSiteSharedFields): ?>
            <fieldset data-admin-site-shared-questions hidden disabled>
                <legend>Site rules and policies</legend>
                <p>These answers apply only to the selected Site. Existing Place
                   policies appear as inherited values until you change them.</p>
                <?php foreach ($adminSiteSharedGroups as $group): ?>
                    <?php if (empty($group['fields'])) continue; ?>
                    <section class="admin-site-policy-group" data-admin-site-policy-group>
                        <h3><?= $e($group['label']) ?></h3>
                        <div class="contribution-section-body">
                            <?php foreach ($group['fields'] as $siteField): ?>
                                <?php
                                // In the same HTML form, equal radio names
                                // form one group even if one fieldset is hidden.
                                // Namespace ONLY the Site control names. The
                                // data-report-field ID stays canonical.
                                ob_start();
                                $renderField($siteField, 'admin-site-');
                                $siteControlHtml = (string) ob_get_clean();
                                // Clear/hidden markers also refer to input
                                // names. Scope them to Site so Clear cannot
                                // uncheck a parent Place radio.
                                $siteControlHtml = preg_replace_callback(
                                    '/\\b(?:name|data-place-report-clear|data-place-report-hidden)="([^"]+)"/',
                                    static function (array $match): string {
                                        $attribute = strtok($match[0], '=');
                                        return $attribute . '="admin_site_' . $match[1] . '"';
                                    },
                                    $siteControlHtml
                                );
                                echo $siteControlHtml;
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>
