<?php
// +----------------------------------------------------------------------+
// |Snap Affiliates for Zen Cart                                          |
// +----------------------------------------------------------------------+
// | Copyright (c) 2013-2016, Vinos de Frutas Tropicales (lat9)           |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.       |
// +----------------------------------------------------------------------+

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
define('SNAP_MODULE_CURRENT_VERSION', '3.0.4');
define('SNAP_MODULE_UPDATE_DATE', '2016-04-16');

//----
// Create each of the database tables for the referrers plugin, if they don't already exist.
//
$sql = "CREATE TABLE IF NOT EXISTS " . TABLE_REFERRERS . " (
  referrer_customers_id int(11) not null primary key,
  referrer_key varchar(32) not null,
  referrer_homepage text not null,
  referrer_approved tinyint(4) not null,
  referrer_banned tinyint(4) not null,
  referrer_commission float not null
  )";
$db->Execute($sql);

$sql = "CREATE TABLE IF NOT EXISTS " . TABLE_COMMISSION . " (
  commission_id int(11) not null auto_increment primary key,
  commission_orders_id int(11) not null,
  commission_referrer_key varchar(96) not null,
  commission_rate float not null,
  commission_paid datetime not null,
  commission_paid_amount float not null,
  commission_manual tinyint(1) NOT NULL default '0'
  )";  //-v2.7.0c
$db->Execute($sql);

// -----
// For upgrades, check for the presence of the commission_id and commission_paid_amount fields and add them if required.
//
//-bof-v2.7.0a
if (!$sniffer->field_exists (TABLE_COMMISSION, 'commission_id')) {
  $db->Execute ("ALTER TABLE " . TABLE_COMMISSION . " DROP PRIMARY KEY , ADD COLUMN commission_id int(11) NOT NULL auto_increment PRIMARY KEY");
}
if (!$sniffer->field_exists (TABLE_COMMISSION, 'commission_paid_amount')) {
  $db->Execute ("ALTER TABLE " . TABLE_COMMISSION . " ADD COLUMN commission_paid_amount float NOT NULL");
}
if (!$sniffer->field_exists (TABLE_COMMISSION, 'commission_manual')) {
  $db->Execute ("ALTER TABLE " . TABLE_COMMISSION . " ADD COLUMN commission_manual tinyint(1) NOT NULL default '0'");
}
//-eof-v2.7.0a

//-bof-20150304-lat9-Add fields for commission payment type
if (!$sniffer->field_exists (TABLE_REFERRERS, 'referrer_payment_type')) {
  $db->Execute ("ALTER TABLE " . TABLE_REFERRERS . " ADD COLUMN referrer_payment_type char(2) NOT NULL default 'CM', ADD COLUMN referrer_payment_type_detail varchar(255) NOT NULL default ''");
  
}
if (!$sniffer->field_exists (TABLE_COMMISSION, 'commission_payment_type')) {
  $db->Execute ("ALTER TABLE " . TABLE_COMMISSION . " ADD COLUMN commission_payment_type char(2) NOT NULL default 'CM', ADD COLUMN commission_payment_type_detail varchar(255) NOT NULL default ''");
  
}
//-eof-20150304-lat9

//----
// Create the Configuration->Affiliate Program item, if it's not already there.
//
$configurationGroupTitle = 'Affiliate Program';
$currentVersion = SNAP_MODULE_CURRENT_VERSION;
$currentDescription = SNAP_MODULE_UPDATE_DATE . ', <a href="http://vinosdefrutastropicales.com" target="_blank" rel="noreferrer">Vinos de Frutas Tropicales</a>';  /*v2.3.0c*/

//-bof-v2.1.2c-Provide fix-up for problem with previous versions' auto-install
$configuration = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = '$configurationGroupTitle' ORDER BY configuration_group_id ASC;");
if ($configuration->EOF) {
  $db->Execute("INSERT INTO " . TABLE_CONFIGURATION_GROUP . " 
                 (configuration_group_title, configuration_group_description, sort_order, visible) 
                 VALUES ('$configurationGroupTitle', 'Set Affiliate Program Options', '1', '1');");
  $configuration_group_id = $db->Insert_ID();
  
  $db->Execute("UPDATE " . TABLE_CONFIGURATION_GROUP . " SET sort_order = $configuration_group_id WHERE configuration_group_id = $configuration_group_id;");

  
} elseif ($configuration->RecordCount() != 1) {
  $configuration_group_id = $configuration->fields['configuration_group_id'];
  $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_group_id = $configuration_group_id WHERE configuration_key LIKE 'SNAP%'");
  $db->Execute("DELETE FROM " . TABLE_CONFIGURATION_GROUP . " WHERE (configuration_group_id != $configuration_group_id AND configuration_group_title = '$configurationGroupTitle')");

} else {
  $configuration_group_id = $configuration->fields['configuration_group_id'];
  
}
//-eof-v2.1.2c-Provide fix-up for problem with previous versions' auto-install

//-----
// If the currently-installed version of the plugin doesn't match the current install's
// version (or it's the first time) ...
//
// Loop through each of the configuration items, inserting them into the database if the
// previously-installed plugin version is less than this current install's version and
// the configuration key doesn't already exist (it "shouldn't", but this provides a bit of 
// an additional safety net).
//
// Once that's complete, update the database configuration to the current install's version.
//
if (!defined('SNAP_MODULE_VERSION') || SNAP_MODULE_VERSION !== $currentVersion) {  /*v2.1.2c*/

  /*----
  ** This array contains the Configuration->Affiliate Program group's options.  Each item will be entered with a
  ** last_modifed and date_added date of 'now()' by the foreach loop that follows.  This structure allows new
  ** configuration items to be added without affecting the store's current setting for the newly-created items.
  */
  $snap_config_items = array (
    array ( 'version' => '1.1', 'title' => 'Module Version', 'key' => 'SNAP_MODULE_VERSION', 'value' => $currentVersion, 'description' => $currentDescription, 'sort_order' => 10, 'use_function' => 'NULL', 'set_function' => 'trim('),
    array ( 'version' => '1.1', 'title' => 'Default Commission', 'key' => 'SNAP_DEFAULT_COMMISSION', 'value' => '0.1', 'description' => 'The default commission rate for your store\'s Affiliate Program.  The value should be specified as a floating-point number in the range 0.0 to 1.0.  The default value (<strong>0.1</strong>) represents a 10% commission rate.<br />', 'sort_order' => 12,  'use_function' => 'NULL', 'set_function' => 'NULL'),
    array ( 'version' => '1.1', 'title' => 'Order Total Exclusions', 'key' => 'SNAP_ORDER_TOTALS_EXCLUSIONS', 'value' => 'ot_shipping,ot_tax', 'description' => 'Exclude these Order Totals classes from an affiliate\'s commission. Enter the values as a comma-separated list with no intervening blanks.<br /><br />Default: <b>ot_shipping,ot_tax</b>.', 'sort_order' => 14, 'use_function' => 'NULL', 'set_function' => 'NULL'),
    array ( 'version' => '1.1', 'title' => 'Affiliate Key Prefix', 'key' => 'SNAP_KEY_PREFIX', 'value' => 'CNWR_', 'description' => 'Enter the prefix value to use for affiliate keys associated with your store\'s Affiliate Program. <strong>Note:</strong> If you change this value after you have started your program, existing affiliates will no longer earn their commissions!<br /><br />Default: <strong>CNWR_</strong>', 'sort_order' => 16, 'use_function' => 'NULL', 'set_function' => 'NULL'),
    array ( 'version' => '1.1', 'title' => 'Send Affiliate Emails To', 'key' => 'SNAP_ADMIN_EMAIL', 'value' => 'Enter email address here', 'description' => 'Enter the email address to which affiliate-related sign-up emails should be sent.<br />', 'sort_order' => 18, 'use_function' => 'NULL', 'set_function' => 'NULL'),
    array ( 'version' => '1.1', 'title' => 'Affiliate Program Images', 'key' => 'SNAP_AFFILIATE_IMAGES', 'value' => '', 'description' => 'Identify the images that your affiliates can use in their back-links.  Each file must be present in your store\'s /images/referrers directory and be named <em>ref.ww.hh.ext</em> where <em>ww</em> is the image width, <em>hh</em> is the image height and <em>ext</em> is the image extension (gif or jpg).<br /><br />Use the format /ww,hh,ext/[ww,hh,ext/...] to identify the files.  For example, if your store uses the files named ref.60.60.gif and ref.120.60.jpg for your program, you will enter this field as <b>/60,60,gif/120,60,jpg/</b><br />', 'sort_order' => 19, 'use_function' => 'NULL', 'set_function' => 'zen_cfg_textarea('),
    array ( 'version' => '1.1', 'title' => 'Include in Information Sidebox?', 'key' => 'SNAP_INFORMATION_SIDEBOX', 'value' => 'false', 'description' => 'Identifies whether (\'true\') or not (\'false\') to include a link to your Affiliate Program in the Information sidebox.<br /><br />Default: <strong>\'false\'</strong>.', 'sort_order' => 20, 'use_function' => 'NULL', 'set_function' => 'zen_cfg_select_option(array(\'true\', \'false\'),'),  //-v2.6.1c
    array ( 'version' => '2.1.0', 'title' => 'Affiliate Display Count', 'key' => 'SNAP_MAX_REFERRER_DISPLAY', 'value' => '50', 'description' => 'Specifies the maximum number of affiliates to show on each page of your admin\'s <em>Customers-&gt;Referrers</em>.<br /><br />Default: <strong>50</strong><br /><br />', 'sort_order' => 22, 'use_function' => 'NULL', 'set_function' => 'NULL'), /*v2.1.0a*/
    array ( 'version' => '2.1.0', 'title' => 'Allow Self-Commissions', 'key' => 'SNAP_AFFILIATE_KEY_USE', 'value' => 'false', 'description' => 'Identifies whether (\'true\') or not (\'false\') an affiliate receives commission for purchases made using their own <em>affiliate key</em>.<br /><br />Default: <strong>\'false\'</strong>.', 'sort_order' => 24, 'use_function' => 'NULL', 'set_function' => 'zen_cfg_select_option(array(\'true\', \'false\'),'), /*v2.1.0a*/
    array ( 'version' => '2.1.0', 'title' => 'Order Status Exclusions', 'key' => 'SNAP_ORDER_STATUS_EXCLUSIONS', 'value' => '', 'description' => 'Exclude orders with the following <em>Order Status</em> values from affiliate commissions. Specify the values as a packed (i.e. no spaces) comma-separated list.<br /><br />Default: <br /><br />', 'sort_order' => 26, 'use_function' => 'NULL', 'set_function' => 'NULL'), /*v2.1.0a*/
    array ( 'version' => '2.2.0', 'title' => 'Cookie Lifetime', 'key' => 'SNAP_COOKIE_LIFETIME', 'value' => 60*60*24*365, 'description' => 'Specify the lifetime, <strong>in seconds</strong>, of the cookie that is set when a customer enters your store via an affiliate\'s link.  As long as this cookie is set in the customer\'s browser cache, the affiliate (if approved and not banned) will receive a commission for any purchase made by the customer.<br /><br />Default: <strong>' . 60*60*24*365 . ' (60*60*24*365, i.e. one year)</strong><br /><br />', 'sort_order' => 30, 'use_function' => 'NULL', 'set_function' => 'NULL'), /*v2.2.0a*/
    array ( 'version' => '2.3.0', 'title' => 'Purchases Per Cookie', 'key' => 'SNAP_AFFILIATE_COOKIE_PURCHASES', 'value' => 'All', 'description' => 'Choose the number of purchases that a customer can make on a single affiliate cookie, either <em>All</em> or <em>One</em>. If you choose <em>All</em>, then all purchases by the customer within the "Cookie Lifetime" will result in a commission to the associated affiliate. If you choose <em>One</em>, the customer\'s affiliate cookie is deleted upon completion of their first purchase within the affiliate cookie\'s lifetime.<br /><br />Default: <strong>All</strong>.', 'sort_order' => 35, 'use_function' => 'NULL', 'set_function' => 'zen_cfg_select_option(array(\'All\', \'One\'),'), /*v2.3.0a*/
    array ( 'version' => '2.4.0', 'title' => 'Combine Exclusions on Referrers Page?', 'key' => 'SNAP_AFFILIATE_COMBINE_EXCLUSIONS', 'value' => 'No', 'description' => 'If your store has a number of <em>Order Status Exclusions</em>, the display on a <em>Customers-&gt;Referrers</em> details page can get overloaded. To combine the "Order Total" and "Commission Total" values associated with <strong>all</strong> the <em>Order Status Exclusions</em> into a single column, choose <b>Yes</b>.  Choose <b>No</b> to display every "Order Status" value in a separate column.<br /><br />Default: <strong>No</strong>.', 'sort_order' => 36, 'use_function' => 'NULL', 'set_function' => 'zen_cfg_select_option(array(\'Yes\', \'No\'),'), /*v2.4.0a*/
    array ( 'version' => '3.0.0', 'title' => 'Enable PayPal&reg; Commission Payment Method?', 'key' => 'SNAP_ENABLE_PAYMENT_CHOICE_PAYPAL', 'value' => 'No', 'description' => 'Do you want to enable your referrers to choose to receive their commission payments via PayPal? If set to <em>Yes</em>, a referrer will be prompted to enter their PayPal account email address upon making that selection.<br /><br /><b>Note:</b> PayPal payments are <em>not</em> paid automatically.  You must sign into your PayPal account to send money to your referrers\' accounts.<br /><br />Default: <strong>No</strong>.', 'sort_order' => 50, 'use_function' => 'NULL', 'set_function' => 'zen_cfg_select_option(array(\'Yes\', \'No\'),'),
  );

  $installedVersion = (defined('SNAP_MODULE_VERSION')) ? SNAP_MODULE_VERSION : '0';  /*v2.1.2c*/
  
  foreach ($snap_config_items as $config_item) {
    if ($installedVersion < $config_item['version'] && !defined($config_item['key'])) {  /*v2.1.2c*/
      $sql = "INSERT INTO " . TABLE_CONFIGURATION . " 
        (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) 
        VALUES
        ('" . $config_item['title'] . "', '" . zen_db_input($config_item['key']) . "', '" . zen_db_input($config_item['value']) . "', '" . zen_db_input($config_item['description']) . "', $configuration_group_id, " . (int)$config_item['sort_order'] . ", NOW(), NOW(), " . (($config_item['use_function'] == 'NULL') ? 'NULL' : ("'" . zen_db_input($config_item['use_function']) . "'")) . ', ' . (($config_item['set_function'] == 'NULL') ? 'NULL' : ("'" . zen_db_input($config_item['set_function']) . "'")) . ');';
      $db->Execute($sql);
    }
  }

  $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '$currentVersion', configuration_description = '$currentDescription', last_modified=now() WHERE configuration_group_id = $configuration_group_id AND configuration_key = 'SNAP_MODULE_VERSION';");  /*v2.5.0c*/

  unset($snap_config_items);
}

//----
// If the installation supports admin-page registration (i.e. v1.5.0 and later), then
// register the Affiliate Program configuration and the Referrers tool into the admin menu structure.
//
if (function_exists('zen_register_admin_page')) {
  if (!zen_page_key_exists('configurationAffiliates')) {
    zen_register_admin_page('configurationAffiliates', 'BOX_CONFIGURATION_AFFILIATES', 'FILENAME_CONFIGURATION', "gID=$configuration_group_id", 'configuration', 'Y', $configuration_group_id);
  }
  
  if (!zen_page_key_exists('customersReferrers')) {
    zen_register_admin_page('customersReferrers', 'BOX_CUSTOMERS_REFERRERS', 'FILENAME_REFERRERS', '', 'customers', 'Y', 20);
  }    
}
