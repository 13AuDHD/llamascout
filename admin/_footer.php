
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
