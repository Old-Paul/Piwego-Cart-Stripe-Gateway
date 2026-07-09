<div class="titrePage">
  <h2>{'Stripe Gateway'|@translate}</h2>
</div>

<form method="post" action="{$STRIPE_ADMIN_URL}">
  <fieldset>
    <legend>{'API Credentials'|@translate}</legend>

    <p>
      <label>{'Stripe API key (restricted key recommended, starts with rk_ or sk_)'|@translate}</label><br>
      <input type="password" name="api_key" value="{$STRIPE_API_KEY}" size="60" autocomplete="off">
    </p>

    <p>
      <label>{'Webhook signing secret (starts with whsec_)'|@translate}</label><br>
      <input type="password" name="webhook_secret" value="{$STRIPE_WEBHOOK_SECRET}" size="60" autocomplete="off">
    </p>

    <p>
      {'Webhook endpoint URL (add this in the Stripe Dashboard, listening for event: checkout.session.completed)'|@translate}<br>
      <code>{$STRIPE_WEBHOOK_URL}</code>
    </p>

    <input type="hidden" name="save_config" value="1">
    <p class="bottomButtons"><input type="submit" value="{'Save Settings'|@translate}"></p>
  </fieldset>

  <fieldset>
    <legend>{'Shipping &amp; Tax'|@translate}</legend>

    <p>
      <label>{'Countries you ship to (comma-separated 2-letter codes, e.g. US,CA)'|@translate}</label><br>
      <input type="text" name="shipping_countries" value="{$STRIPE_SHIPPING_COUNTRIES}" size="30">
    </p>

    <p>
      <label>
        <input type="checkbox" name="enable_automatic_tax"{if $STRIPE_ENABLE_AUTOMATIC_TAX} checked{/if}>
        {'Enable automatic tax calculation'|@translate}
      </label><br>
      <small>{'Only turn this on after registering your tax jurisdictions in the Stripe Dashboard under Settings → Tax. Stripe calculates and collects tax at checkout; filing/remittance is still your responsibility.'|@translate}</small>
    </p>

    <input type="hidden" name="save_config" value="1">
    <p class="bottomButtons"><input type="submit" value="{'Save Settings'|@translate}"></p>
  </fieldset>
</form>
