<?php

declare(strict_types=1);

// This test never connects to the production Llama Scout database.
// It exercises the real invitation creator but never sends an email.
$dsn = (string) getenv('LLAMA_INVITE_TEST_DSN');
if (!str_contains($dsn, 'dbname=llama_invitation_test')) {
    throw new RuntimeException('A dedicated llama_invitation_test database is required.');
}
require_once dirname(__DIR__, 2) . '/app/membership-invitations.php';

$db = new PDO(
    $dsn,
    (string) getenv('LLAMA_INVITE_TEST_USER'),
    (string) getenv('LLAMA_INVITE_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$assert = static function (bool $yes, string $message): void {
    if (!$yes) throw new RuntimeException('FAIL: ' . $message);
};

$db->exec('CREATE TABLE membership_invitations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    grant_duration_days INT NOT NULL,
    reason VARCHAR(255) NULL,
    notes TEXT NULL,
    invited_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    accepted_by BIGINT UNSIGNED NULL,
    revoked_at DATETIME NULL,
    revoked_by BIGINT UNSIGNED NULL,
    revoke_reason VARCHAR(255) NULL
) ENGINE=InnoDB');

$db->exec('CREATE TABLE membership_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(128) NOT NULL,
    subject_type VARCHAR(128) NOT NULL,
    subject_id BIGINT UNSIGNED NULL,
    details_json LONGTEXT NULL
) ENGINE=InnoDB');

$first = llama_create_complimentary_invitation_once(
    $db, 'example@example.com', 365, 'First member', null, 9, 14
);
$assert((int) $first['id'] > 0, 'First invitation must be saved.');

$blocked = false;
try {
    llama_create_complimentary_invitation_once(
        $db, ' EXAMPLE@example.com ', 365, 'First member', null, 9, 14
    );
} catch (InvalidArgumentException $exception) {
    $blocked = str_contains($exception->getMessage(), 'already created');
}
$assert($blocked, 'Accidental second create must be blocked, including case changes.');
$assert((int) $db->query('SELECT COUNT(*) FROM membership_invitations')->fetchColumn() === 1,
    'Double POST must not create a second record.');
$assert((int) $db->query(
    'SELECT COUNT(*) FROM membership_invitations WHERE revoked_at IS NOT NULL'
)->fetchColumn() === 0, 'Double POST must not revoke the original invitation.');

$storedFirst = llama_find_complimentary_invitation($db, (string) $first['token']);
$assert(is_array($storedFirst)
    && llama_complimentary_invitation_status($storedFirst) === 'pending',
    'The first invitation must still be redeemable.');
$assert((int) $db->query('SELECT COUNT(*) FROM membership_audit_log')->fetchColumn() === 1,
    'Only one invite create should be audited.');
$assert($db->query(
    "SELECT IS_USED_LOCK(CONCAT('llama_invite_', SHA1('example@example.com')))"
)->fetchColumn() === null, 'Advisory lock must be released after duplicate rejection.');

// Older deliberate resend still uses the original supersession rules.
$db->exec(
    "UPDATE membership_invitations
     SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 31 SECOND)
     WHERE id = " . (int) $first['id']
);
$second = llama_create_complimentary_invitation_once(
    $db, 'example@example.com', 365, 'First member', null, 9, 14
);
$assert((int) $second['id'] !== (int) $first['id'],
    'A deliberate later resend should create a new invitation.');
$old = llama_find_complimentary_invitation($db, (string) $first['token']);
$new = llama_find_complimentary_invitation($db, (string) $second['token']);
$assert(is_array($old) && is_array($new), 'Both audited invitations must remain stored.');
$assert(llama_complimentary_invitation_status($old) === 'revoked'
    && llama_complimentary_invitation_status($new) === 'pending',
    'Only the newer deliberate resend may replace an earlier pending link.');
$assert((int) $db->query('SELECT COUNT(*) FROM membership_audit_log')->fetchColumn() === 2,
    'Deliberate resends must leave their audit history.');

$other = llama_create_complimentary_invitation_once(
    $db, 'another@example.com', 90, null, null, 9, 14
);
$assert((int) $other['id'] > 0, 'Different recipients must not be blocked.');

echo "PASS: Rapid duplicates leave the original valid, locks release, later resends supersede and audit persists.\n";
