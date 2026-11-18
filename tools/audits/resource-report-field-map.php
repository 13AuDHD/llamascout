<?php

declare(strict_types=1);

// Read-only field-by-field audit. Run:
// php tools/audits/resource-report-field-map.php > resource-field-map.md
//
// The output is GENERATED from current production registries; it must not be
// used as a write migration or as an approved new Scout Report schema.
require_once dirname(__DIR__, 2) . '/app/resource-report-field-reconciliation.php';

$inventory = llama_resource_report_field_reconciliation();
$cell = static fn (mixed $value): string => str_replace(
    ['|', "\r", "\n"],
    ['\\|', '', ' '],
    (string) $value
);

echo "# Resource and Resource-backed Facility field reconciliation\n\n";
echo "Generated from the current Resource form, Service catalog and Experience manifest.\n";
echo "No Resource data or schema is changed by this audit.\n\n";

echo "## Resource fields\n\n";
echo "| Field | Storage source | Role | Decision | Contributor form | Read projection |\n";
echo "|---|---|---|---|---|---|\n";
foreach ($inventory['base_fields'] as $key => $field) {
    echo '| ' . $cell($key)
        . ' | ' . $cell($field['storage'])
        . ' | ' . $cell($field['role'])
        . ' | ' . $cell($field['decision'])
        . ' | ' . ($field['form'] ? 'Yes' : 'No')
        . ' | ' . ($field['legacy_detail'] ? 'Yes' : 'No') . " |\n";
}

echo "\n## Service card fields\n\n";
echo "Each Service card is scoped to its own service_type.\n\n";
echo "| Field | Storage source | Role | Decision |\n";
echo "|---|---|---|---|\n";
foreach ($inventory['service_fields'] as $key => $field) {
    echo '| ' . $cell($key)
        . ' | ' . $cell($field['storage'])
        . ' | ' . $cell($field['role'])
        . ' | ' . $cell($field['decision']) . " |\n";
}

echo "\n## Enabled Service types\n\n";
echo "| Key | Label |\n|---|---|\n";
foreach ($inventory['service_types'] as $key => $label) {
    echo '| ' . $cell($key) . ' | ' . $cell($label) . " |\n";
}

echo "\n## Every Experience question occurrence\n\n";
echo "Composite Service / group / question identity is preserved.\n";
echo "A reused canonical Area question is *not* an instruction to write to an Area target.\n\n";
echo "| Service | Group | Question key | Label | Type | Classification | Canonical question key | Legacy source |\n";
echo "|---|---|---|---|---|---|---|---|\n";
foreach ($inventory['experience_fields'] as $path => $field) {
    echo '| ' . $cell($field['service'])
        . ' | ' . $cell($field['group'])
        . ' | ' . $cell($field['key'])
        . ' | ' . $cell($field['label'])
        . ' | ' . $cell($field['type'])
        . ' | ' . $cell($field['classification'])
        . ' | ' . $cell($field['canonical_question_key'] ?? '(decision needed)')
        . ' | ' . $cell($field['source'])
        . " |\n";
}

echo "\n## Decisions before any write migration\n\n";
foreach ($inventory['unresolved'] as $decision) {
    echo '- ' . $decision . "\n";
}
echo "\nA linked Facility stays a `resources.id` target with a `parent_place_id`.\n";
echo "A legacy mapped Facility stays a distinct Area target; do not merge them automatically.\n";
