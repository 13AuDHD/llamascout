<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/address-autocomplete.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function resource_lookup_reply(array $result, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    moderation_require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        resource_lookup_reply(['ok' => false, 'error' => 'POST required.'], 405);
    }
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        resource_lookup_reply(['ok' => false, 'error' => 'Session expired. Reload Admin.'], 403);
    }
    $id = filter_var($_POST['resource_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) resource_lookup_reply(['ok' => false, 'error' => 'Select an imported Resource.'], 400);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

    $stmt = db()->prepare("SELECT source_external_id, latitude, longitude FROM resources
        WHERE id = ? AND source_provider = 'OpenStreetMap' AND parent_place_id IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !preg_match('~^(node|way|relation)/([1-9][0-9]{0,18})$~D', (string) $row['source_external_id'], $match)) {
        resource_lookup_reply(['ok' => false, 'error' => 'No valid OpenStreetMap source ID.'], 404);
    }

    // A single explicitly requested object, not a bulk crawl of public APIs.
    $query = '[out:json][timeout:8];' . $match[1] . '(' . $match[2] . ');out tags;';
    $url = 'https://overpass-api.de/api/interpreter?' . http_build_query(['data' => $query]);
    $tags = [];
    $osmFound = false;
    $curl = curl_init($url);
    if ($curl !== false) {
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: LlamaScout/1.0 (https://llamascout.com)'],
        ]);
        $body = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($code === 200 && is_string($body)) {
            $data = json_decode($body, true);
            foreach (($data['elements'] ?? []) as $element) {
                if (($element['type'] ?? '') === $match[1] && (string) ($element['id'] ?? '') === $match[2]) {
                    $osmFound = true;
                    $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
                    break;
                }
            }
        }
    }
    $tag = static function (string ...$keys) use ($tags): string {
        foreach ($keys as $key) {
            $value = $tags[$key] ?? '';
            if (is_string($value) && trim($value) !== '') return trim($value);
        }
        return '';
    };
    $street = $tag('addr:street');
    $house = $tag('addr:housenumber');
    $website = $tag('contact:website', 'website');
    if (!preg_match('~^https?://~i', $website) || !filter_var($website, FILTER_VALIDATE_URL)) $website = '';
    $fields = [
        'street_address' => $street !== '' ? trim($house . ' ' . $street) : $tag('addr:full'),
        'city' => $tag('addr:city', 'addr:town', 'addr:village'),
        'postal_code' => $tag('addr:postcode'),
        'phone' => $tag('contact:phone', 'phone'),
        'website_url' => $website,
    ];
    $reverseFound = false;
    $reverseFields = [];
    // Geoapify is already configured for the existing coordinate lookup.
    // Do not infer a business phone or website from a neighboring address.
    try {
        $address = llama_address_reverse_lookup((float) $row['latitude'], (float) $row['longitude'], true);
        if (is_array($address)) {
            $reverseFound = true;
            foreach (['street_address' => 'address_line_1', 'city' => 'city', 'county' => 'county',
                'state_region' => 'state_name', 'postal_code' => 'postal_code', 'country_code' => 'country_code'] as $field => $key) {
                if (empty($fields[$field]) && !empty($address[$key]) && is_string($address[$key])) {
                    $fields[$field] = $address[$key];
                    $reverseFields[] = $field;
                }
            }
        }
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'admin.resource_lookup_address', [], [RuntimeException::class]);
    }
    resource_lookup_reply(['ok' => true, 'fields' => $fields, 'osm_found' => $osmFound,
        'reverse_found' => $reverseFound, 'reverse_fields' => $reverseFields,
        'opening_hours' => $tag('opening_hours')]);
} catch (Throwable $exception) {
    llama_log_caught_exception($exception, 'admin.resource_lookup');
    resource_lookup_reply(['ok' => false, 'error' => 'Resource lookup temporarily unavailable.'], 503);
}
