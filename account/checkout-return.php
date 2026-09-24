<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';

require_login();
start_llama_session();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$sessionId =
    trim(
        (string) (
            $_GET['session_id']
            ?? ''
        )
    );

$db = db();


/* =========================================================
   RETURN STATE
   ========================================================= */

$status =
    'processing';

$message =
    'Stripe is confirming your membership. This usually takes only a moment.';

$reference =
    '';


/* =========================================================
   TEMPORARY MEMBERSHIP BASKET
   ========================================================= */

/*
 * checkout.php stores the Stripe Checkout Session associated
 * with the current temporary membership basket.
 *
 * We use that ID when clearing the basket so an old Checkout
 * Session returning from another tab cannot accidentally erase
 * a newer membership selection.
 */

$pendingCheckoutSessionId =
    trim(
        (string) (
            $_SESSION[
                'pending_membership_checkout_session_id'
            ]
            ?? ''
        )
    );


/* =========================================================
   CHECKOUT RETURN
   ========================================================= */

if ($sessionId === '') {

    http_response_code(400);

    $status =
        'error';

    $message =
        'The checkout return did not include a Stripe session.';

} else {

    try {

        $stripe =
            llama_stripe_client();

        $session =
            $stripe
                ->checkout
                ->sessions
                ->retrieve(
                    $sessionId,
                    []
                );


        /* =====================================================
           OWNERSHIP CHECK
           ===================================================== */

        $sessionUserId =
            (int) (
                $session
                    ->client_reference_id
                ?? $session
                    ->metadata
                    ->llama_user_id
                ?? 0
            );


        if ($sessionUserId !== $userId) {

            throw new RuntimeException(
                'Checkout Session does not belong to the signed-in Llama Scout account.'
            );
        }


        /* =====================================================
           STRIPE STATUS
           ===================================================== */

        $sessionStatus =
            strtolower(
                trim(
                    (string) (
                        $session
                            ->status
                        ?? ''
                    )
                )
            );

        $paymentStatus =
            strtolower(
                trim(
                    (string) (
                        $session
                            ->payment_status
                        ?? ''
                    )
                )
            );

        $subscriptionId =
            trim(
                (string) (
                    $session
                        ->subscription
                    ?? ''
                )
            );


        /* =====================================================
           COMPLETED CHECKOUT
           ===================================================== */

        if (
            $sessionStatus === 'complete'
            && $subscriptionId !== ''
        ) {

            $subscription =
                $stripe
                    ->subscriptions
                    ->retrieve(
                        $subscriptionId,
                        []
                    );


            llama_sync_stripe_subscription(
                $db,
                $subscription,
                $userId
            );


            /* =================================================
               SUCCESSFUL PAYMENT
               ================================================= */

            if (
                in_array(
                    $paymentStatus,
                    [
                        'paid',
                        'no_payment_required',
                    ],
                    true
                )
            ) {

                $status =
                    'success';

                $message =
                    'Your Llama Scout membership is active.';


                /*
                 * Checkout is actually complete now.
                 *
                 * Clear the temporary membership basket only if
                 * this returned Stripe Session is the one that
                 * belongs to the current basket.
                 *
                 * The empty-ID fallback supports purchases that
                 * began before session tracking was added.
                 */
                if (
                    $pendingCheckoutSessionId === ''
                    || hash_equals(
                        $pendingCheckoutSessionId,
                        $sessionId
                    )
                ) {

                    unset(
                        $_SESSION[
                            'pending_membership_plan'
                        ],
                        $_SESSION[
                            'pending_membership_promo_code'
                        ],
                        $_SESSION[
                            'pending_membership_checkout_session_id'
                        ]
                    );
                }


            /* =================================================
               PAYMENT STILL PROCESSING
               ================================================= */

            } else {

                $status =
                    'processing';

                $message =
                    'Checkout is complete and Stripe is still confirming the payment.';


                /*
                 * Keep the membership basket intact until Stripe
                 * confirms successful payment.
                 */
            }


        /* =====================================================
           EXPIRED CHECKOUT
           ===================================================== */

        } elseif (
            $sessionStatus === 'expired'
        ) {

            $status =
                'expired';

            $message =
                'This checkout session expired before the membership was completed.';


            /*
             * The Stripe Session itself is no longer reusable,
             * but the selected plan and promotional code should
             * remain so the customer can try checkout again.
             */
            if (
                $pendingCheckoutSessionId !== ''
                && hash_equals(
                    $pendingCheckoutSessionId,
                    $sessionId
                )
            ) {

                unset(
                    $_SESSION[
                        'pending_membership_checkout_session_id'
                    ]
                );
            }
        }


    } catch (Throwable $exception) {

        $reference =
            llama_log_caught_exception(
                $exception,
                'stripe_checkout_return',
                [
                    'user_id' =>
                        $userId,

                    'session_id' =>
                        $sessionId,
                ]
            );


        http_response_code(500);

        $status =
            'error';

        $message =
            llama_error_message_with_reference(
                'We could not confirm the checkout status yet.',
                $reference
            );
    }
}


/* =========================================================
   DISPLAY
   ========================================================= */

function checkout_return_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


$pageTitle =
    'Membership Checkout | Llama Scout';

$pageRobots =
    'noindex,nofollow';

$pageDescription =
    '';

require dirname(__DIR__)
    . '/partials/header.php';

?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/account/pages/checkout.css"
>


<section class="checkout-page checkout-return-page">


<div
    class="checkout-return-card is-<?= checkout_return_e(
        $status
    ) ?>"
>


    <div class="checkout-return-icon">

        <?php if ($status === 'success'): ?>

            <i aria-hidden="true">
                <?= llama_icon('circle-check') ?>
            </i>


        <?php elseif ($status === 'processing'): ?>

            <i aria-hidden="true">
                <?= llama_icon('clock') ?>
            </i>


        <?php else: ?>

            <i aria-hidden="true">
                <?= llama_icon('alert-triangle') ?>
            </i>

        <?php endif; ?>

    </div>


    <p class="eyebrow">
        Membership checkout
    </p>


    <h1>

        <?= $status === 'success'
            ? 'You’re in.'
            : 'Checkout update'
        ?>

    </h1>


    <p>
        <?= checkout_return_e(
            $message
        ) ?>
    </p>


    <div class="checkout-return-actions">


        <?php if ($status === 'success'): ?>

        <a
            class="checkout-primary-button"
            href="/billing.php"
        >
            Membership & billing
        </a>


        <?php else: ?>

        <a
            class="checkout-primary-button"
            href="/membership.php"
        >
            Return to membership
        </a>

        <?php endif; ?>


        <a
            class="checkout-secondary-button"
            href="https://llamascout.com/"
        >
            Return to Llama Scout
        </a>


    </div>


</div>


</section>


<?php

require dirname(__DIR__)
    . '/partials/footer.php';

?>
