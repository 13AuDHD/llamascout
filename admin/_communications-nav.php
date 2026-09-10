<?php
/*
 * Shared Communications navigation.
 * Included from admin/_header.php.
 */
?>

<p class="admin-nav-label">Communications</p>

<a
    class="<?= admin_shell_nav_class('emails', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/emails.php') ?>"
>
    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
    <span>Emails</span>
</a>

<a
    class="<?= admin_shell_nav_class('email-campaigns', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/email-campaigns.php') ?>"
>
    <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
    <span>Email Campaigns</span>
</a>

<a
    class="<?= admin_shell_nav_class('complimentary-invitations', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/complimentary-invitations.php') ?>"
>
    <i class="fa-solid fa-gift" aria-hidden="true"></i>
    <span>Invitations</span>
</a>

<a
    class="<?= admin_shell_nav_class('email-activity', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/email-activity.php') ?>"
>
    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
    <span>Email Activity</span>
</a>

<a
    class="<?= admin_shell_nav_class('newsletters', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/newsletters.php') ?>"
>
    <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>
    <span>Newsletters</span>

    <?php if ($adminNewsletterQueueCount > 0): ?>
        <b><?= $adminNewsletterQueueCount ?></b>
    <?php endif; ?>
</a>
