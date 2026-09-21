<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'About Llama Scout | Know the Place Before You Go';
$pageDescription = 'Learn what Llama Scout is, how Places are documented, why sensory and access details matter, and how members help keep outdoor information useful.';
$canonicalUrl = 'https://llamascout.com/about.php';

require __DIR__ . '/partials/header.php';
?>

<section class="about-page">

    <header class="about-hero">
        <div class="about-container about-hero-grid">

            <div class="about-hero-copy">
                <p class="about-eyebrow">About Llama Scout</p>

                <h1>
                    Outdoor information for the questions most maps do not answer.
                </h1>

                <p class="about-hero-lede">
                    Llama Scout helps people understand what an outdoor Place is
                    actually like before they commit to the drive. Not just where
                    it is, but what the road feels like, what you may hear, how
                    private it is, whether you can stay connected, what the weather
                    is doing, and whether the Place is likely to work for you.
                </p>

                <div class="about-actions">
                    <a class="about-button is-primary" href="/map.php">
                        <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>
                        Explore the Map
                    </a>

                    <a class="about-button" href="/field-guides">
                        Read the Field Guides
                    </a>

                    <a class="about-button" href="/scout-report-demo.php">
                        <i aria-hidden="true"><?= llama_icon('file-text') ?></i>
                        View Example Scout Report
                    </a>
                </div>
            </div>

            <div class="about-hero-card">
                <p class="about-card-eyebrow">The idea</p>
                <blockquote>
                    A place can look perfect in a photo and still be the wrong place
                    for the person standing there.
                </blockquote>
                <p>
                    Llama Scout is built around the details that help explain that
                    difference.
                </p>
            </div>

        </div>
    </header>


    <section class="about-section">
        <div class="about-container about-two-column">

            <div>
                <p class="about-eyebrow">What Llama Scout does</p>
                <h2>One Place, several different kinds of useful information.</h2>
            </div>

            <div class="about-copy">
                <p>
                    Traditional maps are very good at showing roads, boundaries,
                    coordinates, and destinations. Reviews are good at telling you
                    whether someone liked a place. Neither one necessarily tells
                    you what arriving there will feel like.
                </p>

                <p>
                    Llama Scout separates those questions. Road difficulty is not
                    the same thing as driver stress. Privacy is not the same thing
                    as remoteness. A quiet campsite can still have frequent foot
                    traffic. Strong cell service may be essential for one person
                    and irrelevant to another.
                </p>

                <p>
                    Instead of collapsing everything into one generic rating, each
                    Place can describe the conditions individually so you can decide
                    what matters to you.
                </p>
            </div>

        </div>
    </section>


    <section class="about-feature-section">
        <div class="about-container">

            <div class="about-section-heading">
                <p class="about-eyebrow">What gets documented</p>
                <h2>The details that change a trip.</h2>
            </div>

            <div class="about-feature-grid">

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('road') ?></i>
                    <h3>Road and vehicle access</h3>
                    <p>
                        Surface, difficulty, clearance concerns, exposure,
                        vehicle limitations, and the difference between a road
                        being technically passable and comfortable to drive.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('ear') ?></i>
                    <h3>Sensory conditions</h3>
                    <p>
                        Noise, traffic, crowds, odors, lighting, privacy,
                        repetitive sounds, and other conditions that may change
                        how comfortable a Place feels.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('antenna-bars-5') ?></i>
                    <h3>Connectivity</h3>
                    <p>
                        Cell service, carrier observations, Starlink suitability,
                        and enough context to know whether going offline is likely.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('temperature-sun') ?></i>
                    <h3>Weather</h3>
                    <p>
                        Public city weather for planning, with exact-location
                        weather and the five-day Place forecast available with
                        full membership.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('tent') ?></i>
                    <h3>Site details</h3>
                    <p>
                        Amenities, land manager, elevation, rules, nearby context,
                        photos, vehicle fit, and practical observations that help
                        explain how the Place functions.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('binoculars') ?></i>
                    <h3>Scout context</h3>
                    <p>
                        Field observations, contribution history, provenance,
                        and the record of who helped create or improve the Place.
                    </p>
                </article>

            </div>

        </div>
    </section>


    <section class="about-section about-origin-section">
        <div class="about-container about-two-column">

            <div>
                <p class="about-eyebrow">Why it exists</p>
                <h2>Because "you can camp there" is not enough information.</h2>
            </div>

            <div class="about-copy">
                <p>
                    Llama Scout grew out of a simple problem: a location can be
                    legally accessible and still be a terrible fit for the person
                    trying to use it.
                </p>

                <p>
                    The difference may be a washboard road, a cliff edge, highway
                    noise, generators, bright lighting, an exposed campsite,
                    unreliable connectivity, heavy foot traffic, unexpected odors,
                    a lack of shade, or dozens of other details that are difficult
                    to infer from a pin on a map.
                </p>

                <p>
                    Sensory information is a core part of Llama Scout because it is
                    useful information, not because every traveler experiences a
                    Place the same way. The goal is not to decide whether a Place
                    is "good." The goal is to describe it well enough that you can.
                </p>
            </div>

        </div>
    </section>


    <section class="about-principles-section">
        <div class="about-container">

            <div class="about-section-heading">
                <p class="about-eyebrow">How information is handled</p>
                <h2>A few rules matter more than pretending every answer is certain.</h2>
            </div>

            <div class="about-principles-grid">

                <article>
                    <span>01</span>
                    <h3>Unknown means unknown.</h3>
                    <p>
                        Missing information should not quietly become a zero,
                        a good rating, or a confident guess.
                    </p>
                </article>

                <article>
                    <span>02</span>
                    <h3>Conditions change.</h3>
                    <p>
                        Roads, weather, closures, regulations, service, crowds,
                        and the physical Place can all change after it is documented.
                    </p>
                </article>

                <article>
                    <span>03</span>
                    <h3>Context beats one score.</h3>
                    <p>
                        Separate ratings and field notes are more useful than a
                        single number that tries to decide the entire Place for you.
                    </p>
                </article>

                <article>
                    <span>04</span>
                    <h3>History stays visible.</h3>
                    <p>
                        A Place can begin with one contributor and improve through
                        later edits while preserving where the information came from.
                    </p>
                </article>

            </div>

        </div>
    </section>


    <section class="about-feature-section" id="contribution-labels">
        <div class="about-container">

            <div class="about-section-heading">
                <p class="about-eyebrow">Contribution labels</p>
                <h2>See who has personally helped document a Place.</h2>
                <p>
                    Each Place can show the highest contributor level that has
                    personally added field information to it. The label can move
                    upward when someone at a higher level creates the Place, checks
                    in there, or submits an approved field update. Simply reviewing
                    someone else's work does not change the label.
                </p>
            </div>

            <div class="about-feature-grid">
                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('users') ?></i>
                    <h3>Community Contributed</h3>
                    <p>
                        A signed-in Free Member personally contributed information
                        about the Place.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('user') ?></i>
                    <h3>Member Contributed</h3>
                    <p>
                        A paid Member with Complete Access personally contributed
                        information about the Place.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('binoculars') ?></i>
                    <h3>Scout Contributed</h3>
                    <p>
                        A trained Llama Scout personally documented the Place in the
                        field.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('compass') ?></i>
                    <h3>Master Scout Contributed</h3>
                    <p>
                        A trained Master Scout personally documented the Place.
                        Master Scouts also help moderate community contributions.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('shield-check') ?></i>
                    <h3>Admin Contributed</h3>
                    <p>
                        Llama Scout administration personally documented the Place.
                        Owner contributions are shown publicly as Admin contributions.
                    </p>
                </article>
            </div>

            <div class="about-copy about-contribution-label-note">
                <p>
                    A contribution label tells you who has personally documented the
                    Place. It does not guarantee that every condition is still the same.
                    Use Field Freshness and Place History to see when the Place was last
                    checked and how its information has changed over time.
                </p>

                <p>
                    "Llama Scouted" still means a trained Scout or Master Scout has
                    physically visited and documented the Place through the Scout
                    process. A Place can begin as Community Contributed and later become
                    Scout Contributed without losing its original history.
                </p>
            </div>

        </div>
    </section>


    <section class="about-community-section">
        <div class="about-container about-community-grid">

            <div>
                <p class="about-eyebrow">Community contributions</p>
                <h2>Llama Scout can get better without pretending every submission is automatically correct.</h2>

                <p>
                    Signed-in members can submit new Places, suggest updates, report
                    problems, contribute photos, and check in when they are physically
                    at a Place. Free Members contribute at the Community level. Paid
                    Members contribute at the Member level.
                </p>

                <p>
                    Approved contributions build a visible history and can earn
                    contribution points and eligible badges. Trained Scouts and Master
                    Scouts have separate field roles beyond ordinary membership.
                </p>
            </div>

            <div class="about-community-actions">
                <a class="about-button is-primary" href="/add-place.php">
                    Add a Place
                </a>

                <a class="about-button" href="https://account.llamascout.com/register.php">
                    Create Free Account
                </a>
            </div>

        </div>
    </section>


    <section class="about-membership-section">
        <div class="about-container about-membership-grid">

            <div>
                <p class="about-eyebrow">Membership and contributor roles</p>
                <h2>Access and contribution level answer two different questions.</h2>

                <p>
                    Membership controls what Place information an account can see.
                    Contribution level shows how that person participates when they
                    help document a Place.
                </p>
            </div>

            <div class="about-membership-list">
                <div>
                    <strong>Public</strong>
                    <span>
                        Approximate location, city and county, elevation, land
                        management, amenities, featured photo, and city weather.
                    </span>
                </div>

                <div>
                    <strong>Free Member</strong>
                    <span>
                        Public planning access plus Community-level contributions,
                        saved Places, profile tools, points, and eligible badges.
                        Places they originally contribute unlock completely for them.
                    </span>
                </div>

                <div>
                    <strong>Paid Member</strong>
                    <span>
                        Complete Access to the database plus Member-level contributions,
                        exact coordinates, full reports, exact-location weather, and
                        comparison tools.
                    </span>
                </div>

                <div>
                    <strong>Scout</strong>
                    <span>
                        A trained field contributor who receives complimentary Complete
                        Access while active and contributes at the Scout level.
                    </span>
                </div>

                <div>
                    <strong>Master Scout</strong>
                    <span>
                        A senior trained Scout who contributes at the Master Scout level
                        and can moderate eligible community submissions.
                    </span>
                </div>
            </div>

        </div>

        <div class="about-container about-membership-action">
            <a class="about-button is-primary" href="/membership">
                Compare Membership
            </a>
        </div>
    </section>


    <section class="about-final-section">
        <div class="about-container">

            <p class="about-eyebrow">Know the place before you go.</p>

            <h2>
                The point is not to tell you where to go.
                It is to help you know what you are choosing.
            </h2>

            <div class="about-actions">
                <a class="about-button is-primary" href="/map.php">
                    Explore Places
                </a>

                <a class="about-button" href="/field-guides">
                    Browse Field Guides
                </a>
            </div>

        </div>
    </section>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
