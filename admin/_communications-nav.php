<?php
/*
 * Shared Communications navigation.
 * Included from admin/_header.php.
 */
?>

<p class="admin-nav-label">Communications</p>

<a
    class="<?= admin_shell_nav_class('knowledge-base', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/knowledge-base.php') ?>"
>
    <i aria-hidden="true"><?= llama_icon('article') ?></i>
    <span>Knowledge Base</span>
</a>

<a
    class="<?= admin_shell_nav_class('emails', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/emails.php') ?>"
>
    <i aria-hidden="true"><?= llama_icon('mail') ?></i>
    <span>Emails</span>
</a>

<a
    class="<?= admin_shell_nav_class('email-campaigns', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/email-campaigns.php') ?>"
>
    <i aria-hidden="true"><?= llama_icon('speakerphone') ?></i>
    <span>Email Campaigns</span>
</a>

<a
    class="<?= admin_shell_nav_class('complimentary-invitations', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/complimentary-invitations.php') ?>"
>
    <i aria-hidden="true"><?= llama_icon('gift-card') ?></i>
    <span>Invitations</span>
</a>

<a
    class="<?= admin_shell_nav_class('email-activity', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/email-activity.php') ?>"
>
    <i aria-hidden="true"><?= llama_icon('send') ?></i>
    <span>Email Activity</span>
</a>

<a
    class="<?= admin_shell_nav_class('newsletters', $adminActiveNav) ?>"
    href="<?= moderation_e($adminUrl . '/newsletters.php') ?>"
>
    <i aria-hidden="true"><?= llama_icon('news') ?></i>
    <span>Newsletters</span>

    <?php if ($adminNewsletterQueueCount > 0): ?>
        <b><?= $adminNewsletterQueueCount ?></b>
    <?php endif; ?>
</a>
