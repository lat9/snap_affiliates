<?php
// +----------------------------------------------------------------------+
// | Snap Affiliates for Zen Cart v1.5.0 and later                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)           |
// |                                                                      |
// | Original: Copyright (c) 2009 Michael Burke                           |
// | http://www.filterswept.com                                           |
// |                                                                      |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.       |
// +----------------------------------------------------------------------+
//
function get_var($name) {
  return (isset ($_POST[$name])) ? $_POST[$name] : ((isset ($_GET[$name])) ? $_GET[$name] : '');

}

function send_notification_email($referrer, $subject, $text, $html) {
  global $db;

  $query = "SELECT customers_firstname, customers_lastname, customers_email_address, customers_telephone, customers_fax from " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$referrer['customers_id'];
  $customer = $db->Execute($query);

  if (!$customer->EOF) {
    $html_msg = array( 'EMAIL_SUBJECT' => $subject, 'EMAIL_MESSAGE_HTML' => $html );
    $customers_name = $customer->fields['customers_firstname'] . ' ' . $customer->fields['customers_lastname'];
    $customers_email = $customer->fields['customers_email_address'];
    zen_mail($customers_name, $customers_email, $subject, $text, STORE_NAME, EMAIL_FROM, $html_msg);
    
    if (defined('SNAP_ADMIN_EMAIL') && zen_validate_email(SNAP_ADMIN_EMAIL)) {
      $extra_info = email_collect_extra_info($customers_name, $customers_email, $customers_name, $customers_email, $customer->fields['customers_telephone'], $customer->fields['customers_fax']);
      $html_msg['EXTRA_INFO'] = $extra_info['HTML'];
      zen_mail('', SNAP_ADMIN_EMAIL, $subject, $text . "\n" . $extra_info['TEXT'], STORE_NAME, EMAIL_FROM, $html_msg);
    }
  }

}

require('includes/application_top.php');

if (!defined('SNAP_MAX_REFERRER_DISPLAY')) define ('SNAP_MAX_REFERRER_DISPLAY', 50); /*v2.1.0a*/

require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

$results = $db->Execute("SELECT count(*) AS count FROM " . TABLE_REFERRERS);
$referrercount = ($results->EOF) ? 0 : intval($results->fields['count']);
$selectedID = (int)get_var('referrer');
$mode = get_var('mode');
$and_clause = ($mode == '') ? '' : " AND r.referrer_customers_id = $selectedID";
$query = "SELECT c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address, c.customers_telephone, r.referrer_customers_id, r.referrer_key, r.referrer_homepage, r.referrer_approved, r.referrer_banned, r.referrer_commission, r.referrer_payment_type, r.referrer_payment_type_detail
            FROM " . TABLE_CUSTOMERS ." c, " . TABLE_REFERRERS . " r 
            WHERE c.customers_id = r.referrer_customers_id$and_clause 
            ORDER BY c.customers_lastname, c.customers_firstname, c.customers_id";  /*v2.1.0c*/
if ($mode == '') {
  $referrer_split = new splitPageResults($_GET['page'], SNAP_MAX_REFERRER_DISPLAY, $query, $referrer_query_numrows);  /*v2.1.0a*/
}
          
$referrerResults = $db->Execute($query);
$referrers = array();

$payment_types = array ( 'CM' => array ( 'text' => PAYMENT_TYPE_CHECK_MONEYORDER, 'text_details' => '', 'payment_details' => '' ) );
if (SNAP_ENABLE_PAYMENT_CHOICE_PAYPAL == 'Yes') {
  $payment_types['PP'] = array ( 'text' => PAYMENT_TYPE_PAYPAL, 'text_details' => PAYMENT_TYPE_PAYPAL_DETAILS, 'payment_details' => '' );
  
}
$zco_notifier->notify ('SNAP_GET_PAYMENT_TYPE_DESCRIPTION');

$selected = 0;
$pay_message = '';  //-v2.7.0a

$today = getdate();
$activity_begin = mktime(0, 0, 0, 1, 1, $today['year'] - 9);
$activity_end = time();

if (isset($_GET['start']) ) {
  $activity_begin = intval($_GET['start']);
}

if ($activity_begin > time()) {
  $activity_begin = time();
}

if (isset($_GET['end'])) {
  $activity_end = intval($_GET['end']);
}

if ($activity_begin > $activity_end) {
  $tempDate = getdate($activity_begin);
  $activity_end = mktime(23, 59, 59, $tempDate['mon']+1, 0, $tempDate['year']);
}

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
if (!defined('SNAP_AFFILIATE_COMBINE_EXCLUSIONS')) define('SNAP_AFFILIATE_COMBINE_EXCLUSIONS', 'No');  /*v2.5.0a*/
$order_status_exclusions = explode(',', SNAP_ORDER_STATUS_EXCLUSIONS);  
for ($i = 0, $orders_by_status = array(), $orders_status_names = array(), $n = count($order_status_exclusions); $i < $n; $i++) {
  $current_order_status = (int)$order_status_exclusions[$i];
  if ($current_order_status != 0 && !array_key_exists($current_order_status, $orders_status_names)) {
    $result = $db->Execute("SELECT orders_status_name 
                              FROM " . TABLE_ORDERS_STATUS . " 
                             WHERE language_id = " . (int)$_SESSION['languages_id'] . " 
                               AND orders_status_id = " . $current_order_status);
    if (!$result->EOF) {
      $orders_by_status[$current_order_status] = array ( 'total' => 0, 'commission_total' => 0, 'unpaid_commission' => 0 );
      $orders_status_names[$current_order_status] = ' (' . $result->fields['orders_status_name'] . ')';
    }
  }
}
//-bof-a-v2.5.0
if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes') {
  $orders_by_status['*'] = array ( 'total' => 0, 'commission_total' => 0, 'unpaid_commission' => 0 );
  $orders_status_names['*'] = TEXT_NONCOMMISSIONABLE;
}
//-eof-a-v2.5.0
$orders_by_status[0] = array ( 'total' => 0, 'commission_total' => 0, 'unpaid_commission' => 0 );
$orders_status_names[0] = '';
//-eof-v2.1.0a

// -----
// Loop through each of the current referrers ...
//
while (!$referrerResults->EOF) {
  $idx = count($referrers);
  
  array_push($referrers, $referrerResults->fields);
  $referrers[$idx]['status_breakdown'] = $orders_by_status;  /*v2.1.0a*/

  if ($selectedID == $referrers[$idx]['customers_id']) {
    $selected = $idx;
  }
  
  if (!defined('SNAP_ORDER_TOTALS_EXCLUSIONS')) define('SNAP_ORDER_TOTALS_EXCLUSIONS', 'ot_tax,ot_shipping');
  $exclude_array = explode(',', SNAP_ORDER_TOTALS_EXCLUSIONS);
  
  for ($i = 0, $exclude_clause = '', $n = count($exclude_array); $i < $n; $i++) {
    if ($i != 0) {
      $exclude_clause .= ' OR ';
    }
    $exclude_clause .= ("t.class = '" . $exclude_array[$i] . "'");
  }
  if ($exclude_clause != '') {
    $exclude_clause = " AND ( $exclude_clause ) ";
  }

  $query = "SELECT o.orders_id, o.date_purchased, o.order_total, c.commission_rate, c.commission_paid, o.orders_status, c.commission_id, c.commission_paid_amount, c.commission_manual, c.commission_payment_type, c.commission_payment_type_detail
              FROM " . TABLE_ORDERS . " o, " . TABLE_COMMISSION . " c
             WHERE c.commission_referrer_key = '" . $referrers[$idx]['referrer_key'] . "'
               AND o.orders_id = c.commission_orders_id"; /*v2.1.0c, v2.7.0c*/

  $orderResults = $db->Execute($query);
  $orders = array();

  // -----
  // ... processing each of the referrer's order information.
  //
  while (!$orderResults->EOF) {
    $isCommissionPaid = ($orderResults->fields['commission_paid'] == '0001-01-01 00:00:00') ? false : true;  /*v2.5.0a*/
    $orderResults->fields['ispaid'] = $isCommissionPaid;  /*v2.5.0a*/
    $ordersTotal = $ordersCommission = $ordersUnpaid = 0; /*v2.1.0c*/
    $orders_status = (int)$orderResults->fields['orders_status']; /*v2.1.0a*/
 
    // -----
    // Determine the order-total exclusions associated with the order.
    //
    $query = "SELECT t.value 
                FROM " . TABLE_ORDERS ." o, ". TABLE_ORDERS_TOTAL ." t
                WHERE o.orders_id = " . (int)$orderResults->fields['orders_id'] . " 
                  AND o.orders_id = t.orders_id" . $exclude_clause;
    $totalResults = $db->Execute($query);
    $current_exclusion = 0;
    while (!$totalResults->EOF) {
      $current_exclusion += floatval($totalResults->fields['value']);
      $totalResults->MoveNext();
    }
    
    $orderResults->fields['value'] = $current_exclusion;
    $purchase_date = strtotime($orderResults->fields['date_purchased']);

    $total = floatval($orderResults->fields['order_total']) - $current_exclusion;
    $commission = floatval($orderResults->fields['commission_rate']);

    if ($total < 0) {
      $total = 0;
    }

    if ($activity_begin < $purchase_date && $purchase_date < $activity_end) {
      $ordersCommission = ($isCommissionPaid && $orderResults->fields['commission_paid_amount'] != 0) ? $orderResults->fields['commission_paid_amount'] : ($commission * $total);  //-v2.7.0c
      $ordersTotal = $total;

      if (!$isCommissionPaid) {  /*v2.5.0c*/
        $ordersUnpaid = $commission * $total;
      }

      array_push($orders, $orderResults->fields);
    }
    
//-bof-c-v2.1.0: Add the order-total, order-commission and unpaid-commission values into the element
// associated with this order's order-status value.
//
    $orders_status = ( (!$isCommissionPaid) && in_array($orders_status, $order_status_exclusions)) ? $orders_status : 0;  /*v2.5.0c*/
//-bof-a-v2.5.0
    if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $orders_status !== 0) {
      $orders_status = '*';
      
    }
//-eof-a-v2.5.0
    $referrers[$idx]['status_breakdown'][$orders_status]['total'] += $ordersTotal;
    $referrers[$idx]['status_breakdown'][$orders_status]['commission_total'] += $ordersCommission;
    $referrers[$idx]['status_breakdown'][$orders_status]['unpaid_commission'] += $ordersUnpaid;
//-eof-c-v2.1.0

    $orderResults->MoveNext();
  }

  $referrers[$idx]['orders'] = $orders;

  $referrerResults->MoveNext();
}

switch($mode) {
  case TEXT_APPROVE:
    $approved = ($referrers[$selected]['referrer_approved'] == 1) ? 0 : 1;
    $query = "UPDATE " . TABLE_REFERRERS ." SET referrer_approved = " . $approved . " WHERE referrer_customers_id = " . (int)$referrers[$selected]['customers_id'];
    $result = $db->Execute($query);
    $referrers[$selected]['referrer_approved'] = $approved;

    send_notification_email (
      $referrers[$selected], 
      EMAIL_SUBJECT_APPROVED,
      sprintf(EMAIL_MESSAGE_APPROVED_TEXT, zen_catalog_href_link(FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link(FILENAME_CONTACT_US, '', 'NONSSL')),
      sprintf(EMAIL_MESSAGE_APPROVED_HTML, zen_catalog_href_link(FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link(FILENAME_CONTACT_US, '', 'NONSSL')));
    break;
    
  case TEXT_BAN:
  case TEXT_UNBAN:
    $banned = ($referrers[$selected]['referrer_banned'] == 1) ? 0 : 1;
    $query = "UPDATE " . TABLE_REFERRERS . " SET referrer_banned = " . $banned . " WHERE referrer_customers_id = " . $referrers[$selected]['customers_id'];

    $result = $db->Execute($query);

    $referrers[$selected]['referrer_banned'] = $banned;

    if ($banned == 1) {
      send_notification_email ($referrers[$selected], EMAIL_SUBJECT_BANNED, EMAIL_MESSAGE_BANNED_TEXT, EMAIL_MESSAGE_BANNED_HTML);
    }
    break;
//-bof-v2.7.0c  
  case TEXT_PAY_SELECTED:
    if (!isset ($_POST['payList']) || !is_array ($_POST['payList'])) {
      $pay_message = ERROR_CHOOSE_COMMISSION_TO_PAY;
      $mode = TEXT_PAY;
      
    } else {
      $commissions = array ();
      foreach ($_POST['payList'] as $commission_id => $value) {
        if (!isset ($_POST['commission'][$commission_id]) || (float)$_POST['commission'][$commission_id] <= 0) {  //-v3.0.1c
          $pay_message = ERROR_COMMISSION_CANT_BE_ZERO;
          $mode = TEXT_PAY;
          
        } else {
          $commissions[$commission_id] = array ( 'calculated' => $_POST['calculated'][$commission_id], 'paid' => $_POST['commission'][$commission_id] );
          
        }
      }
          
      if (isset ($payment_types[$_POST['commission_payment_type']])) {
        $commission_payment_type = $_POST['commission_payment_type'];
        $commission_payment_type_name = $payment_types[$commission_payment_type]['text'];
        $commission_payment_type_detail = ($payment_types[$_POST['commission_payment_type']]['payment_details'] == '') ? $_POST['commission_payment_type_detail'] : $payment_types[$_POST['commission_payment_type']]['payment_details'];
        
      } else {
        $commission_payment_type = '??';
        $commission_payment_type_name = PAYMENT_TYPE_UNKNOWN;
        $commission_payment_type_detail = '';
        
      }
      $commission_payment_type_message = $commission_payment_type_name . (($commission_payment_type_detail == '') ? '' : (' (' . $commission_payment_type_detail . ')'));
      
      $now = date("Y-m-d H:i:s", time());
      $total_paid = 0;
      $commission_payment_type_detail_input = zen_sanitize_string ($db->prepare_input ($commission_payment_type_detail));  //-v3.0.1a
      foreach ($commissions as $commission_id => &$commission) {
        $commission_manual = ($commission['calculated'] == $commission['paid']) ? 0 : 1;
        $db->Execute ("UPDATE " . TABLE_COMMISSION . " SET commission_paid = '$now', commission_paid_amount = '" . zen_db_prepare_input ($commission['paid']) . "', commission_manual = $commission_manual, commission_payment_type = '$commission_payment_type', commission_payment_type_detail = '$commission_payment_type_detail_input' WHERE commission_id = $commission_id LIMIT 1");  //-v3.0.1c
         foreach ($referrers[$selected]['orders'] as $current_order) {
          if ($current_order['commission_id'] == $commission_id) {
            $total_paid += $commission['paid'];
            
            // -----
            // Add a comment to the order's status-history table (hidden from the customer) to identify that the commission payment was made.
            // The order's current status is unchanged.
            //
            $status_comment = sprintf (TEXT_ORDERS_STATUS_PAID, $currencies->format ($commission['paid']), $referrers[$selected]['customers_firstname'], $referrers[$selected]['customers_lastname'], $commission_payment_type_message);
            $commission['osid'] = zen_update_orders_history ($current_order['orders_id'], $status_comment);
            break;
            
          }
        }
        unset ($commission);
        
      }

      if ($total_paid != 0) {
        $total_paid_formatted = $currencies->format (floatval ($total_paid));
//-bof-20150304-lat9-Enable alternate payment method handlers.  Check/money-order is the default.
        $snap_payment_email_handled = false;
        $zco_notifier->notify ('SNAP_CHECK_ALTERNATE_PAYMENT', array ( 'total_paid' => $total_paid, 'total_paid_formatted' => $total_paid_formatted, 'referrer' => $referrers[$selected]));
        if (!$snap_payment_email_handled) {
          send_notification_email (
            $referrers[$selected], 
            EMAIL_SUBJECT_PAID,
            sprintf(EMAIL_MESSAGE_PAID_TEXT, $total_paid_formatted, zen_catalog_href_link (FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link (FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link (FILENAME_CONTACT_US, '', 'NONSSL')),
            sprintf(EMAIL_MESSAGE_PAID_HTML, $total_paid_formatted, zen_catalog_href_link (FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link (FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link (FILENAME_CONTACT_US, '', 'NONSSL'))); /*v2.1.0c*/   
          
          $messageStack->add_session (sprintf (SUCCESS_PAYMENT_MADE, $total_paid_formatted, $referrers[$selected]['customers_firstname'], $referrers[$selected]['customers_lastname']), 'success');
          
        }
//-eof-20150304-lat9
        zen_redirect (zen_href_link (FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . '&mode=details' . ((get_var ('page') != '') ? ('&page=' . (int)get_var ('page')) : '')));
        
      }
    }

//-eof-v2.7.0c
    break;
    
  case TEXT_UPDATE:
    if (!is_numeric($_POST['commission']) || ((int)$_POST['commission'] < 0) || ((int)$_POST['commission'] > 100)) {
      $messageStack->add(ERROR_INVALID_PERCENTAGE, 'error');
      
    } else {
      $commission = floatval((int)$_POST['commission']) / 100;
      $query = "UPDATE ". TABLE_REFERRERS ." SET referrer_commission = " . $commission . " WHERE referrer_customers_id = " . (int)$referrers[$selected]['customers_id'];
      $db->Execute($query);

      $referrers[$selected]['referrer_commission'] = $commission;
    }
    break;
    
  case TEXT_UPDATE_PAYMENT_TYPE:
    $error = false;
    if ($payment_types[$_POST['referrer_payment_type']]['text_details'] != '') {
      $payment_details = zen_sanitize_string ($db->prepare_input ($_POST['referrer_payment_type_detail']));  //-v3.0.1c
      if (!zen_not_null ($payment_details)) {
        $mode = 'details';
        $messageStack->add (sprintf (ERROR_PAYMENT_DETAILS_MISSING, $payment_types[$_POST['referrer_payment_type']]['text_details']), 'error');
        $error = true;
      }      
    } else {
      $payment_details = '';
      
    }
    if (!$error) {
      $db->Execute ("UPDATE " . TABLE_REFERRERS . " SET referrer_payment_type = '" . $_POST['referrer_payment_type'] . "', referrer_payment_type_detail = '$payment_details' WHERE referrer_customers_id = " . (int)$referrers[$selected]['customers_id'] . " LIMIT 1");
      zen_redirect (zen_href_link (FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . '&mode=details' . ((get_var ('page') != '') ? ('&page=' . (int)get_var ('page')) : '')));
    }
    break;    

}  // END switch

/* html begins after this */

?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<style type="text/css">
.historyHeader { border-bottom: 1px dashed grey; }
.historyA { background-color: #ccffcc; }
.historyB { }
.historyFooter { border-top: 1px solid grey; font-weight: bold; }
.noInput { padding: 2px 0; }
.center { text-align: center; }
.error { border: 1px dashed red; padding: 2px; color: red; font-weight: bold; }
</style>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css" media="print" href="includes/stylesheet_print.css">
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script type="text/javascript" src="includes/menu.js"></script>
<script type="text/javascript" src="includes/general.js"></script>
<script type="text/javascript">
  <!--
  function init() {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
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
  var detailsInfo = {<?php echo substr ($details, 1); ?>};
  function showHideDetails () {
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

<?php require(DIR_WS_INCLUDES . 'header.php'); ?>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
 <tr>
  <td>
   <table border="0" width="100%" cellspacing="2" cellpadding="2">
    <tr>
     <td class="pageHeading"><?php echo TEXT_REFERRERS; /*v2.1.0c*/ ?></td>
    </tr>
   </table>
  </td>
 </tr>
<?php
if ($mode == '' || $mode == 'summary') {
?>
 <tr>
  <td>
   <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
     <td valign="top" width="75%">
      <table border="0" width="100%" cellspacing="0" cellpadding="2">
       <tr class="dataTableHeadingRow">
        <td class="dataTableHeadingContent"><?php echo HEADING_LAST_NAME; ?></td>
        <td class="dataTableHeadingContent"><?php echo HEADING_FIRST_NAME; ?></td>
        <td class="dataTableHeadingContent"><?php echo HEADING_EMAIL_ADDRESS; ?></td>  <?php /*v2.7.1a*/ ?>
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
       <tr class="dataTableRow<?php echo ($current_selection) ? 'Selected' : ''; ?>" onmouseover="rowOverEffect(this);" onmouseout="rowOutEffect(this);" onclick="document.location.href='<?php echo zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrer['customers_id'] . '&mode=details' . ((get_var ('page') == '') ? '' : ('&page=' . (int)get_var ('page'))), 'NONSSL'); ?>'">
        <td class="dataTableContent"><?php echo $referrer['customers_lastname']; ?></td>
        <td class="dataTableContent"><?php echo $referrer['customers_firstname']; ?></td>
        <td class="dataTableContent"><?php echo $referrer['customers_email_address']; ?></td>  <?php /*v2.7.1a*/ ?>
        <td class="dataTableContent"><?php echo $referrer['referrer_homepage']; ?></td>
        <td class="dataTableContent"><?php echo ($referrer['referrer_approved'] == 1) ? TEXT_YES : '<span class="alert">' . TEXT_NO . '</span>'; ?></td>
        <td class="dataTableContent"><?php echo ($referrer['referrer_banned'] == 1) ? '<span class="alert">' . TEXT_YES . '</span>' : TEXT_NO; ?></td>
        <td class="dataTableContent"><?php echo $currencies->format($referrer['status_breakdown'][0]['unpaid_commission']); /*v2.1.0c*/ ?></td>
        <td class="dataTableContent"><?php echo $referrer['referrer_commission'] * 100 . '%'; ?></td>
        <td class="dataTableContent"><?php echo $payment_types[$referrer['referrer_payment_type']]['text']; ?></td>
       </tr>
<?php
}
?>
       <tr>
        <td colspan="3" class="smallText" valign="top"><?php echo $referrer_split->display_count($referrer_query_numrows, SNAP_MAX_REFERRER_DISPLAY, $_GET['page'], TEXT_DISPLAY_SPLIT); /*v2.1.0c*/ ?></td>
        <td colspan="4" class="smallText" align="right"><?php echo $referrer_split->display_links($referrer_query_numrows, SNAP_MAX_REFERRER_DISPLAY, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); /*v2.1.0c*/ ?></td>
       </tr>
       
      </table>
     </td>
     
     <td valign="top" width="25%">
      <table width="100%" cellspacing="0" cellpadding="2">
       <tr class="infoBoxHeading"><td class="infoBoxHeading"><?php echo $referrers[$selected]['customers_firstname'] . ' ' . $referrers[$selected]['customers_lastname']; ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_REFERRER_ID . ' ' . $referrers[$selected]['referrer_key']; ?></td></tr>
<?php
//-bof-v2.7.3a
$home_page_link = zen_catalog_href_link (FILENAME_DEFAULT, 'referrer=' . $referrers[$selected]['referrer_key']);
?>
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_HOME_PAGE_LINK . ' '; ?><a href="<?php echo $home_page_link; ?>" target="_blank"><?php echo $home_page_link; ?></a></td></tr>
<?php
//-eof-v2.7.3a
?>
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_ORDERS_TOTAL . ' ' . $currencies->format($referrers[$selected]['status_breakdown'][0]['total']); /*v2.1.0c*/ ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_UNPAID . ' ' . $currencies->format($referrers[$selected]['status_breakdown'][0]['unpaid_commission']); /*v2.1.0c*/ ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo sprintf(LABEL_EMAIL, $referrers[$selected]['customers_email_address']); ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo sprintf(LABEL_WEBSITE, $referrers[$selected]['referrer_homepage']); ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_PHONE . ' ' . $referrers[$selected]['customers_telephone']; ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_PAYMENT_TYPE . ' ' . $payment_types[$referrers[$selected]['referrer_payment_type']]['text'] . (($referrers[$selected]['referrer_payment_type_detail'] == '') ? '' : (' (' . $referrers[$selected]['referrer_payment_type_detail'] . ')')); ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo ($referrercount > 0) ? ('<a href="' . zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . '&mode=details' . ((get_var ('page') == '') ? '' : ('&page=' . (int)get_var ('page'))), 'NONSSL') . '">' . zen_image_button('button_details.gif', IMAGE_DETAILS) . '</a>') : '&nbsp;'; /*v2.1.0c*/ ?></td></tr>
      </table>
     </td>
    </tr>
   </table>
  </td>
 </tr>
<?php

} elseif ($mode == 'details' || $mode == TEXT_APPROVE || $mode == TEXT_BAN || $mode == TEXT_UNBAN || $mode == TEXT_PAY_SELECTED || $mode == TEXT_UPDATE) { //-v2.7.0c

?>
 <tr>
  <td>
   <table width="100%">
    <tr><td width="100%" class="formAreaTitle"><br /><?php echo TEXT_REFERRER_INFO; ?></td></tr>
    <tr>
     <td class="formArea">
      <table width="100%">
       <tr>
        <td valign="top"><?php echo LABEL_NAME_ADDRESS; ?></td>
        <td><?php echo nl2br(zen_address_label($referrers[$selected]['customers_id'], zen_get_customers_address_primary($referrers[$selected]['customers_id']))); ?></td>
       </tr>
       <tr>
        <td><?php echo HEADING_WEBSITE . ':'; ?></td>
        <td><a href="<?php echo 'http://' . $referrers[$selected]['referrer_homepage']; /*v2.4.1c*/ ?>" target="_blank"><?php echo $referrers[$selected]['referrer_homepage']; ?></a></td>
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
    <tr><td class="formAreaTitle"><br /><?php echo TEXT_STATUS; ?></td></tr>
    <tr>
     <td class="formArea">
<?php
$commission_to_pay = floatval($referrers[$selected]['status_breakdown'][0]['unpaid_commission']); /*v2.1.0a*/

//-bof-v2.1.0c: Set submit-button disabled flags
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
//-eof-v2.1.0c

echo zen_draw_form('referrers', FILENAME_REFERRERS, '', 'post', '', true);
if (get_var ('page') != '') {
  echo zen_draw_hidden_field ('page', (int)get_var ('page'));
}
?>
      <input type="hidden" name="referrer" value="<?php echo $referrers[$selected]['customers_id']; ?>" />

      <table width="100%">
      
       <tr>
        <td valign="top"><?php echo LABEL_APPROVED; ?></td>
        <td valign="top"><?php echo ($referrers[$selected]['referrer_approved'] == 1) ? TEXT_YES : '<span class="alert">' . TEXT_NO . '</span>'; ?></td>
        <td><input type="submit" name="mode" value="<?php echo TEXT_APPROVE; /*v2.1.0c*/ ?>"<?php echo $approve_disabled; /*v2.1.0a*/ ?> /></td>
       </tr>
       
       <tr>
        <td valign="top"><?php echo LABEL_BANNED; ?></td>
        <td valign="top"><?php echo ($referrers[$selected]['referrer_banned'] == 1) ? '<span class="alert">' . TEXT_YES . '</span>' : TEXT_NO; ?></td>
        <td><input type="submit" name="mode" value="<?php echo ($referrers[$selected]['referrer_banned'] == 1) ? TEXT_UNBAN : TEXT_BAN; ?>"<?php echo $ban_disabled; /*v2.1.0a*/ ?> /></td>
       </tr>
       
       <tr>
        <td valign="top"><?php echo LABEL_UNPAID_COMMISSION; ?></td>
        <td valign="top"><?php echo $currencies->format($commission_to_pay); /*v2.1.0c*/ ?></td>
        <td><input type="submit" name="mode" value="<?php echo TEXT_PAY; /*v2.1.0c*/ ?>"<?php echo $pay_disabled; /*v2.1.0a*/ ?> /></td>
       </tr>
       
       <tr>
        <td valign="top"><?php echo LABEL_CURRENT_COMMISSION_RATE; ?></td>
        <td valign="top"><input type="text" size="5" value="<?php echo $referrers[$selected]['referrer_commission'] * 100; ?>" name="commission" />%</td>
        <td><input type="submit" name="mode" value="<?php echo TEXT_UPDATE; /*v2.1.0c*/ ?>"<?php echo $update_disabled; /*v2.1.0a*/ ?> /></td>
       </tr>
      
       <tr>
        <td valign="top"><?php echo LABEL_PAYMENT_TYPE; ?></td>
<?php
$payment_type_selections = array ();
foreach ($payment_types as $type_id => $type_info) {
  $payment_type_selections[] = array ( 'id' => $type_id, 'text' => $type_info['text'] );
  
}
$referrer_payment_type = (isset ($_POST['referrer_payment_type'])) ? $_POST['referrer_payment_type'] : $referrers[$selected]['referrer_payment_type'];
$referrer_payment_type_detail = (isset ($_POST['referrer_payment_type_detail'])) ? $_POST['referrer_payment_type_detail'] : $referrers[$selected]['referrer_payment_type_detail'];
?>
        <td valign="top"><?php echo zen_draw_pull_down_menu ('referrer_payment_type', $payment_type_selections, $referrer_payment_type, 'id="payment-type" onchange="showHideDetails();"'); ?></td>
        <td><input type="submit" name="mode" value="<?php echo TEXT_UPDATE_PAYMENT_TYPE; ?>"<?php echo $update_disabled; ?> /></td>
       </tr>
       
       <tr id="payment-details">
        <td valign="top" id="payment-details-name">&nbsp;</td>
        <td valign="top"><?php echo zen_draw_input_field ('referrer_payment_type_detail', $referrer_payment_type_detail); ?></td>
        <td>&nbsp;</td>
       </tr>
       
      </table>
     </form>
     </td>
    </tr>
    <tr>
     <td width="100%">
      <br>
<?php
echo zen_draw_form('dateform', FILENAME_REFERRERS, '', 'get', '', true) . zen_draw_hidden_field ('referrer', $referrers[$selected]['customers_id']) . zen_draw_hidden_field ('mode', 'details');
if (get_var ('page') != '') {
  echo zen_draw_hidden_field ('page', (int)get_var ('page'));
  
}
?>
      <table width="100%">
       <tr>
        <td class="formAreaTitle"><?php echo TEXT_ORDER_HISTORY; ?></td>
        <td align="right"><?php echo TEXT_FROM; ?>
         <input type="hidden" name="start" value="<?php echo $activity_begin; ?>" />
         <select onchange="document.dateform.start.value = this.options[this.selectedIndex].value; document.dateform.submit();">
<?php
$begin = getdate($activity_begin);
$end = getdate($activity_end);

$bound = ($begin['year'] == $today['year']) ? $today['mon'] : 12;

for ($i = 1; $i <= $bound; ++$i ) {
  $timetemp = mktime(0, 0, 0, $i, 1, $begin['year']);
  printf("<option value=\"%u\"%s>%s</option>\n", $timetemp, ($i == $begin['mon']) ? ' selected="selected"' : '', date('F j', $timetemp ));
}
?>
         </select>
         <select onchange="document.dateform.start.value = this.options[this.selectedIndex].value; document.dateform.submit();">
<?php
for( $i = $today['year'] - 9; $i <= $today['year']; ++$i ) {
  $timetemp = mktime(0, 0, 0, $begin['mon'], 1, $i);

  printf("<option value=\"%u\"%s>%s</option>\n", $timetemp, ( $i == $begin['year'] ) ? ' selected="selected"' : '', date( 'Y', $timetemp ));
}
?>
         </select><?php echo TEXT_TO; ?>
         <input type="hidden" name="end" value="<?php echo $activity_end; ?>" />
         <select onchange="document.dateform.end.value = this.options[this.selectedIndex].value; document.dateform.submit();">
<?php
$bound = ( $end['year'] == $today['year'] ) ? $today['mon'] : 12;

for( $i = 1; $i <= $bound; ++$i ) {
  $timetemp = mktime(0, 0, 0, $i + 1, 0, $end['year']);

  printf("<option value=\"%u\"%s>%s</option>\n", $timetemp, ($i == $end['mon']) ? ' selected="selected"' : '', date( 'F j', $timetemp ));
}
?>
         </select>
         <select onchange="document.dateform.end.value = this.options[this.selectedIndex].value; document.dateform.submit();">
<?php
$firstvalue = max($begin['year'], $today['year'] - 9);

for( $i = $firstvalue; $i <= $today['year']; ++$i ) {
  $timetemp = mktime(0, 0, 0, $end['mon'] + 1, 0, $i);

  printf("<option value=\"%u\"%s>%s</option>\n", $timetemp, ( $i == $end['year'] ) ? ' selected="selected"' : '', date( 'Y', $timetemp ) );
}
?>
         </select>
        </td>
       </tr>
      </table>
     </form>
     </td>
    </tr>
    <tr>
     <td class="formArea">
      <table width="100%" cellspacing="0" cellpadding="3">
       <tr>
        <td class="historyHeader"><?php echo HEADING_ORDER_ID; ?></td><?php /*v2.3.0a*/ ?>
        <td class="historyHeader"><?php echo HEADING_ORDER_DATE; ?></td>
<?php
//-bof-v2.1.0a: Add order totals exclusions columns
reset($orders_status_names);
foreach ($orders_status_names as $order_status => $status_name) {
  if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $status_name != '' && $status_name != TEXT_NONCOMMISSIONABLE) continue;  /*v2.5.0a*/
?>
        <td class="historyHeader"><?php echo HEADING_ORDER_TOTAL . $status_name; ?></td>
<?php
}
//-eof-v2.1.0a
?>
        <td class="historyHeader"><?php echo HEADING_COMMISSION_RATE; ?></td>
<?php
//-bof-v2.1.0a: Add order totals exclusions columns
reset($orders_status_names);
foreach ($orders_status_names as $order_status => $status_name) {
  if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $status_name != '' && $status_name != TEXT_NONCOMMISSIONABLE) continue;  /*v2.5.0a*/
?>
        <td class="historyHeader"><?php echo HEADING_COMMISSION_TOTAL . $status_name; ?></td>
<?php
}
//-eof-v2.1.0a
?>
        <td class="historyHeader"><?php echo HEADING_COMMISSION_PAY_DATE; ?></td>
        <td class="historyHeader"><?php echo HEADING_COMMISSION_PAID_VIA; ?></td>
       </tr>
<?php
$toggle = 'A';

foreach( $referrers[$selected]['orders'] as $order ) {
  $total = floatval( $order['order_total'] ) - floatval( $order['value'] );
  $commission = floatval( $order['commission_rate'] );
  $orders_status = (int)$order['orders_status']; /*v2.1.0a*/
  $orders_status = ( (!$order['ispaid']) && in_array($orders_status, $order_status_exclusions) ) ? $orders_status : 0; /*v2.5.0c*/

  if( $total < 0 ) {
    $total = 0;
  }
?>
       <tr>
        <td class="history<?php echo $toggle; ?>"><a href="<?php echo zen_href_link(FILENAME_ORDERS, 'oID=' . $order['orders_id'] . '&action=edit', 'NONSSL'); ?>"><?php echo $order['orders_id']; ?></a></td><?php /*v2.3.0a*/ ?>
        <td class="history<?php echo $toggle; ?>"><?php echo $order['date_purchased']; ?></td>
<?php
//-bof-v2.1.0a: Add order totals exclusions columns
reset($orders_status_names);
foreach($orders_status_names as $current_orders_status => $status_name) {
//-bof-a-v2.5.0
  if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes') {
    if ($current_orders_status != '*' && $current_orders_status != 0) continue;
    if ($orders_status !== 0) {
      $orders_status = '*';
    }
  }
//-eof-a-v2.5.0
?>
        <td class="center history<?php echo $toggle; ?>"><?php echo $currencies->format( ($current_orders_status === $orders_status) ? $total : 0 ); /*v2.5.0c*/ ?></td>
<?php
}
//-eof-v2.1.0a
?>
        <td class="center history<?php echo $toggle; ?>"><?php echo $commission * 100; ?>%</td>  <?php /*v2.5.0c, add 'center' */ ?>
<?php
//-bof-v2.1.0a: Add order totals exclusions columns
reset($orders_status_names);
foreach($orders_status_names as $current_orders_status => $status_name) {
//-bof-a-v2.5.0
  if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes') {
    if ($current_orders_status !== 0 && $current_orders_status != '*') continue;
  }
//-eof-a-v2.5.0
  $current_total = ($current_orders_status === $orders_status) ? $total : 0;  /*v2.5.0c*/
  
//-bof-v2.7.0c
  if ($status_name == '' && $order['ispaid'] && $order['commission_paid_amount'] != 0) {
    $commission_value = $order['commission_paid_amount'];
    
  } else {
    $commission_value = $commission * $current_total;
    
  }
?>
        <td class="center history<?php echo $toggle; ?>"><?php echo $currencies->format ( $commission_value ); ?></td> <?php /*v2.5.0c, add 'center' */ ?>
<?php
//-eof-v2.7.0c
}
//-eof-v2.1.0a
  $commission_payment_type_detail = '';
  $commission_payment_type = '&nbsp;';
  if ($order['commission_paid'] == '0001-01-01 00:00:00') {
    $commission_paid = TEXT_UNPAID;
    
  } else {
    $commission_paid = $order['commission_paid'];
    if (!isset ($payment_types[$order['commission_payment_type']])) {
      $commission_payment_type = PAYMENT_TYPE_UNKNOWN;
      
    } else {
      $commission_payment_type = $payment_types[$order['commission_payment_type']]['text'];
      if (zen_not_null ($order['commission_payment_type_detail'])) {
        $commission_payment_type_detail = ' (' . $order['commission_payment_type_detail'] . ')';
        
      }
    }
  }
?>
        <td class="history<?php echo $toggle; ?>"><?php echo $commission_paid; ?></td>
        <td class="history<?php echo $toggle; ?>"><?php echo $commission_payment_type . $commission_payment_type_detail; ?></td>
       </tr>
<?php
  $toggle = ($toggle == 'A') ? 'B' : 'A';
}

?>
       <tr>
        <td class="historyFooter" colspan="2"><?php echo HEADING_TOTALS; ?></td><?php /*v2.3.0c*/ ?>
<?php
//-bof-v2.1.0a: Add order totals exclusions columns
reset($referrers[$selected]['status_breakdown']);
foreach($referrers[$selected]['status_breakdown'] as $order_status => $current_totals) {  /*v2.5.0c*/
  if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $order_status !== 0 && $order_status != '*') continue;  /*v2.5.0a*/
?>
        <td class="center historyFooter"><?php echo $currencies->format( $current_totals['total'] ); ?></td>  <?php /*v2.5.0c, add 'center' */ ?>
<?php
}
//-eof-v2.1.0a
?>
	      <td class="historyFooter">&nbsp;</td>
<?php
//-bof-v2.1.0a: Add order totals exclusions columns
reset($referrers[$selected]['status_breakdown']);
foreach ($referrers[$selected]['status_breakdown'] as $order_status => $current_totals) {  /*v2.5.0c*/
  if (SNAP_AFFILIATE_COMBINE_EXCLUSIONS == 'Yes' && $order_status !== 0 && $order_status != '*') continue;  /*v2.5.0a*/
?>
        <td class="center historyFooter"><?php echo $currencies->format( $current_totals['commission_total'] ); ?></td> <?php /*v2.5.0c, add 'center' */ ?>
<?php
}
//-eof-v2.1.0a
?>
        <td class="historyFooter" colspan="2">&nbsp;</td>
       </tr>

      </table>
     </td>
    </tr>
    <tr>
     <td width="100%" align='right'>
      <br>
      <?php echo "<a href='" . zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . ((get_var ('page') != '') ? ('&page=' . (int)get_var ('page')) : ''), 'NONSSL') . "'>" . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . "</a>"; ?>
     </td>
    </tr>
   </table>
  </td>
 </tr>
<?php
//-bof-v2.7.0
} elseif ($mode == TEXT_PAY) {
?>
  <tr>
    <td><table width="100%">
      <tr><td width="100%" class="formAreaTitle"><br /><?php echo TEXT_REFERRER_INFO; ?></td></tr>
      <tr>
        <td class="formArea"><table width="100%">
         <tr>
          <td valign="top"><?php echo LABEL_NAME_ADDRESS; ?></td>
          <td><?php echo nl2br(zen_address_label($referrers[$selected]['customers_id'], zen_get_customers_address_primary($referrers[$selected]['customers_id']))); ?></td>
         </tr>
         <tr>
          <td><?php echo HEADING_WEBSITE . ':'; ?></td>
          <td><a href="<?php echo 'http://' . $referrers[$selected]['referrer_homepage']; /*v2.4.1c*/ ?>" target="_blank"><?php echo $referrers[$selected]['referrer_homepage']; ?></a></td>
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
        </table></form></td>
      </tr>
      <tr><td width="100%" class="formAreaTitle"><br /><?php echo TEXT_CHOOSE_COMMISSIONS; ?></td></tr>
<?php
  if ($pay_message != '') {
?>
      <tr><td width="100%" class="error"><?php echo $pay_message; ?></td></tr>
<?php
  }
?>
      <tr>
        <td class="formArea">
<?php 
  echo zen_draw_form('referrers', FILENAME_REFERRERS, '', 'post', '', true) . zen_draw_hidden_field ('referrer', $referrers[$selected]['customers_id']) . zen_draw_hidden_field ('commission_payment_type', $referrers[$selected]['referrer_payment_type']) . zen_draw_hidden_field ('commission_payment_type_detail', $referrers[$selected]['referrer_payment_type_detail']);
  if (get_var ('page') != '') {
    echo zen_draw_hidden_field ('page', (int)get_var ('page'));
    
  }
?>
        <table width="100%" cellspacing="0" cellpadding="3">
          <tr>
            <td class="historyHeader center"><?php echo HEADING_CHOOSE; ?></td>
            <td class="historyHeader center"><?php echo HEADING_ORDER_ID; ?></td>
            <td class="historyHeader"><?php echo HEADING_ORDER_DATE; ?></td>
            <td class="historyHeader center"><?php echo HEADING_ORDER_TOTAL; ?></td>
            <td class="historyHeader center"><?php echo HEADING_COMMISSION_RATE; ?></td>
            <td class="historyHeader center"><?php echo HEADING_CALCULATED_COMMISSION; ?></td>
            <td class="historyHeader center"><?php echo HEADING_COMMISSION_TO_PAY; ?></td>
          </tr>
<?php
  $toggle = 'A';
  $customers_id = $referrers[$selected]['customers_id'];
  foreach ($referrers[$selected]['orders'] as $order) {
    if (!$order['ispaid']) {
      $commission_id = $order['commission_id'];
      $commissionable_total = $order['order_total'] - $order['value'];
      $commission = number_format ($commissionable_total * $order['commission_rate'], $currencies->get_decimal_places (DEFAULT_CURRENCY));
      $choose = (isset ($_POST['payList'][$commission_id]));
      $commission_to_pay = ($choose) ? $_POST['commission'][$commission_id] : (string)$commission;
?>
          <tr>
            <td class="history<?php echo $toggle; ?> center"><?php echo zen_draw_checkbox_field ("payList[$commission_id]", false, $choose, '', 'class="cBox"') . zen_draw_hidden_field ("calculated[$commission_id]", (string)$commission); ?></td>
            <td class="history<?php echo $toggle; ?> center"><a href="<?php echo zen_href_link (FILENAME_ORDERS, 'oID=' . $order['orders_id'] . '&action=edit', 'NONSSL'); ?>"><?php echo $order['orders_id']; ?></a></td>
            <td class="history<?php echo $toggle; ?>"><?php echo $order['date_purchased']; ?></td>
            <td class="center history<?php echo $toggle; ?>"><?php echo $currencies->format ($commissionable_total); ?></td>
            <td class="center history<?php echo $toggle; ?>"><?php echo $order['commission_rate'] * 100; ?>%</td>
            <td class="center history<?php echo $toggle; ?>"><?php echo $currencies->format ($commission); ?></td>
            <td class="center history<?php echo $toggle; ?>"><?php echo zen_draw_input_field ("commission[$commission_id]", $commission_to_pay); ?></td>
          </tr>
<?php
      $toggle = ($toggle == 'A') ? 'B' : 'A';
      
    }
  }
?>
          <tr>
            <td colspan="7" align="right" class="history<?php echo $toggle; ?>"><input type="submit" name="mode" value="<?php echo TEXT_PAY_SELECTED; ?>" /></td>
          </tr>
        </table></form></td>
      </tr>
      <tr>
        <td width="100%" align="right">
          <br><?php echo "<a href='" . zen_href_link (FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . '&mode=details' . ((get_var ('page') != '') ? ('&page=' . (int)get_var ('page')) : '')) . "'>" . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . "</a>"; ?>
        </td>
      </tr>
    </table></td>
  </tr>
<?php
}
//-eof-v2.7.0
?>
</table>
<?php
require(DIR_WS_INCLUDES . 'footer.php');
?>
</body>
</html>
<?php
require('includes/application_bottom.php');