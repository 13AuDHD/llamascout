<section class="place-report-section" id="report-place">
    <details class="place-report"<?= $reportError !== null ? ' open' : '' ?>>
        <summary>
            <i aria-hidden="true"><?= llama_icon('flag') ?></i>
            Report a problem with this place
        </summary>

        <div class="place-report-body">
            <?php if ($reportSubmitted): ?>
                <div class="place-report-message is-success" role="status">
                    <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>
                    <p>Thanks. Your report has been submitted for review.</p>
                </div>
            <?php endif; ?>

            <?php if ($reportError !== null): ?>
                <div class="place-report-message is-error" role="alert">
                    <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>
                    <p><?= place_h($reportError) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($userId > 0): ?>
                <p>
                    Tell us what changed or what looks wrong. Reports are reviewed before
                    information on the place is changed.
                </p>

                <form method="post" class="place-report-form">
                    <input type="hidden" name="csrf_token" value="<?= place_h(place_report_csrf_token()) ?>">
                    <input type="hidden" name="place_report_action" value="submit">
                    <input type="hidden" name="photo_stage_token" value="<?= place_h((string) ($_POST['photo_stage_token'] ?? '')) ?>">
                    <input type="hidden" name="photos_json" value="<?= place_h((string) ($_POST['photos_json'] ?? '[]')) ?>">

                    <label for="problem-type">What is the problem?</label>
                    <select id="problem-type" name="problem_type" required>
                        <option value="">Choose one</option>
                        <?php foreach (place_report_problem_types() as $value => $label): ?>
                            <option value="<?= place_h($value) ?>" <?= isset($problemType) && $problemType === $value ? 'selected' : '' ?>>
                                <?= place_h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="report-details">What should we know?</label>
                    <textarea id="report-details" name="report_details" rows="5" maxlength="4000" required><?= place_h($reportDetails ?? '') ?></textarea>

                    <div class="place-report-photo-section">
                        <p>
                            <strong>Closure or status reports require at least one current photo.</strong>
                            Photos are optional for the other report types.
                        </p>

                        <div
                            data-photo-uploader
                            data-photo-context="place-report"
                            data-photo-max="5"
                            data-photo-csrf="<?= place_h(llama_photo_csrf_token()) ?>"
                            data-photo-endpoint="/photo-upload.php"
                            data-photo-title="Photos of the problem"
                            data-photo-help="Add up to 5 photos showing signs, gates, closures, downed trees, washouts, road damage, obstructions, or anything else that helps document the problem."
                        ></div>
                    </div>

                    <button type="submit" class="place-report-submit">
                        <i aria-hidden="true"><?= llama_icon('send') ?></i>
                        Submit report
                    </button>
                </form>
            <?php else: ?>
                <p>You need to be signed in to submit a place report.</p>
                <a class="place-report-signin" href="https://account.llamascout.com/login.php">Sign in</a>
            <?php endif; ?>
        </div>
    </details>
</section>
