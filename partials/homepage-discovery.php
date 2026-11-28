<?php
declare(strict_types=1);

$homepageEscape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="map-home-discovery map-shell" aria-labelledby="home-discovery-heading">
    <p class="map-eyebrow">Plan your next stop</p>
    <h2 id="home-discovery-heading">Camping, overlanding and RV travel, with the details that matter</h2>
    <p class="map-home-intro">Llama Scout is a community-built outdoor travel map for campers, RV travelers, van lifers and overlanders. Explore Places to stay and Resources to use along the way. Our goal is to help you understand what a location is actually like before you drive there.</p>

    <div class="map-home-topic-grid">
        <article>
            <h3>Find campgrounds and places to stay</h3>
            <p>Explore public campgrounds, RV parks and overnight parking options. Compare available amenities, elevation and land management. Membership also opens up dispersed camping and vehicle pull-offs that are not listed on the public map.</p>
            <a href="#map-explorer">Explore the camping map</a>
        </article>
        <article>
            <h3>Find water, RV dump stations and roadside services</h3>
            <p>Use the Resource filters to find water fills, dump stations, showers, laundromats, restrooms, propane, fuel, groceries and EV charging. Check the reported services and hours, and confirm availability before traveling.</p>
            <a href="#map-explorer">Explore Resources on the map</a>
        </article>
        <article>
            <h3>Know the access and camping conditions</h3>
            <p>Available Scout reports describe road difficulty, vehicle fit, accessibility, noise, privacy, shade and connectivity. Complete Access includes detailed road and sensory information and exact-location weather where available.</p>
            <a href="/scout-report-demo.php">See a sample Scout report</a>
        </article>
        <article>
            <h3>Help other travelers know before they go</h3>
            <p>Share a Place, report a change or contribute a photograph from your visit. Real observations help other travelers decide whether a location fits their needs. Imported listings may be incomplete or unverified.</p>
            <a href="/add-place.php">Contribute to Llama Scout</a>
        </article>
    </div>

    <?php if (!empty($homepageLinks['places']) || !empty($homepageLinks['resources'])): ?>
        <section class="map-home-directory" aria-labelledby="home-directory-heading">
            <h2 id="home-directory-heading">Explore public Places and Resources</h2>
            <p>Start with a few listings from the map, or search for a town or location above.</p>
            <div class="map-home-link-grid">
                <?php foreach (['places' => ['Places to stay and explore', '/place.php?slug='], 'resources' => ['Resources along the way', '/resource.php?slug=']] as $kind => [$heading, $base]): ?>
                    <?php if (!empty($homepageLinks[$kind])): ?>
                        <div>
                            <h3><?= $homepageEscape($heading) ?></h3>
                            <ul>
                                <?php foreach ($homepageLinks[$kind] as $row): ?>
                                    <?php $locality = implode(', ', array_filter(array_map('trim', [(string) ($row['city'] ?? ''), (string) ($row['state'] ?? '')]))); ?>
                                    <li>
                                        <a href="<?= $homepageEscape($base . rawurlencode((string) $row['slug'])) ?>"><?= $homepageEscape($row['name']) ?></a>
                                        <?php if ($locality !== ''): ?><span><?= $homepageEscape($locality) ?></span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="map-home-guides" aria-labelledby="home-guides-heading">
        <h2 id="home-guides-heading">Field guides for your next adventure</h2>
        <p>Practical advice for camping on public lands, planning a route and finding a campsite that works for you.</p>
        <ul>
            <li><a href="/field-guides/dispersed-camping-rules-you-need-to-know">Dispersed camping rules you need to know</a></li>
            <li><a href="/field-guides/read-forest-service-road-conditions-before-you-leave">How to read Forest Service road conditions</a></li>
            <li><a href="/field-guides/cell-service-starlink-friendly-campsites">Find cell service and Starlink-friendly campsites</a></li>
            <li><a href="/field-guides/camping-with-sensory-overload">Camping with sensory overload</a></li>
        </ul>
        <a href="/field-guides">Read all Llama Scout field guides</a>
    </section>

    <section class="map-home-about" aria-labelledby="home-about-heading">
        <h2 id="home-about-heading">Know the place before you go.</h2>
        <p>Free access lets you explore public locations. Membership unlocks more camping locations and detailed planning information. Llama Scout is designed to help you make your own decisions; always check current land-use rules, conditions and restrictions before a trip.</p>
        <div class="map-home-actions">
            <a href="/about.php">About Llama Scout</a>
            <a href="/membership">Compare free and paid membership</a>
        </div>
    </section>
</section>
