<?php

declare(strict_types=1);

/**
 * Regressions for a blank complimentary-invite page.
 *
 * Render the actual invitation template against synthetic status states.
 * No database or real tokens needed, no emails sent, and no access granted.
 */
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/account/complimentary-invite.php');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
};

$iconsRequire = "require_once dirname(__DIR__) . '/app/icons.php';";
$assert(str_contains((string) $source, $iconsRequire),
    'Invitation endpoint must load the SVG icon renderer explicitly.');
$firstIcon = strpos((string) $source, "llama_icon('gift')");
$assert($firstIcon !== false && strpos((string) $source, $iconsRequire) < $firstIcon,
    'Icon definition must load before the first rendered badge.');

require_once $root . '/app/branding.php';
require_once $root . '/app/icons.php';

function complimentary_invite_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$marker = "?>\n<!doctype html>";
$markerAt = strpos((string) $source, $marker);
$assert($markerAt !== false, 'Invitation page template boundary changed.');

$template = substr((string) $source, $markerAt + 2);
$cases = [
    'pending_signed_out' => [
        'accepted' => false, 'error' => '',
        'currentUserId' => 0, 'emailMatches' => false, 'emailVerified' => false,
        'alreadyHasMemberAccess' => false,
        'expected' => ['You’ve been invited.', 'Sign In to Accept', 'Create an Account'],
    ],
    'pending_signed_in' => [
        'accepted' => false, 'error' => '',
        'currentUserId' => 21, 'emailMatches' => true, 'emailVerified' => true,
        'alreadyHasMemberAccess' => false,
        'expected' => ['You’ve been invited.', 'Activate Complete Access'],
    ],
    'already_accepted' => [
        'accepted' => true, 'error' => '',
        'currentUserId' => 21, 'emailMatches' => true, 'emailVerified' => true,
        'alreadyHasMemberAccess' => true,
        'expected' => ['Complete Access is yours.', 'Go to My Account'],
    ],
    'revoked_or_expired' => [
        'accepted' => false, 'error' => 'This complimentary membership invitation has been revoked.',
        'currentUserId' => 0, 'emailMatches' => false, 'emailVerified' => false,
        'alreadyHasMemberAccess' => false,
        'expected' => ['Invitation unavailable', 'This complimentary membership invitation has been revoked.'],
    ],
    'unverified_account' => [
        'accepted' => false, 'error' => '',
        'currentUserId' => 21, 'emailMatches' => true, 'emailVerified' => false,
        'alreadyHasMemberAccess' => false,
        'expected' => ['Verify My Email', 'You’ve been invited.'],
    ],
    'different_email' => [
        'accepted' => false, 'error' => '',
        'currentUserId' => 21, 'emailMatches' => false, 'emailVerified' => true,
        'alreadyHasMemberAccess' => false,
        'expected' => ['but this invitation belongs to', 'Sign Out'],
    ],
];

$render = static function (array $state) use ($template): string {
    $accepted = $state['accepted'];
    $error = $state['error'];
    $currentUserId = $state['currentUserId'];
    $emailMatches = $state['emailMatches'];
    $emailVerified = $state['emailVerified'];
    $alreadyHasMemberAccess = $state['alreadyHasMemberAccess'];
    $durationDays = 365;
    $inviteEmail = 'fixture@example.com';
    $currentEmail = 'fixture@example.com';
    $expiresAt = 'November 29, 2026';
    $reason = 'Test fixture invitation';
    $notice = 'Invitation was accepted.';
    $loginUrl = '/login.php?return=fixture';
    $registerUrl = '/register.php?invite=fixture';
    $csrfToken = 'fixture-not-a-real-token';
    $token = 'fixture-not-a-real-token';
    ob_start();
    try {
        eval('?>' . $template);
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
};

foreach ($cases as $name => $case) {
    $html = $render($case);
    $assert(str_contains($html, '<!doctype html>'), "$name: no opening HTML.");
    $assert(str_contains($html, '</html>'), "$name: rendering stopped mid-page.");
    $assert(str_contains($html, 'Complimentary Membership'), "$name: badge missing.");
    $assert(str_contains($html, '<svg'), "$name: local SVG icons failed to render.");
    foreach ($case['expected'] as $expected) {
        $assert(str_contains($html, $expected), "$name: missing '$expected'.");
    }
    echo "PASS: Invitation template renders $name.\n";
}
