<?php
declare(strict_types=1);

/* One modal for both Place and Resource. Callers provide coordinates + URLs. */
$directionsContext = ($directionsContext ?? 'resource') === 'place' ? 'place' : 'resource';
$directionsCoordinates = trim((string) ($directionsCoordinates ?? ''));
if ($directionsCoordinates === '') {
    return;
}
$directionsGoogleUrl = (string) ($directionsGoogleUrl ?? '');
$directionsAppleUrl = (string) ($directionsAppleUrl ?? '');
$directionsOnxUrl = (string) ($directionsOnxUrl ?? 'https://webmap.onxmaps.com/offroad/');
$directionsEscape = static fn (string $value): string =>
    htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<dialog class="map-directions-dialog is-<?= $directionsEscape($directionsContext) ?>" id="map-directions-dialog"
        aria-labelledby="map-directions-title"
        aria-describedby="map-directions-description">
    <div class="map-directions-header">
        <div>
            <p class="map-directions-eyebrow"><?= $directionsContext === 'place' ? 'Place' : 'Resource' ?> directions</p>
            <h2 id="map-directions-title">Choose your maps app</h2>
            <p id="map-directions-description">Open this location with your preferred map.</p>
        </div>
        <button type="button" class="map-directions-close"
                data-map-directions-close aria-label="Close directions menu"><?= llama_icon('x') ?></button>
    </div>
    <div class="map-directions-options">
        <a href="<?= $directionsEscape($directionsGoogleUrl) ?>"
           target="_blank" rel="noopener noreferrer">
            <span class="map-directions-provider-icon" aria-hidden="true"><?= llama_icon('brand-google-maps') ?></span>
            <span class="map-directions-provider-label"><strong>Google Maps</strong><small>Driving directions</small></span>
            <?= llama_icon('arrow-up-right') ?>
        </a>
        <a href="<?= $directionsEscape($directionsAppleUrl) ?>"
           target="_blank" rel="noopener noreferrer">
            <span class="map-directions-provider-icon" aria-hidden="true"><?= llama_icon('brand-apple') ?></span>
            <span class="map-directions-provider-label"><strong>Apple Maps</strong><small>Driving directions</small></span>
            <?= llama_icon('arrow-up-right') ?>
        </a>
        <a href="<?= $directionsEscape($directionsOnxUrl) ?>"
           target="_blank" rel="noopener noreferrer">
            <span class="map-directions-provider-icon" aria-hidden="true"><?= llama_icon('brand-onx') ?></span>
            <span class="map-directions-provider-label"><strong>onX Offroad</strong><small>Explore in onX Maps</small></span>
            <?= llama_icon('arrow-up-right') ?>
        </a>
    </div>
    <div class="map-directions-footer">
        <p class="map-directions-note">For onX, copy these coordinates, search for them in the map, then save a waypoint.</p>
        <div class="map-directions-coordinates">
            <output id="map-directions-coordinates"><?= $directionsEscape($directionsCoordinates) ?></output>
            <button type="button" data-resource-copy-coordinates><?= llama_icon('copy') ?> Copy</button>
        </div>
        <p class="map-directions-copy-status" data-resource-copy-status role="status" aria-live="polite"></p>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('map-directions-dialog');
    const open = document.querySelector('[data-map-directions-open]');
    if (!dialog || !open) return;
    const close = dialog.querySelector('[data-map-directions-close]');
    const copy = dialog.querySelector('[data-resource-copy-coordinates]');
    const status = dialog.querySelector('[data-resource-copy-status]');
    const value = dialog.querySelector('#map-directions-coordinates');
    open.addEventListener('click', () => dialog.showModal());
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
    copy.addEventListener('click', async () => {
        const coordinates = value.textContent.trim();
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(coordinates);
            status.textContent = 'Coordinates copied.';
        } catch (_) {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(value);
            selection.removeAllRanges();
            selection.addRange(range);
            status.textContent = 'Select and copy the highlighted coordinates.';
        }
    });
})();
</script>
