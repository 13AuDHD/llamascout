<?php

declare(strict_types=1);

/**
 * V3: edit this single file to add new Scout Report questions.
 *
 * Example:
 * $add('example_key', 'Example question?', 'site_vehicle', 'tri',
 *      'details.example_key', [
 *          'scope_group' => 'site', // place, place_area, site, all
 *          'report_group' => '<an existing approved report group key>',
 *          'report_visibility' => 'community', // public or community
 *          'allow_unknown' => true,
 *      ]);
 *
 * Field keys must remain stable after publishing. To retire a question,
 * add its key to llama_place_report_retired_question_keys() in fields.php.
 * Existing approved questions retain their authoritative PDF ordering.
 *
 * New questions can use V3 storage without a new legacy SQL column.
 * Actual page permissions and membership gating remain server-side.
 */
function llama_report_register_custom_questions(callable $add): void
{
    // Add future questions here. No page-specific definitions required.
}
