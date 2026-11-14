-- Llama Scout Basecamp Support: inbound Purelymail inbox ingestion.
-- Apply AFTER sql/2026-11-13-support-conversations.sql.
-- This migration does not alter or delete existing support tickets/messages.
-- MariaDB 10.11+

CREATE TABLE IF NOT EXISTS support_inbox_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    uid_validity BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_uid BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_checked_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO support_inbox_state (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);

CREATE TABLE IF NOT EXISTS support_inbound_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uid_validity BIGINT UNSIGNED NOT NULL,
    mailbox_uid BIGINT UNSIGNED NOT NULL,
    message_id VARCHAR(255) NULL,
    sender_email VARCHAR(254) NOT NULL DEFAULT '',
    subject VARCHAR(500) NOT NULL DEFAULT '',
    body_preview TEXT NULL,
    status ENUM('review','imported','ignored') NOT NULL DEFAULT 'review',
    reason VARCHAR(80) NOT NULL DEFAULT '',
    support_request_id BIGINT UNSIGNED NULL,
    support_ticket_message_id BIGINT UNSIGNED NULL,
    attachment_count INT UNSIGNED NOT NULL DEFAULT 0,
    received_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_support_inbox_uid (uid_validity, mailbox_uid),
    KEY idx_support_inbox_review (status, created_at),
    KEY idx_support_inbox_ticket (support_request_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
