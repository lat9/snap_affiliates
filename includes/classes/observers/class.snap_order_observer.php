<?php
// +---------------------------------------------------------------------------+
// |Snap Affiliates for Zen Cart                                               |
// +---------------------------------------------------------------------------+
// | Copyright (c) 2013-2015, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// +---------------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.            |
// +---------------------------------------------------------------------------+

if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== false) {
  die('Illegal Access');
}

class snap_order_observer extends base {
  var $payment_types;

  function __construct () {
    $this->attach($this, array('NOTIFY_ORDER_DURING_CREATE_ADDED_ORDER_HEADER'));
    
  }
  
  function update(&$class, $eventID, $paramsArray) {
    global $db;
    $commission = 0;
    
    if (isset($_SESSION['referrer_key']) && zen_not_null($_SESSION['referrer_key'])) {
      $sql = "SELECT referrer_customers_id, referrer_approved, referrer_banned, referrer_commission 
                FROM " . TABLE_REFERRERS . " 
               WHERE referrer_key = '" . $_SESSION['referrer_key'] . "'";
      $query = $db->Execute($sql);

      if (!$query->EOF) {
        $commission = floatval($query->fields['referrer_commission']);
        if ($commission < 0) {
          $commission = 0;
        }
      }
    } 
    
    /*----
    ** Allow the commission so long as the referrer has been approved, is not banned and (unless specified by the store's
    ** Configuration->Affiliate Program options) is not the customer associated with the referral!
    */
    if ($commission > 0 && 
        $query->fields['referrer_approved'] != 0 && 
        $query->fields['referrer_banned'] == 0 && 
        (SNAP_AFFILIATE_KEY_USE === 'true' || $query->fields['referrer_customers_id'] != $_SESSION['customer_id'])) {  /*v2.1.0c*/
      $sql_data_array = array('commission_orders_id' => $paramsArray['orders_id'],
                              'commission_referrer_key' => $_SESSION['referrer_key'],
                              'commission_rate' => $commission,
                              'commission_paid' => 0);

      zen_db_perform(TABLE_COMMISSION, $sql_data_array);
      
//-bof-a-v2.3.0      
      // -----
      // If an affiliate's referral_key cookie is to be deleted on a customer's first purchase ... delete the cookie.
      //
      if (SNAP_AFFILIATE_COOKIE_PURCHASES == 'One') {
        setcookie('referrer_key', '', time() - 3600, '/');
      }
//-eof-a-v2.3.0

    }
  }

  function get_snap_payment_types () {
    $this->payment_types = array ( 'CM' => array ( 'text' => SNAP_PAYMENT_TYPE_CHECK_MONEYORDER, 'text_details' => '' ) );
    if (SNAP_ENABLE_PAYMENT_CHOICE_PAYPAL == 'Yes') {
      $this->payment_types['PP'] = array ( 'text' => SNAP_PAYMENT_TYPE_PAYPAL, 'text_details' => SNAP_PAYMENT_TYPE_DETAILS_PAYPAL );
      
    }
    $this->notify ('SNAP_GET_PAYMENT_TYPE_DESCRIPTION');
    return $this->payment_types;
    
  }
}