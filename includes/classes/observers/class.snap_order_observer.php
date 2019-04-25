<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
//
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== false) {
    die('Illegal Access');
}

class snap_order_observer extends base 
{
    var $payment_types;

    public function __construct() 
    {
        if (defined('SNAP_ENABLED') && SNAP_ENABLED == 'true' && defined('TABLE_REFERRERS') && $GLOBALS['sniffer']->table_exists(TABLE_REFERRERS)) {
            $this->attach(
                $this, 
                array(
                    'NOTIFY_ORDER_DURING_CREATE_ADDED_ORDER_HEADER'
                )
            );
        }
    }
  
    public function update(&$class, $eventID, $paramsArray) 
    {
        global $db;
        $commission = 0;
    
        if (isset($_SESSION['referrer_key']) && !empty($_SESSION['referrer_key'])) {
            $query = $db->Execute(
                "SELECT referrer_customers_id, referrer_approved, referrer_banned, referrer_commission 
                   FROM " . TABLE_REFERRERS . " 
                  WHERE referrer_key = '" . $_SESSION['referrer_key'] . "'
                  LIMIT 1"
            );

            if (!$query->EOF) {
                $commission = $query->fields['referrer_commission'];
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
            (SNAP_AFFILIATE_KEY_USE === 'true' || $query->fields['referrer_customers_id'] != $_SESSION['customer_id'])) {
            $sql_data_array = array(
                'commission_orders_id' => $paramsArray['orders_id'],
                'commission_referrer_key' => $_SESSION['referrer_key'],
                'commission_rate' => $commission,
                'commission_paid' => '0001-01-01 00:00:00',
                'commission_paid_amount' => 0.0
            );
            zen_db_perform(TABLE_COMMISSION, $sql_data_array);
              
            // -----
            // If an affiliate's referral_key cookie is to be deleted on a customer's first purchase ... delete the cookie.
            //
            if (defined('SNAP_AFFILIATE_COOKIE_PURCHASES') && SNAP_AFFILIATE_COOKIE_PURCHASES == 'One') {
                setcookie('referrer_key', '', time() - 3600, '/');
            }
        }
    }

    public function get_snap_payment_types() 
    {
        $this->payment_types = array(
            'CM' => array( 
                'text' => SNAP_PAYMENT_TYPE_CHECK_MONEYORDER, 
                'text_details' => '' 
             ) 
         );
        if (SNAP_ENABLE_PAYMENT_CHOICE_PAYPAL == 'Yes') {
            $this->payment_types['PP'] = array( 
                'text' => SNAP_PAYMENT_TYPE_PAYPAL, 
                'text_details' => SNAP_PAYMENT_TYPE_DETAILS_PAYPAL 
            );
        }
        $this->notify('SNAP_GET_PAYMENT_TYPE_DESCRIPTION');
        return $this->payment_types;
    }
}
