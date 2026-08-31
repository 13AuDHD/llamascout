/* =========================================================
   LLAMA SCOUT
   MAINTENANCE COUNTDOWN
   ========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    initMaintenancePage
);

function initMaintenancePage() {
    initMaintenanceCountdown();
    initNoTimeMessage();
}

function initMaintenanceCountdown() {
    const countdown =
        document.querySelector(
            "[data-maintenance-countdown]"
        );

    if (!countdown) {
        return;
    }

    const returnAt =
        countdown.dataset.returnAt;

    if (!returnAt) {
        return;
    }

    const targetTime =
        new Date(returnAt).getTime();

    if (!Number.isFinite(targetTime)) {
        return;
    }

    const finishedNow =
        updateMaintenanceCountdown(
            countdown,
            targetTime
        );

    if (finishedNow) {
        return;
    }

    const interval =
        window.setInterval(
            function () {
                const finished =
                    updateMaintenanceCountdown(
                        countdown,
                        targetTime
                    );

                if (finished) {
                    window.clearInterval(interval);
                }
            },
            1000
        );
}

function updateMaintenanceCountdown(
    countdown,
    targetTime
) {
    const remaining =
        targetTime - Date.now();

    if (remaining <= 0) {
        finishMaintenanceCountdown(countdown);
        return true;
    }

    const totalSeconds =
        Math.floor(remaining / 1000);

    const days =
        Math.floor(totalSeconds / 86400);

    const hours =
        Math.floor(
            (totalSeconds % 86400) / 3600
        );

    const minutes =
        Math.floor(
            (totalSeconds % 3600) / 60
        );

    const seconds =
        totalSeconds % 60;

    setCountdownValue(
        countdown,
        "[data-countdown-days]",
        days
    );

    setCountdownValue(
        countdown,
        "[data-countdown-hours]",
        hours
    );

    setCountdownValue(
        countdown,
        "[data-countdown-minutes]",
        minutes
    );

    setCountdownValue(
        countdown,
        "[data-countdown-seconds]",
        seconds
    );

    return false;
}

function setCountdownValue(
    countdown,
    selector,
    value
) {
    const element =
        countdown.querySelector(selector);

    if (!element) {
        return;
    }

    element.textContent =
        String(Math.max(0, value))
            .padStart(2, "0");
}

function finishMaintenanceCountdown(
    countdown
) {
    const grid =
        countdown.querySelector(
            ".maintenance-countdown-grid"
        );

    const label =
        countdown.querySelector(
            ".maintenance-countdown-label"
        );

    const finished =
        countdown.querySelector(
            "[data-countdown-finished]"
        );

    if (grid) {
        grid.hidden = true;
    }

    if (label) {
        label.hidden = true;
    }

    if (finished) {
        const messages = [
            "Any minute now. The llama says they are almost done.",
            "Time is up. The llama is still holding the wrench.",
            "The countdown ended. Nobody make eye contact with the extra screw.",
            "We should be back. The llama would like another five minutes.",
            "Zeroes across the board. Apparently the llama works on mountain time.",
            "The timer says done. The toolbox says otherwise."
        ];

        finished.textContent =
            randomMaintenanceMessage(messages);

        finished.hidden = false;
    }
}

function initNoTimeMessage() {
    const message =
        document.querySelector(
            "[data-maintenance-no-time]"
        );

    if (!message) {
        return;
    }

    const messages = [
        "We will be back as soon as the llama stops touching things.",
        "Temporary maintenance. The llama found a wrench and now we all have to live with it.",
        "No countdown this time. We are fixing something before it becomes a more interesting problem.",
        "Quick trail repair. The llama insists this will only take a minute.",
        "The site is taking a short break while the llama checks under the hood.",
        "We are tightening a few things. The leftover screw is probably decorative."
    ];

    message.textContent =
        randomMaintenanceMessage(messages);
}

function randomMaintenanceMessage(messages) {
    return messages[
        Math.floor(
            Math.random() * messages.length
        )
    ];
}
