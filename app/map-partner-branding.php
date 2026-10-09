<?php

declare(strict_types=1);

/** Enrich public map rows with approved active partner presentation settings. */
function llama_map_partner_branding(PDO $db, array $places): array
{
    if (!$places) {
        return $places;
    }

    $ids = array_values(array_unique(array_filter(array_map(
        static fn (array $place): int => (int) ($place['id'] ?? 0),
        $places
    ))));
    if (!$ids) {
        return $places;
    }

    $lookup = [];
    foreach (array_chunk($ids, 400) as $chunk) {
        $sql = 'SELECT pp.place_id, pp.relationship_type, pp.is_primary,
                       p.id AS partner_id, p.name AS partner_name,
                       p.primary_color, p.accent_color, p.marker_icon,
                       p.show_map_markers, p.use_branded_cards
                FROM place_partners pp
                JOIN partners p ON p.id = pp.partner_id
                WHERE pp.place_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')
                  AND pp.branding_enabled = 1
                  AND p.status = ?
                  AND p.branding_permitted = 1
                  AND pp.relationship_type IN (?, ?)
                ORDER BY pp.is_primary DESC, p.id ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute([...$chunk, 'active', 'affiliate', 'official']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['place_id'];
            if (isset($lookup[$id])) {
                continue;
            }
            $primary = strtoupper((string) ($row['primary_color'] ?? ''));
            $accent = strtoupper((string) ($row['accent_color'] ?? ''));
            $icon = strtolower(trim((string) ($row['marker_icon'] ?? '')));
            $lookup[$id] = [
                'id' => (int) $row['partner_id'],
                'name' => (string) $row['partner_name'],
                'relationship' => (string) $row['relationship_type'],
                'primary_color' => preg_match('/^#[0-9A-F]{6}$/', $primary) ? $primary : null,
                'accent_color' => preg_match('/^#[0-9A-F]{6}$/', $accent) ? $accent : null,
                'marker_icon' => preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $icon) ? $icon : null,
                'show_map_markers' => (bool) $row['show_map_markers'],
                'use_branded_cards' => (bool) $row['use_branded_cards'],
            ];
        }
    }

    foreach ($places as &$place) {
        $place['map_partner'] = $lookup[(int) ($place['id'] ?? 0)] ?? null;
    }
    unset($place);
    return $places;
}
