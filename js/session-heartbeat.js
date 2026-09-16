(() => {
    'use strict';

    const endpoint =
        window.location.origin
        + '/session-heartbeat.php';

    const intervalMs =
        60 * 1000;

    let timer = null;
    let requestInFlight = false;


    async function checkSession() {
        if (requestInFlight) {
            return;
        }

        requestInFlight = true;

        try {
            const response =
                await fetch(
                    endpoint,
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                        },
                    }
                );

            if (!response.ok) {
                return;
            }

            const payload =
                await response.json();

            /*
             * A heartbeat is presence/session monitoring, not the
             * authority that destroys a browsing session. Only an
             * explicit server-side revocation may force Account back
             * to the sign-in page.
             */
            if (
                payload
                && payload.revoked === true
                && window.location.hostname
                    === 'account.llamascout.com'
                && !window.location.pathname.endsWith('/login.php')
                && !window.location.pathname.endsWith('/register.php')
            ) {
                window.location.replace(
                    'https://account.llamascout.com/login.php'
                );
            }
        } catch (error) {
            /*
             * Temporary network, browser-sleep, or tab-resume failures
             * are intentionally non-critical and must not sign anyone out.
             */
        } finally {
            requestInFlight = false;
        }
    }


    function schedule() {
        if (timer !== null) {
            window.clearInterval(timer);
        }

        timer =
            window.setInterval(
                checkSession,
                intervalMs
            );
    }


    checkSession();
    schedule();


    document.addEventListener(
        'visibilitychange',
        () => {
            if (!document.hidden) {
                checkSession();
            }
        }
    );
})();
