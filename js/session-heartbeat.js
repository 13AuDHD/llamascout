(() => {
    'use strict';

    const endpoint =
        'https://llamascout.com/session-heartbeat.php';

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
                        credentials: 'include',
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
             * Account pages should never continue presenting a
             * signed-in dashboard after an admin has invalidated
             * the session.
             */
            if (
                payload
                && payload.authenticated === false
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
             * Presence is intentionally non-critical. A temporary
             * network problem should not interrupt the page.
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


    window.addEventListener(
        'focus',
        checkSession
    );
})();
