<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/memberships.php';

$pageTitle = 'Membership | Llama Scout';
$pageDescription = 'Compare free Llama Scout access with full paid membership and see current monthly and annual membership pricing.';
$canonicalUrl = 'https://llamascout.com/membership';

function membership_public_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function membership_public_money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

$db = db();
$offers = llama_membership_offers($db);

$monthly = $offers['monthly'] ?? null;
$annual = $offers['annual'] ?? null;

require __DIR__ . '/partials/header.php';
?>

<section class="membership-public-page">

    <header class="membership-public-hero">
        <div class="public-home-container">
            <p class="public-home-eyebrow">Llama Scout Membership</p>

            <h1>Know what you get before you pay for anything.</h1>

            <p>
                Anyone can use the public side of Llama Scout. A free account
                adds personal tools and contribution features. Paid membership
                unlocks the complete place report, including the exact location
                and the details that require actually documenting the place.
            </p>
        </div>
    </header>


    <section class="membership-public-plans">
        <div class="public-home-container">

            <div class="membership-public-plan-grid">

                <article class="membership-public-plan">
                    <span class="membership-public-kicker">No account required</span>
                    <h2>Public</h2>
                    <p class="membership-public-price"><strong>$0</strong></p>
                    <p>Useful planning context without exposing the exact place.</p>

                    <ul>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Browse published places</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Approximate public map location</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> City, county, and state</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Elevation</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Land manager and land type</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Amenities</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> City-based current weather</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Featured place photo</li>
                    </ul>

                    <a class="public-home-button" href="/map.php">Explore Places</a>
                </article>


                <article class="membership-public-plan">
                    <span class="membership-public-kicker">Free account</span>
                    <h2>Member</h2>
                    <p class="membership-public-price"><strong>$0</strong></p>
                    <p>The public place view, plus account and contribution tools.</p>

                    <ul>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Everything in Public</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Save and remove favorite places</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Add new Place submissions</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Suggest Place updates</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Report problems with photos</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Member profile, badges, and contribution stats</li>
                    </ul>

                    <a
                        class="public-home-button"
                        href="https://account.llamascout.com/register.php"
                    >
                        Create Free Account
                    </a>
                </article>


                <article class="membership-public-plan is-paid">
                    <span class="membership-public-kicker">Complete access</span>
                    <h2>Paid Member</h2>

                    <div class="membership-public-paid-prices">

                        <?php if ($monthly): ?>
                            <div class="membership-price-option">
                                <?php if (!empty($monthly['on_sale'])): ?>
                                    <del>
                                        <?= membership_public_e(
                                            membership_public_money((int) $monthly['base_price_cents'])
                                        ) ?>
                                    </del>
                                <?php endif; ?>
                                <strong>
                                    <?= membership_public_e(
                                        membership_public_money((int) $monthly['effective_price_cents'])
                                    ) ?>
                                </strong>
                                <span>/ month</span>
                                <?php if (!empty($monthly['on_sale'])): ?>
                                    <small>First year only</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($monthly && $annual): ?>
                            <div class="membership-price-separator">
                                <span>or</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($annual): ?>
                            <div class="membership-price-option">
                                <?php if (!empty($annual['on_sale'])): ?>
                                    <del>
                                        <?= membership_public_e(
                                            membership_public_money((int) $annual['base_price_cents'])
                                        ) ?>
                                    </del>
                                <?php endif; ?>
                                <strong>
                                    <?= membership_public_e(
                                        membership_public_money((int) $annual['effective_price_cents'])
                                    ) ?>
                                </strong>
                                <span>/ year</span>
                                <?php if (!empty($annual['on_sale'])): ?>
                                    <small>First year only</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>

                    <?php
                    $activePromo = $monthly['promotion'] ?? $annual['promotion'] ?? null;
                    ?>
                    <?php if ($activePromo): ?>
                        <div class="membership-public-promo-note">
                            <strong>
                                <?= membership_public_e(
                                    (string) (
                                        $activePromo['public_label']
                                        ?? $activePromo['promotion_name']
                                        ?? 'Limited-time promotion'
                                    )
                                ) ?>
                            </strong>
                            <span>
                                <?= membership_public_e(
                                    (string) (
                                        $activePromo['public_description']
                                        ?? 'Introductory pricing applies during the first year, then renews at the regular price.'
                                    )
                                ) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <p>The complete Llama Scout Place report. Still no actual llama included.</p>

                    <ul>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Everything in Member</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Exact coordinates</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Exact road and location details</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Complete place photo gallery</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Full description</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Road and vehicle access</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Sensory conditions</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Connectivity</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Rules, experience, and Scout Notes</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Exact-location weather plus 5-day forecast</li>
                    </ul>

                    <div class="membership-public-paid-actions">
                        <?php if ($monthly): ?>
                            <a
                                class="public-home-button is-primary"
                                href="https://account.llamascout.com/membership.php?plan=monthly"
                            >
                                Choose Monthly
                            </a>
                        <?php endif; ?>

                        <?php if ($annual): ?>
                            <a
                                class="public-home-button"
                                href="https://account.llamascout.com/membership.php?plan=annual"
                            >
                                Choose Annual
                            </a>
                        <?php endif; ?>
                    </div>
                </article>

            </div>

        </div>
    </section>


    <section class="membership-public-comparison">
        <div class="public-home-container">

            <div class="public-home-section-heading">
                <div>
                    <p class="public-home-eyebrow">Compare Access</p>
                    <h2>What each level includes.</h2>
                </div>
            </div>

            <div class="membership-public-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Feature</th>
                            <th scope="col">Public</th>
                            <th scope="col">Member</th>
                            <th scope="col">Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Browse places</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Featured photo</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Approximate map location</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>City / county / state</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Land manager / land type</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Amenities</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>City weather today</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Save places</td><td>No</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Contribute Places and updates</td><td>No</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Member profile / badges</td><td>No</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Exact coordinates</td><td>No</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Full photo gallery</td><td>No</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Road and access details</td><td>No</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Sensory conditions</td><td>No</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Connectivity</td><td>No</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Scout Notes and complete report</td><td>No</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Exact-location 5-day weather</td><td>No</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Free llama rides</td><td>No</td><td>No</td><td>Still No</td></tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>


    <section class="membership-public-faq">
        <div class="public-home-container">
            <div class="public-home-section-heading">
                <div>
                    <p class="public-home-eyebrow">Membership FAQ</p>
                    <h2>Before you join.</h2>
                </div>
            </div>

            <div class="membership-public-faq-list">
                <details>
                    <summary>Do I have to pay to use Llama Scout?</summary>
                    <p>
                        No. Public Place information and the Field Guides are
                        available without a paid membership. A free account adds
                        saved Places, contributions, profiles, badges, and related
                        account tools.
                    </p>
                </details>

                <details>
                    <summary>How do automatic promotional prices work?</summary>
                    <p>
                        A holiday or signup promotion applies automatically to a new
                        membership started during the promotional window. The
                        introductory rate lasts for the first year only. After that,
                        the membership renews at the regular recurring price.
                    </p>
                </details>

                <details>
                    <summary>Are promotion codes the same thing as promotional pricing?</summary>
                    <p>
                        No. Automatic promotional pricing is tied to a scheduled
                        signup period. Promotion codes are separate special offers
                        that may be made available for specific occasions.
                    </p>
                </details>

                <details>
                    <summary>Can I choose monthly or annual billing?</summary>
                    <p>
                        Yes. Active monthly and annual options are shown above using
                        the current prices configured in Llama Scout.
                    </p>
                </details>

                <details>
                    <summary>Is there a difference between monthly and annual membership?</summary>
                    <p>
                        The access is the same. Monthly membership is billed month to month.
                        Annual membership covers the full year in one payment. Either way,
                        you get the same complete Llama Scout Place reports. No secret
                        platinum llama lounge exists for annual members.
                    </p>
                </details>

                <details>
                    <summary>What do llamas eat?</summary>
                    <p>
                        Mostly grass, hay, leaves, and other plant material. They do not
                        require a paid Llama Scout membership, although we have been unable
                        to confirm whether any llamas are currently sharing passwords.
                    </p>
                </details>
            </div>
        </div>
    </section>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
