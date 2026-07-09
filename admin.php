<?php
defined('STRIPE_GATEWAY_PATH') or die('Hacking attempt!');

check_status(ACCESS_WEBMASTER);

$gconf = stripe_gateway_conf();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_config']))
{
  $gconf['api_key'] = trim($_POST['api_key']);
  $gconf['webhook_secret'] = trim($_POST['webhook_secret']);
  $gconf['enable_automatic_tax'] = isset($_POST['enable_automatic_tax']);
  $gconf['shipping_countries'] = strtoupper(preg_replace('/[^A-Za-z,]/', '', $_POST['shipping_countries']));
  conf_update_param('stripe_gateway', $gconf);
  $page['infos'][] = l10n('Information data registered in database');
}

// absolute, not get_root_url() - this gets pasted into Stripe's dashboard
$webhook_url = get_absolute_root_url().'plugins/'.STRIPE_GATEWAY_ID.'/webhook.php';

$template->assign(
  array(
    'STRIPE_API_KEY' => $gconf['api_key'],
    'STRIPE_WEBHOOK_SECRET' => $gconf['webhook_secret'],
    'STRIPE_WEBHOOK_URL' => $webhook_url,
    'STRIPE_ENABLE_AUTOMATIC_TAX' => !empty($gconf['enable_automatic_tax']),
    'STRIPE_SHIPPING_COUNTRIES' => $gconf['shipping_countries'],
    'STRIPE_ADMIN_URL' => get_root_url().'admin.php?page=plugin-'.STRIPE_GATEWAY_ID,
    )
  );

$template->set_filename('stripe_admin_content', realpath(STRIPE_GATEWAY_PATH.'template/admin.tpl'));
$template->assign_var_from_handle('ADMIN_CONTENT', 'stripe_admin_content');
