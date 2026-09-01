(() => {
    "use strict";

    const buttons =
        document.querySelectorAll(
            "[data-share]"
        );

    if (!buttons.length) {
        return;
    }

    buttons.forEach(
        initializeShareButton
    );

    function initializeShareButton(button) {
        const label =
            button.querySelector(
                "[data-share-label]"
            );

        const originalLabel =
            label
                ? label.textContent
                : "Share";

        button.addEventListener(
            "click",
            async () => {
                if (button.disabled) {
                    return;
                }

                const title =
                    button.dataset.shareTitle
                    || document.title
                    || "Llama Scout";

                const text =
                    button.dataset.shareText
                    || "";

                const url =
                    button.dataset.shareUrl
                    || window.location.href;

                button.disabled = true;

                try {
                    if (
                        typeof navigator.share
                        === "function"
                    ) {
                        await navigator.share({
                            title,
                            text,
                            url
                        });

                        flashLabel(
                            label,
                            "Shared",
                            originalLabel
                        );

                        return;
                    }

                    await copyShareUrl(url);

                    flashLabel(
                        label,
                        "Link copied",
                        originalLabel
                    );
                } catch (error) {
                    if (
                        error
                        && error.name === "AbortError"
                    ) {
                        return;
                    }

                    try {
                        await copyShareUrl(url);

                        flashLabel(
                            label,
                            "Link copied",
                            originalLabel
                        );
                    } catch (copyError) {
                        window.prompt(
                            "Copy this link:",
                            url
                        );
                    }
                } finally {
                    button.disabled = false;
                }
            }
        );
    }

    async function copyShareUrl(url) {
        if (
            navigator.clipboard
            && typeof navigator.clipboard.writeText
                === "function"
        ) {
            await navigator.clipboard.writeText(
                url
            );

            return;
        }

        const input =
            document.createElement(
                "textarea"
            );

        input.value = url;
        input.setAttribute(
            "readonly",
            ""
        );

        input.style.position =
            "fixed";
        input.style.opacity =
            "0";

        document.body.appendChild(
            input
        );

        input.select();

        const copied =
            document.execCommand(
                "copy"
            );

        input.remove();

        if (!copied) {
            throw new Error(
                "Copy failed."
            );
        }
    }

    function flashLabel(
        label,
        message,
        original
    ) {
        if (!label) {
            return;
        }

        label.textContent =
            message;

        window.setTimeout(
            () => {
                label.textContent =
                    original;
            },
            1600
        );
    }
})();
