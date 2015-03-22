<?php
// +---------------------------------------------------------------------------+
// | Snap Affiliates for Zen Cart                                              |
// +---------------------------------------------------------------------------+
// | Copyright (c) 2013-2015, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// |                                                                           |
// | Original: Copyright (c) 2009 Michael Burke                                |
// | http://www.filterswept.com                                                |
// |                                                                           |
// +---------------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.            |
// +---------------------------------------------------------------------------+

require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));

// include template specific file name defines
$define_terms = zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] . '/html_includes/', FILENAME_DEFINE_REFERRAL_TERMS, 'false');
$breadcrumb->add(NAVBAR_TITLE);

function send_notification_email() {
  global $db;

  if (defined('SNAP_ADMIN_EMAIL') && zen_validate_email(SNAP_ADMIN_EMAIL)) { 
    $query = "SELECT customers_firstname, customers_lastname FROM ". TABLE_CUSTOMERS ." WHERE customers_id = " . (int)$_SESSION['customer_id'];
    $customer = $db->Execute($query);

    if (!$customer->EOF) {
      $html_msg = array();
      $subject = EMAIL_SUBJECT;
      $html_msg['EMAIL_SUBJECT'] = $subject;

      $email_text = sprintf(EMAIL_BODY, $customer->fields['customers_firstname'], $customer->fields['customers_lastname'], (int)$_SESSION['customer_id']);
      $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_text);
      zen_mail('', SNAP_ADMIN_EMAIL, $subject, $email_text, STORE_NAME, EMAIL_FROM, $html_msg);

    }
  }
}

$referrer = null;
$is_logged_in = isset($_SESSION['customer_id']);
$show_terms = isset($_GET['terms']);
$error = '';

if ($is_logged_in) {
  if (isset($_POST['action']) && $_POST['action'] == 'signup') {
    $url = $_POST['url'];

    if ($url == '') {
      $error = ERROR_NO_URL;
      
    } else {
      $url = zen_db_input (zen_db_prepare_input($url));  //-v3.0.1c
      $tag = SNAP_KEY_PREFIX . $_SESSION['customer_id'] . time();
      $commission = SNAP_DEFAULT_COMMISSION;

      $query = "INSERT INTO " . TABLE_REFERRERS . " 
                  (referrer_customers_id, referrer_key, referrer_homepage, referrer_approved, referrer_banned, referrer_commission) 
                  VALUES (" . (int)$_SESSION['customer_id'] . ", '$tag', '$url', 0, 0, $commission)";
     

      $result = $db->Execute($query);

      send_notification_email();
      
      $_SESSION['navigation']->remove_current_page();
      zen_redirect (zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'));
      
    }
    
  } else {
    $query = "SELECT * FROM " . TABLE_REFERRERS . " WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'];
    $referrer = $db->Execute($query);

    if (!$referrer->EOF && !$show_terms ) {
      zen_redirect( zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL') );
      
    }
  }
} 
