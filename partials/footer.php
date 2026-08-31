<?php

declare(strict_types=1);

$config = llama_config();
$siteUrl = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);
?>

</main>

<footer class="site-footer">
    <div class="site-footer-inner">
        <p>&copy; <?= date('Y') ?> Llama Scout</p>
    </div>
</footer>

<script src="<?= htmlspecialchars($siteUrl . '/js/accessibility.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($siteUrl . '/js/mobile-menu.js', ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>
