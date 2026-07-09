# Setting Up Your Store

This gallery has a built-in shop: any photo can be priced and sold, with checkout, shipping, and (optionally) tax handled by Stripe. This guide walks you through connecting your own Stripe account so you can start taking orders.

You do **not** need any coding knowledge for any of this — everything below happens on Stripe's own website or in this gallery's admin panel.

---

## 1. Create your Stripe account

If you don't already have one, sign up at [stripe.com](https://dashboard.stripe.com/register). It's free to create — Stripe only takes a small fee per transaction once you start accepting real payments.

Stripe gives you two modes:
- **Test mode** — fake payments, for trying things out. Nothing here charges real money.
- **Live mode** — real payments. You'll switch to this once you're ready to actually sell.

Do your setup and testing in test mode first.

---

## 2. Get your API key

Your gallery needs a key to talk to your Stripe account.

1. In the Stripe Dashboard, go to **Developers → API keys**.
2. We recommend creating a **restricted key** (not your main secret key) — it's safer, since it only has the specific permissions your gallery needs rather than full access to your account. Click **Create restricted key**, give it a name (e.g. "Gallery Cart"), and grant it write access to **Checkout Sessions** and read access to the rest it asks for.
3. Copy the key (it starts with `rk_` for a restricted key, or `sk_` if you use your main secret key instead).
4. In your gallery, go to **Plugins → Stripe Gateway → Settings**, paste it into the **Stripe API key** field, and save.

Keep this key private — never share it or paste it anywhere public.

---

## 3. Connect the webhook

The webhook is how Stripe tells your gallery "this order was paid." Without it, orders won't be marked as paid even though the customer was charged.

1. In your gallery, go to **Plugins → Stripe Gateway → Settings**. Copy the **Webhook endpoint URL** shown there.
2. In the Stripe Dashboard, go to **Developers → Webhooks → Add endpoint**.
3. Paste the URL, and select the event **`checkout.session.completed`**.
4. Stripe will show you a **signing secret** (starts with `whsec_`). Copy it.
5. Back in your gallery's Stripe settings, paste it into the **Webhook signing secret** field and save.

---

## 4. Set which countries you ship to

In **Plugins → Stripe Gateway → Settings**, the **Countries you ship to** field controls which countries a customer can enter a shipping address for at checkout (comma-separated 2-letter codes, e.g. `US,CA`). Only list countries you're actually prepared to ship to.

---

## 5. Price your products and set shipping

In **Plugins → Cart → Settings**, search for a photo and set:
- **Price** — required.
- **Shipping** — optional per-photo shipping cost, or check **Free Shipping** to waive it for that item.
- **SKU** — optional. If several photos are the same physical product (e.g. different angles of one item), give them all the *same* SKU — customers can add any of them to their cart, but they'll fold into a single line instead of duplicating. Up to 4 photos can share one SKU.

You can also set:
- How many of one item a customer can buy at once, and how many different products can be in a cart at once (prevents runaway orders).
- How long an unpaid order is held before the cart is released (default 90 minutes) — no reason to hold inventory for someone who never completes checkout.
- Where new-order notification emails go (defaults to your site's webmaster email if left blank).

---

## 6. Sales tax (optional)

Stripe can automatically calculate and collect sales tax at checkout. This is entirely optional, and there's a real setup step before you turn it on.

**Before enabling it:**
1. In the Stripe Dashboard, go to **Settings → Tax**.
2. Add a business address if you haven't already — Stripe requires this before it will calculate any tax, even in test mode.
3. Add a registration for each place you're required to collect tax (state, country, etc.). If you're not sure where you're obligated to collect, that's a question for your accountant, not something to guess at here.

**Then, in your gallery**, go to **Plugins → Stripe Gateway → Settings** and check **Enable automatic tax calculation**.

**Important — what this does and doesn't do:**
- Stripe will calculate the correct tax for each order and collect it from the customer at checkout.
- That collected tax money lands in your normal Stripe balance along with the rest of the sale — Stripe does **not** automatically send it to the tax authority for you.
- **Filing and paying that tax to the correct authority is your responsibility.** Stripe's calculation feature only handles the math at checkout.
- If you'd rather not handle filing yourself, Stripe has optional partner services (TaxJar, Taxually, Marosa, Hands-off Sales Tax) that can automate the actual filing for an additional fee. That's entirely your call — nothing here requires it, and this gallery doesn't set it up for you. Search "Stripe Tax filing partners" from the Stripe Dashboard's Tax section if you want to look into it.

If you skip this section entirely, your store still works fine — orders just won't have sales tax added.

---

## 7. Coupons and discounts (optional)

This gallery doesn't have its own coupon feature — instead, it uses Stripe's directly. Once your Stripe account is connected, you can create discount codes right in the Stripe Dashboard under **Product catalog → Coupons**, and customers can enter them at checkout. No extra setup here is required to use this.

---

## 8. Test before you go live

While still in Stripe's test mode, place a real test order through your gallery. Stripe provides test card numbers (e.g. `4242 4242 4242 4242`, any future expiry, any CVC) that simulate a successful payment without charging anything. Confirm:
- The order shows up in **Plugins → Cart → Orders** in your gallery.
- You receive the new-order notification email.
- The customer's confirmation email arrives (check spam if not).

Once that all works, switch Stripe to **live mode**, get your **live** API key and webhook secret the same way as above (steps 2–3), and update your gallery's Stripe settings with the live versions. You're now accepting real orders.
