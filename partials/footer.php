<?php

declare(strict_types=1);

$config = llama_config();

$footerSiteBase = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);

$footerAccountBase = rtrim(
    (string) ($config['app']['account_url'] ?? 'https://account.llamascout.com'),
    '/'
);

$footerUser = current_user();
?>

</main>

<footer class="site-footer">

    <div class="footer-inner">

        <div class="footer-brand-block">

            <a
                class="footer-brand"
                href="<?= htmlspecialchars($footerSiteBase . '/', ENT_QUOTES, 'UTF-8') ?>"
                aria-label="Llama Scout home"
            >
                <img
                    src="<?= htmlspecialchars($footerSiteBase . '/images/logo-footer.png', ENT_QUOTES, 'UTF-8') ?>"
                    alt="Llama Scout"
                >
            </a>

            <p class="footer-tagline">
                Detailed, field-scouted information for outdoor travel.
            </p>

            <div
                class="social-links"
                aria-label="Follow Llama Scout"
            >

                <a
                    href="https://instagram.com/thellamascout"
                    aria-label="Llama Scout on Instagram"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <i class="fa-brands fa-instagram" aria-hidden="true"></i>
                </a>

                <a
                    href="https://tiktok.com/@thellamascout"
                    aria-label="Llama Scout on TikTok"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <i class="fa-brands fa-tiktok" aria-hidden="true"></i>
                </a>

                <a
                    href="https://x.com/thellamascout"
                    aria-label="Llama Scout on X"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                </a>

                <a
                    href="https://bsky.app/profile/llamascout.com"
                    aria-label="Llama Scout on Bluesky"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <i class="fa-brands fa-bluesky" aria-hidden="true"></i>
                </a>

                <a
                    href="https://facebook.com/thellamascout"
                    aria-label="Llama Scout on Facebook"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
                </a>

            </div>

        </div>


        <div class="footer-link-columns">

            <nav
                class="footer-link-column"
                aria-label="Explore Llama Scout"
            >

                <h2>Explore</h2>

                <a href="<?= htmlspecialchars($footerSiteBase . '/map.php', ENT_QUOTES, 'UTF-8') ?>">
                    Map
                </a>

                <a href="<?= htmlspecialchars($footerSiteBase . '/field-guides', ENT_QUOTES, 'UTF-8') ?>">
                    Field Guides
                </a>

                <a href="<?= htmlspecialchars($footerSiteBase . '/about.php', ENT_QUOTES, 'UTF-8') ?>">
                    About Llama Scout
                </a>

                <a href="<?= htmlspecialchars($footerSiteBase . '/membership', ENT_QUOTES, 'UTF-8') ?>">
                    Membership
                </a>

                <?php if ($footerUser): ?>
                    <a href="<?= htmlspecialchars($footerSiteBase . '/add-place.php', ENT_QUOTES, 'UTF-8') ?>">
                        Add a Place
                    </a>

                    <a href="<?= htmlspecialchars($footerAccountBase . '/', ENT_QUOTES, 'UTF-8') ?>">
                        My Account
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($footerAccountBase . '/register.php', ENT_QUOTES, 'UTF-8') ?>">
                        Create an Account
                    </a>

                    <a href="<?= htmlspecialchars($footerAccountBase . '/login.php', ENT_QUOTES, 'UTF-8') ?>">
                        Sign In
                    </a>
                <?php endif; ?>

            </nav>


            <nav
                class="footer-link-column"
                aria-label="Legal"
            >

                <h2>Legal</h2>

                <a href="<?= htmlspecialchars($footerSiteBase . '/privacy.php', ENT_QUOTES, 'UTF-8') ?>">
                    Privacy Policy
                </a>

                <a href="<?= htmlspecialchars($footerSiteBase . '/privacy-choices.php', ENT_QUOTES, 'UTF-8') ?>">
                    Privacy Choices
                </a>

                <a href="<?= htmlspecialchars($footerSiteBase . '/terms.php', ENT_QUOTES, 'UTF-8') ?>">
                    Terms of Use
                </a>

                <a href="<?= htmlspecialchars($footerSiteBase . '/accessibility.php', ENT_QUOTES, 'UTF-8') ?>">
                    Accessibility
                </a>

                <a href="<?= htmlspecialchars($footerSiteBase . '/disclaimer.php', ENT_QUOTES, 'UTF-8') ?>">
                    Outdoor Disclaimer
                </a>

            </nav>

        </div>

    </div>


    <div class="footer-bottom">

        <span>
            &copy; <?= date('Y') ?> Llama Scout. All Rights Reserved.
        </span>

        <span>
            Know the place before you go.
        </span>

    </div>

</footer>

<script src="<?= htmlspecialchars($footerSiteBase . '/js/privacy.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($footerSiteBase . '/js/accessibility.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($footerSiteBase . '/js/mobile-menu.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($footerSiteBase . '/js/photo-uploader.js', ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>
