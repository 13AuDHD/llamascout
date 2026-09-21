<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/memberships.php';

$pageTitle = 'Membership | Llama Scout';
$pageDescription = 'Compare free and paid Llama Scout membership and see current monthly and annual membership pricing.';
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

            <h1>Choose how much of the place you want to know.</h1>

            <p>
                A free Llama Scout membership lets you help build the database as a
                Community contributor. Paid membership adds Complete Access to the
                database and identifies your approved field work at the Member level.
                Scout and Master Scout are separate trained roles.
            </p>
        </div>
    </header>


    <section class="membership-public-plans">
        <div class="public-home-container">

            <div class="membership-public-plan-grid">

                <article class="membership-public-plan">
                    <span class="membership-public-kicker">Free membership</span>
                    <h2>Free Member</h2>
                    <p class="membership-public-price"><strong>$0</strong></p>
                    <p>
                        Useful planning information plus Community-level contribution
                        tools. Places you originally contribute unlock completely for you.
                    </p>

                    <ul>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Browse published Places</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Approximate public map location</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> City, county, and state</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Elevation</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Land manager and land type</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Amenities</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> City-based current weather</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Featured Place photo</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Save and remove favorite Places</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Add new Place submissions</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Suggest Place updates</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Report problems with photos</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Member profile, badges, and contribution stats</li>
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

                    <p>
                        Complete Access plus Member-level contribution tools.
                        Still no actual llama included.
                    </p>

                    <ul>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Everything in Free Member</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Exact coordinates</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Exact road and location details</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Complete Place photo gallery</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Full description</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Road and vehicle access</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Sensory conditions</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Environment and accessibility</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Safety and warnings</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Connectivity</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Rules, experience, and Scout Notes</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Exact-location weather plus 5-day forecast</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Member Contributed label on approved field work</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Geofenced Place check-ins</li>
                        <li><i aria-hidden="true"><?= llama_icon('check') ?></i> Compare Places and historical Place reports</li>
                    </ul>

                    <a
                        class="public-home-button"
                        href="/scout-report-demo.php"
                    >
                        <i aria-hidden="true"><?= llama_icon('binoculars') ?></i>
                        See a Complete Example
                    </a>

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
                    <p class="public-home-eyebrow">Compare Memberships</p>
                    <h2>What each membership includes.</h2>
                </div>
            </div>

            <div class="membership-public-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Feature</th>
                            <th scope="col">Free Member</th>
                            <th scope="col">Paid Member</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Browse Places</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Featured photo</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Approximate map location</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>City / county / state</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Land manager / land type</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Amenities</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>City weather today</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Save Places</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Contribute Places and updates</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Contribution label</td><td>Community Contributed</td><td>Member Contributed</td></tr>
                        <tr><td>Geofenced Place check-ins</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Historical report comparison</td><td>Own contributed Places</td><td>Complete Access Places</td></tr>
                        <tr><td>Compare Places</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Member profile / badges</td><td>Yes</td><td>Yes</td></tr>
                        <tr><td>Exact coordinates</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Full photo gallery</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Road and access details</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Sensory conditions</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Environment and accessibility</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Safety and warnings</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Connectivity</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Scout Notes and complete report</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Exact-location 5-day weather</td><td>No</td><td>Yes</td></tr>
                        <tr><td>Free llama rides</td><td>No</td><td>Still No</td></tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>


    <section class="membership-public-explainer" id="contribution-levels">
        <div class="public-home-container membership-public-explainer-grid">
            <div>
                <p class="public-home-eyebrow">Contribution labels</p>
                <h2>Membership controls access. Contribution level shows who documented the Place.</h2>
            </div>

            <div>
                <p>
                    <strong>Community Contributed:</strong> a signed-in Free Member
                    personally added field information.
                </p>
                <p>
                    <strong>Member Contributed:</strong> a paid Member with Complete
                    Access personally added field information.
                </p>
                <p>
                    <strong>Scout Contributed:</strong> a trained Llama Scout personally
                    documented the Place in the field.
                </p>
                <p>
                    <strong>Master Scout Contributed:</strong> a trained Master Scout
                    personally documented the Place. Master Scouts also have limited
                    moderation responsibilities.
                </p>
                <p>
                    <strong>Admin Contributed:</strong> Llama Scout administration
                    personally documented the Place. Owner contributions are shown
                    publicly as Admin contributions.
                </p>
                <p>
                    The label can move upward as higher-level contributors personally
                    add field information. It is not a guarantee that conditions have
                    stayed unchanged, so Place History and Field Freshness remain part
                    of the picture.
                </p>
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
                        No. A free membership includes useful Place information,
                        saved Places, Community-level contributions, profiles, points,
                        eligible badges, and related account tools. Paid membership
                        unlocks Complete Access to the database.
                    </p>
                </details>

                <details>
                    <summary>Does paid membership change how my contributions are identified?</summary>
                    <p>
                        Yes. Free accounts contribute at the Community level. An active
                        paid membership contributes at the Member level and includes Complete
                        Access. Scout and Master Scout are separate trained roles and cannot
                        be purchased through membership.
                    </p>
                </details>


                <details>
                    <summary>Does a higher contribution label guarantee that a Place is accurate?</summary>
                    <p>
                        No. The label identifies the highest contributor level that has
                        personally documented the Place. Conditions can change after any
                        visit, so Llama Scout also shows Field Freshness and Place History.
                    </p>
                </details>

                <details>
                    <summary>Can I become a Scout or Master Scout by buying a membership?</summary>
                    <p>
                        No. Paid membership provides Complete Access and Member-level
                        contribution status. Scout is a trained field role. Master Scout is
                        a senior trained role with limited moderation responsibility.
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
