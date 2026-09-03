# Llama Scout

**Know the place before you go.**

Llama Scout is an outdoor campsite and dispersed-camping discovery platform built to help people understand what a place is actually like before committing to the drive.

Instead of stopping at a pin on a map, Llama Scout focuses on the practical details that matter once you arrive: road access, vehicle fit, amenities, connectivity, weather, sensory conditions, privacy, noise, crowds, accessibility, and other field-tested information that traditional campground directories often leave out.

The project is currently being rebuilt as a cleaner, lighter, production-ready version of the original AutismOverland/Llama Scout site.

## What Llama Scout does

Llama Scout combines public place information, community contributions, and in-person Scout reports into detailed place pages.

Depending on the place and the user's access level, a listing can include:

- General location, city, county, state, elevation, and land manager
- Amenities and facilities
- Road and access conditions
- Vehicle size and campsite fit information
- Cell-service and Starlink observations
- Noise, crowds, privacy, lighting, odors, and other sensory details
- Accessibility observations
- Photos and galleries
- Weather information
- Exact coordinates for members
- Community edits and problem reports
- Llama Scout field reports and Scout history

The goal is not simply to answer **where can I camp?** It is to answer **what am I actually getting into when I get there?**

## Membership model

Llama Scout uses a free-plus-membership model.

Public and free users can access useful planning information such as general location, elevation, land manager, amenities, and local weather.

Paid members receive the deeper planning layer, including exact coordinates, detailed Scout information, expanded photos, sensory and accessibility data, and more precise location-based weather.

Membership billing is handled through Stripe.

## Community and Scouts

Llama Scout is designed to improve over time through a combination of community participation and structured Scout visits.

Community members can contribute new places, submit edits, report problems, upload supporting photos, save places, maintain profiles, and earn badges and points.

A place can also be **Llama Scouted**, meaning a Llama Scout participant has physically visited the location and completed a structured field report. A later community edit does not erase the fact that the site was previously Scouted; that history remains part of the place record.

Scout and Master Scout systems include applications, training, policies, moderation controls, and administrative oversight.

## Shop

Llama Scout also includes a branded merchandise shop.

The Shop supports:

- Products with option-based variants
- Variant-specific pricing and photos
- Inventory tracking
- Low-stock states
- Preorders and backorders
- Shopping cart management
- Stripe embedded checkout
- Shipping rates and addresses
- Local order records
- Webhook-confirmed payment status
- Inventory reservation and deduction
- Administrative product and order management

Shop purchases and memberships are intentionally handled as separate Stripe workflows.

## Administration

The administrative system provides tools for managing the platform without hardcoding routine business decisions into the public site.

Current administrative areas include:

- Dashboard and platform activity
- Users and account management
- Places and submissions
- Reports and moderation
- Scout and Master Scout management
- Policies and points
- Memberships
- Products, orders, shipping, and fulfillment
- Maintenance mode
- Error logging and system health
- Testing tools

The project includes a centralized application error log with unique `LS-XXXXXXXX` references, occurrence tracking, technical context, resolution status, retention controls, and system-health integration.

## Architecture

Llama Scout is primarily a server-rendered PHP application backed by MariaDB/MySQL.

Major pieces include:

- PHP application code
- MariaDB/MySQL database
- Stripe for memberships and Shop payments
- Cloudflare for DNS/CDN services
- Porkbun/cPanel hosting
- GitHub as the production source repository
- Separate account and admin subdomains
- Cloudflare Turnstile in authentication flows

The current production domains include:

- `llamascout.com`
- `account.llamascout.com`
- `admin.llamascout.com`

## Production workflow

The `main` branch is treated as the production source of truth.

Changes committed to GitHub are expected to correspond with the live production site, so development work should begin by reading the current version of a file from the repository rather than relying on older local copies.

Database schema changes should be performed as explicit, one-time SQL migrations. Normal page requests, checkout requests, and webhook requests should not perform `CREATE TABLE` or `ALTER TABLE` operations at runtime.

## Current development focus

The current rebuild is focused on finishing the production foundation rather than adding large amounts of new surface area.

Major areas of active work include:

1. Completing and hardening the Shop order and fulfillment workflow
2. Keeping membership and Shop Stripe webhooks cleanly separated
3. Improving moderation and new-place review tools
4. Refining Scout workflows and policies
5. Completing admin dashboards and operational tooling
6. Polishing public place pages, profiles, memberships, and responsive layouts
7. Restoring and standardizing image-upload workflows
8. Continuing accessibility and mobile usability improvements
9. Removing stale code, hardcoded fallbacks, and legacy behavior from the rebuild

## Accessibility

Accessibility is a core product requirement rather than an afterthought.

The site includes or is being built around features such as:

- Dark and light themes
- Font-size controls
- Reduced-motion support
- Responsive mobile navigation
- Keyboard-friendly controls
- Clear status and error messaging
- Sensory information as first-class place data

## Project philosophy

Llama Scout is being built around a few practical rules:

- Useful field information is more valuable than a generic map pin.
- Community contributions should improve the database without erasing prior Scout history.
- Free users should still receive genuinely useful information.
- Paid features should provide meaningful planning value rather than arbitrary restrictions.
- Administrative settings should control business rules wherever practical.
- Production errors should be traceable instead of disappearing into blank pages.
- Database migrations should be deliberate and one-time.
- The codebase should remain understandable enough to maintain from real-world devices, including an iPad.

## Status

Llama Scout v2 is under active development and is running as a live production application while the rebuild is completed.

The platform is not yet considered feature-complete, but the major foundations now include authentication, profiles, memberships, Scout workflows, place data, moderation, error monitoring, merchandise management, cart functionality, and working Stripe Shop checkout.

---

Llama Scout  
**Know the place before you go.**
