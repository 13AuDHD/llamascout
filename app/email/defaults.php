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
  Youâre almost in. Verify your email address to finish setting up your
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
  If you didnât create a Llama Scout account, you can ignore this email.
</p>
HTML,
        ],


        'email_change_verification' => [
            'template_key' => 'email_change_verification',
            'category' => 'Account',
            'name' => 'Verify Email Change',
            'description' =>
                'Sent to a new email address when a member requests to change the email on their account.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'new_email',
                'verification_url',
            ],
            'subject' => 'Verify your new Llama Scout email address',
            'preheader' =>
                'Confirm this email address to finish changing your Llama Scout login email.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

You requested to change the email address on your Llama Scout account to:

{{new_email}}

Verify this new email address to complete the change:

{{verification_url}}

This verification link expires in 24 hours.

Your current email address will remain on the account until the new address is verified.

If you did not request this change, do not verify the new address.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Verify your new email address
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 16px;line-height:1.65;">
  You requested to change the email address on your Llama Scout account to:
</p>

<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  <strong>{{new_email}}</strong>
</div>

<p style="margin:0 0 20px;line-height:1.65;">
  Verify this address to complete the change. Your current email address
  will remain on the account until verification is complete.
</p>

<p style="margin:28px 0;">
  <a
    href="{{verification_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Verify New Email</a>
</p>

<p style="margin:0 0 10px;color:#667069;font-size:14px;line-height:1.6;">
  This verification link expires in 24 hours.
</p>

<p style="margin:0;color:#667069;font-size:14px;line-height:1.6;">
  If you did not request this change, do not verify the new address.
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
                'Youâre officially part of the herd. Hereâs what your account can do.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Youâre officially part of the herd.

Your free Llama Scout account is ready. You can save Places, contribute new Places and updates, report problems, and build your Scout profile.

Want the complete Place report?

Paid membership unlocks:
â¢ Exact coordinates and exact Place locations
â¢ Exact road and location details
â¢ Complete Place photo galleries
â¢ Full descriptions and Scout Notes
â¢ Road and vehicle access
â¢ Sensory conditions
â¢ Connectivity
â¢ Exact-location weather plus a 5-day forecast
â¢ Member map layers: Street, Terrain, Topo, Dark, and Satellite
â¢ The monthly member-only Llama Scout newsletter

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
  Youâre officially part of the herd.
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
    Paid membership also includes Llama Scoutâs monthly member-only email
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
  >Choose Monthly Â· {{monthly_price}}</a>
</p>

<p style="margin:10px 0 0;">
  <a
    href="{{annual_url}}"
    style="display:block;padding:13px 18px;border:1px solid #172822;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;"
  >Choose Annual Â· {{annual_price}}</a>
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
  If you didnât request it, you can ignore this email.
</p>
HTML,
        ],


        'password_changed' => [
            'template_key' => 'password_changed',
            'category' => 'Account',
            'name' => 'Password Changed',
            'description' =>
                'Sent after a member successfully changes their Llama Scout password.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'account_url',
                'support_url',
            ],
            'subject' => 'Your Llama Scout password was changed',
            'preheader' =>
                'Your account password was changed successfully.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your Llama Scout password was changed successfully.

If you made this change, no further action is needed.

If you did not change your password, contact Llama Scout support right away:
{{support_url}}

Your account:
{{account_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Your password was changed.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Your Llama Scout password was changed successfully.
</p>

<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  If you made this change, no further action is needed.
</div>

<p style="margin:0 0 22px;line-height:1.65;">
  If you did not change your password, contact Llama Scout support right away.
</p>

<p style="margin:10px 0;">
  <a
    href="{{support_url}}"
    style="display:block;padding:14px 18px;border-radius:9px;background:#172822;color:#ffffff;text-align:center;text-decoration:none;font-weight:700;"
  >Contact Support</a>
</p>

<p style="margin:10px 0 0;">
  <a
    href="{{account_url}}"
    style="display:block;padding:13px 18px;border:1px solid #172822;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;"
  >Open Your Account</a>
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
            'subject' => 'Weâll miss you',
            'preheader' =>
                'Your Llama Scout account has been deleted.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your Llama Scout account has been deleted and your personal account identity has been anonymized.

Weâll miss having you around the herd.

If your travels bring you back someday, Llama Scout will still be here:

{{site_url}}

Take care out there.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Weâll miss you.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 16px;line-height:1.65;">
  Your Llama Scout account has been deleted and your personal account identity
  has been anonymized.
</p>

<p style="margin:0 0 24px;line-height:1.65;">
  Weâll miss having you around the herd. If your travels bring you back someday,
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

Weâll send another update when shipping information is available.

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
  Weâll send another update when shipping information is available.
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
            'subject' => 'Thereâs a problem with your Llama Scout membership payment',
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
  Thereâs a problem with your payment.
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


        'llamaversary' => [
            'template_key' => 'llamaversary',
            'category' => 'Membership',
            'name' => 'Llamaversary / Cake Day',
            'description' =>
                'Sent once each year on the anniversary of the member joining Llama Scout.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'years_with_us',
                'anniversary_number',
                'member_since',
                'account_url',
                'map_url',
            ],
            'subject' => 'Happy Llamaversary, {{display_name}}!',
            'preheader' =>
                'You have officially been part of the herd for {{years_with_us}}.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Happy Llamaversary!

Today marks your {{anniversary_number}} Llamaversary and {{years_with_us}} since you joined Llama Scout.

Whether you have been scouting Places, planning trips, collecting badges, contributing information, or just seeing where the map takes you, thank you for being part of the herd.

There should probably be cake.

There is not.

The cake is completely fictional.

But your Llamaversary is real, and we are glad you are here.

Member since: {{member_since}}

Explore the map:
{{map_url}}

Visit your account:
{{account_url}}

Here is to another year of knowing the place before you go.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Llamaversary
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Happy Llamaversary, {{display_name}}!
</h1>

<p style="margin:0 0 20px;line-height:1.65;">
  Today marks your <strong>{{anniversary_number}} Llamaversary</strong> and
  <strong>{{years_with_us}}</strong> since you joined Llama Scout.
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Whether you have been scouting Places, planning trips, collecting badges,
  contributing information, or just seeing where the map takes you, thank you
  for being part of the herd.
</p>

<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  <strong>There should probably be cake.</strong><br>
  There is not.<br>
  The cake is completely fictional.<br><br>
  But your Llamaversary is real, and we are glad you are here.
</div>

<p style="margin:0 0 22px;color:#52605a;line-height:1.65;">
  Member since <strong>{{member_since}}</strong>.
</p>

<p style="margin:10px 0;">
  <a
    href="{{map_url}}"
    style="display:block;padding:14px 18px;border-radius:9px;background:#172822;color:#ffffff;text-align:center;text-decoration:none;font-weight:700;"
  >Explore the Map</a>
</p>

<p style="margin:10px 0 0;">
  <a
    href="{{account_url}}"
    style="display:block;padding:13px 18px;border:1px solid #d7d9d5;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;"
  >Visit Your Account</a>
</p>

<p style="margin:22px 0 0;line-height:1.65;">
  Here is to another year of knowing the place before you go.
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


        'complimentary_invitation' => [
            'template_key' => 'complimentary_invitation',
            'category' => 'Membership',
            'name' => 'Complimentary Membership Invitation',
            'description' =>
                'Sent when Llama Scout invites someone to receive complimentary Complete Access.',
            'enabled' => 1,
            'variables' => [
                'recipient_name',
                'invite_email',
                'complimentary_days',
                'invite_expires',
                'invite_reason',
                'invite_url',
            ],
            'subject' => 'Youâve been invited to Llama Scout Complete Access',
            'preheader' =>
                'Complimentary Complete Access has been reserved for you.',
            'text_body' => <<<'TEXT'
Hi {{recipient_name}},

Youâve been invited to receive {{complimentary_days}} days of complimentary Llama Scout Complete Access.

{{invite_reason}}

Your invitation is reserved for:
{{invite_email}}

Accept your invitation:
{{invite_url}}

The invitation expires {{invite_expires}}.

If you do not already have a Llama Scout account, the invitation will guide you through creating one and verifying your email before Complete Access is activated.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Complimentary Membership
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Youâve been invited.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{recipient_name}},
</p>

<p style="margin:0 0 20px;line-height:1.65;">
  Llama Scout has reserved
  <strong>{{complimentary_days}} days</strong>
  of complimentary Complete Access for you.
</p>

<div style="margin:0 0 20px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  {{invite_reason}}
</div>

<div style="margin:0 0 22px;padding:16px;border:1px solid #dcded8;border-radius:12px;line-height:1.7;">
  <strong>Reserved for:</strong> {{invite_email}}<br>
  <strong>Invitation expires:</strong> {{invite_expires}}
</div>

<p style="margin:0 0 22px;line-height:1.65;">
  If you do not already have a Llama Scout account, the invitation will guide
  you through creating one and verifying your email before Complete Access is activated.
</p>

<p style="margin:0;">
  <a
    href="{{invite_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Accept Complimentary Access</a>
</p>
HTML,
        ],


        'scout_invitation' => [
            'template_key' => 'scout_invitation',
            'category' => 'Scout',
            'name' => 'Scout Invitation',
            'description' =>
                'Sent when an eligible member is invited to begin Scout onboarding.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'scout_invite_url',
            ],
            'subject' => 'You are invited to become a Llama Scout',
            'preheader' =>
                'You have been invited to join the Llama Scout team as a Scout.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

You have been invited to join the Llama Scout team as a Scout.

Active Scouts receive Scout tools and complimentary Complete Access while their Scout status remains active.

Review your invitation and Scout expectations:
{{scout_invite_url}}

Becoming a Scout is optional. This invitation expires 30 days after it was sent.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Llama Scout Invitation
</p>

<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  You're invited to become a Scout.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{display_name}},
</p>

<p style="margin:0 0 16px;line-height:1.65;">
  You've been invited to join the Llama Scout team as a Scout.
</p>

<p style="margin:0 0 22px;line-height:1.65;">
  Active Scouts receive Scout tools and complimentary Complete Access while their Scout status remains active.
</p>

<p style="margin:0 0 22px;">
  <a
    href="{{scout_invite_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Review Scout Invitation</a>
</p>

<p style="margin:0;color:#667069;font-size:14px;line-height:1.6;">
  Becoming a Scout is optional. Review the invitation and Scout expectations before deciding.
  This invitation expires 30 days after it was sent.
</p>
HTML,
        ],


        'support_admin_new_ticket' => [
            'template_key' => 'support_admin_new_ticket',
            'category' => 'Support',
            'name' => 'New Ticket Admin Alert',
            'description' =>
                'Sent internally when a new support ticket is created.',
            'enabled' => 1,
            'variables' => [
                'ticket_number',
                'support_category',
                'requester_name',
                'requester_email',
                'preferred_contact',
                'request_details',
                'ticket_subject',
                'ticket_message',
                'admin_ticket_url',
            ],
            'subject' => '[Ticket #{{ticket_number}}] {{ticket_subject}}',
            'preheader' =>
                'A new Llama Scout support ticket needs review.',
            'text_body' => <<<'TEXT'
New Llama Scout support ticket

Ticket: #{{ticket_number}}
Category: {{support_category}}
Name: {{requester_name}}
Email: {{requester_email}}
Preferred contact: {{preferred_contact}}
{{request_details}}

Subject: {{ticket_subject}}

{{ticket_message}}

Open in Basecamp:
{{admin_ticket_url}}
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  Support
</p>

<h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;color:#172822;">
  New support ticket #{{ticket_number}}
</h1>

<div style="margin:0 0 20px;padding:16px;border:1px solid #dcded8;border-radius:12px;line-height:1.7;">
  <strong>Category:</strong> {{support_category}}<br>
  <strong>Name:</strong> {{requester_name}}<br>
  <strong>Email:</strong> {{requester_email}}<br>
  <strong>Preferred contact:</strong> {{preferred_contact}}
</div>

<pre style="margin:0 0 20px;padding:14px;border-radius:10px;background:#f7f8f4;color:#33443d;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;white-space:pre-wrap;">{{request_details}}</pre>

<h2 style="margin:0 0 8px;font-size:18px;color:#172822;">
  {{ticket_subject}}
</h2>

<pre style="margin:0 0 22px;color:#263b33;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;white-space:pre-wrap;">{{ticket_message}}</pre>

<p style="margin:0;">
  <a
    href="{{admin_ticket_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Open Ticket in Basecamp</a>
</p>
HTML,
        ],


        'support_ticket_received' => [
            'template_key' => 'support_ticket_received',
            'category' => 'Support',
            'name' => 'Ticket Received',
            'description' =>
                'Sent to the requester after Llama Scout receives a support ticket.',
            'enabled' => 1,
            'variables' => [
                'requester_name',
                'ticket_number',
                'ticket_subject',
                'preferred_contact',
                'ticket_extra_details',
                'support_url',
            ],
            'subject' => 'Ticket #{{ticket_number}} received by Llama Scout',
            'preheader' =>
                'We received your Llama Scout support ticket.',
            'text_body' => <<<'TEXT'
Hi {{requester_name}},

We received your Llama Scout support ticket.

Ticket: #{{ticket_number}}
Subject: {{ticket_subject}}
Preferred contact: {{preferred_contact}}
{{ticket_extra_details}}

Keep the ticket number above if you need to follow up.

Support:
{{support_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  We received your ticket.
</h1>

<p style="margin:0 0 16px;line-height:1.65;">
  Hi {{requester_name}},
</p>

<div style="margin:0 0 20px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  <strong>Ticket:</strong> #{{ticket_number}}<br>
  <strong>Subject:</strong> {{ticket_subject}}<br>
  <strong>Preferred contact:</strong> {{preferred_contact}}
</div>

<pre style="margin:0 0 20px;color:#52605a;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;white-space:pre-wrap;">{{ticket_extra_details}}</pre>

<p style="margin:0 0 22px;line-height:1.65;">
  Keep your ticket number if you need to follow up.
</p>

<p style="margin:0;">
  <a
    href="{{support_url}}"
    style="display:inline-block;border:1px solid #172822;color:#172822;padding:12px 18px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Contact Support</a>
</p>
HTML,
        ],


        'support_ticket_waiting' => [
            'template_key' => 'support_ticket_waiting',
            'category' => 'Support',
            'name' => 'Ticket Waiting',
            'description' =>
                'Sent when a support ticket moves to Waiting status.',
            'enabled' => 1,
            'variables' => [
                'requester_name',
                'ticket_number',
                'ticket_subject',
                'support_url',
            ],
            'subject' => 'Ticket #{{ticket_number}} is now Waiting',
            'preheader' =>
                'Your support ticket is waiting for the next step.',
            'text_body' => <<<'TEXT'
Hi {{requester_name}},

Your support ticket is currently waiting. If Llama Scout requested additional information, reply to the most recent support email or contact support so we can continue.

Ticket: #{{ticket_number}}
Subject: {{ticket_subject}}
Status: Waiting

{{support_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Your ticket is waiting.
</h1>
<p style="margin:0 0 16px;line-height:1.65;">Hi {{requester_name}},</p>
<p style="margin:0 0 20px;line-height:1.65;">
  If Llama Scout requested additional information, reply to the most recent support email or contact support so we can continue.
</p>
<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  <strong>Ticket:</strong> #{{ticket_number}}<br>
  <strong>Subject:</strong> {{ticket_subject}}<br>
  <strong>Status:</strong> Waiting
</div>
<p style="margin:0;"><a href="{{support_url}}" style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;">Contact Support</a></p>
HTML,
        ],


        'support_ticket_resolved' => [
            'template_key' => 'support_ticket_resolved',
            'category' => 'Support',
            'name' => 'Ticket Resolved',
            'description' =>
                'Sent when a support ticket is marked Resolved.',
            'enabled' => 1,
            'variables' => [
                'requester_name',
                'ticket_number',
                'ticket_subject',
                'support_url',
            ],
            'subject' => 'Ticket #{{ticket_number}} is now Resolved',
            'preheader' =>
                'Your Llama Scout support ticket has been marked resolved.',
            'text_body' => <<<'TEXT'
Hi {{requester_name}},

Your support ticket has been marked resolved.

Ticket: #{{ticket_number}}
Subject: {{ticket_subject}}
Status: Resolved

If the problem returns or you still need help, you can create another support ticket:
{{support_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Your ticket is resolved.
</h1>
<p style="margin:0 0 16px;line-height:1.65;">Hi {{requester_name}},</p>
<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  <strong>Ticket:</strong> #{{ticket_number}}<br>
  <strong>Subject:</strong> {{ticket_subject}}<br>
  <strong>Status:</strong> Resolved
</div>
<p style="margin:0 0 22px;line-height:1.65;">If the problem returns or you still need help, you can create another support ticket.</p>
<p style="margin:0;"><a href="{{support_url}}" style="display:inline-block;border:1px solid #172822;color:#172822;padding:12px 18px;border-radius:9px;text-decoration:none;font-weight:700;">Open Support</a></p>
HTML,
        ],


        'support_ticket_reopened' => [
            'template_key' => 'support_ticket_reopened',
            'category' => 'Support',
            'name' => 'Ticket Reopened',
            'description' =>
                'Sent when a resolved or waiting support ticket is reopened.',
            'enabled' => 1,
            'variables' => [
                'requester_name',
                'ticket_number',
                'ticket_subject',
                'support_url',
            ],
            'subject' => 'Ticket #{{ticket_number}} is open again',
            'preheader' =>
                'Your Llama Scout support ticket is active again.',
            'text_body' => <<<'TEXT'
Hi {{requester_name}},

Your support ticket has been reopened and is active again.

Ticket: #{{ticket_number}}
Subject: {{ticket_subject}}
Status: Open

{{support_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">
  Your ticket is open again.
</h1>
<p style="margin:0 0 16px;line-height:1.65;">Hi {{requester_name}},</p>
<div style="margin:0 0 22px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;">
  <strong>Ticket:</strong> #{{ticket_number}}<br>
  <strong>Subject:</strong> {{ticket_subject}}<br>
  <strong>Status:</strong> Open
</div>
<p style="margin:0;"><a href="{{support_url}}" style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;">Open Support</a></p>
HTML,
        ],


        'contribution_approved' => [
            'template_key' => 'contribution_approved',
            'category' => 'Contributions',
            'name' => 'Contribution Approved',
            'description' =>
                'Sent when a new Place or Place update is approved.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'contribution_type',
                'contribution_id',
                'place_name',
                'review_notes',
                'points_awarded',
                'contribution_url',
                'place_url',
            ],
            'subject' => '{{contribution_type}} approved: {{place_name}}',
            'preheader' =>
                'Your Llama Scout contribution was approved.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your {{contribution_type}} for {{place_name}} was approved.

Contribution: #{{contribution_id}}
Points awarded: {{points_awarded}}

Review notes:
{{review_notes}}

View your contribution:
{{contribution_url}}

View the Place:
{{place_url}}

Thanks for helping make Llama Scout more useful for the herd.

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Contribution Review</p>
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">Approved.</h1>
<p style="margin:0 0 16px;line-height:1.65;">Hi {{display_name}},</p>
<p style="margin:0 0 20px;line-height:1.65;">Your {{contribution_type}} for <strong>{{place_name}}</strong> was approved.</p>
<div style="margin:0 0 20px;padding:16px;border-radius:12px;background:#f7f8f4;line-height:1.7;"><strong>Contribution:</strong> #{{contribution_id}}<br><strong>Points awarded:</strong> {{points_awarded}}</div>
<pre style="margin:0 0 22px;padding:14px;border:1px solid #dcded8;border-radius:10px;color:#33443d;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;white-space:pre-wrap;">{{review_notes}}</pre>
<p style="margin:10px 0;"><a href="{{contribution_url}}" style="display:block;padding:14px 18px;border-radius:9px;background:#172822;color:#ffffff;text-align:center;text-decoration:none;font-weight:700;">View Your Contribution</a></p>
<p style="margin:10px 0 0;"><a href="{{place_url}}" style="display:block;padding:13px 18px;border:1px solid #172822;border-radius:9px;color:#172822;text-align:center;text-decoration:none;font-weight:700;">View the Place</a></p>
HTML,
        ],


        'contribution_changes_requested' => [
            'template_key' => 'contribution_changes_requested',
            'category' => 'Contributions',
            'name' => 'Changes Requested',
            'description' =>
                'Sent when a new Place or Place update needs changes before approval.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'contribution_type',
                'contribution_id',
                'place_name',
                'review_notes',
                'contribution_url',
            ],
            'subject' => 'Changes requested for {{place_name}}',
            'preheader' =>
                'Your Llama Scout contribution needs a few changes.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your {{contribution_type}} for {{place_name}} needs a few changes before it can be approved.

Contribution: #{{contribution_id}}

Review notes:
{{review_notes}}

Open your contribution to make the requested changes:
{{contribution_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Contribution Review</p>
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">A few changes are needed.</h1>
<p style="margin:0 0 16px;line-height:1.65;">Hi {{display_name}},</p>
<p style="margin:0 0 20px;line-height:1.65;">Your {{contribution_type}} for <strong>{{place_name}}</strong> needs a few changes before it can be approved.</p>
<pre style="margin:0 0 22px;padding:14px;border:1px solid #dcded8;border-radius:10px;color:#33443d;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;white-space:pre-wrap;">{{review_notes}}</pre>
<p style="margin:0;"><a href="{{contribution_url}}" style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700;">Edit and Resubmit</a></p>
HTML,
        ],


        'contribution_not_approved' => [
            'template_key' => 'contribution_not_approved',
            'category' => 'Contributions',
            'name' => 'Contribution Not Approved',
            'description' =>
                'Sent when a new Place or Place update is not approved.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'contribution_type',
                'contribution_id',
                'place_name',
                'review_notes',
                'contribution_url',
            ],
            'subject' => 'Update on your {{contribution_type}} for {{place_name}}',
            'preheader' =>
                'Your Llama Scout contribution was not approved.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

Your {{contribution_type}} for {{place_name}} was not approved.

Contribution: #{{contribution_id}}

Review notes:
{{review_notes}}

View your contribution:
{{contribution_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Contribution Review</p>
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.1;color:#172822;">This contribution was not approved.</h1>
<p style="margin:0 0 16px;line-height:1.65;">Hi {{display_name}},</p>
<p style="margin:0 0 20px;line-height:1.65;">Your {{contribution_type}} for <strong>{{place_name}}</strong> was not approved.</p>
<pre style="margin:0 0 22px;padding:14px;border:1px solid #dcded8;border-radius:10px;color:#33443d;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;white-space:pre-wrap;">{{review_notes}}</pre>
<p style="margin:0;"><a href="{{contribution_url}}" style="display:inline-block;border:1px solid #172822;color:#172822;padding:12px 18px;border-radius:9px;text-decoration:none;font-weight:700;">View Contribution</a></p>
HTML,
        ],


        'newsletter_issue' => [
            'template_key' => 'newsletter_issue',
            'category' => 'Newsletters',
            'name' => 'Newsletter Delivery',
            'description' =>
                'Shared Email Center template used when a scheduled Llama Scout newsletter is delivered.',
            'enabled' => 1,
            'variables' => [
                'newsletter_subject',
                'newsletter_type',
                'newsletter_title',
                'newsletter_content',
                'site_url',
                'email_preferences_url',
                'newsletter_footer_copy',
            ],
            'subject' => '{{newsletter_subject}}',
            'preheader' => '{{newsletter_title}}',
            'text_body' => <<<'TEXT'
{{newsletter_title}}

{{newsletter_content}}

Open Llama Scout:
{{site_url}}

{{newsletter_footer_copy}}:
{{email_preferences_url}}

Llama Scout
Know the place before you go.
TEXT,
            'html_body' => <<<'HTML'
<p style="margin:0 0 8px;color:#667069;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
  {{newsletter_type}}
</p>

<h1 style="margin:0 0 22px;font-size:30px;line-height:1.2;color:#172822;">
  {{newsletter_title}}
</h1>

<div style="margin:0;line-height:1.65;color:#263b33;">
{{newsletter_content}}
</div>

<p style="margin:30px 0;">
  <a
    href="{{site_url}}"
    style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;"
  >Open Llama Scout</a>
</p>

<hr style="border:0;border-top:1px solid #e4e4e0;margin:30px 0 22px;">

<p style="margin:0;color:#667069;font-size:12px;line-height:1.6;">
  {{newsletter_footer_copy}}
  <a
    href="{{email_preferences_url}}"
    style="color:#445c52;"
  >Email Preferences</a>.
  Account and service messages are not affected.
</p>
HTML,
        ],


        'promotion_campaign_announcement' => [
            'template_key' => 'promotion_campaign_announcement',
            'category' => 'Campaigns',
            'name' => 'Campaign Email',
            'description' =>
                'Primary promotional email used by scheduled membership campaigns.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'campaign_name',
                'campaign_label',
                'campaign_description',
                'promotion_url',
                'starts_at',
                'ends_at',
                'monthly_regular_price',
                'monthly_sale_price',
                'monthly_year_total',
                'monthly_discount',
                'monthly_offer',
                'annual_regular_price',
                'annual_sale_price',
                'annual_month_equivalent',
                'annual_discount',
                'annual_offer',
                'unsubscribe_url',
            ],
            'subject' => '{{campaign_label}} at Llama Scout',
            'preheader' => '{{annual_offer}} Â· {{monthly_offer}}',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

{{campaign_description}}

Annual: {{annual_offer}}
Monthly: {{monthly_offer}}

View the membership offer:
{{promotion_url}}
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;color:#172822;">{{campaign_label}}</h1>
<p style="margin:0 0 18px;line-height:1.65;">Hi {{display_name}},</p>
<p style="margin:0 0 18px;line-height:1.65;">{{campaign_description}}</p>
<p style="margin:0 0 10px;line-height:1.65;"><strong>Annual:</strong> {{annual_offer}}</p>
<p style="margin:0 0 22px;line-height:1.65;"><strong>Monthly:</strong> {{monthly_offer}}</p>
<p style="margin:0;">
  <a href="{{promotion_url}}" style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;">View membership offer</a>
</p>
HTML,
        ],


        'promotion_campaign_reminder' => [
            'template_key' => 'promotion_campaign_reminder',
            'category' => 'Campaigns',
            'name' => 'Final Reminder',
            'description' =>
                'Final promotional reminder used by scheduled membership campaigns.',
            'enabled' => 1,
            'variables' => [
                'display_name',
                'username',
                'campaign_name',
                'campaign_label',
                'campaign_description',
                'promotion_url',
                'starts_at',
                'ends_at',
                'monthly_regular_price',
                'monthly_sale_price',
                'monthly_year_total',
                'monthly_discount',
                'monthly_offer',
                'annual_regular_price',
                'annual_sale_price',
                'annual_month_equivalent',
                'annual_discount',
                'annual_offer',
                'unsubscribe_url',
            ],
            'subject' => 'Last chance: {{campaign_label}}',
            'preheader' => 'The sale ends {{ends_at}}.',
            'text_body' => <<<'TEXT'
Hi {{display_name}},

This is your final reminder for {{campaign_label}}.

{{campaign_description}}

Annual: {{annual_offer}}
Monthly: {{monthly_offer}}

View the membership offer:
{{promotion_url}}
TEXT,
            'html_body' => <<<'HTML'
<h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;color:#172822;">Last chance: {{campaign_label}}</h1>
<p style="margin:0 0 18px;line-height:1.65;">Hi {{display_name}},</p>
<p style="margin:0 0 18px;line-height:1.65;">{{campaign_description}}</p>
<p style="margin:0 0 10px;line-height:1.65;"><strong>Annual:</strong> {{annual_offer}}</p>
<p style="margin:0 0 22px;line-height:1.65;"><strong>Monthly:</strong> {{monthly_offer}}</p>
<p style="margin:0;">
  <a href="{{promotion_url}}" style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:700;">View membership offer</a>
</p>
HTML,
        ],

    ];
}
