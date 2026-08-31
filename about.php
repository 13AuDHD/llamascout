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
                        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                        Explore the Map
                    </a>

                    <a class="about-button" href="/field-guides">
                        Read the Field Guides
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
                    <i class="fa-solid fa-road" aria-hidden="true"></i>
                    <h3>Road and vehicle access</h3>
                    <p>
                        Surface, difficulty, clearance concerns, exposure,
                        vehicle limitations, and the difference between a road
                        being technically passable and comfortable to drive.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i class="fa-solid fa-ear-listen" aria-hidden="true"></i>
                    <h3>Sensory conditions</h3>
                    <p>
                        Noise, traffic, crowds, odors, lighting, privacy,
                        repetitive sounds, and other conditions that may change
                        how comfortable a Place feels.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i class="fa-solid fa-signal" aria-hidden="true"></i>
                    <h3>Connectivity</h3>
                    <p>
                        Cell service, carrier observations, Starlink suitability,
                        and enough context to know whether going offline is likely.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i class="fa-solid fa-cloud-sun" aria-hidden="true"></i>
                    <h3>Weather</h3>
                    <p>
                        Public city weather for planning, with exact-location
                        weather and the five-day Place forecast available with
                        full membership.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i class="fa-solid fa-campground" aria-hidden="true"></i>
                    <h3>Site details</h3>
                    <p>
                        Amenities, land manager, elevation, rules, nearby context,
                        photos, vehicle fit, and practical observations that help
                        explain how the Place functions.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
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


    <section class="about-section">
        <div class="about-container about-two-column">

            <div>
                <p class="about-eyebrow">Llama Scouted</p>
                <h2>What that label actually means.</h2>
            </div>

            <div class="about-copy">
                <p>
                    "Llama Scouted" means a Llama Scout has physically been at the
                    Place and documented it through the Scout process. It is a record
                    that field observation happened, not a permanent guarantee that
                    every condition remains unchanged forever.
                </p>

                <p>
                    A Place can also begin as a member contribution and later become
                    Llama Scouted. Future member edits can improve that Place without
                    erasing the fact that it was field-scouted at an earlier point.
                </p>
            </div>

        </div>
    </section>


    <section class="about-community-section">
        <div class="about-container about-community-grid">

            <div>
                <p class="about-eyebrow">Member contributions</p>
                <h2>Llama Scout can get better without pretending every submission is automatically correct.</h2>

                <p>
                    Members can submit new Places, suggest updates, report problems,
                    and contribute photos. Those contributions go through review
                    before they change the published Place.
                </p>

                <p>
                    Approved contributions build a visible history. Members can earn
                    contribution points and badges, while the Place keeps provenance
                    showing how it was created and improved.
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
                <p class="about-eyebrow">Public and paid access</p>
                <h2>Useful public context, complete Place reports for members with full access.</h2>

                <p>
                    Public visitors can see general location and planning information
                    without exposing the exact Place. Full membership unlocks the
                    exact coordinates and the detailed field report.
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
                        Public access plus saved Places, contributions, profile,
                        badges, reports, and member tools.
                    </span>
                </div>

                <div>
                    <strong>Paid Member</strong>
                    <span>
                        Exact coordinates, full gallery, road and access details,
                        sensory conditions, connectivity, complete report, and
                        exact-location weather.
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
