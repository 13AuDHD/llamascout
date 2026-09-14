<?php

declare(strict_types=1);

$adminProfileImage =
    $siteUrl
    . '/images/default-profile.png';

try {
    if (
        $adminUserId > 0
        && function_exists(
            'llama_primary_profile_image'
        )
        && function_exists(
            'llama_profile_image_url'
        )
    ) {
        $adminProfileImage =
            llama_profile_image_url(
                llama_primary_profile_image(
                    db(),
                    $adminUserId
                ),
                $siteUrl
            );
    }
} catch (Throwable $exception) {
    $adminProfileImage =
        $siteUrl
        . '/images/default-profile.png';
}
?>

<link
    rel="stylesheet"
    href="<?= moderation_e(
        $siteUrl
        . '/css/admin/features/topbar-user-menu.css'
    ) ?>"
>

<div
    class="admin-topbar-user-menu"
    data-admin-user-menu
>
    <button
        class="admin-topbar-user-trigger"
        type="button"
        aria-expanded="false"
        aria-haspopup="menu"
        aria-controls="admin-user-dropdown"
        data-admin-user-menu-trigger
    >
        <span class="admin-user-avatar">
            <img
                src="<?= moderation_e(
                    $adminProfileImage
                ) ?>"
                alt=""
            >
        </span>

        <span class="admin-topbar-user-copy">
            <strong>
                <?= moderation_e(
                    $adminDisplayName
                ) ?>
            </strong>

            <?php if (
                $adminUsername !== ''
            ): ?>
                <small>
                    @<?= moderation_e(
                        $adminUsername
                    ) ?>
                </small>
            <?php endif; ?>
        </span>

        <i
            class="admin-topbar-user-chevron"
            aria-hidden="true"
        ><?= llama_icon('chevron-down') ?></i>
    </button>


    <div
        class="admin-topbar-user-dropdown"
        id="admin-user-dropdown"
        role="menu"
        hidden
        data-admin-user-menu-dropdown
    >
        <a
            href="<?= moderation_e(
                $accountUrl . '/'
            ) ?>"
            role="menuitem"
        >
            <i
                
                aria-hidden="true"
            ><?= llama_icon('user') ?></i>

            <span>My Account</span>
        </a>

        <a
            href="<?= moderation_e(
                $accountUrl . '/logout.php'
            ) ?>"
            role="menuitem"
            class="admin-topbar-user-signout"
        >
            <i
                
                aria-hidden="true"
            ><?= llama_icon('logout') ?></i>

            <span>Sign Out</span>
        </a>
    </div>
</div>

<script
    src="<?= moderation_e(
        $siteUrl
        . '/js/admin-user-menu.js'
    ) ?>"
    defer
></script>
