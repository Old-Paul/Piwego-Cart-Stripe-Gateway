# Stripe Gateway

**Version:** 1.1.0
**Requires:** the **Cart** plugin active (this plugin only provides payment processing for it — it has no standalone function).

## What it does

Connects the Cart plugin to Stripe. Builds a Stripe-hosted Checkout Session for a pending order and handles the webhook Stripe calls back to confirm payment. Currently the only payment gateway — Cart's `checkout.php` calls this plugin's functions directly rather than through an abstract gateway interface, since there's only ever been one gateway to support so far.

## Admin settings

**Plugins → Stripe Gateway → Settings** (requires webmaster-level access, not just administrator — this page handles live API credentials)

| Field | What it does |
|---|---|
| Stripe API key | A restricted key (starts `rk_`) is recommended over your main secret key (`sk_`) — narrower permissions if it's ever exposed |
| Webhook signing secret | From Stripe's webhook setup (starts `whsec_`) — lets this plugin verify a webhook call actually came from Stripe |
| Webhook endpoint URL | Shown on this page, not entered — copy it into Stripe's Dashboard under Developers → Webhooks |
| Enable automatic tax calculation | Turns on Stripe Tax at checkout (see the caveats in `STORE-SETUP.md` — this only calculates/collects, it does not file taxes for you) |
| Countries you ship to | Comma-separated 2-letter codes (e.g. `US,CA`) — controls which countries a customer can enter a shipping address for |

## Setup

Full step-by-step (creating the Stripe account, restricted key, webhook, test-mode order, going live) is in `STORE-SETUP.md` one level up in `plugins/` — that guide is written for the store owner, not a developer, and covers this plugin and Cart together since they're always configured as a pair.

## Notes

- `STRIPE_API_VERSION` is pinned in `main.inc.php` (`2026-06-24.dahlia`) — update deliberately, not incidentally, if you ever bump it.
- Uninstalling only removes this plugin's own config row; it does not touch Cart's tables or orders.
