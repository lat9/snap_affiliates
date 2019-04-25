<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
$zco_notifier->notify('NOTIFY_START_REFERRER_MAIN');

require DIR_WS_FUNCTIONS . 'snap_functions.php';
require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

// -----
// If the customer is not logged in, redirect them to the referrer sign-up page.
//
if (!snap_is_logged_in()) {
    zen_redirect(zen_href_link(FILENAME_REFERRER_SIGNUP, '', "SSL"));
}

// -----
// If the logged-in customer is not yet an affiliate, redirect them to the
// referrer sign-up page.
//
$referrer = $db->Execute(
    "SELECT *
       FROM " . TABLE_REFERRERS . "
      WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'] . "
      LIMIT 1"
);
if ($referrer->EOF ) {
    zen_redirect(zen_href_link(FILENAME_REFERRER_SIGNUP, '', "SSL"));
}

// -----
// The customer has signed up to be an affiliate.  If they've been approved
// and not banned, continue to see if they've been paid any commissions.
//
$approved = (bool)$referrer->fields['referrer_approved'];
$banned = (bool)$referrer->fields['referrer_banned'];
if ($approved && !$banned) {
    // -----
    // Determine the starting and ending dates to be used to find any commissions associated with
    // the affiliate's account.
    //
    $today = getdate();
    $start_mon = (isset($_GET['start_mon'])) ? (int)$_GET['start_mon'] : $today['mon'];
    $start_year = (isset($_GET['start_year'])) ? (int)$_GET['start_year'] : $today['year'];
    if ($start_mon < 1 || $start_mon > 12 || $start_year < 2000 || $start_year > $today['year'] || ($start_year == $today['year'] && $start_mon > $today['mon'])) {
        $start_mon = $today['mon'];
        $start_year = $today['year'];
    }
    
    $end_mon = (isset($_GET['end_mon'])) ? (int)$_GET['end_mon'] : $today['mon'];
    $end_year = (isset($_GET['end_year'])) ? (int)$_GET['end_year'] : $today['year'];
    if ($end_mon < 1 || $end_mon > 12 || $end_year < 2000 || $end_year > $today['year'] || ($end_year == $today['year'] && $end_mon > $today['mon'])) {
        $end_mon = $today['mon'];
        $end_year = $today['year'];
    }

    $activity_begin = new DateTime("$start_year-$start_mon-1");
    $begin_timestamp = $activity_begin->getTimestamp();
    $activity_end = new DateTime("$end_year-$end_mon-1");
    if ($begin_timestamp > $activity_end->getTimestamp()) {
        $activity_end = $activity_begin;
    }
    $activity_end->setTime(23, 59, 59);
    $activity_end->modify('last day of');
    $end_timestamp = $activity_end->getTimestamp();

    // -----
    // Determine if any order-total exclusions have been configured.  If so,
    // the totals associated with those order-total classes will be excluded
    // from any affiliate pay-out.
    //
    if (!defined('SNAP_ORDER_TOTALS_EXCLUSIONS')) define('SNAP_ORDER_TOTALS_EXCLUSIONS', 'ot_tax,ot_shipping');
    $totals_exclude_clause = '';
    if (SNAP_ORDER_TOTALS_EXCLUSIONS != '') {
        $totals_exclude_array = explode(',', str_replace(' ', '', SNAP_ORDER_TOTALS_EXCLUSIONS));
        $totals_exclude_clause = " AND t.class IN ('" . implode("', '", $totals_exclude_array) . "')";
    }

    // -----
    // Determine if any order-status exclusions have been configured.  If so,
    // any order in one of those statuses will be excluded from the affiliate's payout.
    //
    if (!defined('SNAP_ORDER_STATUS_EXCLUSIONS')) define('SNAP_ORDER_STATUS_EXCLUSIONS', '');
    $status_exclude_clause = '';
    if (SNAP_ORDER_STATUS_EXCLUSIONS != '') {
        $status_exclude_array = explode(',', str_replace(' ', '', SNAP_ORDER_STATUS_EXCLUSIONS));
        for ($i = 0, $n = count($status_exclude_array); $i < $n; $i++) {
            $status_exclude_array[$i] = (int)$status_exclude_array[$i];
        }
        $status_exclude_clause = " AND o.orders_status NOT IN (" . implode(',', $status_exclude_array) . ")";
    }

    // -----
    // Initialize counts and totals.
    //
    $payment_types = $snap_order_observer->get_snap_payment_types();
    
    $total_total = 0;
    $total_commission = 0;
    
    $unpaid_total = 0;
    $unpaid_commission = 0;
    
    $yearly_total = 0;
    $yearly_commission = 0;
    $this_year = date('Y');
    
    $last_payout_timestamp = 0;
    
    $activity_total = 0;
    $activity_commission = 0;
    
    // -----
    // Gather the orders associated with the customer's referrer-key, weeding out those with excluded status-codes,
    // and loop to create the customer's yearly totals and activity during the requested period.
    //
    $activity = array();
    $orders = $db->Execute(
        "SELECT o.orders_id, o.date_purchased, o.order_total, c.commission_paid, c.commission_rate, o.orders_status, c.commission_paid_amount, c.commission_payment_type, c.commission_payment_type_detail
           FROM " . TABLE_ORDERS . " o
                INNER JOIN " . TABLE_COMMISSION . " c
                    ON c.commission_orders_id = o.orders_id
          WHERE c.commission_referrer_key = '" . $referrer->fields['referrer_key'] . "'
            $status_exclude_clause
          ORDER BY o.orders_id ASC"
    );
    while (!$orders->EOF) {
        // -----
        // Determine if the current order has any excluded totals, subtracting the
        // excluded value(s) from the order's total value.
        //
        $current_exclusion = 0;
        if ($totals_exclude_clause != '') {
            $totals = $db->Execute(
                "SELECT SUM(t.value) AS exclusion
                   FROM " . TABLE_ORDERS . " o
                        INNER JOIN " . TABLE_ORDERS_TOTAL . " t
                            ON t.orders_id = o.orders_id
                  WHERE o.orders_id = " . $orders->fields['orders_id'] . 
                  $totals_exclude_clause                  
            );
            $current_exclusion = $totals->fields['exclusion'];
        }
        $current_amount = $orders->fields['order_total'] - $current_exclusion;
        if ($current_amount < 0) {
            $current_amount = 0;
        }

        // -----
        // Update the customer's overall commission total/payout, based on the current
        // order.
        //
        $commission = $orders->fields['commission_rate'];
        $commission_calculated = $commission * $current_amount;
        $commission_paid = $orders->fields['commission_paid_amount'];
        
        $total_total += $current_amount;
        $total_commission += $commission_paid;

        // -----
        // Determine whether the order's commission has been paid, converting that
        // date into a timestamp for further use if so or setting that timestamp
        // to 0 to indicate that it's unpaid.
        //
        if ($orders->fields['commission_paid'] == '0001-01-01 00:00:00') {
            $current_timestamp = 0;
        } else {
            $current_date = new DateTime($orders->fields['commission_paid']);
            $current_timestamp = $current_date->getTimestamp();
        }
        if ($current_timestamp > $last_payout_timestamp) {
            $last_payout_timestamp = $current_timestamp;
        }
        
        // -----
        // If the order's commission is unpaid, update the customer's unpaid order total
        // and calculated commission.
        //
        if ($current_timestamp === 0) {
            $unpaid_total += $current_amount;
            $unpaid_commission += $commission_calculated;
        } else {
            // -----
            // If the order's purchase date was this year, update the customer's
            // yearly total and commission paid.
            //
            $purchase_date = new DateTime($orders->fields['date_purchased']);
            if ($purchase_date->format('Y') == $this_year) {
                $yearly_total += $current_amount;
                $yearly_commission += $commission_paid;
            }

            // -----
            // If the order's commission was paid during the requested time period, we'll add
            // the order's details to the activity to be displayed.
            //
            if ($begin_timestamp <= $current_timestamp && $current_timestamp <= $end_timestamp) {
                $activity_total += $current_amount;
                $activity_commission += $commission_paid;
                $commission_payment_type = (isset($payment_types[$orders->fields['commission_payment_type']])) ? $payment_types[$orders->fields['commission_payment_type']]['text'] : TEXT_UNKNOWN;
                $commission_payment_type_detail = '';
                if ($commission_payment_type != TEXT_UNKNOWN && !empty($orders->fields['commission_payment_type_detail'])) {
                    $commission_payment_type_detail = ' (' . $orders->fields['commission_payment_type_detail'] . ')';
                  
                }
                $activity[] = array(
                    'amount' => $current_amount, 
                    'date' => $purchase_date->getTimestamp(), 
                    'paid' => $current_timestamp, 
                    'commission' => $commission, 
                    'commission_calculated' => $commission_calculated, 
                    'commission_paid' => $commission_paid, 
                    'payment_type' => $commission_payment_type, 
                    'payment_type_detail' => $commission_payment_type_detail
                );
            }
        }
        $orders->MoveNext();
    }
}

$breadcrumb->add(NAVBAR_TITLE);
$zco_notifier->notify('NOTIFY_END_REFERRER_MAIN');
