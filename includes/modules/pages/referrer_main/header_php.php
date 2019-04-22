<?php
// +---------------------------------------------------------------------------+
// |Snap Affiliates for Zen Cart                                               |
// +---------------------------------------------------------------------------+
// | Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// |                                                                           |
// | Original: Copyright (c) 2009 Michael Burke                                |
// | http://www.filterswept.com                                                |
// |                                                                           |
// | This source file is subject to version 2.0 of the GPL license.            |
// +---------------------------------------------------------------------------+

require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));

$breadcrumb->add(NAVBAR_TITLE);

$today = getdate();

$referrer = null;
$submitted = false;
$approved = false;
$banned = false;
$is_referrer = false;
$is_logged_in = isset ($_SESSION['customer_id']);

$total_total = 0;
$total_commission = 0;
$unpaid_total = 0;
$unpaid_commission = 0;
$yearly_total = 0;
$yearly_commission = 0;
$last_payout = 0;
$next_payout = 0;

$activity_begin = mktime(0, 0, 0, $today['mon'], 1, $today['year']);
$activity_end = mktime(23, 59, 59, $activity_begin['mon'] + 1, 0, $today['year']);
$activity_total = 0;
$activity_commission = 0;
$activity = array();

if (isset($_GET['start'])) {
  $activity_begin = intval ($_GET['start']);
}

if( $activity_begin > time() ) {
  $activity_begin = time();
}

if (isset ($_GET['end'])) {
  $activity_end = intval( $_GET['end'] );
}

if ($activity_begin > $activity_end) {
  $tempDate = getdate ($activity_begin);

  $activity_end = mktime (23, 59, 59, $tempDate['mon']+1, 0, $tempDate['year']);
}

if (!$is_logged_in) {
  zen_redirect(zen_href_link(FILENAME_REFERRER_SIGNUP, '', "SSL"));
  
} else {
  $query = "SELECT * FROM ". TABLE_REFERRERS ." WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'];
  $referrer = $db->Execute($query);

  if (!is_object($referrer) || $referrer->EOF ) {
    zen_redirect(zen_href_link(FILENAME_REFERRER_SIGNUP, '', "SSL"));
    
  } else {
    $submitted = true;
    $approved = (bool)$referrer->fields['referrer_approved'];
    $banned = (bool)$referrer->fields['referrer_banned'];

    if ($approved) {
      if (!defined('SNAP_ORDER_STATUS_EXCLUSIONS')) define('SNAP_ORDER_STATUS_EXCLUSIONS', ''); /*v2.1.0a*/     
      if (!defined('SNAP_ORDER_TOTALS_EXCLUSIONS')) define('SNAP_ORDER_TOTALS_EXCLUSIONS', 'ot_tax,ot_shipping');

//-bof-v2.1.0c (changed $exclude_array to $totals_exclude_array
      $totals_exclude_array = explode(',', SNAP_ORDER_TOTALS_EXCLUSIONS);
      
      for ($i = 0, $totals_exclude_clause = '', $n = count($totals_exclude_array); $i < $n; $i++) {
        if ($i != 0) {
          $totals_exclude_clause .= ' OR ';
        }
        $totals_exclude_clause .= ("t.class = '" . $totals_exclude_array[$i] . "'");
      }
      if ($totals_exclude_clause != '') {
        $totals_exclude_clause = " AND ( $totals_exclude_clause ) ";
      }
//-eof-v2.1.0c

      $year_start = mktime(0,0,0, 1, 1, $today['year']);

//-bof-v2.1.0a: Don't show commission for orders status values in the "exclude list".
      $status_exclude_array = explode(',', SNAP_ORDER_STATUS_EXCLUSIONS);
//-eof-v2.1.0a

      $no_status_exclusions = (sizeof($status_exclude_array) == 1 && $status_exclude_array[0] == '') ? true : false;  /*v2.5.1a*/
      
      $query = "SELECT o.orders_id, o.date_purchased, o.order_total, c.commission_paid, c.commission_rate, o.orders_status, c.commission_paid_amount, c.commission_payment_type, c.commission_payment_type_detail
	                FROM ". TABLE_ORDERS ." o, " . TABLE_COMMISSION . " c 
                  WHERE c.commission_referrer_key = '" . $referrer->fields['referrer_key'] . "' 
                    AND o.orders_id = c.commission_orders_id"; /*v2.1.0c*/

      $orders = $db->Execute($query);      
      $payment_types = $snap_order_observer->get_snap_payment_types ();
      while (!$orders->EOF) {
        $commission = floatval($orders->fields['commission_rate']);
        $purchase_date = strtotime($orders->fields['date_purchased']);
        $current_date = $orders->fields['commission_paid'];
        
        $query = "SELECT t.value 
                    FROM " . TABLE_ORDERS ." o, ". TABLE_ORDERS_TOTAL ." t
                    WHERE o.orders_id = " . $orders->fields['orders_id'] . " 
                      AND o.orders_id = t.orders_id" . $totals_exclude_clause; /*v2.1.0c*/
        $totals = $db->Execute($query);
        $current_exclusion = 0;
        while (!$totals->EOF) {
          $current_exclusion += floatval($totals->fields['value']);
          
          $totals->MoveNext();
        }

        $current_amount = floatval($orders->fields['order_total']) - $current_exclusion;

        if ($current_amount < 0) {
          $current_amount = 0;
        }

        if ($current_date != "0001-01-01 00:00:00") {
          $current_date = strtotime($current_date);
        } else {
          $current_date = 0;
        }
        
        $commission_calculated = $commission * $current_amount;
        $commission_paid = $orders->fields['commission_paid_amount'];
        if ( $no_status_exclusions || !($current_date == 0 && in_array($orders->fields['orders_status'], $status_exclude_array)) ) {  /*v2.5.0a,v2.5.1c*/
          $total_total += $current_amount;
          $total_commission += $commission_paid;

          if( $purchase_date > $year_start ) {
            $yearly_total += $current_amount;
            $yearly_commission += $commission_paid;
          }
          
          if ($current_date === 0) {
            $unpaid_total += $current_amount;
            $unpaid_commission += $commission_calculated;
          }
          
          if ($current_date > $last_payout) {
            $last_payout = $current_date;
          }

          if ($activity_begin < $current_date && $current_date < $activity_end) {  /*v2.5.0c*/
            $activity_total += $current_amount;
            $activity_commission += $commission_paid;
            $commission_payment_type = (isset ($payment_types[$orders->fields['commission_payment_type']])) ? $payment_types[$orders->fields['commission_payment_type']]['text'] : TEXT_UNKNOWN;
            $commission_payment_type_detail = '';
            if ($commission_payment_type != TEXT_UNKNOWN && zen_not_null ($orders->fields['commission_payment_type_detail'])) {
              $commission_payment_type_detail = ' (' . $orders->fields['commission_payment_type_detail'] . ')';
              
            }
            array_push( $activity, array('amount' => $current_amount, 'date' => $purchase_date, 'paid' => $current_date, 'commission' => $commission, 'commission_calculated' => $commission_calculated, 'commission_paid' => $commission_paid, 'payment_type' => $commission_payment_type, 'payment_type_detail' => $commission_payment_type_detail) );
          }
          
        }  /*v2.5.0a*/

        $orders->MoveNext();
      }
    }
  }
}
$flag_disable_right = $flag_disable_left = true;