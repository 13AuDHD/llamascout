<?php

declare(strict_types=1);

function llama_email_default_templates(): array
{
    return [

        'verify_email' => [
            'template_key' => 'verify_email',
            'category' => 'Account',
            'name' => 'Verify Email',
            'description' =>
                'Sent when a new account needs to verify its email address.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'verification_url',
            ],
            'subject' => 'Verify your Llama Scout email',
            'preheader' =>
                'One click and your Llama Scout account is ready.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Verify your email address to finish setting up your Llama Scout account:

{{verification_url}}

This verification link expires in 24 hours.

If you did not create a Llama Scout account, you can ignore this email.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Verify your email
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  You’re almost in. Verify your email address to finish setting up your
  Llama Scout account.
</p>

<p style="margin:28px 0;">
  <a
    href="{{verification_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Verify Email</a>
</p>

<p style="margin:0 0 10px;color:#667069;font-size:14px;line-height:1.6;">
  This verification link expires in 24 hours.
</p>

<p style="margin:0;color:#667069;font-size:14px;line-height:1.6;">
  If you didn’t create a Llama Scout account, you can ignore this email.
</p>
HTML,
        ],


        'welcome' => [
            'template_key' => 'welcome',
            'category' => 'Account',
            'name' => 'Welcome',
            'description' =>
                'Sent once after a newly registered account successfully verifies its email.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'membership_url',
                'monthly_url',
                'annual_url',
                'monthly_price',
                'annual_price',
                'demo_report_url',
                'map_url',
            ],
            'subject' => 'Welcome to Llama Scout',
            'preheader' =>
                'You’re officially part of the herd. Here’s what your account can do.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

You’re officially part of the herd.

Your free Llama Scout account is ready. You can save Places, contribute new Places and updates, report problems, and build your Scout profile.

Want the complete Place report?

Paid membership unlocks:
• Exact coordinates and exact Place locations
• Exact road and location details
• Complete Place photo galleries
• Full descriptions and Scout Notes
• Road and vehicle access
• Sensory conditions
• Connectivity
• Exact-location weather plus a 5-day forecast
• Member map layers: Street, Terrain, Topo, Dark, and Satellite
• The monthly member-only Llama Scout newsletter

See a complete Scout Report:
{{demo_report_url}}

Explore the member map:
{{map_url}}

Monthly membership: {{monthly_price}}
{{monthly_url}}

Annual membership: {{annual_price}}
{{annual_url}}

Or compare membership:
{{membership_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 10px;font-size:30px;line-height:1.1;color:#172822;">
  You’re officially part of the herd.
</h1>

<p style="margin:0 0 22px;color:#667069;font-size:16px;line-height:1.65;">
  Hi {{display_name}}. Your Llama Scout account is verified and ready.
</p>

<div style="margin:0 0 24px;padding:18px;border:1px solid #dcded8;border-radius:12px;background:#f7f8f4;">
  <strong style="display:block;margin-bottom:8px;color:#172822;">
    Your free account already does useful stuff.
  </strong>
  <span style="color:#4f5b55;line-height:1.65;">
    Save Places, contribute new Places and updates, report problems,
    and build your Scout profile.
  </span>
</div>

<h2 style="margin:28px 0 12px;font-size:22px;color:#172822;">
  Complete Access goes a lot further.
</h2>

<ul style="margin:0 0 24px;padding-left:22px;color:#263b33;line-height:1.8;">
  <li>Exact coordinates and exact Place locations</li>
  <li>Exact road and location details</li>
  <li>Complete Place photo galleries</li>
  <li>Full descriptions, access details, sensory conditions, and Scout Notes</li>
  <li>Connectivity and exact-location weather with a 5-day forecast</li>
</ul>

<div style="margin:24px 0;padding:18px;border-radius:12px;background:#172822;color:#ffffff;">
  <strong style="display:block;margin-bottom:7px;font-size:18px;">
    A much better map.
  </strong>
  <span style="display:block;line-height:1.65;color:#e8eee9;">
    Paid members get exact pins, street-level zoom, and Street, Terrain,
    Topo, Dark, and Satellite map layers.
  </span>

  <a
    href="{{map_url}}"
    style="display:inline-block;margin-top:14px;color:#ffffff;font-weight:700;"
  >Explore the map</a>
</div>

<div style="margin:24px 0;padding:18px;border:1px solid #dcded8;border-radius:12px;">
  <strong style="display:block;margin-bottom:7px;color:#172822;">
    Monthly member-only newsletter
  </strong>
  <span style="color:#4f5b55;line-height:1.65;">
    Paid membership also includes Llama Scout’s monthly member-only email
    publication with Place discoveries, site updates, field notes, and useful
    planning information.
  </span>
</div>

<p style="margin:26px 0 10px;">
  <a
    href="{{demo_report_url}}"
    style="display:block;padding:13px 18px;border:1px solid #172822;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;"
  >See a Complete Scout Report</a>
</p>

<p style="margin:10px 0;">
  <a
    href="{{monthly_url}}"
    style="display:block;padding:14px 18px;border-radius:9px;background:#172822;color:#ffffff;text-align:center;text-decoration:none;font-weight:700;"
  >Choose Monthly · {{monthly_price}}</a>
</p>

<p style="margin:10px 0 0;">
  <a
    href="{{annual_url}}"
    style="display:block;padding:13px 18px;border:1px solid #172822;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;"
  >Choose Annual · {{annual_price}}</a>
</p>
HTML,
        ],


        'password_reset' => [
            'template_key' => 'password_reset',
            'category' => 'Account',
            'name' => 'Password Reset',
            'description' =>
                'Sent when a member requests a password-reset link.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'reset_url',
            ],
            'subject' => 'Reset your Llama Scout password',
            'preheader' =>
                'Use this secure link to choose a new Llama Scout password.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

A password reset was requested for your Llama Scout account.

Use this secure link to choose a new password:

{{reset_url}}

This link expires in 60 minutes and can only be used once.

If you did not request this, you can ignore this email.

Llama Scout
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Reset your password
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  A password reset was requested for your Llama Scout account.
</p>

<p style="margin:28px 0;">
  <a
    href="{{reset_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Choose a New Password</a>
</p>

<p style="margin:0;color:#667069;font-size:14px;line-height:1.6;">
  This link expires in 60 minutes and can only be used once.
  If you didn’t request it, you can ignore this email.
</p>
HTML,
        ],


        'goodbye' => [
            'template_key' => 'goodbye',
            'category' => 'Account',
            'name' => 'Goodbye',
            'description' =>
                'Sent after successful self-service account deletion.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'site_url',
            ],
            'subject' => 'We’ll miss you',
            'preheader' =>
                'Your Llama Scout account has been deleted.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your Llama Scout account has been deleted and your personal account identity has been anonymized.

We’ll miss having you around the herd.

If your travels bring you back someday, Llama Scout will still be here:

{{site_url}}

Take care out there.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  We’ll miss you.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 16px;line-height:1.65;">
  Your Llama Scout account has been deleted and your personal account identity
  has been anonymized.
</p>

<p style="margin:0 0 24px;line-height:1.65;">
  We’ll miss having you around the herd. If your travels bring you back someday,
  Llama Scout will still be here.
</p>

<p style="margin:28px 0;">
  <a
    href="{{site_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Visit Llama Scout</a>
</p>

<p style="margin:0;color:#667069;font-size:14px;line-height:1.6;">
  Take care out there.
</p>
HTML,
        ],


        'order_confirmation' => [
            'template_key' => 'order_confirmation',
            'category' => 'Commerce',
            'name' => 'Order Confirmation',
            'description' =>
                'Sent once after a Shop order reaches a valid paid state.',
            'enabled' => 1,
            'variables' => [
                'customer_name',
                'order_number',
                'order_items',
                'subtotal',
                'shipping',
                'tax',
                'discount_line',
                'total',
                'order_action_url',
                'order_action_label',
            ],
            'subject' => 'Order confirmed: {{order_number}}',
            'preheader' =>
                'Your Llama Scout Shop payment has been confirmed.',
            'text_body' => <<<'TEXT'
Hi {{customer_name}},

Thanks for your order. Your payment has been confirmed.

Order: {{order_number}}

{{order_items}}

Subtotal: {{subtotal}}
Shipping: {{shipping}}
Tax: {{tax}}
{{discount_line}}
Total: {{total}}

We’ll send another update when shipping information is available.

{{order_action_label}}:
{{order_action_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Llama Scout Shop
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Order confirmed
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{customer_name}},
</p>

<p style="margin:0 0 22px;line-height:1.65;">
  Thanks for your order. Your payment has been confirmed.
</p>

<div style="margin:0 0 18px;padding:14px 16px;background:#f7f7f3;border-radius:10px;">
  <strong>Order {{order_number}}</strong>
</div>

<div style="margin:0 0 22px;padding:14px 16px;border:1px solid #e4e4e0;border-radius:10px;white-space:pre-line;line-height:1.7;">
{{order_items}}
</div>

<div style="margin:0 0 24px;padding:14px 16px;background:#f7f7f3;border-radius:10px;white-space:pre-line;line-height:1.7;">
Subtotal: {{subtotal}}
Shipping: {{shipping}}
Tax: {{tax}}
{{discount_line}}
<strong>Total: {{total}}</strong>
</div>

<p style="margin:0 0 22px;color:#52605a;line-height:1.65;">
  We’ll send another update when shipping information is available.
</p>

<p style="margin:0;">
  <a
    href="{{order_action_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;"
  >{{order_action_label}}</a>
</p>
HTML,
        ],


        'order_shipped' => [
            'template_key' => 'order_shipped',
            'category' => 'Commerce',
            'name' => 'Order Shipped',
            'description' =>
                'Sent for each fulfillment when it first reaches shipped.',
            'enabled' => 1,
            'variables' => [
                'customer_name',
                'order_number',
                'tracking_carrier',
                'tracking_number',
                'tracking_url',
            ],
            'subject' => 'Your order shipped: {{order_number}}',
            'preheader' =>
                'Your Llama Scout order is on the way.',
            'text_body' => <<<'TEXT'
Hi {{customer_name}},

Your Llama Scout order is on the way.

Order: {{order_number}}
Carrier: {{tracking_carrier}}
Tracking: {{tracking_number}}

Track shipment:
{{tracking_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Llama Scout Shop
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Your order is on the way.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{customer_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Your shipment has been marked shipped.
</p>

<div style="margin:0 0 22px;padding:14px 16px;background:#f7f7f3;border-radius:10px;line-height:1.7;">
  <strong>Order:</strong> {{order_number}}<br>
  <strong>Carrier:</strong> {{tracking_carrier}}<br>
  <strong>Tracking:</strong> {{tracking_number}}
</div>

<p style="margin:0;">
  <a
    href="{{tracking_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Track shipment</a>
</p>
HTML,
        ],


        'order_delivered' => [
            'template_key' => 'order_delivered',
            'category' => 'Commerce',
            'name' => 'Order Delivered',
            'description' =>
                'Sent for each fulfillment when the provider marks it delivered.',
            'enabled' => 1,
            'variables' => [
                'customer_name',
                'order_number',
                'tracking_carrier',
                'tracking_number',
                'tracking_url',
            ],
            'subject' => 'Delivered: {{order_number}}',
            'preheader' =>
                'Your Llama Scout order was marked delivered.',
            'text_body' => <<<'TEXT'
Hi {{customer_name}},

Your Llama Scout order was delivered.

Order: {{order_number}}
Carrier: {{tracking_carrier}}
Tracking: {{tracking_number}}

Shipment details:
{{tracking_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Llama Scout Shop
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Your order was delivered.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{customer_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  The fulfillment provider has marked this shipment as delivered.
</p>

<div style="margin:0 0 22px;padding:14px 16px;background:#f7f7f3;border-radius:10px;line-height:1.7;">
  <strong>Order:</strong> {{order_number}}<br>
  <strong>Carrier:</strong> {{tracking_carrier}}<br>
  <strong>Tracking:</strong> {{tracking_number}}
</div>

<p style="margin:0;">
  <a
    href="{{tracking_url}}"
    style="display:inline-block;border:1px solid #172822;color:#172822;padding:12px 20px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Shipment details</a>
</p>
HTML,
        ],


        'refund_confirmation' => [
            'template_key' => 'refund_confirmation',
            'category' => 'Commerce',
            'name' => 'Refund Confirmation',
            'description' =>
                'Sent after both payment and order state confirm a completed refund.',
            'enabled' => 1,
            'variables' => [
                'customer_name',
                'order_number',
                'refund_amount',
            ],
            'subject' => 'Refund confirmed: {{order_number}}',
            'preheader' =>
                'Your Llama Scout Shop refund has been confirmed.',
            'text_body' => <<<'TEXT'
Hi {{customer_name}},

Your Llama Scout Shop refund has been confirmed.

Order: {{order_number}}
Refund: {{refund_amount}}

Your bank or card provider may take additional time to post the credit.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Llama Scout Shop
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Refund confirmed
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{customer_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Your Llama Scout Shop refund has been confirmed.
</p>

<div style="margin:0 0 20px;padding:14px 16px;background:#f7f7f3;border-radius:10px;line-height:1.7;">
  <strong>Order:</strong> {{order_number}}<br>
  <strong>Refund:</strong> {{refund_amount}}
</div>

<p style="margin:0;color:#52605a;line-height:1.65;">
  Your bank or card provider may take additional time to post the credit.
</p>
HTML,
        ],


        'membership_started' => [
            'template_key' => 'membership_started',
            'category' => 'Membership',
            'name' => 'Membership Started',
            'description' =>
                'Sent once when a paid Stripe membership becomes active.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'membership_plan',
                'membership_renews_at',
                'account_url',
                'map_url',
                'demo_report_url',
            ],
            'subject' => 'Your Llama Scout membership is active',
            'preheader' =>
                'Complete Access is unlocked. Your member features are ready.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your Llama Scout {{membership_plan}} membership is active.

Complete Access is now unlocked, including exact Place locations, complete photo galleries, sensory and access details, exact-location weather, the 5-day forecast, and the full member map.

Your current membership period runs through {{membership_renews_at}}.

Manage your membership:
{{account_url}}

Explore the member map:
{{map_url}}

See a complete Scout Report:
{{demo_report_url}}

Thanks for supporting Llama Scout.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Membership
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Complete Access is unlocked.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Your Llama Scout <strong>{{membership_plan}}</strong> membership is active.
</p>

<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  Exact Place locations, complete photo galleries, sensory and access details,
  exact-location weather, the 5-day forecast, and the full member map are now available.
</div>

<p style="margin:0 0 22px;line-height:1.65;">
  Your current membership period runs through
  <strong>{{membership_renews_at}}</strong>.
</p>

<p style="margin:10px 0;">
  <a
    href="{{map_url}}"
    style="display:block;padding:14px 18px;border-radius:9px;background:#172822;color:#ffffff;text-align:center;text-decoration:none;font-weight:700;"
  >Explore the Member Map</a>
</p>

<p style="margin:10px 0;">
  <a
    href="{{demo_report_url}}"
    style="display:block;padding:13px 18px;border:1px solid #172822;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;"
  >See a Complete Scout Report</a>
</p>

<p style="margin:10px 0 0;">
  <a
    href="{{account_url}}"
    style="display:block;padding:13px 18px;border:1px solid #d7d9d5;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;"
  >Manage Membership</a>
</p>
HTML,
        ],


        'membership_cancel_scheduled' => [
            'template_key' => 'membership_cancel_scheduled',
            'category' => 'Membership',
            'name' => 'Cancellation Scheduled',
            'description' =>
                'Sent once when paid membership renewal is turned off but access remains active.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'membership_plan',
                'membership_ends_at',
                'account_url',
            ],
            'subject' => 'Your Llama Scout membership will end {{membership_ends_at}}',
            'preheader' =>
                'Renewal is off, but your Complete Access remains active through the paid period.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your Llama Scout {{membership_plan}} membership is scheduled to end on {{membership_ends_at}}.

You still have Complete Access until then. Nothing has been removed early.

If you change your mind, you can manage your membership here:

{{account_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Membership
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Renewal is turned off.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Your Llama Scout <strong>{{membership_plan}}</strong> membership is scheduled
  to end on <strong>{{membership_ends_at}}</strong>.
</p>

<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  You still have Complete Access until then. Nothing has been removed early.
</div>

<p style="margin:0;">
  <a
    href="{{account_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Manage Membership</a>
</p>
HTML,
        ],


        'membership_payment_failed' => [
            'template_key' => 'membership_payment_failed',
            'category' => 'Membership',
            'name' => 'Payment Failed',
            'description' =>
                'Sent when Stripe places a paid membership into past-due status.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'membership_plan',
                'membership_ends_at',
                'account_url',
            ],
            'subject' => 'There’s a problem with your Llama Scout membership payment',
            'preheader' =>
                'Your membership is past due. Update billing details to avoid losing Complete Access.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Stripe was unable to complete a payment for your Llama Scout {{membership_plan}} membership.

Your membership is currently past due. Please review your billing information so Complete Access can continue without interruption.

Current access period:
{{membership_ends_at}}

Manage your membership:
{{account_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Membership
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  There’s a problem with your payment.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Stripe was unable to complete a payment for your Llama Scout
  <strong>{{membership_plan}}</strong> membership.
</p>

<div style="margin:0 0 22px;padding:16px;border:1px solid #dcded8;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  Your membership is currently past due. Review your billing information
  to avoid losing Complete Access.
</div>

<p style="margin:0 0 22px;line-height:1.65;">
  Current access period: <strong>{{membership_ends_at}}</strong>
</p>

<p style="margin:0;">
  <a
    href="{{account_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Review Membership</a>
</p>
HTML,
        ],


        'membership_ended' => [
            'template_key' => 'membership_ended',
            'category' => 'Membership',
            'name' => 'Membership Ended',
            'description' =>
                'Sent once after a paid membership actually reaches canceled status.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'membership_plan',
                'membership_url',
            ],
            'subject' => 'Your Llama Scout membership has ended',
            'preheader' =>
                'Your paid Complete Access has ended. Your free account remains available.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your paid Llama Scout {{membership_plan}} membership has ended.

Your Llama Scout account is still here. You can continue using the free account features, and your profile, saved information, contribution history, points, and badges remain intact.

If you want Complete Access again:

{{membership_url}}

Thanks for having been a member.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Membership
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Your paid membership has ended.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Your Llama Scout <strong>{{membership_plan}}</strong> membership has ended.
</p>

<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  Your free account remains available. Your profile, saved information,
  contribution history, points, and badges stay intact.
</div>

<p style="margin:0;">
  <a
    href="{{membership_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Restore Complete Access</a>
</p>
HTML,
        ],


        'complimentary_started' => [
            'template_key' => 'complimentary_started',
            'category' => 'Membership',
            'name' => 'Complimentary Access Granted',
            'description' =>
                'Sent once when a complimentary membership grant becomes active.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'membership_ends_at',
                'account_url',
                'map_url',
                'demo_report_url',
            ],
            'subject' => 'Complimentary Llama Scout access is active',
            'preheader' =>
                'Complete Access has been added to your account.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Complimentary Llama Scout Complete Access has been added to your account.

Your complimentary access runs through {{membership_ends_at}}.

Explore the member map:
{{map_url}}

See a complete Scout Report:
{{demo_report_url}}

View your account:
{{account_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Membership
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Complete Access is on us.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Complimentary Llama Scout Complete Access has been added to your account.
</p>

<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  Your complimentary access runs through
  <strong>{{membership_ends_at}}</strong>.
</div>

<p style="margin:10px 0;">
  <a
    href="{{map_url}}"
    style="display:block;padding:14px 18px;border-radius:9px;background:#172822;color:#ffffff;text-align:center;text-decoration:none;font-weight:700;"
  >Explore the Member Map</a>
</p>

<p style="margin:10px 0;">
  <a
    href="{{demo_report_url}}"
    style="display:block;padding:13px 18px;border:1px solid #172822;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;"
  >See a Complete Scout Report</a>
</p>
HTML,
        ],


        'complimentary_ending' => [
            'template_key' => 'complimentary_ending',
            'category' => 'Membership',
            'name' => 'Complimentary Access Ending',
            'description' =>
                'Sent once when complimentary Complete Access has seven days or less remaining.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'membership_ends_at',
                'membership_url',
            ],
            'subject' => 'Your complimentary Llama Scout access ends {{membership_ends_at}}',
            'preheader' =>
                'Your complimentary Complete Access is nearing its end.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your complimentary Llama Scout Complete Access ends on {{membership_ends_at}}.

Your free account will remain available afterward. If you would like to keep Complete Access, you can choose a monthly or annual membership here:

{{membership_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Membership
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Your complimentary access is nearing its end.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Your complimentary Llama Scout Complete Access ends on
  <strong>{{membership_ends_at}}</strong>.
</p>

<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  Your free account will remain available afterward.
</div>

<p style="margin:0;">
  <a
    href="{{membership_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Keep Complete Access</a>
</p>
HTML,
        ],

    ];
}
