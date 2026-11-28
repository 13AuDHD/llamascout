<?php
declare(strict_types=1);

/**
 * A small, server-rendered selection of public records. Never use the
 * signed-in viewer's privileges for homepage discovery or expose coordinates.
 */
function llama_homepage_public_links(PDO $db): array
{
    $result = ['places' => [], 'resources' => []];

    try {
        $visibility = llama_place_visibility_sql('p', 0);
        $demoVisibility = llama_partner_demo_visibility_sql(false, 'p');
        $stmt = $db->query("SELECT p.name, p.slug, p.city, p.state
            FROM places p
            WHERE $visibility AND $demoVisibility
              AND TRIM(COALESCE(p.slug,'')) <> ''
              AND TRIM(COALESCE(p.name,'')) <> ''
            ORDER BY CASE WHEN p.status='featured' THEN 0 ELSE 1 END, p.id DESC
            LIMIT 8");
        $result['places'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception($e, 'homepage.public_places');
        }
    }

    try {
        // Resources, unlike Facilities, have no parent Place visibility to resolve.
        $stmt = $db->query("SELECT r.name, r.slug, r.city, r.state_region AS state
            FROM resources r
            WHERE r.is_active=1 AND r.parent_place_id IS NULL
              AND TRIM(COALESCE(r.slug,'')) <> ''
              AND TRIM(COALESCE(r.name,'')) <> ''
            ORDER BY r.id DESC LIMIT 8");
        $result['resources'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception($e, 'homepage.public_resources');
        }
    }

    return $result;
}
