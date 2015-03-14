<?php
// +---------------------------------------------------------------------------+
// |Snap Affiliates for Zen Cart                                               |
// +---------------------------------------------------------------------------+
// | Copyright (c) 2013-2015, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// |                                                                           |
// | Original: Copyright (c) 2009 Michael Burke                                |
// | http://www.filterswept.com                                                |
// |                                                                           |
// | This source file is subject to version 2.0 of the GPL license.            |
// +---------------------------------------------------------------------------+
//-bof-c-v2.4.0
require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));

$breadcrumb->add(NAVBAR_TITLE);

if (!isset($_SESSION['customer_id'])) {
  zen_redirect(zen_href_link(FILENAME_REFERRER_SIGNUP, '', 'SSL'));
  
} else {
  $query = "SELECT * FROM ". TABLE_REFERRERS ." WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'];
  $referrer = $db->Execute($query);

  if ($referrer->EOF ) {
    zen_redirect(zen_href_link(FILENAME_REFERRER_SIGNUP, '', 'SSL'));
    
  } else {
    $approved = (bool)$referrer->fields['referrer_approved'];
    $banned = (bool)$referrer->fields['referrer_banned'];
    $payment_types = $snap_order_observer->get_snap_payment_types ();
    if ( !( $approved && !$banned ) ) {
      zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
      
    } elseif (isset($_POST['action']) && $_POST['action'] == 'update') {
      if (!(isset($_POST['url']) && strlen($_POST['url']) != 0)) {
        $messageStack->add('referrer_edit', ERROR_NO_HOMEPAGE);
        
      } else {
        if ($payment_types[$_POST['payment_method']]['text_details'] != '' && !zen_not_null (zen_db_prepare_input ($_POST['payment_method_details']))) {
          $messageStack->add ('referrer_edit', sprintf (ERROR_PAYMENT_DETAILS_MISSING, $payment_types[$_POST['payment_method']]['text_details']), 'error');
          
        } else {
          $url = zen_db_input($_POST['url']);
          $payment_method = $_POST['payment_method'];
          $payment_method_details = ($payment_types[$payment_method]['text_details']) == '' ? '' : zen_db_prepare_input ($_POST['payment_method_details']);
          $db->Execute( 'UPDATE ' . TABLE_REFERRERS . " SET referrer_homepage = '$url', referrer_payment_type = '$payment_method', referrer_payment_type_detail = '$payment_method_details' WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'] );
          $messageStack->add_session('referrer_main', SUCCESS_HOMEPAGE_UPDATED, 'success');
          zen_redirect(zen_href_link(FILENAME_REFERRER_MAIN), '', 'SSL');
          
        }        
      }
    }
    $payment_method_selections = array ();
    foreach ($payment_types as $type_code => $type_details) {
      $payment_method_selections[] = array ( 'id' => $type_code, 'text' => $type_details['text'] );
      
    }
    $selected_payment_method = (isset ($_POST['payment_method'])) ? $_POST['payment_method'] : $referrer->fields['referrer_payment_type'];
    $payment_method_details = (isset ($_POST['payment_method_details'])) ? $_POST['payment_method_details'] : $referrer->fields['referrer_payment_type_detail'];

  }
}
//-eof-c-v2.4.0