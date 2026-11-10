<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-place-sites.php';
require_once __DIR__ . '/admin-place-areas.php';
require_once __DIR__ . '/admin-place-report.php';
require_once __DIR__ . '/place-verifications.php';
require_once __DIR__ . '/place-site-identity.php';

/** POST controller for the existing Admin Place page. */
function admin_place_handle_actions(PDO $db, int $actorUserId, int $placeId): array
{
    $backgroundPlaceSave = admin_place_background_save_requested();
    $notice = '';
    $error = '';
    $action = '';

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        if ((string) ($_GET['saved'] ?? '') === 'report') {
            $notice = 'Place Report saved.';
        } elseif ((string) ($_GET['saved'] ?? '') === 'season') {
            $notice = 'Operating season saved.';
        } elseif ((string) ($_GET['saved'] ?? '') === 'season-answers') {
            $notice = 'Seasonal answers saved.';
        } elseif ((string) ($_GET['saved'] ?? '') === 'season-builder') {
            $notice = 'Seasonal changes saved for selected reporting targets.';
        } elseif ((string) ($_GET['saved'] ?? '') === 'season-bulk-sites') {
            $changedSites = max(0, (int) ($_GET['changed'] ?? 0));
            $notice = $changedSites > 0
                ? 'Seasonal status updated for ' . $changedSites . ' Sites.'
                : 'Selected Sites already had the requested seasonal status.';
        }
        return ['notice' => $notice, 'error' => $error, 'action' => $action];
    }

    $action = (string) ($_POST['place_admin_action'] ?? '');
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = $action === 'save-report'
            ? 'Your session token expired. Your Place Report changes are preserved below. Try Save Place Report again.'
            : 'Your session token expired. Reload and try again.';
        if ($backgroundPlaceSave && $action === 'save-report') {
            admin_place_background_save_respond(403, [
                'success' => false,
                'message' => $error,
                'photos_added' => 0,
            ]);
        }
        return ['notice' => $notice, 'error' => $error, 'action' => $action];
    }

    try {
        if (in_array($action, ['save-season-builder', 'save-season-prices'], true)) {
            require_once __DIR__ . '/place-report/season-builder.php';
            $seasonId = (int) ($_POST['season_id'] ?? 0);
            $selected = $_POST['season_targets'] ?? null;
            $hasRates = isset($_POST['place_rate_cards_json']);
            $db->beginTransaction();
            try {
                if ($action === 'save-season-builder') {
                    llama_season_builder_save(
                        $db, $placeId, $seasonId, $actorUserId, $selected,
                        $_POST['season_changes'] ?? [], $_POST['season_removals'] ?? [],
                        $_POST['season_answer_source_url'] ?? null, $hasRates
                    );
                }
                if ($hasRates) {
                    require_once __DIR__ . '/place-pricing.php';
                    $lock = $db->prepare('SELECT id FROM places WHERE id = ? FOR UPDATE');
                    $lock->execute([$placeId]);
                    if (!$lock->fetchColumn()) {
                        throw new InvalidArgumentException('Place not found.');
                    }
                    $current = llama_pricing_document($db, ['id' => $placeId]);
                    $version = hash('sha256', json_encode($current, JSON_THROW_ON_ERROR));
                    if (!hash_equals($version, (string) ($_POST['season_rate_version'] ?? ''))) {
                        throw new InvalidArgumentException('Rates changed in another tab. Reload to get the latest rates.');
                    }
                    $rates = llama_pricing_validate($_POST['place_rate_cards_json']);
                    $seasonIds = array_map('intval', array_column(llama_seasons_for_place($db, $placeId, true), 'id'));
                    foreach ($rates['rows'] as $row) {
                        if (array_diff(array_column($row['seasons'], 'season_id'), $seasonIds)) {
                            throw new InvalidArgumentException('A rate refers to a season that does not belong to this Place.');
                        }
                    }
                    admin_place_save_shared_report($db, $actorUserId, $placeId, [
                        'place_rate_cards_json' => json_encode($rates, JSON_THROW_ON_ERROR),
                    ]);
                }
                $db->commit();
            } catch (Throwable $exception) {
                $db->rollBack();
                throw $exception;
            }
            $query = [
                'id' => $placeId,
                'saved' => $action === 'save-season-builder' ? 'season-builder' : 'season-prices',
                'season_view' => $seasonId,
            ];
            // Avoid enormous redirects for very large Site batches.
            if (is_array($selected) && count($selected) <= 50) {
                $query['season_targets'] = $selected;
            }
            header('Location: /place.php?' . http_build_query($query)
                . '#place-seasons', true, 303);
            exit;
        }

        if ($action === 'save-season-bulk-sites') {
            require_once __DIR__ . '/place-report/season-answers.php';

            $seasonId = (int) ($_POST['season_id'] ?? 0);
            $result = llama_season_bulk_site_statuses(
                $db,
                $placeId,
                $seasonId,
                $actorUserId,
                $_POST['season_site_ids'] ?? null,
                (string) ($_POST['season_bulk_status'] ?? ''),
                $_POST['season_bulk_source_url'] ?? null
            );

            header(
                'Location: /place.php?id=' . $placeId
                . '&saved=season-bulk-sites'
                . '&changed=' . (int) $result['changed']
                . '&season_view=' . $seasonId
                . '#place-seasons',
                true,
                303
            );
            exit;
        }

        if ($action === 'save-season-answers') {
            require_once __DIR__ . '/place-report/season-answers.php';

            $seasonId = (int) ($_POST['season_id'] ?? 0);
            $rawTarget = trim((string) ($_POST['season_target'] ?? ''));
            $parts = explode(':', $rawTarget);
            if (count($parts) !== 3 || !ctype_digit($parts[2]) || (int) $parts[2] < 1) {
                throw new InvalidArgumentException('Choose a valid Place, Area, or Site.');
            }
            $target = [
                'scope' => $parts[0],
                'source' => $parts[1],
                'id' => (int) $parts[2],
            ];
            $modes = $_POST['season_modes'] ?? null;
            $values = $_POST['season_values'] ?? [];
            if (!is_array($modes) || !is_array($values)) {
                throw new InvalidArgumentException('Seasonal answers were not submitted correctly.');
            }

            llama_season_save_target_answers(
                $db, $placeId, $seasonId, $target, $actorUserId,
                $modes, $values,
                $parts[0] === 'site'
                    ? (string) ($_POST['season_site_status'] ?? 'inherit')
                    : null,
                $_POST['season_answer_source_url'] ?? ''
            );
            header(
                'Location: /place.php?id=' . $placeId
                . '&saved=season-answers&season_view=' . $seasonId
                . '&season_target=' . rawurlencode($rawTarget)
                . '#place-seasons',
                true,
                303
            );
            exit;
        }

        if ($action === 'save-season') {
            require_once __DIR__ . '/place-report/season-definitions.php';

            $savedSeasonId = llama_season_save(
                $db,
                $placeId,
                $actorUserId,
                [
                    'id' => (int) ($_POST['season_id'] ?? 0),
                    'name' => (string) ($_POST['season_name'] ?? ''),
                    'start_mmdd' => (string) ($_POST['start_mmdd'] ?? ''),
                    'end_mmdd' => (string) ($_POST['end_mmdd'] ?? ''),
                    'is_default' => (string) ($_POST['is_default'] ?? '0'),
                    'is_active' => (string) ($_POST['is_active'] ?? '0'),
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                    'notes' => (string) ($_POST['season_notes'] ?? ''),
                    'source_url' => (string) ($_POST['season_source_url'] ?? ''),
                ]
            );

            header(
                'Location: /place.php?id=' . $placeId
                . '&saved=season&season_edit=' . $savedSeasonId
                . '&season_view=' . $savedSeasonId
                . '#place-seasons',
                true,
                303
            );
            exit;
        }

        if ($action === 'save-report') {
            // Save staged photos without rewriting unrelated report answers.
            // This uses the same existing staging and publication functions.
            $photoOnly = $backgroundPlaceSave
                && (string) ($_POST['admin_photo_only'] ?? '') === '1';
            $photosAdded = 0;
            $bulkResult = ['sites' => 0, 'answers' => 0];
            $committedPhotosForCleanup = [];
            $deferredPhotoDeletes = [];

            // A single transaction covers Place, Area, Site, photos and audit.
            $db->beginTransaction();
            try {
                if ($photoOnly) {
                    $photoTarget = admin_place_report_photo_target(
                        $db,
                        $placeId,
                        $_POST
                    );
                } else {
                    // Validate the separate Area payload before changing Place data.
                    $areaTarget = trim((string) ($_POST['selected_area_id'] ?? ''));
                    $areaPayload = (string) ($_POST['admin_area_answers_json'] ?? '');
                    $areaId = 0;
                    $areaAnswers = [];
                    if ($areaTarget !== '' || $areaPayload !== '') {
                        if (!preg_match('/^map_feature:([1-9][0-9]*)$/D', $areaTarget, $matches)) {
                            throw new InvalidArgumentException('Invalid Area reporting target.');
                        }
                        if ($areaPayload === '' || strlen($areaPayload) > 200000) {
                            throw new InvalidArgumentException('Area answers are missing or too large.');
                        }
                        $areaId = (int) $matches[1];
                        $areaAnswers = json_decode($areaPayload, true, 512, JSON_THROW_ON_ERROR);
                        if (!is_array($areaAnswers) || array_is_list($areaAnswers) && $areaAnswers !== []) {
                            throw new InvalidArgumentException('Invalid Area answer format.');
                        }
                        llama_report_verified_target($db, $placeId, 'area', 'map_feature', $areaId);
                    }

                    $selectedSiteTarget =
                        trim(
                            (string) (
                                $_POST['selected_campsite_id']
                                ?? ''
                            )
                        );

                    if ($selectedSiteTarget !== '') {
                        $parsedSiteTarget =
                            admin_place_parse_site_target(
                                $selectedSiteTarget
                            );

                        $siteId =
                            llama_place_site_identity_ensure_target(
                                $db,
                                $placeId,
                                (string) $parsedSiteTarget['source'],
                                (int) $parsedSiteTarget['id']
                            );

                        $_POST['selected_campsite_id'] =
                            'campsite_record:'
                            . $siteId;
                    }

                    $photoTarget =
                        admin_place_report_photo_target(
                            $db,
                            $placeId,
                            $_POST
                        );

                    admin_place_save_shared_report($db, $actorUserId, $placeId, $_POST);
                    admin_place_save_selected_campsite_answers($db, $actorUserId, $placeId, $_POST);
                    $bulkResult = admin_place_bulk_save_site_answers($db, $actorUserId, $placeId, $_POST);

                    if ($areaId > 0) {
                        $saved = admin_place_save_area_answers($db, $placeId, $areaId, $areaAnswers);
                        admin_users_audit(
                            $db, $actorUserId, null,
                            'place.area_report_updated',
                            'Updated an individual Area report.',
                            ['place_id' => $placeId, 'area_id' => $areaId, 'answers_saved' => $saved]
                        );
                    }
                }

                $removePaths = is_array($_POST['remove_existing_photos'] ?? null)
                    ? array_values(array_unique(array_filter(array_map('strval', $_POST['remove_existing_photos']))))
                    : [];

                if ($removePaths) {
                    foreach (
                        admin_place_report_target_images(
                            $db,
                            $placeId,
                            $photoTarget
                        )
                        as $image
                    ) {
                        if (!in_array((string) ($image['src'] ?? ''), $removePaths, true)) {
                            continue;
                        }

                        $path = admin_place_delete_image_in_report_transaction(
                            $db,
                            $actorUserId,
                            $placeId,
                            (int) $image['id'],
                            $photoTarget
                        );

                        if ($path !== '') {
                            $deferredPhotoDeletes[] = $path;
                        }
                    }
                }

                // Reorder the remaining attached images after removals;
                // newly staged images then append without disrupting hero.
                admin_place_save_gallery_in_report_transaction(
                    $db,
                    $actorUserId,
                    $placeId,
                    $photoTarget,
                    trim((string) ($_POST['admin_photo_gallery_json'] ?? ''))
                );

                $photoToken = trim((string) ($_POST['photo_stage_token'] ?? ''));
                $newPhotos = llama_photo_decode_form_photos($_POST['photos_json'] ?? '[]');

                if ($photoOnly && !$newPhotos) {
                    throw new InvalidArgumentException('Choose at least one photo before saving photos.');
                }

                if ($newPhotos) {
                    if ($photoToken === '') {
                        throw new RuntimeException('The photo upload session is missing. Upload the photos again.');
                    }

                    $manifest = llama_photo_read_manifest('admin-place', $actorUserId, $photoToken);
                    $manifestPaths = [];

                    foreach ($manifest as $photo) {
                        $path = trim((string) ($photo['path'] ?? ''));

                        if ($path !== '') {
                            $manifestPaths[$path] = true;
                        }
                    }

                    $submittedPaths = [];

                    foreach ($newPhotos as $photo) {
                        $path = trim((string) ($photo['path'] ?? ''));

                        if ($path !== '') {
                            $submittedPaths[$path] = true;
                        }
                    }

                    if (array_diff_key($submittedPaths, $manifestPaths)) {
                        throw new RuntimeException(
                            'One or more staged photos are no longer available. Upload those photos again before saving.'
                        );
                    }

                    $photosAdded = admin_place_add_photos_in_report_transaction(
                        $db,
                        $actorUserId,
                        $placeId,
                        $photoToken,
                        $newPhotos,
                        $photoTarget,
                        $committedPhotosForCleanup
                    );

                    if ($photosAdded !== count($newPhotos)) {
                        throw new RuntimeException('The Place Report could not save all staged photos.');
                    }
                }

                $db->commit();
            } catch (Throwable $saveException) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                foreach ($committedPhotosForCleanup as $photo) {
                    llama_photo_delete_owned_permanent_path(
                        (string) ($photo['path'] ?? ''),
                        ['uploads/places']
                    );
                }

                throw $saveException;
            }

            foreach (array_unique($deferredPhotoDeletes) as $path) {
                if (!llama_photo_delete_owned_permanent_path((string) $path, ['uploads/places'])) {
                    error_log('Llama Scout could not remove committed photo file: ' . (string) $path);
                }
            }

            if ($backgroundPlaceSave) {
                $photoTargetTotal =
                    isset($photoTarget)
                        ? count(
                            admin_place_report_target_images(
                                $db,
                                $placeId,
                                $photoTarget
                            )
                        )
                        : 0;

                // Return the committed gallery for both Save Place Report
                // and photo-only uploads. The editor can update hero and
                // captions without reloading or losing unsaved fields.
                $gallery = [];
                foreach (admin_place_report_target_images($db, $placeId, $photoTarget) as $image) {
                    $gallery[] = [
                        'id' => (int) ($image['id'] ?? 0),
                        'src' => (string) ($image['src'] ?? ''),
                        'url' => admin_place_photo_url($image),
                        'alt' => (string) ($image['alt_text'] ?? ''),
                        'sort_order' => (int) ($image['sort_order'] ?? 0),
                        'is_featured' => (int) ($image['is_featured'] ?? 0),
                    ];
                }

                admin_place_background_save_respond(200, [
                    'success' => true,
                    'message' => $photoOnly ? 'Photos saved.' : 'Place Report saved.',
                    'photos_added' => $photosAdded,
                    'gallery' => $gallery,
                    'gallery_target' => (string) ($photoTarget['scope'] ?? '')
                        . ':' . (string) ($photoTarget['source'] ?? '')
                        . ':' . (int) ($photoTarget['id'] ?? 0),
                    'bulk_sites_updated' => $bulkResult['sites'],
                    'bulk_answers_saved' => $bulkResult['answers'],
                    'photo_total' => $photoTargetTotal,
                    'csrf_token' => moderation_csrf_token(),
                ]);
            }

            header('Location: /place.php?id=' . $placeId . '&saved=report', true, 303);
            exit;
        }

        if ($action === 'change-status') {
            admin_place_change_status(
                $db, $actorUserId, $placeId,
                (string) ($_POST['status'] ?? ''),
                (string) ($_POST['status_reason'] ?? '')
            );
            $notice = 'Place status updated.';
        } elseif ($action === 'add-verification') {
            llama_place_add_verification($db, $actorUserId, $placeId, $_POST);
            $notice = 'Verification added.';
        } elseif ($action === 'delete-verification') {
            llama_place_delete_verification(
                $db, $actorUserId, $placeId, (int) ($_POST['verification_id'] ?? 0)
            );
            $notice = 'Verification deleted.';
        } elseif ($action === 'featured-image') {
            admin_place_set_featured_image($db, $actorUserId, $placeId, (int) ($_POST['image_id'] ?? 0));
            $notice = 'Featured image updated.';
        } elseif ($action === 'delete-image') {
            admin_place_delete_image($db, $actorUserId, $placeId, (int) ($_POST['image_id'] ?? 0));
            $notice = 'Place image deleted.';
        } elseif ($action === 'save-image-meta') {
            admin_place_save_image_metadata(
                $db, $actorUserId, $placeId,
                (int) ($_POST['image_id'] ?? 0),
                (string) ($_POST['alt_text'] ?? ''),
                (int) ($_POST['sort_order'] ?? 0)
            );
            $notice = 'Photo caption and order updated.';
        } elseif ($action === 'add-note') {
            admin_place_add_note($db, $actorUserId, $placeId, (string) ($_POST['note'] ?? ''));
            $notice = 'Place note added.';
        } elseif ($action === 'delete-note') {
            admin_place_delete_note($db, $actorUserId, $placeId, (int) ($_POST['note_id'] ?? 0));
            $notice = 'Place note deleted.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();

        if ($backgroundPlaceSave && $action === 'save-report') {
            $status = $exception instanceof PDOException ? 500 : 422;
            $payload = [
                'success' => false,
                'message' => $error !== '' ? $error : 'The Place Report could not be saved.',
                'photos_added' => 0,
            ];

            if ($status === 500 && function_exists('llama_log_exception')) {
                $payload['reference'] = llama_log_exception(
                    $exception,
                    'admin.place_report_save',
                    ['place_id' => $placeId, 'actor_user_id' => $actorUserId]
                );
            }

            admin_place_background_save_respond($status, $payload);
        }
    }

    return ['notice' => $notice, 'error' => $error, 'action' => $action];
}
