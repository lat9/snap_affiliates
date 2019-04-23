<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
require 'includes/application_top.php';
require DIR_FS_CATALOG . 'includes/functions/snap_functions.php';

if (!defined('SNAP_MAX_REFERRER_DISPLAY')) define('SNAP_MAX_REFERRER_DISPLAY', 50);

require DIR_WS_CLASSES . 'currencies.php';
$currencies = new currencies();

$results = $db->Execute(
    "SELECT COUNT(*) AS count 
       FROM " . TABLE_REFERRERS
);
$referrercount = ($results->EOF) ? 0 : $results->fields['count'];

$selectedID = (int)(isset($_POST['referrer'])) ? $_POST['referrer'] : ((isset($_GET['referrer'])) ? $_GET['referrer'] : 0);
$mode = (isset($_POST['mode'])) ? $_POST['mode'] : ((isset($_GET['mode'])) ? $_GET['mode'] : '');

$snap_page = (int)(isset($_GET['page'])) ? $_GET['page'] : 1;
if ($snap_page < 1) {
    $snap_page = 1;
}

// -----
// Create the array of available commission-payment types.
//
$payment_types = array( 
    'CM' => array( 
        'text' => PAYMENT_TYPE_CHECK_MONEYORDER, 
        'text_details' => '', 
        'payment_details' => '' 
    ) 
);
if (SNAP_ENABLE_PAYMENT_CHOICE_PAYPAL == 'Yes') {
    $payment_types['PP'] = array( 
        'text' => PAYMENT_TYPE_PAYPAL, 
        'text_details' => PAYMENT_TYPE_PAYPAL_DETAILS, 
        'payment_details' => '' 
    );
}
$zco_notifier->notify ('SNAP_GET_PAYMENT_TYPE_DESCRIPTION');

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

//-bof-v2.1.0a: Build Orders Status id/names array for which no commission is given.
//
// Two arrays are built: (1) holds the name 'suffix' for the order-status value and
// (2) holds the default 'status_breakdown' array for each affiliate found.
//
// Since an order-status value can NEVER be 0 (since it's a SQL table index), the 
// array index value of 0 has special meaning: it's associated with *all* the non-
// excluded order-status values!
//
if (!defined('SNAP_ORDER_STATUS_EXCLUSIONS')) define('SNAP_ORDER_STATUS_EXCLUSIONS', '');
if (!defined('SNAP_AFFILIATE_COMBINE_EXCLUSIONS')) define('SNAP_AFFILIATE_COMBINE_EXCLUSIONS', 'No');

$orders_by_status = array();
$orders_status_names = array();
$order_status_exclusions = array();
if (SNAP_ORDER_STATUS_EXCLUSIONS != '') {
    $order_status_exclusions = explode(',', str_replace(' ', '', SNAP_ORDER_STATUS_EXCLUSIONS));  
    for ($i = 0, $n = count($order_status_exclusions); $i < $n; $i++) {
        $current_order_status = (int)$order_status_exclusions[$i];
        if ($current_order_status != 0 && !isset($orders_status_names[$current_order_status])) {
            $result = $db->Execute(
                "SELECT orders_status_name 
                   FROM " . TABLE_ORDERS_STATUS . " 
                  WHERE language_id = " . (int)$_SESSION['languages_id'] . " 
                    AND orders_status_id = $current_order_status
                  LIMIT 1"
            );
            if (!$result->EOF) {
                $orders_by_status[$current_order_status] = array( 
                    'total' => 0, 
                    'commission_total' => 0, 
                    'unpaid_commission' => 0 
                );
                $orders_status_names[$current_order_status] = ' (' . $result->fields['orders_status_name'] . ')';
            }
        }
    }
    if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes') {
        $orders_by_status['*'] = array(
            'total' => 0, 
            'commission_total' => 0, 
            'unpaid_commission' => 0 
        );
        $orders_status_names['*'] = TEXT_NONCOMMISSIONABLE;
    }
}
$orders_by_status[0] = array( 
    'total' => 0, 
    'commission_total' => 0, 
    'unpaid_commission' => 0 
);
$orders_status_names[0] = '';

// -----
// If order-total exclusions are configured, prepare a quoted list to
// use in the order-related query in the referrers' loop that follows.
//
if (!defined('SNAP_ORDER_TOTALS_EXCLUSIONS')) define('SNAP_ORDER_TOTALS_EXCLUSIONS', 'ot_tax,ot_shipping');
$totals_exclude_clause = '';
if (SNAP_ORDER_TOTALS_EXCLUSIONS != '') {
    $exclude_array = explode(',', str_replace(' ', '', SNAP_ORDER_TOTALS_EXCLUSIONS));
    $totals_exclude_clause = " AND t.class IN ('" . implode("', '", $exclude_array) . "')";
}
  
// -----
// Gather the (possibly paged) list of current affiliates.
//
$where_clause = ($mode == '') ? '' : " WHERE r.referrer_customers_id = $selectedID";
$query = 
    "SELECT c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address, c.customers_telephone, r.*
       FROM " . TABLE_CUSTOMERS . " c
            INNER JOIN " . TABLE_REFERRERS . " r 
                ON r.referrer_customers_id = c.customers_id
       $where_clause
       ORDER BY c.customers_lastname, c.customers_firstname, c.customers_id";
if ($mode == '') {
    $referrer_split = new splitPageResults($snap_page, SNAP_MAX_REFERRER_DISPLAY, $query, $referrer_query_numrows);
}          
$results = $db->Execute($query);
$referrers = array();

// -----
// Loop through each of the current referrers ...
//
$idx = 0;
$selected = 0;
$pay_message = '';
while (!$results->EOF) {
    $referrers[] = array_merge($results->fields, array('status_breakdown' => $orders_by_status));
    if ($selectedID == $referrers[$idx]['customers_id']) {
        $selected = $idx;
    }
    
    $orderResults = $db->Execute(
        "SELECT o.orders_id, o.date_purchased, o.order_total, o.orders_status, c.*
           FROM " . TABLE_ORDERS . " o
                INNER JOIN " . TABLE_COMMISSION . " c
                    ON c.commission_orders_id = o.orders_id
          WHERE c.commission_referrer_key = '" . $referrers[$idx]['referrer_key'] . "'
          ORDER BY o.orders_id ASC"
    );
    $orders = array();

    // -----
    // ... processing each of the referrer's orders' information.
    //
    while (!$orderResults->EOF) {
        $isCommissionPaid = ($orderResults->fields['commission_paid'] != '0001-01-01 00:00:00');
        $orderResults->fields['ispaid'] = $isCommissionPaid;
        $ordersTotal = $ordersCommission = $ordersUnpaid = 0;
        $orders_status = $orderResults->fields['orders_status'];
 
        // -----
        // Determine the order-total exclusions associated with the order.
        //
        $current_exclusion = 0;
        if ($totals_exclude_clause != '') {
            $totalResults = $db->Execute(
                "SELECT SUM(t.value) AS totals_exclusion
                   FROM " . TABLE_ORDERS ." o
                        INNER JOIN " . TABLE_ORDERS_TOTAL ." t
                            ON t.orders_id = o.orders_id$totals_exclude_clause
                  WHERE o.orders_id = {$orderResults->fields['orders_id']}"
            );
            $current_exclusion = $totalResults->fields['totals_exclusion'];
        }
    
        $orderResults->fields['value'] = $current_exclusion;

        $total = $orderResults->fields['order_total'] - $current_exclusion;
        $commission = $orderResults->fields['commission_rate'];

        if ($total < 0) {
            $total = 0;
        }
        
        $purchase_date = new DateTime($orderResults->fields['date_purchased']);
        $purchase_date_timestamp = $purchase_date->getTimestamp();
        if ($begin_timestamp <= $purchase_date_timestamp && $purchase_date_timestamp <= $end_timestamp) {
            $ordersCommission = ($isCommissionPaid && $orderResults->fields['commission_paid_amount'] != 0) ? $orderResults->fields['commission_paid_amount'] : ($commission * $total);
            $ordersTotal = $total;

            if (!$isCommissionPaid) { 
                $ordersUnpaid = $commission * $total;
            }
            $orders[] = $orderResults->fields;
        }
    
//-bof-c-v2.1.0: Add the order-total, order-commission and unpaid-commission values into the element
// associated with this order's order-status value.
//
        $orders_status = (!$isCommissionPaid && in_array($orders_status, $order_status_exclusions)) ? $orders_status : 0;
        if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $orders_status !== 0) {
            $orders_status = '*';
        }
        $referrers[$idx]['status_breakdown'][$orders_status]['total'] += $ordersTotal;
        $referrers[$idx]['status_breakdown'][$orders_status]['commission_total'] += $ordersCommission;
        $referrers[$idx]['status_breakdown'][$orders_status]['unpaid_commission'] += $ordersUnpaid;

        $orderResults->MoveNext();
    }

    $referrers[$idx]['orders'] = $orders;
    $idx++;
    $results->MoveNext();
}

// -----
// Mode-specific processing ...
//
switch ($mode) {
    case TEXT_APPROVE:
        $approved = ($referrers[$selected]['referrer_approved'] == 1) ? 0 : 1;
        $db->Execute(
            "UPDATE " . TABLE_REFERRERS . " 
                SET referrer_approved = $approved
              WHERE referrer_customers_id = " . (int)$referrers[$selected]['customers_id'] . "
              LIMIT 1"
        );
        $referrers[$selected]['referrer_approved'] = $approved;

        snap_send_notification_email(
            $referrers[$selected], 
            EMAIL_SUBJECT_APPROVED,
            sprintf(EMAIL_MESSAGE_APPROVED_TEXT, zen_catalog_href_link(FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link(FILENAME_CONTACT_US, '', 'SSL')),
            sprintf(EMAIL_MESSAGE_APPROVED_HTML, zen_catalog_href_link(FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link(FILENAME_CONTACT_US, '', 'SSL'))
        );
        break;
    
    case TEXT_BAN:
    case TEXT_UNBAN:
        $banned = ($referrers[$selected]['referrer_banned'] == 1) ? 0 : 1;
        $result = $db->Execute(
            "UPDATE " . TABLE_REFERRERS . " 
                SET referrer_banned = $banned 
              WHERE referrer_customers_id = " . $referrers[$selected]['customers_id'] . "
              LIMIT 1"
        );
        $referrers[$selected]['referrer_banned'] = $banned;

        if ($banned == 1) {
            snap_send_notification_email(
                $referrers[$selected], 
                EMAIL_SUBJECT_BANNED, 
                EMAIL_MESSAGE_BANNED_TEXT, 
                EMAIL_MESSAGE_BANNED_HTML
            );
        }
        break;

    case TEXT_PAY_SELECTED:
        if (!isset($_POST['payList']) || !is_array($_POST['payList'])) {
            $pay_message = ERROR_CHOOSE_COMMISSION_TO_PAY;
            $mode = TEXT_PAY;
        } else {
            $commissions = array();
            foreach ($_POST['payList'] as $commission_id => $value) {
                if (!isset($_POST['commission'][$commission_id]) || (float)$_POST['commission'][$commission_id] <= 0) {
                    $pay_message = ERROR_COMMISSION_CANT_BE_ZERO;
                    $mode = TEXT_PAY;
                } else {
                    $commissions[$commission_id] = array( 
                        'calculated' => $_POST['calculated'][$commission_id], 
                        'paid' => $_POST['commission'][$commission_id] 
                    );
                }
            }
              
            if (isset($payment_types[$_POST['commission_payment_type']])) {
                $commission_payment_type = $_POST['commission_payment_type'];
                $commission_payment_type_name = $payment_types[$commission_payment_type]['text'];
                $commission_payment_type_detail = ($payment_types[$_POST['commission_payment_type']]['payment_details'] == '') ? $_POST['commission_payment_type_detail'] : $payment_types[$_POST['commission_payment_type']]['payment_details'];
            } else {
                $commission_payment_type = '??';
                $commission_payment_type_name = PAYMENT_TYPE_UNKNOWN;
                $commission_payment_type_detail = '';
            }
            $commission_payment_type_message = $commission_payment_type_name . (($commission_payment_type_detail == '') ? '' : (' (' . $commission_payment_type_detail . ')'));
          
            $now = date("Y-m-d H:i:s");
            $total_paid = 0;
            $commission_payment_type_detail_input = zen_sanitize_string($db->prepare_input($commission_payment_type_detail));
            foreach ($commissions as $commission_id => &$commission) {
                $commission_manual = ($commission['calculated'] == $commission['paid']) ? 0 : 1;
                $db->Execute(
                    "UPDATE " . TABLE_COMMISSION . " 
                        SET commission_paid = '$now', 
                            commission_paid_amount = " . zen_db_prepare_input($commission['paid']) . ", 
                            commission_manual = $commission_manual, 
                            commission_payment_type = '$commission_payment_type', 
                            commission_payment_type_detail = '$commission_payment_type_detail_input' 
                      WHERE commission_id = $commission_id 
                      LIMIT 1"
                );
                foreach ($referrers[$selected]['orders'] as $current_order) {
                    if ($current_order['commission_id'] == $commission_id) {
                        $total_paid += $commission['paid'];
                
                        // -----
                        // Add a comment to the order's status-history table (hidden from the customer) to identify that the commission payment was made.
                        // The order's current status is unchanged.
                        //
                        $status_comment = sprintf(TEXT_ORDERS_STATUS_PAID, $currencies->format($commission['paid']), $referrers[$selected]['customers_firstname'], $referrers[$selected]['customers_lastname'], $commission_payment_type_message);
                        $commission['osid'] = zen_update_orders_history($current_order['orders_id'], $status_comment);
                        break;
                    }
                }
                unset($commission);
            }

            if ($total_paid != 0) {
                $total_paid_formatted = $currencies->format($total_paid);
                //-bof-20150304-lat9-Enable alternate payment method handlers.  Check/money-order is the default.
                $snap_payment_email_handled = false;
                $zco_notifier->notify('SNAP_CHECK_ALTERNATE_PAYMENT', array('total_paid' => $total_paid, 'total_paid_formatted' => $total_paid_formatted, 'referrer' => $referrers[$selected]));
                if (!$snap_payment_email_handled) {
                    snap_send_notification_email (
                        $referrers[$selected], 
                        EMAIL_SUBJECT_PAID,
                        sprintf(EMAIL_MESSAGE_PAID_TEXT, $total_paid_formatted, zen_catalog_href_link (FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link (FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link (FILENAME_CONTACT_US, '', 'SSL')),
                        sprintf(EMAIL_MESSAGE_PAID_HTML, $total_paid_formatted, zen_catalog_href_link (FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link (FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link (FILENAME_CONTACT_US, '', 'SSL')));          
                    $messageStack->add_session(sprintf(SUCCESS_PAYMENT_MADE, $total_paid_formatted, $referrers[$selected]['customers_firstname'], $referrers[$selected]['customers_lastname']), 'success');
                }
                zen_redirect(zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . "&amp;mode=details&amp;page=$snap_page"));
            }
        }
        break;
    
    case TEXT_UPDATE:
        if (!is_numeric($_POST['commission']) || ((int)$_POST['commission'] < 0) || ((int)$_POST['commission'] > 100)) {
            $messageStack->add(ERROR_INVALID_PERCENTAGE, 'error');
        } else {
            $commission = $_POST['commission'] / 100;
            $db->Execute(
                "UPDATE ". TABLE_REFERRERS . " 
                    SET referrer_commission = $commission
                  WHERE referrer_customers_id = " . $referrers[$selected]['customers_id'] . "
                  LIMIT 1"
            );
            $referrers[$selected]['referrer_commission'] = $commission;
        }
        break;
    
    case TEXT_UPDATE_PAYMENT_TYPE:
        $error = false;
        if (isset($payment_types[$_POST['referrer_payment_type']]) && $payment_types[$_POST['referrer_payment_type']]['text_details'] != '') {
            $payment_details = zen_sanitize_string($db->prepare_input($_POST['referrer_payment_type_detail']));  //-v3.0.1c
            if (!zen_not_null($payment_details)) {
                $mode = 'details';
                $messageStack->add(sprintf(ERROR_PAYMENT_DETAILS_MISSING, $payment_types[$_POST['referrer_payment_type']]['text_details']), 'error');
                $error = true;
            }      
        } else {
            $payment_details = '';
        }
        if (!$error) {
            $db->Execute(
                "UPDATE " . TABLE_REFERRERS . " 
                    SET referrer_payment_type = '" . $_POST['referrer_payment_type'] . "', 
                        referrer_payment_type_detail = '$payment_details' 
                  WHERE referrer_customers_id = " . $referrers[$selected]['customers_id'] . " 
                  LIMIT 1"
            );
            zen_redirect(zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . "&amp;mode=details&amp;page=$snap_page"));
        }
        break;    
}  // END switch
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta charset="<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" href="includes/stylesheet.css">
<link rel="stylesheet" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script src="includes/menu.js"></script>
<script src="includes/general.js"></script>
<style>
.history { width: 100%; padding: 0.5em; }
.history td, .history th { text-align: center; }
.h-r { text-align: right; }
.historyFooter td { border-top: 1px solid grey; font-weight: bold; }
.noInput { padding: 2px 0; }
.center { text-align: center; }
.error { border: 1px dashed red; padding: 2px; color: red; font-weight: bold; }
</style>
<script>
  function init() {
      cssjsmenu('navbar');
      if (document.getElementById) {
          var kill = document.getElementById('hoverJS');
          kill.disabled = true;
      }
  }
<?php
$details = '';
foreach ($payment_types as $type_id => $type_info) {
    $details .= ", $type_id: '" . $type_info['text_details'] . "'";
}
?>
  var detailsInfo = {<?php echo substr($details, 1); ?>};
  function showHideDetails() {
    var e = document.getElementById('payment-type');
    if (detailsInfo[e.options[e.selectedIndex].value] == '') {
      document.getElementById('payment-details').style.display = 'none';
      document.getElementById('payment-details-name').innerHTML = '&nbsp;';
    } else {
      document.getElementById('payment-details').style.display = '';
      document.getElementById('payment-details-name').innerHTML = detailsInfo[e.options[e.selectedIndex].value];
    }
  }
  // -->
</script>
</head>
<body onload="init(); showHideDetails();">
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="container-fluid">
    <h1><?php echo TEXT_REFERRERS; ?></h1>
<?php
if ($mode == '' || $mode == 'summary') {
?>
   <table class="table">
        <tr>
            <td valign="top" width="75%">
                <table class="table">
                    <tr class="dataTableHeadingRow">
                        <td class="dataTableHeadingContent"><?php echo HEADING_LAST_NAME; ?></td>
                        <td class="dataTableHeadingContent"><?php echo HEADING_FIRST_NAME; ?></td>
                        <td class="dataTableHeadingContent"><?php echo HEADING_EMAIL_ADDRESS; ?></td>
                        <td class="dataTableHeadingContent"><?php echo HEADING_WEBSITE; ?></td>
                        <td class="dataTableHeadingContent"><?php echo HEADING_APPROVED; ?></td>
                        <td class="dataTableHeadingContent"><?php echo HEADING_BANNED; ?></td>
                        <td class="dataTableHeadingContent"><?php echo HEADING_UNPAID_TOTAL; ?></td>
                        <td class="dataTableHeadingContent"><?php echo HEADING_COMMISSION_RATE; ?></td>
                        <td class="dataTableHeadingContent"><?php echo HEADING_PAYMENT_TYPE; ?></td>
                    </tr>
<?php
    foreach ($referrers as $referrer) {
        $current_selection = ($referrers[$selected] == $referrer);
?>
                    <tr class="dataTableRow<?php echo ($current_selection) ? 'Selected' : ''; ?>" onmouseover="rowOverEffect(this);" onmouseout="rowOutEffect(this);" onclick="document.location.href='<?php echo zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrer['customers_id'] . "&amp;mode=details&amp;page=$snap_page", 'NONSSL'); ?>'">
                        <td class="dataTableContent"><?php echo $referrer['customers_lastname']; ?></td>
                        <td class="dataTableContent"><?php echo $referrer['customers_firstname']; ?></td>
                        <td class="dataTableContent"><?php echo $referrer['customers_email_address']; ?></td>
                        <td class="dataTableContent"><?php echo $referrer['referrer_homepage']; ?></td>
                        <td class="dataTableContent"><?php echo ($referrer['referrer_approved'] == 1) ? TEXT_YES : '<span class="alert">' . TEXT_NO . '</span>'; ?></td>
                        <td class="dataTableContent"><?php echo ($referrer['referrer_banned'] == 1) ? '<span class="alert">' . TEXT_YES . '</span>' : TEXT_NO; ?></td>
                        <td class="dataTableContent"><?php echo $currencies->format($referrer['status_breakdown'][0]['unpaid_commission']); ?></td>
                        <td class="dataTableContent"><?php echo $referrer['referrer_commission'] * 100 . '%'; ?></td>
                        <td class="dataTableContent"><?php echo $payment_types[$referrer['referrer_payment_type']]['text']; ?></td>
                    </tr>
<?php
    }
?>
                    <tr>
                        <td colspan="4" class="smallText" valign="top"><?php echo $referrer_split->display_count($referrer_query_numrows, SNAP_MAX_REFERRER_DISPLAY, $snap_page, TEXT_DISPLAY_SPLIT); ?></td>
                        <td colspan="5" class="smallText h-r"><?php echo $referrer_split->display_links($referrer_query_numrows, SNAP_MAX_REFERRER_DISPLAY, MAX_DISPLAY_PAGE_LINKS, $snap_page); ?></td>
                    </tr>
                </table>
            </td>
     
            <td valign="top" width="25%">
                <table width="100%" cellspacing="0" cellpadding="2">
                    <tr class="infoBoxHeading"><td class="infoBoxHeading"><?php echo $referrers[$selected]['customers_firstname'] . ' ' . $referrers[$selected]['customers_lastname']; ?></td></tr>
                    <tr><td class="infoBoxContent"><br /><?php echo LABEL_REFERRER_ID . ' ' . $referrers[$selected]['referrer_key']; ?></td></tr>
<?php
    $home_page_link = zen_catalog_href_link(FILENAME_DEFAULT, 'referrer=' . $referrers[$selected]['referrer_key']);
?>
                    <tr><td class="infoBoxContent"><br /><?php echo LABEL_HOME_PAGE_LINK . ' '; ?><a href="<?php echo $home_page_link; ?>" target="_blank"><?php echo $home_page_link; ?></a></td></tr>
                    <tr><td class="infoBoxContent"><br /><?php echo LABEL_ORDERS_TOTAL . ' ' . $currencies->format($referrers[$selected]['status_breakdown'][0]['total']); ?></td></tr>
                    <tr><td class="infoBoxContent"><br /><?php echo LABEL_UNPAID . ' ' . $currencies->format($referrers[$selected]['status_breakdown'][0]['unpaid_commission']); ?></td></tr>
                    <tr><td class="infoBoxContent"><br /><?php echo sprintf(LABEL_EMAIL, $referrers[$selected]['customers_email_address']); ?></td></tr>
                    <tr><td class="infoBoxContent"><br /><?php echo sprintf(LABEL_WEBSITE, $referrers[$selected]['referrer_homepage']); ?></td></tr>
                    <tr><td class="infoBoxContent"><br /><?php echo LABEL_PHONE . ' ' . $referrers[$selected]['customers_telephone']; ?></td></tr>
                    <tr><td class="infoBoxContent"><br /><?php echo LABEL_PAYMENT_TYPE . ' ' . $payment_types[$referrers[$selected]['referrer_payment_type']]['text'] . (($referrers[$selected]['referrer_payment_type_detail'] == '') ? '' : (' (' . $referrers[$selected]['referrer_payment_type_detail'] . ')')); ?></td></tr>
                    <tr><td class="infoBoxContent center"><?php echo ($referrercount > 0) ? snap_button_link(zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . "&amp;mode=details&amp;page=$snap_page", 'NONSSL'), IMAGE_DETAILS, 'button_details.gif') : '&nbsp;'; ?></td></tr>
                </table>
            </td>
        </tr>
    </table>
<?php
} elseif ($mode == 'details' || $mode == TEXT_APPROVE || $mode == TEXT_BAN || $mode == TEXT_UNBAN || $mode == TEXT_PAY_SELECTED || $mode == TEXT_UPDATE) {
    $referrer_homepage = $referrers[$selected]['referrer_homepage'];
    $referrer_homepage = (strpos($referrer_homepage, 'http') === 0) ? $referrer_homepage : ('http://' . $referrer_homepage);
?>
    <table width="100%">
        <tr><td><h3><?php echo TEXT_REFERRER_INFO; ?></h3></td></tr>
        <tr>
            <td>
                <table class="table">
                    <tr>
                        <td valign="top"><?php echo LABEL_NAME_ADDRESS; ?></td>
                        <td><?php echo nl2br(zen_address_label($referrers[$selected]['customers_id'], zen_get_customers_address_primary($referrers[$selected]['customers_id']))); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo HEADING_WEBSITE . ':'; ?></td>
                        <td><a href="<?php echo $referrer_homepage; ?>" target="_blank"><?php echo $referrers[$selected]['referrer_homepage']; ?></a></td>
                    </tr>
                    <tr>
                        <td><?php echo HEADING_EMAIL . ':'; ?></td>
                        <td><a href="mailto:<?php echo $referrers[$selected]['customers_email_address']; ?>"><?php echo $referrers[$selected]['customers_email_address']; ?></a></td>
                    </tr>
                    <tr>
                        <td><?php echo LABEL_PHONE; ?></td>
                        <td><?php echo $referrers[$selected]['customers_telephone']; ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr><td><h3><?php echo TEXT_STATUS; ?></h3></td></tr>
        <tr>
            <td>
<?php
    $commission_to_pay = $referrers[$selected]['status_breakdown'][0]['unpaid_commission'];
    if ($referrers[$selected]['referrer_approved'] == 0) {
        $approve_disabled = '';
        $ban_disabled = ' disabled="disabled"';
        $pay_disabled = ' disabled="disabled"';
        $update_disabled = ' disabled="disabled"';
    } else {
        $approve_disabled = ' disabled="disabled"';
        $ban_disabled = '';
        $update_disabled = '';
        $pay_disabled = ($commission_to_pay == 0) ? ' disabled="disabled"' : '';
    }
                echo zen_draw_form('referrers', FILENAME_REFERRERS, '', 'post', '', true) . zen_draw_hidden_field('page', $snap_page) . zen_draw_hidden_field('referrer', $referrers[$selected]['customers_id']);
?>
                <table class="table">
                   <tr>
                        <td valign="top"><?php echo LABEL_APPROVED; ?></td>
                        <td valign="top"><?php echo ($referrers[$selected]['referrer_approved'] == 1) ? TEXT_YES : '<span class="alert">' . TEXT_NO . '</span>'; ?></td>
                        <td><?php echo snap_submit_button('mode', TEXT_APPROVE, 'btn-warning', $approve_disabled) . '&nbsp;' . TEXT_EMAIL_WILL_BE_SENT; ?></td>
                   </tr>
       
                   <tr>
                        <td valign="top"><?php echo LABEL_BANNED; ?></td>
                        <td valign="top"><?php echo ($referrers[$selected]['referrer_banned'] == 1) ? '<span class="alert">' . TEXT_YES . '</span>' : TEXT_NO; ?></td>
                        <td><?php echo snap_submit_button('mode', ($referrers[$selected]['referrer_banned'] == 1) ? TEXT_UNBAN : TEXT_BAN, 'btn-warning', $ban_disabled) . '&nbsp;' . TEXT_EMAIL_WILL_BE_SENT; ?></td>
                   </tr>
                   
                   <tr>
                        <td valign="top"><?php echo LABEL_UNPAID_COMMISSION; ?></td>
                        <td valign="top"><?php echo $currencies->format($commission_to_pay); ?></td>
                        <td><?php echo snap_submit_button('mode', TEXT_PAY, '', $pay_disabled); ?></td>
                   </tr>
                   
                   <tr>
                        <td valign="top"><?php echo LABEL_CURRENT_COMMISSION_RATE; ?></td>
                        <td valign="top"><input type="text" size="3" value="<?php echo $referrers[$selected]['referrer_commission'] * 100; ?>" name="commission" />%</td>
                        <td><?php echo snap_submit_button('mode', TEXT_UPDATE, 'btn-warning', $update_disabled); ?></td>
                   </tr>
                  
                   <tr>
                        <td valign="top"><?php echo LABEL_PAYMENT_TYPE; ?></td>
<?php
    $payment_type_selections = array();
    foreach ($payment_types as $type_id => $type_info) {
        $payment_type_selections[] = array( 
            'id' => $type_id, 
            'text' => $type_info['text'] 
        );
    }
    $referrer_payment_type = (isset($_POST['referrer_payment_type'])) ? $_POST['referrer_payment_type'] : $referrers[$selected]['referrer_payment_type'];
    $referrer_payment_type_detail = (isset($_POST['referrer_payment_type_detail'])) ? $_POST['referrer_payment_type_detail'] : $referrers[$selected]['referrer_payment_type_detail'];
?>
                        <td valign="top"><?php echo zen_draw_pull_down_menu ('referrer_payment_type', $payment_type_selections, $referrer_payment_type, 'id="payment-type" onchange="showHideDetails();"'); ?></td>
                        <td><?php echo snap_submit_button('mode', TEXT_UPDATE_PAYMENT_TYPE, 'btn-warning', $update_disabled); ?></td>
                   </tr>
                       
                   <tr id="payment-details">
                        <td valign="top" id="payment-details-name">&nbsp;</td>
                        <td valign="top"><?php echo zen_draw_input_field ('referrer_payment_type_detail', $referrer_payment_type_detail); ?></td>
                        <td>&nbsp;</td>
                   </tr>
                </table></form>
            </td>
        </tr>
        <tr>
            <td width="100%">
<?php
                echo zen_draw_form('dateform', FILENAME_REFERRERS, '', 'get', '', true) . zen_draw_hidden_field('referrer', $referrers[$selected]['customers_id']) . zen_draw_hidden_field('mode', 'details') . zen_draw_hidden_field('page', $snap_page);
?>
                <table class="table">
                    <tr>
                        <td><h3><?php echo TEXT_ORDER_HISTORY; ?></h3></td>
                        <td align="right"><?php echo TEXT_FROM . snap_get_date_dropdown('start', $start_mon, $start_year) . TEXT_TO . snap_get_date_dropdown('end', $end_mon, $end_year) . '&nbsp;&nbsp;' . snap_submit_button('choose', TEXT_CHOOSE); ?></td>
                    </tr>
                </table></form>
            </td>
        </tr>
        <tr>
            <td>
                <table class="history table">
                    <tr>
                        <th><?php echo HEADING_ORDER_ID; ?></th>
                        <th><?php echo HEADING_ORDER_DATE; ?></th>
<?php
    foreach ($orders_status_names as $order_status => $status_name) {
        if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $status_name != '' && $status_name != TEXT_NONCOMMISSIONABLE) {
            continue;
        }
?>
                        <th><?php echo HEADING_ORDER_TOTAL . $status_name; ?></th>
<?php
    }
?>
                        <th><?php echo HEADING_COMMISSION_RATE; ?></th>
<?php
    foreach ($orders_status_names as $order_status => $status_name) {
        if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $status_name != '' && $status_name != TEXT_NONCOMMISSIONABLE) {
            continue;
        }
?>
                        <th><?php echo HEADING_COMMISSION_TOTAL . $status_name; ?></th>
<?php
    }
?>
                        <th><?php echo HEADING_COMMISSION_PAY_DATE; ?></th>
                        <th><?php echo HEADING_COMMISSION_PAID_VIA; ?></th>
                    </tr>
<?php
    foreach( $referrers[$selected]['orders'] as $order ) {
        $total = $order['order_total'] - $order['value'];
        $commission = $order['commission_rate'];
        $orders_status = $order['orders_status'];
        $orders_status = (!$order['ispaid'] && in_array($orders_status, $order_status_exclusions)) ? $orders_status : 0;

        if ($total < 0 ) {
            $total = 0;
        }
?>
                    <tr>
                        <td><a href="<?php echo zen_href_link(FILENAME_ORDERS, 'oID=' . $order['orders_id'] . '&action=edit', 'NONSSL'); ?>"><?php echo $order['orders_id']; ?></a></td>
                        <td><?php echo $order['date_purchased']; ?></td>
<?php
        foreach($orders_status_names as $current_orders_status => $status_name) {
            if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes') {
                if ($current_orders_status != '*' && $current_orders_status != 0) {
                    continue;
                }
                if ($orders_status !== 0) {
                    $orders_status = '*';
                }
            }
?>
                        <td><?php echo $currencies->format(($current_orders_status === $orders_status) ? $total : 0 ); ?></td>
<?php
        }
?>
                        <td><?php echo $commission * 100; ?>%</td>
<?php
        foreach($orders_status_names as $current_orders_status => $status_name) {
            if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes') {
                if ($current_orders_status !== 0 && $current_orders_status != '*') {
                    continue;
                }
            }
            $current_total = ($current_orders_status === $orders_status) ? $total : 0;
          
            if ($status_name == '' && $order['ispaid'] && $order['commission_paid_amount'] != 0) {
                $commission_value = $order['commission_paid_amount'];
            } else {
                $commission_value = $commission * $current_total;
            }
?>
                        <td><?php echo $currencies->format ( $commission_value ); ?></td>
<?php
        }
        $commission_payment_type_detail = '';
        $commission_payment_type = '&nbsp;';
        if ($order['commission_paid'] == '0001-01-01 00:00:00') {
            $commission_paid = TEXT_UNPAID;
        } else {
            $commission_paid = $order['commission_paid'];
            if (!isset($payment_types[$order['commission_payment_type']])) {
                $commission_payment_type = PAYMENT_TYPE_UNKNOWN;
            } else {
                $commission_payment_type = $payment_types[$order['commission_payment_type']]['text'];
                if (!empty($order['commission_payment_type_detail'])) {
                    $commission_payment_type_detail = ' (' . $order['commission_payment_type_detail'] . ')';
            
                }
            }
        }
?>
                        <td><?php echo $commission_paid; ?></td>
                        <td><?php echo $commission_payment_type . $commission_payment_type_detail; ?></td>
                    </tr>
<?php
    }

?>
                    <tr class="historyFooter">
                        <td class="h-r" colspan="2"><?php echo HEADING_TOTALS; ?></td>
<?php
    foreach($referrers[$selected]['status_breakdown'] as $order_status => $current_totals) {
      if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $order_status !== 0 && $order_status != '*') {
          continue;
      }
?>
                        <td><?php echo $currencies->format( $current_totals['total'] ); ?></td>
<?php
    }
?>
                        <td>&nbsp;</td>
<?php
    foreach ($referrers[$selected]['status_breakdown'] as $order_status => $current_totals) {
        if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $order_status !== 0 && $order_status != '*') {
            continue;
        }
?>
                        <td><?php echo $currencies->format($current_totals['commission_total']); ?></td>
<?php
    }
?>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="h-r"><?php echo snap_button_link(zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . "&amp;page=$snap_page", 'NONSSL'), IMAGE_CANCEL, 'button_cancel.gif'); ?></td>
        </tr>
    </table>
<?php
} elseif ($mode == TEXT_PAY) {
?>
    <table class="table">
        <tr><td><h3><?php echo TEXT_REFERRER_INFO; ?></h3></td></tr>
        <tr>
            <td><table class="table">
                <tr>
                    <td valign="top"><?php echo LABEL_NAME_ADDRESS; ?></td>
                    <td><?php echo nl2br(zen_address_label($referrers[$selected]['customers_id'], zen_get_customers_address_primary($referrers[$selected]['customers_id']))); ?></td>
                </tr>
                <tr>
                    <td><?php echo HEADING_WEBSITE . ':'; ?></td>
                    <td><a href="<?php echo 'http://' . $referrers[$selected]['referrer_homepage']; ?>" target="_blank"><?php echo $referrers[$selected]['referrer_homepage']; ?></a></td>
                </tr>
                <tr>
                    <td><?php echo HEADING_EMAIL . ':'; ?></td>
                    <td><a href="mailto:<?php echo $referrers[$selected]['customers_email_address']; ?>"><?php echo $referrers[$selected]['customers_email_address']; ?></a></td>
                </tr>
                <tr>
                    <td><?php echo LABEL_PHONE; ?></td>
                    <td><?php echo $referrers[$selected]['customers_telephone']; ?></td>
                </tr>
                <tr>
                    <td><?php echo LABEL_PAYMENT_TYPE; ?></td>
                    <td><?php echo $payment_types[$referrers[$selected]['referrer_payment_type']]['text'] . (($referrers[$selected]['referrer_payment_type_detail'] == '') ? '' : (' (' . $referrers[$selected]['referrer_payment_type_detail'] . ')')); ?></td>
                </tr>
            </table></td>
        </tr>
        <tr><td><h3><?php echo TEXT_CHOOSE_COMMISSIONS; ?></h3></td></tr>
<?php
    if ($pay_message != '') {
?>
        <tr><td class="error"><?php echo $pay_message; ?></td></tr>
<?php
    }
?>
        <tr>
            <td>
<?php 
                echo zen_draw_form('referrers', FILENAME_REFERRERS, '', 'post', '', true) . zen_draw_hidden_field('referrer', $referrers[$selected]['customers_id']) . zen_draw_hidden_field('commission_payment_type', $referrers[$selected]['referrer_payment_type']) . zen_draw_hidden_field('commission_payment_type_detail', $referrers[$selected]['referrer_payment_type_detail']) . zen_draw_hidden_field('page', $snap_page);
?>
                <table class="history table">
                    <tr>
                        <th><?php echo HEADING_CHOOSE; ?></th>
                        <th><?php echo HEADING_ORDER_ID; ?></th>
                        <th><?php echo HEADING_ORDER_DATE; ?></th>
                        <th><?php echo HEADING_ORDER_TOTAL; ?></th>
                        <th><?php echo HEADING_COMMISSION_RATE; ?></th>
                        <th><?php echo HEADING_CALCULATED_COMMISSION; ?></th>
                        <th><?php echo HEADING_COMMISSION_TO_PAY; ?></th>
                    </tr>
<?php
    $customers_id = $referrers[$selected]['customers_id'];
    foreach ($referrers[$selected]['orders'] as $order) {
        if (!$order['ispaid']) {
            $commission_id = $order['commission_id'];
            $commissionable_total = $order['order_total'] - $order['value'];
            $commission = number_format($commissionable_total * $order['commission_rate'], $currencies->get_decimal_places(DEFAULT_CURRENCY));
            $choose = (isset($_POST['payList'][$commission_id]));
            $commission_to_pay = ($choose) ? $_POST['commission'][$commission_id] : $commission;
?>
                    <tr>
                        <td><?php echo zen_draw_checkbox_field("payList[$commission_id]", false, $choose, '', 'class="cBox"') . zen_draw_hidden_field("calculated[$commission_id]", (string)$commission); ?></td>
                        <td><a href="<?php echo zen_href_link(FILENAME_ORDERS, 'oID=' . $order['orders_id'] . '&action=edit', 'NONSSL'); ?>"><?php echo $order['orders_id']; ?></a></td>
                        <td><?php echo $order['date_purchased']; ?></td>
                        <td><?php echo $currencies->format($commissionable_total); ?></td>
                        <td><?php echo $order['commission_rate'] * 100; ?>%</td>
                        <td><?php echo $currencies->format ($commission); ?></td>
                        <td><?php echo zen_draw_input_field("commission[$commission_id]", $commission_to_pay); ?></td>
                    </tr>
<?php
        }
    }
?>
                    <tr>
                        <td colspan="7" class="h-r"><?php echo snap_submit_button('mode', TEXT_PAY_SELECTED, 'btn-warning'); ?></td>
                    </tr>
                </table></form>
            </td>
        </tr>
        <tr>
            <td width="100%" align="right"><?php echo snap_button_link(zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . "&amp;mode=details&amp;page=$snap_page"), IMAGE_CANCEL, 'button_cancel.gif'); ?></td>
        </tr>
    </table>
<?php
}
require DIR_WS_INCLUDES . 'footer.php';
?>
</div>
</body>
</html>
<?php
require 'includes/application_bottom.php';
