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
        // The Place/Scout Report endpoint must never become a second price
        // writer through a stale form or direct POST from an old browser tab.
        if (array_key_exists('place_rate_cards_json', $_POST)
            || array_key_exists('season_rate_version', $_POST)) {
            throw new InvalidArgumentException(
                'Campsite rates can only be saved in Admin > Campsite Pricing.'
            );
        }
        if ($action === 'save-season-prices') {
            // The old rate-card editor has been retired. A stale browser
            // tab must not be allowed to overwrite current Campsite Pricing.
            throw new InvalidArgumentException(
                'The old rates form is retired. Use Admin > Places > Campsite Pricing.'
            );
        }

        if ($action === 'save-season-builder') {
            // Seasonal Place/Area/Site Scout Report conditions remain distinct
            // from prices. The pricing document is never writable here.
            if (isset($_POST['place_rate_cards_json'])
                || isset($_POST['season_rate_version'])) {
                throw new InvalidArgumentException(
                    'Rates can only be edited in Campsite Pricing.'
                );
            }
            require_once __DIR__ . '/place-report/season-builder.php';
            $seasonId = (int) ($_POST['season_id'] ?? 0);
            $selected = $_POST['season_targets'] ?? null;
            llama_season_builder_save(
                $db, $placeId, $seasonId, $actorUserId, $selected,
                $_POST['season_changes'] ?? [], $_POST['season_removals'] ?? [],
                $_POST['season_answer_source_url'] ?? null
            );
            $query = [
                'id' => $placeId,
                'saved' => 'season-builder',
                'season_view' => $seasonId,
            ];
            if (is_array($selected) && count($selected) <= 50) {
                $query['season_targets'] = $selected;
            }
            header('Location: /place.php?' . http_build_query($query)
                . '#place-seasons', true, 303);
            exit;
        }

        if ($action === 'save-season-bulk-sites') {
            // Seasonal availability is exclusively controlled per Site in
            // Campsite Pricing. Reject stale bulk forms rather than allowing
            // the old workflow to overwrite the new editor.
            throw new InvalidArgumentException(
                'Site availability is now managed in Campsite Pricing.'
            );
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
            if ($parts[0] === 'site' && (
                isset($modes['site_reservation_method'])
                || isset($_POST['season_site_status'])
            )) {
                throw new InvalidArgumentException(
                    'Site availability and booking methods belong to Campsite Pricing.'
                );
            }
            if ($parts[0] === 'place' && isset($modes['seasonal_booking_method'])) {
                throw new InvalidArgumentException(
                    'Default Site booking methods are now managed per Site in Campsite Pricing.'
                );
            }
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
                // The photo gallery's owner is independent of the form's
                // selected Site/Area, which may still be set from another tab.
                $explicitPhotoOwner = trim((string) ($_POST['admin_photo_owner'] ?? ''));
                if ($photoOnly) {
                    $photoTarget = $explicitPhotoOwner !== ''
                        ? admin_place_report_photo_target_from_owner($db, $placeId, $explicitPhotoOwner)
                        : admin_place_report_photo_target($db, $placeId, $_POST);
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

                    $photoTarget = $explicitPhotoOwner !== ''
                        ? admin_place_report_photo_target_from_owner($db, $placeId, $explicitPhotoOwner)
                        : admin_place_report_photo_target($db, $placeId, $_POST);

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

                if (trim((string) ($_POST['admin_photo_gallery_json'] ?? '')) !== ''
                    && $explicitPhotoOwner === '') {
                    throw new InvalidArgumentException(
                        'Reload this page before saving photos so their owner can be verified.'
                    );
                }

                // Admin tiles submit unique image IDs. This matters when two
                // records have the same src (for example an imported duplicate).
                // Older static forms with path checkboxes are accepted only
                // when the path identifies exactly one image on this target.
                $rawIds = $_POST['remove_existing_photo_ids'] ?? [];
                $rawPaths = $_POST['remove_existing_photos'] ?? [];
                if (!is_array($rawIds) || !is_array($rawPaths)) {
                    throw new InvalidArgumentException('Invalid photo removal selection.');
                }
                $removeIds = [];
                foreach ($rawIds as $id) {
                    if ((!is_int($id) && !is_string($id))
                        || !ctype_digit((string) $id)
                        || (int) $id < 1) {
                        throw new InvalidArgumentException('Invalid photo ID for removal.');
                    }
                    $removeIds[(int) $id] = true;
                }
                $existingGallery = admin_place_report_target_images($db, $placeId, $photoTarget);
                $byId = [];
                $byPath = [];
                foreach ($existingGallery as $image) {
                    $id = (int) ($image['id'] ?? 0);
                    $src = (string) ($image['src'] ?? '');
                    $byId[$id] = true;
                    $byPath[$src][] = $id;
                }
                foreach ($rawPaths as $path) {
                    if (!is_string($path) || count($byPath[$path] ?? []) !== 1) {
                        throw new InvalidArgumentException(
                            'This photo path is ambiguous. Reload the photo editor to remove one image.'
                        );
                    }
                    $removeIds[$byPath[$path][0]] = true;
                }
                foreach (array_keys($removeIds) as $imageId) {
                    if (!isset($byId[$imageId])) {
                        throw new InvalidArgumentException(
                            'A selected photo does not belong to this gallery.'
                        );
                    }
                    $path = admin_place_delete_image_in_report_transaction(
                        $db, $actorUserId, $placeId, $imageId, $photoTarget
                    );
                    if ($path !== '') {
                        $deferredPhotoDeletes[] = $path;
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
                // Multiple owned gallery rows may reference one image file.
                // Delete the selected row, not another photo's underlying bytes.
                if (admin_place_gallery_photo_path_in_use($db, (string) $path)) {
                    continue;
                }
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
