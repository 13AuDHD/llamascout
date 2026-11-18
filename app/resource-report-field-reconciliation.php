<?php

declare(strict_types=1);

require_once __DIR__ . '/resources.php';

/**
 * Field-by-field Resource / Resource-backed Facility reconciliation.
 *
 * This catalog describes the EXISTING Resource data model. It does not
 * register new approved Scout Report questions, move values between tables,
 * change moderator permissions, or authorize writes into Place/Area/Site.
 *
 * "metadata" is Resource identity/location/administrative context.
 * "service_fact" belongs to one specific enabled Service.
 * "observation_candidate" requires an explicit ownership/semantics decision.
 * "experience" uses the service/group/question composite identity below.
 * "form_marker" is never a persisted observation.
 *
 * When updating the Resource contributor form, update this catalog instead
 * of duplicating its POST allowlist in resource-submissions.php.
 *
 * @return array<string,array{storage:string,role:string,decision:string,form:bool,legacy_detail:bool,read_order?:int}>
 */
function llama_resource_report_base_field_catalog(): array
{
    // Keep submission field order stable. This is a registry, not a schema
    // conversion: storage paths are for reconciliation and audit only.
    return [
        'name' => ['storage' => 'resources.name', 'role' => 'identity', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 1],
        'parent_place_id' => ['storage' => 'resources.parent_place_id', 'role' => 'parent_link', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => false],
        'resource_type' => ['storage' => 'resources.resource_type', 'role' => 'identity', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 2],
        'marker_service_type' => ['storage' => 'resources.marker_service_type', 'role' => 'identity', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 3],
        'status' => ['storage' => 'resources.status', 'role' => 'operational', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 4],
        'coordinates' => ['storage' => 'resources.latitude + resources.longitude', 'role' => 'location', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => false],
        'hours_mode' => ['storage' => 'resources.hours_mode', 'role' => 'hours', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 16],
        'fixed_open_hour' => ['storage' => 'resources.fixed_open_hour', 'role' => 'hours', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 17],
        'fixed_close_hour' => ['storage' => 'resources.fixed_close_hour', 'role' => 'hours', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 18],
        'weekly_hours' => ['storage' => 'resource_weekly_hours[]', 'role' => 'hours', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 20],
        'street_address' => ['storage' => 'resources.street_address', 'role' => 'location', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 8],
        'city' => ['storage' => 'resources.city', 'role' => 'location', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 9],
        'county' => ['storage' => 'resources.county', 'role' => 'location', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 10],
        'state_region' => ['storage' => 'resources.state_region', 'role' => 'location', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 11],
        'postal_code' => ['storage' => 'resources.postal_code', 'role' => 'location', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 12],
        'country_code' => ['storage' => 'resources.country_code', 'role' => 'location', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 13],
        'phone' => ['storage' => 'resources.phone', 'role' => 'contact', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 14],
        'website_url' => ['storage' => 'resources.website_url', 'role' => 'contact', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => true, 'read_order' => 15],
        'services' => ['storage' => 'resource_services[]', 'role' => 'service_collection', 'decision' => 'metadata', 'form' => true, 'legacy_detail' => false],
        'notes' => ['storage' => 'resources.notes', 'role' => 'description', 'decision' => 'review_as_question', 'form' => true, 'legacy_detail' => true, 'read_order' => 5],
        'experience' => ['storage' => 'resources.experience_json', 'role' => 'experience', 'decision' => 'compound_questions', 'form' => true, 'legacy_detail' => false],
        'experience_present' => ['storage' => '(not persisted)', 'role' => 'form_marker', 'decision' => 'not_a_question', 'form' => true, 'legacy_detail' => false],
        // Persisted/hydrated read fields, rather than independent form fields.
        'latitude' => ['storage' => 'resources.latitude', 'role' => 'location', 'decision' => 'metadata', 'form' => false, 'legacy_detail' => true, 'read_order' => 6],
        'longitude' => ['storage' => 'resources.longitude', 'role' => 'location', 'decision' => 'metadata', 'form' => false, 'legacy_detail' => true, 'read_order' => 7],
        'hours_text' => ['storage' => 'resources.hours_text (presented summary)', 'role' => 'hours', 'decision' => 'metadata', 'form' => false, 'legacy_detail' => true, 'read_order' => 19],
    ];
}

/** @return list<string> */
function llama_resource_report_submittable_field_keys(): array
{
    return array_keys(array_filter(
        llama_resource_report_base_field_catalog(),
        static fn (array $field): bool => $field['form']
    ));
}

/** @return list<string> */
function llama_resource_report_legacy_detail_keys(): array
{
    $fields = array_filter(
        llama_resource_report_base_field_catalog(),
        static fn (array $field): bool => $field['legacy_detail']
    );
    // Keep the exact historical insertion order used by the public/read
    // projection, independently of the contributor form's input order.
    uasort($fields, static fn (array $a, array $b): int =>
        ($a['read_order'] ?? PHP_INT_MAX) <=> ($b['read_order'] ?? PHP_INT_MAX)
    );
    return array_keys($fields);
}

/**
 * Service card data is separately scoped to resource_services.service_type.
 * A fee for one Service is not the Resource's generic camping or parking fee.
 *
 * @return array<string,array{storage:string,role:string,decision:string}>
 */
function llama_resource_report_service_field_catalog(): array
{
    return [
        'enabled' => ['storage' => '(contributor UI selection)', 'role' => 'form_marker', 'decision' => 'not_a_question'],
        'service_type' => ['storage' => 'resource_services.service_type', 'role' => 'service_identity', 'decision' => 'metadata'],
        'status' => ['storage' => 'resource_services.status', 'role' => 'service_fact', 'decision' => 'review_as_question'],
        'fee_status' => ['storage' => 'resource_services.fee_status', 'role' => 'service_fact', 'decision' => 'review_as_question'],
        'fee_description' => ['storage' => 'resource_services.fee_description', 'role' => 'service_fact', 'decision' => 'review_as_question'],
        'access_notes' => ['storage' => 'resource_services.access_notes', 'role' => 'service_fact', 'decision' => 'review_as_question'],
        'notes' => ['storage' => 'resource_services.notes', 'role' => 'service_fact', 'decision' => 'review_as_question'],
        // Technical DB metadata, never Scout Report answers.
        'id' => ['storage' => 'resource_services.id', 'role' => 'database_identity', 'decision' => 'not_a_question'],
        'resource_id' => ['storage' => 'resource_services.resource_id', 'role' => 'database_identity', 'decision' => 'not_a_question'],
        'sort_order' => ['storage' => 'resource_services.sort_order', 'role' => 'display', 'decision' => 'not_a_question'],
        'is_active' => ['storage' => 'resource_services.is_active', 'role' => 'publication', 'decision' => 'not_a_question'],
        'created_at' => ['storage' => 'resource_services.created_at', 'role' => 'audit', 'decision' => 'not_a_question'],
        'updated_at' => ['storage' => 'resource_services.updated_at', 'role' => 'audit', 'decision' => 'not_a_question'],
    ];
}

/**
 * Inspect the actual Experience manifest instead of copying question lists.
 * Each occurrence is identified by Service / group / key. A single question
 * (e.g. facility_cleanliness) can be independently answered for several
 * services and restroom groups.
 *
 * @return array<string,array<string,mixed>> keyed by composite answer path
 */
function llama_resource_report_experience_field_catalog(): array
{
    $manifest = llama_resource_experience_manifest();
    $canonical = llama_report_questions();
    $result = [];
    foreach ($manifest as $service => $groups) {
        foreach ($groups as $group => $definition) {
            foreach ($definition['questions'] as $key => $field) {
                $path = $service . '/' . $group . '/' . $key;
                if (isset($result[$path])) {
                    throw new LogicException('Duplicate Resource Experience answer path: ' . $path);
                }
                $existing = $canonical[$key] ?? null;
                if (is_array($existing)) {
                    // This is ALREADY a canonical Area question. Detect any
                    // catalog drift rather than assigning a new question ID.
                    foreach (['label', 'type'] as $property) {
                        if (($existing[$property] ?? null) !== ($field[$property] ?? null)) {
                            throw new LogicException('Canonical Experience question drift: ' . $path);
                        }
                    }
                    if ((array) ($existing['options'] ?? []) !== (array) ($field['options'] ?? [])) {
                        throw new LogicException('Canonical Experience options drift: ' . $path);
                    }
                }
                $result[$path] = [
                    'source' => 'resources.experience_json.' . $service . '.' . $group . '.' . $key,
                    'service' => (string) $service,
                    'group' => (string) $group,
                    'key' => (string) $key,
                    'label' => (string) ($field['label'] ?? $key),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'options' => (array) ($field['options'] ?? []),
                    'classification' => $existing === null
                        ? 'service_only_question' : 'canonical_area_question',
                    'canonical_question_key' => $existing === null ? null : (string) $key,
                    'decision' => $existing === null
                        ? 'approved_existing_resource_facility_keep_compound_identity'
                        : 'preserve_compound_identity_before_any_migration',
                ];
            }
        }
    }
    return $result;
}

/**
 * Review inventory. No database connection; no schema or write assumptions.
 * Adds one entry per Resource input, Service card field and Experience answer
 * path, with a clear marker where a semantic decision is still required.
 *
 * @return array<string,mixed>
 */
function llama_resource_report_field_reconciliation(): array
{
    $base = llama_resource_report_base_field_catalog();
    $service = llama_resource_report_service_field_catalog();
    $experience = llama_resource_report_experience_field_catalog();
    return [
        'base_fields' => $base,
        'service_fields' => $service,
        'service_types' => llama_resource_service_types(),
        'experience_fields' => $experience,
        'unresolved' => [
            'Resource description notes: is this a report observation or reference metadata?',
            'Service fee, availability and access notes: establish review/provenance policy per Service, not per parent Place.',
            'Existing Resource-only Experience question ownership is approved; validate separate Resource/Facility ledger publication and provenance before writing history.',
            'Repeated Area question keys across Services/restroom groups: preserve their compound identity, not just the bare question key.',
            'Historical Resource authorship and observation dates: do not infer missing provenance from administrative update timestamps.',
        ],
    ];
}
