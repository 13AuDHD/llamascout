(() => {
    const countdowns = document.querySelectorAll('[data-promotion-countdown]');

    if (!countdowns.length) {
        return;
    }

    const formatRemaining = (remainingMs) => {
        const totalSeconds = Math.max(0, Math.floor(remainingMs / 1000));
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        const clock =
            String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');

        return days > 0
            ? `${days}d ${clock}`
            : clock;
    };

    countdowns.forEach((countdown) => {
        const targetValue = countdown.dataset.endsAt || '';
        const targetTime = Date.parse(targetValue);

        if (!Number.isFinite(targetTime)) {
            countdown.hidden = true;
            return;
        }

        const value = countdown.querySelector('[data-promotion-countdown-value]');

        if (!value) {
            return;
        }

        const update = () => {
            const remaining = targetTime - Date.now();

            if (remaining <= 0) {
                window.location.reload();
                return;
            }

            value.textContent = formatRemaining(remaining);
        };

        update();
        window.setInterval(update, 1000);
    });
})();
