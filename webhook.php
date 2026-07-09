<?php
// Stripe webhook endpoint. Verifies the signature itself (does not rely on
// any Piwigo session/auth - Stripe calls this directly, unauthenticated).

define('PHPWG_ROOT_PATH', '../../');
include_once(PHPWG_ROOT_PATH.'include/common.inc.php');
include_once(PHPWG_ROOT_PATH.'plugins/cart/functions.inc.php');

$gconf = stripe_gateway_conf();

$payload = file_get_contents('php://input');
$sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

$event = stripe_verify_webhook($payload, $sig_header, $gconf['webhook_secret']);

if ($event === false)
{
  http_response_code(400);
  echo 'signature verification failed';
  exit;
}

if ($event['type'] == 'checkout.session.completed')
{
  $session = $event['data']['object'];

  if ($session['payment_status'] == 'paid')
  {
    $order = cart_get_order_by_session_id($session['id']);

    if (!is_null($order) && $order['status'] != 'paid')
    {
      $payment_intent_id = isset($session['payment_intent']) ? $session['payment_intent'] : null;
      cart_mark_paid($order['id'], $payment_intent_id, stripe_extract_session_details($session));
      cart_clear($order['cart_key']);
      cart_send_order_emails($order['id']);
    }
  }
}

http_response_code(200);
echo 'ok';

// Verifies a Stripe webhook signature per Stripe's documented algorithm
// (https://docs.stripe.com/webhooks#verify-events) without needing the
// full Stripe SDK. Returns the decoded event array, or false if invalid.
function stripe_verify_webhook($payload, $sig_header, $webhook_secret)
{
  if (empty($webhook_secret) || empty($sig_header))
  {
    return false;
  }

  $parts = array();
  foreach (explode(',', $sig_header) as $pair)
  {
    $kv = explode('=', $pair, 2);
    if (count($kv) == 2)
    {
      $parts[$kv[0]] = $kv[1];
    }
  }

  if (!isset($parts['t']) || !isset($parts['v1']))
  {
    return false;
  }

  $timestamp = $parts['t'];
  $expected_sig = hash_hmac('sha256', $timestamp.'.'.$payload, $webhook_secret);

  if (!hash_equals($expected_sig, $parts['v1']))
  {
    return false;
  }

  // reject events older than 5 minutes to guard against replay attacks
  if (abs(time() - (int)$timestamp) > 300)
  {
    return false;
  }

  $event = json_decode($payload, true);
  if (json_last_error() !== JSON_ERROR_NONE)
  {
    return false;
  }

  return $event;
}
