<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();

$db = db();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);

llama_ensure_community_profile($db, $userId);

if (empty($_SESSION['community_profile_csrf'])) {
    $_SESSION['community_profile_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['profile_image_csrf'])) {
    $_SESSION['profile_image_csrf'] = bin2hex(random_bytes(32));
}

$profileCsrf = (string) $_SESSION['community_profile_csrf'];
$profileImageCsrf = (string) $_SESSION['profile_image_csrf'];
$errors = [];
$success = '';

if (!empty($_SESSION['profile_flash_error'])) {
    $errors[] = (string) $_SESSION['profile_flash_error'];
    unset($_SESSION['profile_flash_error']);
}
if (!empty($_SESSION['profile_flash_success'])) {
    $success = (string) $_SESSION['profile_flash_success'];
    unset($_SESSION['profile_flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $submittedCsrf = (string) ($_POST['csrf_token'] ?? '');

    if ($submittedCsrf === '' || !hash_equals($profileCsrf, $submittedCsrf)) {
        $errors[] = 'Your session expired. Reload the page and try again.';
    } else {
        try {
            llama_profile_save($db, $userId, $_POST);
            $success = 'Your Profile has been updated.';
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'account.profile_save',
                ['user_id' => $userId],
                [InvalidArgumentException::class]
            );

            $errors[] = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'Your profile could not be updated. Please try again.',
                    $reference
                );
        }
    }
}

$profile = llama_community_profile($db, $userId);
$profileImages = llama_community_profile_images($db, $userId);
$primaryImage = llama_primary_profile_image($db, $userId);
$userBadges = llama_user_badges($db, $userId);

$config = llama_config();
$siteUrl = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);
$username = (string) ($user['username'] ?? '');
$displayName = (string) (
    $user['display_name']
    ?? $username
    ?: 'Llama Scout Member'
);

function profile_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function profile_value(array $profile, string $key): string
{
    return (string) ($profile[$key] ?? '');
}

$pageTitle = 'Profile | Llama Scout';
$pageRobots = 'noindex,nofollow';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= profile_e($siteUrl . '/css/account/pages/profile.css') ?>"
>

<section class="community-profile-editor">
    <header class="community-profile-editor-header">
        <div>
            <p class="account-eyebrow">Your profile</p>
            <h1><?= profile_e($displayName) ?></h1>
            <p>
                Build the profile other Llama Scout members see when they
                come across your contributions.
            </p>
        </div>

        <div class="community-profile-editor-actions">
            <a
                class="place-save-button"
                href="<?= profile_e(
                    $siteUrl . '/' . rawurlencode($username)
                ) ?>"
            >
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                View profile
            </a>

            <a class="place-save-button" href="/index.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Account
            </a>
        </div>
    </header>

    <?php if ($success !== ''): ?>
        <div class="contribution-message is-success" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span><?= profile_e($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="contribution-message is-error" role="alert">
            <i
                class="fa-solid fa-triangle-exclamation"
                aria-hidden="true"
            ></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <p><?= profile_e($error) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <section
        class="community-profile-section"
        aria-labelledby="profile-photos-heading"
    >
        <div class="community-profile-section-heading">
            <div>
                <p class="account-eyebrow">Your face around the herd</p>
                <h2 id="profile-photos-heading">Profile photos</h2>
            </div>

            <span class="account-section-count">
                <?= count($profileImages) ?>/5
            </span>
        </div>

        <div class="community-profile-avatar-row">
            <img
                class="community-profile-avatar-large"
                src="<?= profile_e(
                    llama_profile_image_url(
                        $primaryImage,
                        $siteUrl
                    )
                ) ?>"
                alt="Current profile picture"
            >

            <div>
                <strong>Current profile picture</strong>
                <p>
                    If you do not choose a photo, the default
                    Llama Scout llama is used automatically.
                </p>
            </div>
        </div>

        <?php if ($profileImages): ?>
            <div class="community-profile-image-manager">
                <?php foreach ($profileImages as $image): ?>
                    <?php
                    $isPrimary =
                        (int) ($profile['primary_image_id'] ?? 0)
                        === (int) $image['id'];
                    ?>

                    <article class="community-profile-image-card">
                        <img
                            src="<?= profile_e(
                                llama_profile_image_url(
                                    (string) $image['image_src'],
                                    $siteUrl
                                )
                            ) ?>"
                            alt="<?= profile_e(
                                $image['alt_text']
                                ?: 'Profile photo'
                            ) ?>"
                        >

                        <div class="community-profile-image-card-body">
                            <?php if ($isPrimary): ?>
                                <span class="community-profile-primary-badge">
                                    <i
                                        class="fa-solid fa-circle-check"
                                        aria-hidden="true"
                                    ></i>
                                    Profile picture
                                </span>
                            <?php else: ?>
                                <form
                                    method="post"
                                    action="/profile-image-action.php"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= profile_e(
                                            $profileImageCsrf
                                        ) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="image_id"
                                        value="<?= (int) $image['id'] ?>"
                                    >
                                    <button
                                        class="photo-manager-button"
                                        type="submit"
                                        name="action"
                                        value="primary"
                                    >
                                        <i
                                            class="fa-solid fa-user"
                                            aria-hidden="true"
                                        ></i>
                                        Make primary
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form
                                method="post"
                                action="/profile-image-action.php"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= profile_e(
                                        $profileImageCsrf
                                    ) ?>"
                                >
                                <input
                                    type="hidden"
                                    name="image_id"
                                    value="<?= (int) $image['id'] ?>"
                                >
                                <button
                                    class="photo-manager-button"
                                    type="submit"
                                    name="action"
                                    value="delete"
                                >
                                    <i
                                        class="fa-solid fa-trash"
                                        aria-hidden="true"
                                    ></i>
                                    Remove
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($profileImages) < 5): ?>
            <form
                method="post"
                action="/save-profile-images.php"
                class="community-profile-photo-upload-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= profile_e($profileImageCsrf) ?>"
                >
                <input
                    type="hidden"
                    name="photo_stage_token"
                    value=""
                >
                <input
                    type="hidden"
                    name="photos_json"
                    value="[]"
                >

                <div
                    data-photo-uploader
                    data-photo-context="profile-images"
                    data-photo-max="<?= 5 - count($profileImages) ?>"
                    data-photo-csrf="<?= profile_e(
                        llama_photo_csrf_token()
                    ) ?>"
                    data-photo-endpoint="/photo-upload.php"
                    data-photo-title="Add profile photos"
                    data-photo-help="Choose up to <?= 5 - count($profileImages) ?> more. You can remove photos before saving."
                ></div>

                <button
                    type="submit"
                    class="contribution-submit"
                >
                    <i
                        class="fa-solid fa-cloud-arrow-up"
                        aria-hidden="true"
                    ></i>
                    Save selected photos
                </button>
            </form>
        <?php endif; ?>
    </section>

    <section
        class="community-profile-section"
        aria-labelledby="profile-badges-heading"
    >
        <div class="community-profile-section-heading">
            <div>
                <p class="account-eyebrow">Recognition</p>
                <h2 id="profile-badges-heading">Badges</h2>
            </div>

            <span class="account-section-count">
                <?= count($userBadges) ?>
            </span>
        </div>

        <?php if ($userBadges): ?>
            <div class="community-badge-grid">
                <?php foreach ($userBadges as $badge): ?>
                    <article class="community-badge-card">
                        <span class="community-badge-icon">
                            <?php if (!empty($badge['image_src'])): ?>
                                <img
                                    src="<?= profile_e(
                                        llama_profile_image_url(
                                            (string) $badge['image_src'],
                                            $siteUrl
                                        )
                                    ) ?>"
                                    alt=""
                                >
                            <?php else: ?>
                                <i
                                    class="fa-solid <?= profile_e(
                                        $badge['icon']
                                        ?: 'fa-award'
                                    ) ?>"
                                    aria-hidden="true"
                                ></i>
                            <?php endif; ?>
                        </span>

                        <div>
                            <strong>
                                <?= profile_e($badge['name']) ?>
                            </strong>

                            <?php if (!empty($badge['description'])): ?>
                                <p>
                                    <?= profile_e(
                                        $badge['description']
                                    ) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="account-empty-state">
                <i
                    class="fa-solid fa-award"
                    aria-hidden="true"
                ></i>
                <h3>No badges yet</h3>
                <p>
                    Badges appear here as you earn Llama Scout
                    achievements, contribution milestones, and
                    recognized training.
                </p>
            </div>
        <?php endif; ?>
    </section>

    <form
        method="post"
        class="community-profile-form"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= profile_e($profileCsrf) ?>"
        >

        <section class="community-profile-section">
            <div class="community-profile-section-heading">
                <div>
                    <p class="account-eyebrow">About you</p>
                    <h2>Profile details</h2>
                </div>
            </div>

            <div class="community-profile-form-grid">
                <label
                    class="community-profile-field community-profile-field-wide"
                >
                    <span>Bio</span>
                    <textarea
                        name="bio"
                        rows="6"
                        maxlength="1000"
                        placeholder="Tell the herd a little about yourself."
                    ><?= profile_e(
                        profile_value($profile, 'bio')
                    ) ?></textarea>
                </label>

                <label class="community-profile-field">
                    <span>General location</span>
                    <input
                        type="text"
                        name="location"
                        maxlength="150"
                        value="<?= profile_e(
                            profile_value($profile, 'location')
                        ) ?>"
                        placeholder="Durango, Colorado"
                    >
                    <small>
                        Keep it general. Do not enter a street address.
                    </small>
                </label>

                <label class="community-profile-field">
                    <span>Squad / club</span>
                    <input
                        type="text"
                        name="squad"
                        maxlength="150"
                        value="<?= profile_e(
                            profile_value($profile, 'squad')
                        ) ?>"
                        placeholder="Trail club, camping group, etc."
                    >
                </label>

                <label class="community-profile-field">
                    <span>Camping style</span>
                    <input
                        type="text"
                        name="camping_style"
                        maxlength="255"
                        value="<?= profile_e(
                            profile_value(
                                $profile,
                                'camping_style'
                            )
                        ) ?>"
                        placeholder="Overlanding, tent camping, vanlife..."
                    >
                </label>

                <label class="community-profile-field">
                    <span>Favorite kind of place</span>
                    <input
                        type="text"
                        name="favorite_places"
                        maxlength="255"
                        value="<?= profile_e(
                            profile_value(
                                $profile,
                                'favorite_places'
                            )
                        ) ?>"
                        placeholder="High desert, alpine forest, riverside..."
                    >
                </label>

                <label
                    class="community-profile-field community-profile-field-wide"
                >
                    <span>Camping soundtrack</span>
                    <input
                        type="text"
                        name="favorite_camping_music"
                        maxlength="255"
                        value="<?= profile_e(
                            profile_value(
                                $profile,
                                'favorite_camping_music'
                            )
                        ) ?>"
                        placeholder="What belongs on the camp playlist?"
                    >
                </label>
            </div>
        </section>

        <section class="community-profile-section">
            <div class="community-profile-section-heading">
                <div>
                    <p class="account-eyebrow">Around the internet</p>
                    <h2>Social profiles</h2>
                </div>
            </div>

            <p class="community-profile-section-copy">
                For social networks, enter only your username or
                handle. Llama Scout builds the profile link for you.
            </p>

            <div class="community-profile-form-grid">
                <label
                    class="community-profile-field community-profile-field-wide"
                >
                    <span>
                        <i
                            class="fa-solid fa-globe"
                            aria-hidden="true"
                        ></i>
                        Website
                    </span>
                    <input
                        type="url"
                        name="website_url"
                        maxlength="500"
                        value="<?= profile_e(
                            profile_value(
                                $profile,
                                'website_url'
                            )
                        ) ?>"
                        placeholder="https://example.com"
                    >
                </label>

                <?php
                $socialFields = [
                    [
                        'name' => 'instagram_url',
                        'label' => 'Instagram',
                        'icon' => 'fa-brands fa-instagram',
                        'placeholder' => 'username',
                    ],
                    [
                        'name' => 'facebook_url',
                        'label' => 'Facebook',
                        'icon' => 'fa-brands fa-facebook',
                        'placeholder' => 'username',
                    ],
                    [
                        'name' => 'bluesky_url',
                        'label' => 'Bluesky',
                        'icon' => 'fa-solid fa-cloud',
                        'placeholder' => 'name.bsky.social',
                    ],
                    [
                        'name' => 'youtube_url',
                        'label' => 'YouTube',
                        'icon' => 'fa-brands fa-youtube',
                        'placeholder' => 'channelhandle',
                    ],
                    [
                        'name' => 'tiktok_url',
                        'label' => 'TikTok',
                        'icon' => 'fa-brands fa-tiktok',
                        'placeholder' => 'username',
                    ],
                ];
                ?>

                <?php foreach ($socialFields as $field): ?>
                    <label class="community-profile-field">
                        <span>
                            <i
                                class="<?= profile_e(
                                    $field['icon']
                                ) ?>"
                                aria-hidden="true"
                            ></i>
                            <?= profile_e($field['label']) ?>
                        </span>

                        <div class="profile-handle-input">
                            <span>@</span>
                            <input
                                type="text"
                                name="<?= profile_e(
                                    $field['name']
                                ) ?>"
                                maxlength="150"
                                value="<?= profile_e(
                                    profile_value(
                                        $profile,
                                        $field['name']
                                    )
                                ) ?>"
                                placeholder="<?= profile_e(
                                    $field['placeholder']
                                ) ?>"
                                autocapitalize="none"
                                spellcheck="false"
                            >
                        </div>
                    </label>
                <?php endforeach; ?>

                <label class="community-profile-field">
                    <span>
                        <i
                            class="fa-solid fa-link"
                            aria-hidden="true"
                        ></i>
                        Other link
                    </span>
                    <input
                        type="url"
                        name="other_social_url"
                        maxlength="500"
                        value="<?= profile_e(
                            profile_value(
                                $profile,
                                'other_social_url'
                            )
                        ) ?>"
                        placeholder="https://"
                    >
                </label>
            </div>
        </section>

        <section class="community-profile-section">
            <div class="community-profile-section-heading">
                <div>
                    <p class="account-eyebrow">Visibility</p>
                    <h2>Who can see it?</h2>
                </div>
            </div>

            <label class="community-profile-visibility-toggle">
                <input
                    type="checkbox"
                    name="is_public"
                    value="1"
                    <?= !empty($profile['is_public'])
                        ? 'checked'
                        : '' ?>
                >
                <span>
                    <strong>Create my public profile</strong>
                    <small>
                        When enabled, your profile is available at
                        llamascout.com/<?= profile_e($username) ?>,
                        can be viewed by anyone, and may be indexed
                        by search engines. When disabled, signed-in
                        members can still see your basic profile,
                        badges, and contribution stats.
                    </small>
                </span>
            </label>
        </section>

        <div class="community-profile-save-row">
            <button
                type="submit"
                name="save_profile"
                value="1"
                class="contribution-submit"
            >
                <i
                    class="fa-solid fa-floppy-disk"
                    aria-hidden="true"
                ></i>
                Save Profile
            </button>
        </div>
    </form>
</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
