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

    ];
}
