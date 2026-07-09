<?php
/*
Plugin Name: Stripe Gateway
Version: 1.1.0
Description: Stripe payment gateway for the Cart plugin. Requires the "cart" plugin to be active.
Author: Webgoodies
Has Settings: true
License: GPL2
*/

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

define('STRIPE_GATEWAY_ID', basename(dirname(__FILE__)));
define('STRIPE_GATEWAY_PATH', PHPWG_PLUGINS_PATH.STRIPE_GATEWAY_ID.'/');
define('STRIPE_API_VERSION', '2026-06-24.dahlia');
define('STRIPE_API_BASE', 'https://api.stripe.com/v1');

add_event_handler('init', 'stripe_gateway_init');
function stripe_gateway_init()
{
  load_language('plugin.lang', STRIPE_GATEWAY_PATH);
}

function stripe_gateway_conf()
{
  global $conf;
  return safe_unserialize($conf['stripe_gateway']);
}

// Builds a Stripe-hosted Checkout Session for a pending cart order and
// returns the URL to redirect the shopper to, or false on failure.
// Called directly by plugins/cart/checkout.php - no abstract gateway
// interface yet, see project notes (only one gateway exists so far).
function stripe_create_checkout_session_for_order($order_id)
{
  $gconf = stripe_gateway_conf();
  if (empty($gconf['api_key']))
  {
    error_log('[stripe] checkout session requested but no API key configured');
    return false;
  }

  $order = cart_get_order($order_id);
  if (is_null($order))
  {
    return false;
  }

  $items = cart_get_order_items($order_id);
  if (empty($items))
  {
    return false;
  }

  $params = array();
  $params['mode'] = 'payment';
  $params['client_reference_id'] = $order['order_ref'];
  $params['metadata']['order_id'] = $order_id;

  // Stripe requires fully-qualified URLs (scheme + host) here -
  // get_root_url() is relative, only fine for in-page links.
  $success_url = get_absolute_root_url().'plugins/cart/confirmation.php?order_ref='.urlencode($order['order_ref']).'&session_id={CHECKOUT_SESSION_ID}';
  $cancel_url = get_absolute_root_url().'plugins/cart/cart.php';
  $params['success_url'] = $success_url;
  $params['cancel_url'] = $cancel_url;

  if (!empty($order['email']))
  {
    $params['customer_email'] = $order['email'];
  }

  $i = 0;
  foreach ($items as $item)
  {
    $name = !empty($item['name']) ? $item['name'] : $item['file'];
    $params['line_items'][$i]['quantity'] = (int)$item['quantity'];
    $params['line_items'][$i]['price_data']['currency'] = $order['currency'];
    $params['line_items'][$i]['price_data']['unit_amount'] = (int)$item['price_cents'];
    $params['line_items'][$i]['price_data']['product_data']['name'] = $name;
    $i++;
  }

  // shipping is billed as its own line item (computed per-product in the
  // Cart plugin and summed onto the order already) rather than Stripe's
  // shipping_options, which is for customer-selectable rate choices -
  // not needed here since the cost is admin-set, not chosen at checkout
  if ($order['shipping_cents'] > 0)
  {
    $params['line_items'][$i]['quantity'] = 1;
    $params['line_items'][$i]['price_data']['currency'] = $order['currency'];
    $params['line_items'][$i]['price_data']['unit_amount'] = (int)$order['shipping_cents'];
    $params['line_items'][$i]['price_data']['product_data']['name'] = l10n('Shipping');
  }

  // collects name + shipping address natively on Stripe's hosted page -
  // no custom address form needed on our side
  $countries = array_filter(array_map('trim', explode(',', $gconf['shipping_countries'])));
  if (empty($countries))
  {
    $countries = array('US');
  }
  $j = 0;
  foreach ($countries as $country)
  {
    $params['shipping_address_collection']['allowed_countries'][$j] = strtoupper($country);
    $j++;
  }

  if (!empty($gconf['enable_automatic_tax']))
  {
    $params['automatic_tax']['enabled'] = 'true';
  }

  // intentionally no payment_method_types - let Stripe show the most
  // relevant dynamic payment methods for the buyer

  $response = stripe_api_request('POST', '/checkout/sessions', $params, $gconf['api_key']);

  if ($response === false || !isset($response['url']) || !isset($response['id']))
  {
    error_log('[stripe] failed to create checkout session for order '.$order_id.': '.json_encode($response));
    return false;
  }

  cart_attach_stripe_session($order_id, $response['id']);

  return $response['url'];
}

// Pulls the customer/shipping/tax/total details that are only available
// once a session is complete, in the shape cart_mark_paid() expects.
// Shared by the webhook and the confirmation-page fallback so both stay
// in sync with Stripe's actual field paths (customer_details.*,
// shipping_details.*, total_details.amount_tax, amount_total).
function stripe_extract_session_details($session)
{
  $details = array();

  if (!empty($session['customer_details']['email']))
  {
    $details['email'] = $session['customer_details']['email'];
  }
  if (!empty($session['customer_details']['name']))
  {
    $details['customer_name'] = $session['customer_details']['name'];
  }
  if (!empty($session['shipping_details']['address']))
  {
    $details['shipping_address'] = array_merge(
      array('name' => isset($session['shipping_details']['name']) ? $session['shipping_details']['name'] : ''),
      $session['shipping_details']['address']
      );
  }
  if (isset($session['total_details']['amount_tax']))
  {
    $details['tax_cents'] = $session['total_details']['amount_tax'];
  }
  if (isset($session['amount_total']))
  {
    $details['total_cents'] = $session['amount_total'];
  }

  return $details;
}

// Fallback for the confirmation page: if the webhook hasn't landed yet
// (network delay, misconfigured endpoint), check the session directly.
function stripe_retrieve_checkout_session($session_id)
{
  $gconf = stripe_gateway_conf();
  if (empty($gconf['api_key']))
  {
    return false;
  }

  return stripe_api_request('GET', '/checkout/sessions/'.rawurlencode($session_id), array(), $gconf['api_key']);
}

// Minimal Stripe REST client using cURL - no SDK/composer dependency so
// this plugin drops onto any shared host as-is.
function stripe_api_request($method, $path, $params, $api_key)
{
  if (!function_exists('curl_init'))
  {
    error_log('[stripe] cURL extension is not available');
    return false;
  }

  $ch = curl_init(STRIPE_API_BASE.$path);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);

  // Some shared hosts (and stock Windows/XAMPP-style stacks) ship PHP
  // without curl.cainfo configured, which makes SSL verification fail
  // outright. Bundling our own CA file keeps this plugin working
  // regardless of host php.ini - never disable verification instead.
  // __DIR__ (not STRIPE_GATEWAY_PATH) because plain filesystem functions
  // resolve relative paths against the process cwd, not the including
  // file's directory the way include/require do - STRIPE_GATEWAY_PATH is
  // only meant for the latter.
  $bundled_cacert = __DIR__.DIRECTORY_SEPARATOR.'cacert.pem';
  if (is_file($bundled_cacert))
  {
    curl_setopt($ch, CURLOPT_CAINFO, $bundled_cacert);
  }

  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer '.$api_key,
    'Stripe-Version: '.STRIPE_API_VERSION,
    'Content-Type: application/x-www-form-urlencoded',
    ));

  if ($method == 'POST')
  {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, stripe_http_build_query($params));
  }

  $body = curl_exec($ch);
  $errno = curl_errno($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($errno !== 0)
  {
    error_log('[stripe] cURL error calling '.$path.': '.curl_error($ch));
    return false;
  }

  $decoded = json_decode($body, true);

  if ($http_code >= 400)
  {
    error_log('[stripe] API error ('.$http_code.') calling '.$path.': '.$body);
    return false;
  }

  return $decoded;
}

// Stripe's API expects PHP-style bracketed nested form encoding
// (line_items[0][price_data][currency]=usd), which is exactly what
// http_build_query already produces.
function stripe_http_build_query($params)
{
  return http_build_query($params);
}
