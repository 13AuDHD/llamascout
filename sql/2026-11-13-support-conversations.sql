-- Basecamp Support: threaded messages, reusable replies, secure attachment metadata.
-- Apply once in phpMyAdmin on the Llama Scout database.
-- Existing support_requests rows and ticket numbers are unchanged.
-- MariaDB 10.11+.
CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    support_request_id BIGINT UNSIGNED NOT NULL,
    author_role ENUM('customer','staff','system') NOT NULL,
    author_user_id BIGINT UNSIGNED NULL,
    sender_email VARCHAR(254) NULL,
    visibility ENUM('public','internal') NOT NULL DEFAULT 'public',
    channel ENUM('web','email') NOT NULL DEFAULT 'web',
    body_text TEXT NOT NULL,
    send_status ENUM('none','pending','sent','failed') NOT NULL DEFAULT 'none',
    submission_key VARCHAR(64) NULL,
    external_message_id VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_support_message_submission (submission_key),
    UNIQUE KEY uq_support_external_message (external_message_id),
    KEY idx_support_conversation (support_request_id, created_at, id),
    KEY idx_support_outbox (send_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    support_ticket_message_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    storage_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_support_attachment_storage (storage_name),
    KEY idx_support_attachment_message (support_ticket_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_canned_responses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(80) NOT NULL DEFAULT 'General',
    title VARCHAR(150) NOT NULL,
    body_text TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    use_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_support_canned_active (is_active, category, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
