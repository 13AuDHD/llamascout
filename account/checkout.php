<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stripe.php';
require_once dirname(__DIR__) . '/app/memberships.php';
require_once dirname(__DIR__) . '/app/promotion-events.php';
require_once dirname(__DIR__) . '/app/promotion-codes.php';

require_verified_email();
start_llama_session();

$db = db();
$user = current_user();

if (!$user) {
    http_response_code(401);
    exit('Authentication required.');
}


/* =========================================================
   REQUEST
   ========================================================= */

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    header(
        'Location: /membership.php',
        true,
        303
    );

    exit;
}


/* =========================================================
   REQUIRED MEMBERSHIP STORAGE
   ========================================================= */

$requiredTables = [
    'membership_plans',
    'membership_plan_prices',
    'membership_promotions',
    'membership_promotion_plans',
    'membership_checkout_settings',
];

foreach (
    $requiredTables
    as $requiredTable
) {
    if (
        !llama_membership_table_exists(
            $db,
            $requiredTable
        )
    ) {
        http_response_code(503);

        exit(
            'Membership checkout is temporarily unavailable while configuration is completed.'
        );
    }
}


/* =========================================================
   CSRF
   ========================================================= */

$expectedToken =
    $_SESSION[
        'membership_checkout_csrf'
    ]
    ?? '';

$submittedToken =
    $_POST[
        'csrf_token'
    ]
    ?? '';

if (
    !is_string($expectedToken)
    || $expectedToken === ''
    || !is_string($submittedToken)
    || !hash_equals(
        $expectedToken,
        $submittedToken
    )
) {
    http_response_code(403);

    exit(
        'Your session could not be verified. Reload the membership page and try again.'
    );
}


/* =========================================================
   SELECTED PLAN
   ========================================================= */

$interval =
    strtolower(
        trim(
            (string) (
                $_POST[
                    'interval'
                ]
                ?? ''
            )
        )
    );

if (
    !in_array(
        $interval,
        [
            LLAMA_MEMBERSHIP_INTERVAL_MONTHLY,
            LLAMA_MEMBERSHIP_INTERVAL_ANNUAL,
        ],
        true
    )
) {
    http_response_code(400);

    exit(
        'That membership option is not valid.'
    );
}


/* =========================================================
   ACCOUNT
   ========================================================= */

$stmt =
    $db->prepare(
        'SELECT
            id,
            email,
            username,
            display_name,
            stripe_customer_id,
            stripe_subscription_id,
            membership_status
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

$stmt->execute([
    (int) $user['id'],
]);

$account =
    $stmt->fetch(PDO::FETCH_ASSOC)
    ?: null;

if (!$account) {
    http_response_code(404);
    exit('Account not found.');
}


/* =========================================================
   EXISTING ACCESS
   ========================================================= */

$membershipStatus =
    strtolower(
        trim(
            (string) (
                $account[
                    'membership_status'
                ]
                ?? 'none'
            )
        )
    );

$stripeSubscriptionId =
    trim(
        (string) (
            $account[
                'stripe_subscription_id'
            ]
            ?? ''
        )
    );

$hasExistingStripeMembership =
    $stripeSubscriptionId !== ''
    && in_array(
        $membershipStatus,
        [
            'active',
            'trialing',
            'past_due',
        ],
        true
    );


/*
 * Existing Stripe subscribers manage their subscription through
 * Billing instead of creating another subscription.
 *
 * Complimentary access is deliberately NOT blocked here.
 * Someone with complimentary access may choose to begin paying.
 * This also prevents complimentary-grant records from creating
 * a redirect cycle between Checkout, Billing and Membership.
 */
if ($hasExistingStripeMembership) {

    header(
        'Location: /billing.php',
        true,
        303
    );

    exit;
}


$hasLegacyComplimentaryAccess =
    $membershipStatus ===
    'complimentary';

$hasComplimentaryGrant =
    llama_user_has_complimentary_grant(
        $db,
        (int) $account['id']
    );

$hasComplimentaryAccess =
    $hasLegacyComplimentaryAccess
    || $hasComplimentaryGrant;


/* =========================================================
   OFFER
   ========================================================= */

$offer =
    llama_membership_plan_offer(
        $db,
        $interval
    );

$checkoutError = '';
$checkoutReference = '';

$clientSecret = '';
$publishableKey = '';

$plan = null;

$manualPromotionCodesEnabled =
    false;

$linkedPromotionCode =
    null;


/* =========================================================
   PENDING PROMOTION CODE
   ========================================================= */

$pendingPromotionCodeValue =
    strtoupper(
        trim(
            (string) (
                $_SESSION[
                    'pending_membership_promo_code'
                ]
                ?? ''
            )
        )
    );


/* =========================================================
   CHECKOUT SETTINGS
   ========================================================= */

$manualCodesStmt =
    $db->query(
        'SELECT
            manual_promotion_codes_enabled
         FROM membership_checkout_settings
         WHERE id = 1
         LIMIT 1'
    );

if ($manualCodesStmt) {
    $manualPromotionCodesEnabled =
        (bool)
        $manualCodesStmt
            ->fetchColumn();
}


/* =========================================================
   DISPLAY PRICE
   ========================================================= */

$checkoutDisplayPriceCents = 0;
$checkoutRegularPriceCents = 0;
$checkoutHasDiscount = false;


/* =========================================================
   CREATE STRIPE CHECKOUT SESSION
   ========================================================= */

if (!$offer) {

    http_response_code(409);

    $checkoutError =
        'That membership plan is not currently available.';

} else {

    $plan =
        $offer[
            'plan'
        ];

    $priceId =
        trim(
            (string) (
                $plan[
                    'stripe_price_id'
                ]
                ?? ''
            )
        );

    $couponId =
        trim(
            (string) (
                $offer[
                    'stripe_coupon_id'
                ]
                ?? ''
            )
        );

    $promotion =
        $offer[
            'promotion'
        ]
        ?? null;

    $promotionId =
        $promotion
            ? (int) (
                $promotion[
                    'promotion_id'
                ]
                ?? 0
            )
            : 0;

    $onSale =
        !empty(
            $offer[
                'on_sale'
            ]
        );

    $checkoutRegularPriceCents =
        (int) (
            $offer[
                'base_price_cents'
            ]
            ?? 0
        );

    $checkoutDisplayPriceCents =
        (int) (
            $offer[
                'effective_price_cents'
            ]
            ?? $checkoutRegularPriceCents
        );

    $checkoutHasDiscount =
        $checkoutDisplayPriceCents
        < $checkoutRegularPriceCents;


    /*
     * Automatic campaign pricing takes priority.
     *
     * Otherwise, look up the Promotion Code carried from the
     * promotional share link.
     */
    if (
        !$onSale
        && $pendingPromotionCodeValue !== ''
    ) {

        $linkedPromotionCode =
            llama_membership_promotion_code_by_code(
                $db,
                $pendingPromotionCodeValue,
                $interval
            );


        if (!$linkedPromotionCode) {

            unset(
                $_SESSION[
                    'pending_membership_promo_code'
                ]
            );

            $pendingPromotionCodeValue =
                '';

        } else {

            /*
             * Calculate the same promotional price shown on the
             * membership selection page.
             */
            $checkoutDisplayPriceCents =
                llama_membership_discounted_price_cents(
                    $checkoutRegularPriceCents,
                    (string) (
                        $linkedPromotionCode[
                            'discount_type'
                        ]
                        ?? ''
                    ),
                    (int) (
                        $linkedPromotionCode[
                            'discount_value'
                        ]
                        ?? 0
                    )
                );

            $checkoutHasDiscount =
                $checkoutDisplayPriceCents
                < $checkoutRegularPriceCents;
        }
    }


    if ($priceId === '') {

        http_response_code(503);

        $checkoutError =
            'Checkout is not configured for this membership plan yet.';

    } elseif (
        $onSale
        && $couponId === ''
    ) {

        http_response_code(503);

        $checkoutError =
            'This membership promotion is temporarily unavailable at checkout.';

    } else {

        try {

            $publishableKey =
                llama_stripe_publishable_key();

            $stripe =
                llama_stripe_client();


            $sessionData = [

                'mode' =>
                    'subscription',

                'ui_mode' =>
                    'embedded_page',

                'line_items' => [
                    [
                        'price' =>
                            $priceId,

                        'quantity' =>
                            1,
                    ],
                ],

                'client_reference_id' =>
                    (string) $account[
                        'id'
                    ],

                'metadata' => [

                    'llama_user_id' =>
                        (string) $account[
                            'id'
                        ],

                    'membership_interval' =>
                        $interval,

                    'membership_plan_id' =>
                        (string) $plan[
                            'id'
                        ],

                    'membership_promotion_id' =>
                        $promotionId > 0
                            ? (string) $promotionId
                            : '',

                    'membership_promotion_policy' =>
                        $promotionId > 0
                            ? 'first_year_only'
                            : '',
                ],

                'subscription_data' => [

                    'metadata' => [

                        'llama_user_id' =>
                            (string) $account[
                                'id'
                            ],

                        'membership_interval' =>
                            $interval,

                        'membership_plan_id' =>
                            (string) $plan[
                                'id'
                            ],

                        'membership_promotion_id' =>
                            $promotionId > 0
                                ? (string) $promotionId
                                : '',

                        'membership_promotion_policy' =>
                            $promotionId > 0
                                ? 'first_year_only'
                                : '',
                    ],
                ],

                'return_url' =>
                    'https://account.llamascout.com/checkout-return.php?session_id={CHECKOUT_SESSION_ID}',

                'redirect_on_completion' =>
                    'always',

                'billing_address_collection' =>
                    'auto',
            ];


            /* =================================================
               AUTOMATIC CAMPAIGN
               ================================================= */

            if ($onSale) {

                $sessionData[
                    'discounts'
                ] = [
                    [
                        'coupon' =>
                            $couponId,
                    ],
                ];


            /* =================================================
               LINKED PROMOTION CODE
               ================================================= */

            } elseif ($linkedPromotionCode) {

                $sessionData[
                    'discounts'
                ] = [
                    [
                        'promotion_code' =>
                            (string) $linkedPromotionCode[
                                'stripe_promotion_code_id'
                            ],
                    ],
                ];


                $sessionData[
                    'metadata'
                ][
                    'llama_promotion_code'
                ] =
                    (string) $linkedPromotionCode[
                        'code'
                    ];


                $sessionData[
                    'subscription_data'
                ][
                    'metadata'
                ][
                    'llama_promotion_code'
                ] =
                    (string) $linkedPromotionCode[
                        'code'
                    ];


            /* =================================================
               MANUAL PROMOTION CODE ENTRY
               ================================================= */

            } else {

                $sessionData[
                    'allow_promotion_codes'
                ] =
                    $manualPromotionCodesEnabled;
            }


            /* =================================================
               STRIPE CUSTOMER
               ================================================= */

            if (
                !empty(
                    $account[
                        'stripe_customer_id'
                    ]
                )
            ) {

                $sessionData[
                    'customer'
                ] =
                    (string) $account[
                        'stripe_customer_id'
                    ];

            } else {

                $sessionData[
                    'customer_email'
                ] =
                    (string) $account[
                        'email'
                    ];
            }


            /* =================================================
               CREATE SESSION
               ================================================= */

            $session =
                $stripe
                    ->checkout
                    ->sessions
                    ->create(
                        $sessionData
                    );

            $clientSecret =
                trim(
                    (string) (
                        $session
                            ->client_secret
                        ?? ''
                    )
                );

            $sessionId =
                trim(
                    (string) (
                        $session
                            ->id
                        ?? ''
                    )
                );


            if ($clientSecret === '') {

                throw new RuntimeException(
                    'Stripe did not return an Embedded Checkout client secret.'
                );
            }


            /* =================================================
               CAMPAIGN CHECKOUT EVENT
               ================================================= */

            if (
                $promotionId > 0
                && $sessionId !== ''
            ) {

                llama_membership_promotion_event(
                    $db,
                    $promotionId,
                    'checkout_started',
                    (int) $account[
                        'id'
                    ],
                    $interval,
                    $sessionId,
                    null,
                    $checkoutDisplayPriceCents,
                    [
                        'plan_id' =>
                            (int) (
                                $plan[
                                    'id'
                                ]
                                ?? 0
                            ),

                        'base_price_cents' =>
                            $checkoutRegularPriceCents,
                    ]
                );
            }


            /*
             * The Checkout Session now owns the purchase state.
             * Clear the temporary membership basket only after
             * Stripe successfully creates the session.
             */
            unset(
                $_SESSION[
                    'pending_membership_plan'
                ],
                $_SESSION[
                    'pending_membership_promo_code'
                ]
            );


        } catch (Throwable $exception) {

            $checkoutReference =
                llama_log_caught_exception(
                    $exception,
                    'stripe_embedded_checkout',
                    [
                        'user_id' =>
                            (int) $account[
                                'id'
                            ],

                        'interval' =>
                            $interval,

                        'plan_id' =>
                            (int) (
                                $plan[
                                    'id'
                                ]
                                ?? 0
                            ),

                        'promotion_id' =>
                            $promotionId,

                        'promotion_code' =>
                            $pendingPromotionCodeValue,
                    ]
                );


            http_response_code(500);


            $checkoutError =
                llama_error_message_with_reference(
                    'Secure checkout could not be started. No payment was created.',
                    $checkoutReference
                );
        }
    }
}


/* =========================================================
   DISPLAY HELPERS
   ========================================================= */

function checkout_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function checkout_money(
    int $cents,
    string $currency
): string {

    if (
        strtolower(
            $currency
        )
        === 'usd'
    ) {
        return
            '$'
            . number_format(
                $cents / 100,
                2
            );
    }

    return
        number_format(
            $cents / 100,
            2
        )
        . ' '
        . strtoupper(
            $currency
        );
}


/* =========================================================
   PAGE
   ========================================================= */

$pageTitle =
    'Secure Membership Checkout | Llama Scout';

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


<?php if ($clientSecret !== ''): ?>

<script src="https://js.stripe.com/clover/stripe.js"></script>

<?php endif; ?>


<section class="checkout-page">


<header class="checkout-page-header">

    <a
        class="checkout-back-link"
        href="/membership.php?plan=<?= checkout_e(
            $interval
        ) ?>"
    >
        <i aria-hidden="true">
            <?= llama_icon('arrow-left') ?>
        </i>

        Change membership
    </a>


    <p class="eyebrow">
        Secure membership checkout
    </p>


    <h1>
        Finish joining Llama Scout.
    </h1>


    <p>
        You stay on Llama Scout while Stripe securely handles the
        payment fields. Llama Scout never receives or stores your
        financial data.
    </p>

</header>


<?php if ($checkoutError !== ''): ?>


<div class="checkout-error-card">

    <i aria-hidden="true">
        <?= llama_icon('alert-triangle') ?>
    </i>

    <h2>
        Checkout could not start.
    </h2>

    <p>
        <?= checkout_e(
            $checkoutError
        ) ?>
    </p>

    <a
        class="checkout-primary-button"
        href="/membership.php?plan=<?= checkout_e(
            $interval
        ) ?>"
    >
        Return to membership
    </a>

</div>


<?php else: ?>


<div class="checkout-shell">


<aside class="checkout-summary-card">

    <p class="eyebrow">
        Your membership
    </p>


    <h2>
        <?= checkout_e(
            (string) (
                $plan[
                    'name'
                ]
                ?? ucfirst(
                    $interval
                )
                    . ' membership'
            )
        ) ?>
    </h2>


    <div class="checkout-summary-price">

        <?php if ($checkoutHasDiscount): ?>

        <del>
            <?= checkout_e(
                checkout_money(
                    $checkoutRegularPriceCents,
                    (string) (
                        $plan[
                            'currency'
                        ]
                        ?? 'usd'
                    )
                )
            ) ?>
        </del>

        <?php endif; ?>


        <strong>
            <?= checkout_e(
                checkout_money(
                    $checkoutDisplayPriceCents,
                    (string) (
                        $plan[
                            'currency'
                        ]
                        ?? 'usd'
                    )
                )
            ) ?>
        </strong>


        <span>
            / <?= $interval === 'annual'
                ? 'year'
                : 'month'
            ?>
        </span>

    </div>


    <?php if (
        !empty(
            $offer[
                'on_sale'
            ]
        )
    ): ?>

    <p class="checkout-sale-note">

        Introductory promotion applied automatically.

        Regular price
        <?= checkout_e(
            checkout_money(
                $checkoutRegularPriceCents,
                (string) (
                    $plan[
                        'currency'
                    ]
                    ?? 'usd'
                )
            )
        ) ?>.

        The promotional rate applies only during your first year,
        then renews at the regular price.

    </p>


    <?php elseif ($linkedPromotionCode): ?>

    <p class="checkout-sale-note">

        Promotion code

        <strong>
            <?= checkout_e(
                (string) $linkedPromotionCode[
                    'code'
                ]
            ) ?>
        </strong>

        is applied.

        Your first payment is
        <?= checkout_e(
            checkout_money(
                $checkoutDisplayPriceCents,
                (string) (
                    $plan[
                        'currency'
                    ]
                    ?? 'usd'
                )
            )
        ) ?>.

        Regular renewal price is
        <?= checkout_e(
            checkout_money(
                $checkoutRegularPriceCents,
                (string) (
                    $plan[
                        'currency'
                    ]
                    ?? 'usd'
                )
            )
        ) ?>.

    </p>


    <?php elseif ($manualPromotionCodesEnabled): ?>

    <p class="checkout-sale-note">
        Have a special promotion code? Enter it in the secure
        Stripe checkout form.
    </p>

    <?php endif; ?>


    <?php if ($hasComplimentaryAccess): ?>

    <p class="checkout-sale-note">

        <strong>
            Your account currently has complimentary access.
        </strong>

        Continuing will start a paid membership subscription.

    </p>

    <?php endif; ?>


    <ul class="checkout-trust-list">

        <li>
            <i aria-hidden="true">
                <?= llama_icon('check') ?>
            </i>
            Full Llama Scout membership access
        </li>

        <li>
            <i aria-hidden="true">
                <?= llama_icon('lock') ?>
            </i>
            Payment details handled by Stripe
        </li>

        <li>
            <i aria-hidden="true">
                <?= llama_icon('refresh') ?>
            </i>
            Manage membership from your account
        </li>

    </ul>


    <div class="checkout-secure-note">

        <i aria-hidden="true">
            <?= llama_icon('brand-stripe') ?>
        </i>

        <span>
            Secure payment processing by Stripe
        </span>

    </div>

</aside>


<main class="checkout-form-card">

    <div
        id="llama-embedded-checkout"
        class="checkout-embed"
        data-publishable-key="<?= checkout_e(
            $publishableKey
        ) ?>"
        data-client-secret="<?= checkout_e(
            $clientSecret
        ) ?>"
    >

        <div class="checkout-loading">

            <i
                class="llama-icon-spin"
                aria-hidden="true"
            >
                <?= llama_icon('loader-2') ?>
            </i>

            Loading secure payment form...

        </div>

    </div>


    <div
        id="checkout-load-error"
        class="checkout-load-error"
        hidden
    >
        Secure payment fields could not load.
        Refresh this page and try again.
    </div>

</main>


</div>


<script
    src="https://llamascout.com/js/membership-checkout.js"
    defer
></script>


<?php endif; ?>


</section>


<?php

require dirname(__DIR__)
    . '/partials/footer.php';

?>
