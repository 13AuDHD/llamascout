<?php

declare(strict_types=1);

/**
 * USFS Recreation Site enrichment (read-only remote API).
 * Source records are retained verbatim. Matching is explicit and reviewed.
 * This module does not publish fees or mark campgrounds closed.
 */

const LLAMA_USFS_RECREATION_LAYER =
    'https://apps.fs.usda.gov/fsgisx05/rest/services/wo_nfs_gtac/IVMRecreation/MapServer/0';

function llama_usfs_request(array $params): array
{
    $url = LLAMA_USFS_RECREATION_LAYER . '/query?' . http_build_query(
        $params + ['f' => 'json', 'returnGeometry' => 'false'],
        '', '&', PHP_QUERY_RFC3986
    );
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Could not start the USFS request.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: LlamaScout-USFS-source-evaluation/1.0'],
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false || $status !== 200) {
        throw new RuntimeException('USFS request failed (' . ($error ?: 'HTTP ' . $status) . ').');
    }
    $payload = json_decode((string) $body, true);
    if (!is_array($payload)) {
        throw new RuntimeException('USFS returned invalid JSON.');
    }
    if (isset($payload['error'])) {
        $remote = (array) $payload['error'];
        $code = (int) ($remote['code'] ?? 0);
        $message = trim((string) ($remote['message'] ?? 'Invalid query'));
        $details = array_filter(array_map('strval', (array) ($remote['details'] ?? [])));
        throw new RuntimeException(
            'USFS ArcGIS query error ' . $code . ': ' . $message
            . ($details ? ' (' . implode('; ', array_slice($details, 0, 2)) . ')' : '')
        );
    }
    if (!isset($payload['features']) || !is_array($payload['features'])) {
        throw new RuntimeException('USFS response did not contain the expected features list.');
    }
    return $payload;
}

/**
 * Resolve a selected site using the same name query that produced the
 * review card. This USFS layer rejects numeric site_id WHERE queries on
 * some requests (ArcGIS 400), even though the field is returned in data.
 * Match the server-fetched site_id exactly before saving anything.
 */
function llama_usfs_site_by_id(int $siteId, ?string $placeName = null): ?array
{
    if ($siteId < 1) {
        throw new InvalidArgumentException('A positive USFS site ID is required.');
    }

    if ($placeName !== null && trim($placeName) !== '') {
        foreach (llama_usfs_find_campgrounds($placeName, 20) as $record) {
            if ((int) ($record['site_id'] ?? 0) === $siteId) {
                return $record;
            }
        }
        return null;
    }

    // Kept for callers that already have only a numeric site ID.
    $data = llama_usfs_request([
        'where' => 'site_id = ' . $siteId,
        'outFields' => '*',
        'resultRecordCount' => 2,
    ]);
    if (count($data['features']) > 1) {
        throw new RuntimeException('USFS returned multiple records for one site ID.');
    }
    $attributes = $data['features'][0]['attributes'] ?? null;
    return is_array($attributes) ? $attributes : null;
}

function llama_usfs_fee_candidate(array $attributes): array
{
    $charged = strtoupper(trim((string) ($attributes['fee_charged'] ?? '')));
    $description = trim((string) ($attributes['fee_description'] ?? ''));
    $feeType = trim((string) ($attributes['fee_type'] ?? ''));

    // Nothing beyond clearly applicable overnight camping charges is inferred.
    if ($charged !== 'Y' || $description === '') {
        return ['status' => 'unknown', 'amount' => null, 'reason' => 'No explicit overnight camping charge.'];
    }
    if (!preg_match('/\b(?:overnight\s+use|overnight\s+camping|camping\s+fee|per\s+night)\b/i', $description)) {
        return ['status' => 'unknown', 'amount' => null, 'reason' => 'Fee may be for another use.'];
    }
    if (preg_match('/\b(?:free\s+(?:sites?|camping)|some\s+sites?\s+free)\b/i', $description)) {
        return ['status' => 'unknown', 'amount' => null, 'reason' => 'Possible mixed pricing; manual review required.'];
    }
    preg_match('/\$\s*(\d+(?:\.\d{1,2})?)/', $description, $match);
    $amount = isset($match[1]) ? (float) $match[1] : null;
    if ($amount === null || $amount <= 0) {
        return ['status' => 'unknown', 'amount' => null, 'reason' => 'No positive overnight rate identified.'];
    }
    return [
        'status' => 'paid_candidate',
        'amount' => $amount,
        'reason' => 'Explicit overnight fee found; verify that it applies to this campground.',
        'fee_type' => $feeType,
    ];
}

/**
 * Saves one explicitly matched record for later admin review.
 * Never calls the camping-fee evidence publisher and never changes Place status.
 */
function llama_usfs_enrichment_save(PDO $db, int $placeId, int $siteId, int $actorId): array
{
    if ($placeId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('Place and administrator are required.');
    }
    $placeCheck = $db->prepare('SELECT name FROM places WHERE id = ? LIMIT 1');
    $placeCheck->execute([$placeId]);
    $placeName = $placeCheck->fetchColumn();
    if ($placeName === false) {
        throw new InvalidArgumentException('Place not found.');
    }
    $attributes = llama_usfs_site_by_id($siteId, (string) $placeName);
    if ($attributes === null) {
        throw new RuntimeException('The USFS site ID was not found.');
    }
    if (
        llama_usfs_normalized_campground_name((string) $placeName)
        !== llama_usfs_normalized_campground_name((string) ($attributes['site_name'] ?? ''))
    ) {
        throw new RuntimeException('The selected USFS name no longer matches this Llama Scout campground.');
    }
    if (strtoupper((string) ($attributes['site_type'] ?? '')) !== 'CAMPGROUND') {
        throw new RuntimeException('The selected USFS record is not a campground.');
    }
    $candidate = llama_usfs_fee_candidate($attributes);
    $sourceUrl = trim((string) ($attributes['usda_portal_url'] ?? ''));
    $raw = json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $stmt = $db->prepare(
        'INSERT INTO place_usfs_enrichment
        (place_id, usfs_site_id, usfs_site_cn, usfs_site_name, fee_charged,
        fee_type, fee_description, open_season, operational_hours,
        seasonal_operational_status, op_status_reason, source_url,
        source_json, camping_fee_candidate, camping_rate_candidate,
        synced_at, linked_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?)
        ON DUPLICATE KEY UPDATE
        usfs_site_cn = VALUES(usfs_site_cn),
        usfs_site_name = VALUES(usfs_site_name),
        fee_charged = VALUES(fee_charged),
        fee_type = VALUES(fee_type),
        fee_description = VALUES(fee_description),
        open_season = VALUES(open_season),
        operational_hours = VALUES(operational_hours),
        seasonal_operational_status = VALUES(seasonal_operational_status),
        op_status_reason = VALUES(op_status_reason),
        source_url = VALUES(source_url),
        source_json = VALUES(source_json),
        camping_fee_candidate = VALUES(camping_fee_candidate),
        camping_rate_candidate = VALUES(camping_rate_candidate),
        synced_at = UTC_TIMESTAMP()'
    );
    $stmt->execute([
        $placeId,
        $siteId,
        (string) ($attributes['site_cn'] ?? ''),
        (string) ($attributes['site_name'] ?? ''),
        (string) ($attributes['fee_charged'] ?? ''),
        (string) ($attributes['fee_type'] ?? ''),
        (string) ($attributes['fee_description'] ?? ''),
        (string) ($attributes['open_season'] ?? ''),
        (string) ($attributes['operational_hours'] ?? ''),
        (string) ($attributes['seasonal_operational_status'] ?? ''),
        (string) ($attributes['op_status_reason'] ?? ''),
        $sourceUrl,
        $raw,
        $candidate['status'],
        $candidate['amount'],
        $actorId,
    ]);
    return ['place_name' => (string) $placeName, 'attributes' => $attributes, 'candidate' => $candidate];
}

/**
 * Use the identical query proven by admin/usfs-source-test.php.
 * Normalize the local label before querying, but do not change the
 * remote ArcGIS WHERE expression or request limit.
 */
function llama_usfs_find_campgrounds(string $name, int $limit = 12): array
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $target = llama_usfs_normalized_campground_name($name);
    if (mb_strlen($target) < 3) {
        return [];
    }

    $escapedName = str_replace("'", "''", strtoupper($target));
    $query = llama_usfs_request([
        'where' => "UPPER(site_name) LIKE '%{$escapedName}%'",
        'outFields' => '*',
        'returnGeometry' => 'false',
        'resultRecordCount' => '25',
        'f' => 'json',
    ]);

    $found = [];
    foreach ($query['features'] as $feature) {
        $attributes = $feature['attributes'] ?? null;
        if (!is_array($attributes)) {
            continue;
        }
        $siteId = (int) ($attributes['site_id'] ?? 0);
        if ($siteId < 1 || strtoupper(trim((string) ($attributes['site_type'] ?? ''))) !== 'CAMPGROUND') {
            continue;
        }
        $candidateName = llama_usfs_normalized_campground_name((string) ($attributes['site_name'] ?? ''));
        if ($candidateName === $target || str_contains($candidateName, $target)) {
            $found[$siteId] = $attributes;
        }
    }

    uasort($found, static function (array $a, array $b) use ($target): int {
        $aExact = llama_usfs_normalized_campground_name((string) ($a['site_name'] ?? '')) === $target;
        $bExact = llama_usfs_normalized_campground_name((string) ($b['site_name'] ?? '')) === $target;
        return ($bExact <=> $aExact) ?: strcmp((string) ($a['site_name'] ?? ''), (string) ($b['site_name'] ?? ''));
    });

    return array_slice(array_values($found), 0, $limit);
}

function llama_usfs_normalized_campground_name(string $name): string
{
    $name = mb_strtolower(trim($name));
    $name = preg_replace('/\b(?:campground|campgrounds|camping area)\b/u', ' ', $name) ?? $name;
    $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? $name;
    return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
}
