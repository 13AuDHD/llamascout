ALTER TABLE resource_submissions
    MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'needs-changes') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS review_notes TEXT NULL AFTER reviewed_at;
