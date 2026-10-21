<?php

declare(strict_types=1);

require_once __DIR__ . '/place-map-features.php';
require_once __DIR__ . '/place-campsites.php';
require_once __DIR__ . '/place-site-identity.php';

/** A Scout can propose a polygon. Only moderation can publish it. */
function llama_map_proposal_submit(PDO $db, int $placeId, int $userId, array $input): int
{
    if ($placeId < 1 || $userId < 1) {
        throw new InvalidArgumentException('A Place and signed-in Scout are required.');
    }
    $featureId = max(0, (int) ($input['feature_id'] ?? 0));
    $type = strtolower(trim((string) ($input['feature_type'] ?? '')));
    $label = trim((string) ($input['label'] ?? ''));
    if (!llama_place_map_feature_type_is_valid($type)) {
        throw new InvalidArgumentException('Choose a valid mapped-area type.');
    }
    if ($label === '' || mb_strlen($label) > 255) {
        throw new InvalidArgumentException('Give the mapped location a name (up to 255 characters).');
    }
    if ($featureId > 0) {
        $check = $db->prepare('SELECT id FROM place_map_features WHERE id = ? AND place_id = ? AND is_active = 1 LIMIT 1');
        $check->execute([$featureId, $placeId]);
        if (!$check->fetchColumn()) {
            throw new InvalidArgumentException('That mapped location no longer belongs to this Place.');
        }
    }
    $geometry = $input['geometry'] ?? null;
    if (!is_array($geometry)) {
        throw new InvalidArgumentException('Draw a polygon before submitting.');
    }
    $geometry = llama_place_map_feature_validate_polygon($geometry);
    $vertexCount = count($geometry['coordinates'][0]) - 1;
    $payload = [
        'geometry' => $geometry,
        'source_type' => llama_place_map_feature_validate_source_type((string) ($input['source_type'] ?? 'manual')),
        'accuracy_m' => llama_place_map_feature_validate_accuracy($input['accuracy_m'] ?? null),
        'metadata' => llama_place_map_feature_validate_metadata($input['metadata'] ?? [], $vertexCount),
        'area_details' => is_array($input['area_details'] ?? null) ? $input['area_details'] : [],
        'site_details' => is_array($input['site_details'] ?? null) ? $input['site_details'] : [],
        'rates' => is_array($input['rates'] ?? null) ? $input['rates'] : [],
        'site_classes' => is_array($input['site_classes'] ?? null) ? $input['site_classes'] : [],
    ];
    if ($type === 'camping_site') {
        $siteId = max(0, (int) ($payload['site_details']['proposed_campsite_id'] ?? 0));
        if ($siteId < 1) {
            throw new InvalidArgumentException('Choose the existing Site this perimeter represents.');
        }
        $siteCheck = $db->prepare('SELECT id FROM place_campsites WHERE id = ? AND place_id = ? AND is_active = 1 LIMIT 1');
        $siteCheck->execute([$siteId, $placeId]);
        if (!$siteCheck->fetchColumn()) {
            throw new InvalidArgumentException('This Site does not belong to the selected Place.');
        }
        // The final Site association is still explicitly reviewed by Admin.
    }
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($json) > 150000) {
        throw new InvalidArgumentException('The mapped-area proposal is too large. Simplify the perimeter.');
    }
    // Limit repeated pending proposals per user/Place, without modifying any map record.
    $count = $db->prepare('SELECT COUNT(*) FROM place_map_proposals WHERE place_id = ? AND user_id = ? AND status = "pending"');
    $count->execute([$placeId, $userId]);
    if ((int) $count->fetchColumn() >= 20) {
        throw new InvalidArgumentException('Please wait for review of your other mapped-area proposals.');
    }
    $stmt = $db->prepare('INSERT INTO place_map_proposals (place_id,user_id,feature_id,feature_type,label,proposal_json,status) VALUES (?,?,?,?,?, ?,"pending")');
    $stmt->execute([$placeId, $userId, $featureId ?: null, $type, $label, $json]);
    return (int) $db->lastInsertId();
}

/**
 * Apply one reviewed polygon through the existing map writer. A mapped Site
 * must be linked to a specifically chosen existing campsite, never created
 * from a guessed Site number. No record is published on rejection.
 */
function llama_map_proposal_review(PDO $db, int $proposalId, int $reviewerId, string $decision, int $campsiteId = 0, string $note = ''): array
{
    if (!in_array($decision, ['approve','reject'], true) || $proposalId < 1 || $reviewerId < 1) {
        throw new InvalidArgumentException('Invalid moderation request.');
    }
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM place_map_proposals WHERE id = ? AND status = "pending" LIMIT 1 FOR UPDATE');
        $stmt->execute([$proposalId]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$proposal) {
            throw new InvalidArgumentException('This proposal has already been reviewed or does not exist.');
        }
        $placeId = (int) $proposal['place_id'];
        $published = null;
        if ($decision === 'approve') {
            $data = json_decode((string) $proposal['proposal_json'], true, 512, JSON_THROW_ON_ERROR);
            $type = (string) $proposal['feature_type'];
            if ($type === 'camping_site' && $campsiteId < 1) {
                throw new InvalidArgumentException('Choose the existing campsite this polygon represents before approving.');
            }
            if ($type === 'camping_site' && !empty($proposal['feature_id'])) {
                $linkedStmt = $db->prepare('SELECT id FROM place_campsites WHERE place_id = ? AND map_feature_id = ? AND is_active = 1');
                $linkedStmt->execute([$placeId, (int) $proposal['feature_id']]);
                $linked = array_map('intval', $linkedStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
                if (count($linked) > 1 || ($linked && $linked[0] !== $campsiteId)) {
                    throw new InvalidArgumentException('This mapped Site already belongs to a different campsite. Review the identity conflict before approving.');
                }
            }
            $published = llama_place_map_feature_save(
                $db, $placeId, $reviewerId, $type,
                (string) $proposal['label'], (array) $data['geometry'],
                (int) ($proposal['feature_id'] ?? 0),
                (string) ($data['source_type'] ?? 'manual'),
                $data['accuracy_m'] ?? null, $data['metadata'] ?? [],
                $data['area_details'] ?? [], $data['site_details'] ?? [],
                $data['rates'] ?? [], $data['site_classes'] ?? []
            );
            if ($type === 'camping_site') {
                llama_place_campsite_map_association_save($db, $placeId, (int) $published, $campsiteId);
            } elseif (!empty($proposal['feature_id'])) {
                // Changing an existing mapped Site to an Area cannot retain its Site geometry association.
                llama_place_campsite_map_association_clear($db, $placeId, (int) $published);
            }
        }
        $stmt = $db->prepare('UPDATE place_map_proposals SET status = ?, moderated_by = ?, reviewed_at = UTC_TIMESTAMP(), moderation_note = ?, published_feature_id = ? WHERE id = ? AND status = "pending"');
        $stmt->execute([$decision === 'approve' ? 'approved' : 'rejected', $reviewerId, mb_substr($note, 0, 2000), $published, $proposalId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('The proposal changed during review.');
        }
        $db->commit();
        return ['proposal_id' => $proposalId, 'status' => $decision, 'feature_id' => $published];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

/** An explicit parent Area, never an inferred or duplicated Place. */
function llama_map_link_site_area(PDO $db, int $placeId, int $siteId, int $areaId, int $actorId): void
{
    if ($placeId < 1 || $siteId < 1 || $actorId < 1 || $areaId < 0) {
        throw new InvalidArgumentException('Invalid Place, Site, or Area.');
    }
    $site = $db->prepare('SELECT id FROM place_campsites WHERE place_id = ? AND id = ? AND is_active = 1 LIMIT 1');
    $site->execute([$placeId,$siteId]);
    if (!$site->fetchColumn()) throw new InvalidArgumentException('That Site is not part of this Place.');
    if ($areaId === 0) {
        $stmt = $db->prepare('DELETE FROM place_campsite_area_links WHERE place_id = ? AND campsite_id = ?');
        $stmt->execute([$placeId,$siteId]);
        return;
    }
    $area = $db->prepare('SELECT id FROM place_map_features WHERE place_id = ? AND id = ? AND is_active = 1 AND feature_type IN ("camping_area","parking_area") LIMIT 1');
    $area->execute([$placeId,$areaId]);
    if (!$area->fetchColumn()) throw new InvalidArgumentException('That Area does not belong to this Place.');
    $stmt = $db->prepare('INSERT INTO place_campsite_area_links (place_id,campsite_id,area_feature_id,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE area_feature_id = VALUES(area_feature_id), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$placeId,$siteId,$areaId,$actorId]);
}
