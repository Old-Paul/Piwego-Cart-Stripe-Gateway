<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class stripe_maintain extends PluginMaintain
{
  private $default_conf = array(
    'api_key' => '',
    'webhook_secret' => '',
    'enable_automatic_tax' => false,
    'shipping_countries' => 'US',
    );

  function install($plugin_version, &$errors=array())
  {
    global $conf;

    if (empty($conf['stripe_gateway']))
    {
      conf_update_param('stripe_gateway', $this->default_conf, true);
    }
    else
    {
      // fill in any new settings added since this site's gateway was
      // installed, without touching settings the admin already customised
      $existing = safe_unserialize($conf['stripe_gateway']);
      $merged = array_merge($this->default_conf, is_array($existing) ? $existing : array());
      conf_update_param('stripe_gateway', $merged);
    }
  }

  function activate($plugin_version, &$errors=array())
  {
    $this->install($plugin_version, $errors);
  }

  function update($old_version, $new_version, &$errors=array())
  {
    $this->install($new_version, $errors);
  }

  function deactivate()
  {
  }

  function uninstall()
  {
    pwg_query('DELETE FROM `'.CONFIG_TABLE.'` WHERE param = "stripe_gateway";');
  }
}
