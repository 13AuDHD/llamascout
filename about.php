<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'About Llama Scout | Know the Place Before You Go';
$pageDescription = 'Llama Scout helps you know what an outdoor Place is actually like before you go, with road, sensory, access, weather, connectivity, and field details you usually have to discover the hard way.';
$canonicalUrl = 'https://llamascout.com/about.php';

require __DIR__ . '/partials/header.php';
?>

<section class="about-page">

    <header class="about-hero">
        <div class="about-container about-hero-grid">

            <div class="about-hero-copy">
                <p class="about-eyebrow">About Llama Scout</p>

                <h1>
                    Know the place before you go.
                </h1>

                <p class="about-hero-lede">
                    Llama Scout helps you figure out what an outdoor Place is
                    actually like before you spend an hour bouncing down a forest
                    road to discover it the hard way.
                </p>

                <p>
                    We document the details a map pin usually leaves out: road
                    conditions, privacy, noise, campsite size, accessibility,
                    cell service, weather, crowds, smells, shade, nearby activity,
                    and all the other little things that can turn a great-looking
                    Place into either your new favorite spot or a very quick U-turn.
                </p>

                <div class="about-actions">
                    <a class="about-button is-primary" href="/map.php">
                        <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>
                        Explore the Map
                    </a>

                    <a class="about-button" href="/scout-report-demo.php">
                        <i aria-hidden="true"><?= llama_icon('file-text') ?></i>
                        See a Scout Report
                    </a>

                    <a class="about-button" href="/field-guides">
                        Read the Field Guides
                    </a>
                </div>
            </div>

            <div class="about-hero-card">
                <p class="about-card-eyebrow">The basic idea</p>

                <blockquote>
                    A Place can look perfect in a photo and still be completely
                    wrong for the person standing there.
                </blockquote>

                <p>
                    The photo probably did not mention the washboard road,
                    generators, low branches, questionable cell service,
                    surprise mud pit, or cliff behind your bumper.
                </p>

                <p>
                    We like knowing that part too.
                </p>
            </div>

        </div>
    </header>


    <section class="about-section">
        <div class="about-container about-two-column">

            <div>
                <p class="about-eyebrow">More than a pin on a map</p>
                <h2>We scout the details.</h2>
            </div>

            <div class="about-copy">
                <p>
                    Maps are great at telling you where something is. Reviews are
                    great at telling you whether somebody liked it.
                </p>

                <p>
                    Llama Scout is interested in the weirdly specific stuff you
                    usually discover after you arrive.
                </p>

                <p>
                    How rough is the road? Can you turn a big vehicle around?
                    Is the campsite actually private? Are planes flying overhead?
                    Does Verizon work? Is there enough open sky for Starlink?
                    Is that peaceful river going to be the perfect soundtrack,
                    or the only thing you hear all night?
                </p>

                <p>
                    We keep those details separate instead of squashing an entire
                    Place into one magical rating. You decide what matters to you.
                    We just bring the binoculars.
                </p>
            </div>

        </div>
    </section>


    <section class="about-feature-section">
        <div class="about-container">

            <div class="about-section-heading">
                <p class="about-eyebrow">What gets documented</p>
                <h2>The details that can change an entire trip.</h2>
                <p>
                    Some of this sounds oddly specific. That is very much the point.
                </p>
            </div>

            <div class="about-feature-grid">

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('road') ?></i>
                    <h3>Road and vehicle access</h3>
                    <p>
                        Road surface, clearance, ruts, rocks, mud, steep sections,
                        tight turns, water crossings, drop-offs, and what kinds of
                        vehicles can realistically get there.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('ear') ?></i>
                    <h3>Sensory conditions</h3>
                    <p>
                        Noise, smells, crowds, lighting, privacy, traffic,
                        generators, airplanes, repetitive sounds, and whatever
                        else your senses are probably going to notice.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('antenna-bars-5') ?></i>
                    <h3>Connectivity</h3>
                    <p>
                        Cell service, tested carriers, Starlink suitability, and
                        enough context to know whether you can stay connected or
                        are about to accidentally disappear from civilization.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('temperature-sun') ?></i>
                    <h3>Weather</h3>
                    <p>
                        Current conditions and forecasts tied as closely as possible
                        to the actual Place instead of whichever town happens to be
                        somewhere down the mountain.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('tent') ?></i>
                    <h3>The Place itself</h3>
                    <p>
                        Space, vehicle fit, tent room, leveling, shade, tree cover,
                        amenities, surroundings, rules, hazards, and the practical
                        details you need once you arrive.
                    </p>
                </article>

                <article class="about-feature-card">
                    <i aria-hidden="true"><?= llama_icon('binoculars') ?></i>
                    <h3>Field observations</h3>
                    <p>
                        Photos, Scout Notes, check-ins, Place History, and reports
                        from people who have actually been there, looked around,
                        and paid attention.
                    </p>
                </article>

            </div>

        </div>
    </section>


    <section class="about-section about-origin-section">
        <div class="about-container about-two-column">

            <div>
                <p class="about-eyebrow">Why Llama Scout exists</p>
                <h2>Because "you can camp there" is not enough information.</h2>
            </div>

            <div class="about-copy">
                <p>
                    Two people can arrive at the exact same campsite and have
                    completely different experiences.
                </p>

                <p>
                    One person hears a river and thinks, "perfect." Another person
                    hears that same river all night and wonders why nobody mentioned
                    that it sounds like sleeping beside a washing machine.
                </p>

                <p>
                    One traveler wants total isolation. Another needs reliable cell
                    service for work. One rig can crawl over rocks without a second
                    thought. Another needs a smooth road and plenty of room to turn
                    around.
                </p>

                <p>
                    None of those people are wrong.
                </p>

                <p>
                    That is why Llama Scout focuses on describing what is actually
                    there instead of deciding whether a Place is "good" or "bad."
                    Give people the details and let them decide whether it works
                    for them.
                </p>
            </div>

        </div>
    </section>


    <section class="about-principles-section">
        <div class="about-container">

            <div class="about-section-heading">
                <p class="about-eyebrow">The Llama Scout rulebook</p>
                <h2>Observe it. Describe it. Do not invent it.</h2>
            </div>

            <div class="about-principles-grid">

                <article>
                    <span>01</span>
                    <h3>Unknown means unknown.</h3>
                    <p>
                        We would rather leave something unanswered than fill the
                        blank with a confident guess wearing a tiny fake mustache.
                    </p>
                </article>

                <article>
                    <span>02</span>
                    <h3>Conditions change.</h3>
                    <p>
                        Roads wash out. Trees fall. Cell towers change. Rules change.
                        Weather does whatever weather feels like doing.
                    </p>
                </article>

                <article>
                    <span>03</span>
                    <h3>Details beat one score.</h3>
                    <p>
                        Privacy, road difficulty, noise, shade, connectivity, and
                        accessibility deserve better than being mashed into one number.
                    </p>
                </article>

                <article>
                    <span>04</span>
                    <h3>History matters.</h3>
                    <p>
                        Places evolve, reports improve, and new observations get added.
                        Llama Scout keeps that history instead of pretending the latest
                        answer appeared out of thin air.
                    </p>
                </article>

            </div>

        </div>
    </section>


    <section class="about-community-section">
        <div class="about-container about-community-grid">

            <div>
                <p class="about-eyebrow">Built in the field</p>
                <h2>Better information comes from people who actually go outside.</h2>

                <p>
                    Llama Scout grows through real observations. Members can add
                    Places, suggest updates, contribute photos, report problems,
                    check in, and help keep information current.
                </p>

                <p>
                    Approved contributions build Place History, earn points, unlock
                    eligible badges, and help the next person know a little more
                    before they turn onto that suspiciously narrow dirt road.
                </p>

                <p>
                    The goal is not to collect the most pins. The goal is to make
                    every useful pin better.
                </p>
            </div>

            <div class="about-community-actions">
                <a class="about-button is-primary" href="/add-place.php">
                    Add a Place
                </a>

                <a class="about-button" href="https://account.llamascout.com/register.php">
                    Join Llama Scout
                </a>
            </div>

        </div>
    </section>


    <section class="about-membership-section">
        <div class="about-container about-membership-grid">

            <div>
                <p class="about-eyebrow">Around the herd</p>
                <h2>Different roles. Same mission.</h2>

                <p>
                    Not everyone participates in Llama Scout the same way.
                    Some people are here to plan trips. Some help improve Places.
                    Some put on the imaginary field vest and go scouting.
                </p>
            </div>

            <div class="about-membership-list">

                <div>
                    <strong>Community</strong>
                    <span>
                        A signed-in member of Llama Scout who can contribute Places,
                        submit updates, add photos, earn points, collect eligible
                        badges, and help improve the map.
                    </span>
                </div>

                <div>
                    <strong>Member</strong>
                    <span>
                        A member with Complete Access to Llama Scout's full Place
                        information, detailed reports, exact locations, comparison
                        tools, and Member-level contributions.
                    </span>
                </div>

                <div>
                    <strong>Scout</strong>
                    <span>
                        A trained field contributor who completes Scout training,
                        learns the Llama Scout reporting standards, and receives
                        access to Scout reporting and field tools.
                    </span>
                </div>

                <div>
                    <strong>Master Scout</strong>
                    <span>
                        An experienced Scout who has met the additional requirements
                        for Master Scout, demonstrated consistently strong field work,
                        and receives additional review and moderation tools.
                    </span>
                </div>

            </div>

        </div>

        <div class="about-container about-membership-action">
            <a class="about-button is-primary" href="/membership">
                Compare Memberships
            </a>
        </div>
    </section>


    <section class="about-final-section">
        <div class="about-container">

            <p class="about-eyebrow">Know the place before you go.</p>

            <h2>
                Less guessing. Fewer surprises. Better adventures.
            </h2>

            <p>
                There is still no guarantee a llama will be waiting when you arrive.
                We are working with the llamas on that.
            </p>

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
