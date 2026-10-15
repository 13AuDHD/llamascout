<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-place-sites.php';
require_once __DIR__ . '/admin-place-areas.php';
require_once __DIR__ . '/admin-place-report.php';
require_once __DIR__ . '/place-verifications.php';

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
        if ($action === 'save-site-association') {
            admin_place_save_site_association($db, $actorUserId, $placeId, $_POST);
            $notice = 'Site map association updated.';

        } elseif ($action === 'save-report') {
            $photosAdded = 0;
            $committedPhotosForCleanup = [];
            $deferredPhotoDeletes = [];

            // A single transaction covers Place, Area, Site, photos and audit.
            $db->beginTransaction();
            try {
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

                /*
                 * Resolve the photo target from the same verified Place/Area/Site
                 * reporting selectors used by the form. Never trust a separate
                 * browser-supplied photo target.
                 */
                $photoTarget =
                    admin_place_report_photo_target(
                        $db,
                        $placeId,
                        $_POST
                    );

                admin_place_save_shared_report($db, $actorUserId, $placeId, $_POST);
                admin_place_save_selected_campsite_answers($db, $actorUserId, $placeId, $_POST);

                if ($areaId > 0) {
                    $saved = admin_place_save_area_answers($db, $placeId, $areaId, $areaAnswers);
                    admin_users_audit(
                        $db, $actorUserId, null,
                        'place.area_report_updated',
                        'Updated an individual Area report.',
                        ['place_id' => $placeId, 'area_id' => $areaId, 'answers_saved' => $saved]
                    );
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

                $photoToken = trim((string) ($_POST['photo_stage_token'] ?? ''));
                $newPhotos = llama_photo_decode_form_photos($_POST['photos_json'] ?? '[]');

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
                    error_log('Llama Scout could not remove committed reporting-target photo file: ' . (string) $path);
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

                admin_place_background_save_respond(200, [
                    'success' => true,
                    'message' => 'Place Report saved.',
                    'photos_added' => $photosAdded,
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
