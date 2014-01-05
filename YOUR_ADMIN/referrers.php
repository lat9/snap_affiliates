<?php
// +----------------------------------------------------------------------+
// | Snap Affiliates for Zen Cart                                         |
// +----------------------------------------------------------------------+
// | Copyright (c) 2013, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// |                                                                      |
// | Original: Copyright (c) 2009 Michael Burke                           |
// | http://www.filterswept.com                                           |
// |                                                                      |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.       |
// +----------------------------------------------------------------------+

function get_var($name) {
  $result = '';

  if( isset( $_GET[$name] ) ) {
    $result = $_GET[$name];

  } else if( isset( $_POST[$name] ) ) {
    $result = $_POST[$name];
  }

  return $result;
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

$query = "SELECT c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address, c.customers_telephone, r.referrer_customers_id, r.referrer_key, r.referrer_homepage, r.referrer_approved, r.referrer_banned, r.referrer_commission
            FROM " . TABLE_CUSTOMERS ." c, " . TABLE_REFERRERS . " r 
            WHERE c.customers_id = r.referrer_customers_id 
            ORDER BY c.customers_lastname";  /*v2.1.0c*/
$referrer_split = new splitPageResults($_GET['page'], SNAP_MAX_REFERRER_DISPLAY, $query, $referrer_query_numrows);  /*v2.1.0a*/
          
$referrerResults = $db->Execute($query);
$referrers = array();
$selectedID = get_var('referrer');
$mode = get_var('mode');
$selected = 0;
$referrersdisplayed = 0;

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
  $referrersdisplayed++;

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

  $query = "SELECT o.orders_id, o.date_purchased, o.order_total, c.commission_rate, c.commission_paid, o.orders_status
              FROM " . TABLE_ORDERS . " o, " . TABLE_COMMISSION . " c
             WHERE c.commission_referrer_key = '" . $referrers[$idx]['referrer_key'] . "'
               AND o.orders_id = c.commission_orders_id"; /*v2.1.0c*/

  $orderResults = $db->Execute($query);
  $orders = array();

  // -----
  // ... processing each of the referrer's order information.
  //
  while (!$orderResults->EOF) {
    $isCommissionPaid = ($orderResults->fields['commission_paid'] == '0000-00-00 00:00:00') ? false : true;  /*v2.5.0a*/
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
      $ordersCommission = $commission * $total;
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
    
  case TEXT_PAY:
    $commission_paid = $referrers[$selected]['status_breakdown'][0]['unpaid_commission']; /*v2.1.0a*/
    $referrers[$selected]['status_breakdown'][0]['unpaid_commission'] = 0; /*v2.1.0c*/
    
    $now = date("Y-m-d H:i:s", time());

//-bof-c-v2.1.0: Don't show commission paid if the order_status value for the current order is
// one of the "excluded" ones.  Note that the 'unpaid_commission' has already factored this in!
//
    reset($referrers[$selected]['orders']);
    foreach($referrers[$selected]['orders'] as &$current_order) {
      if ( $current_order['commission_paid'] == '0000-00-00 00:00:00' && !array_key_exists((int)$current_order['orders_status'], $orders_status_names) ) {
        $query = "UPDATE " . TABLE_COMMISSION . " SET commission_paid = NOW() WHERE commission_orders_id = " . (int)$current_order['orders_id'];
        $db->Execute($query);
        
        $current_order['commission_paid'] = $now;
//-bof-a-v2.5.0
        // -----
        // Add a comment to the order's status-history table (hidden from the customer) to identify that the commission payment was made.
        // The order's current status is unchanged.
        //
        $status_comment = sprintf(TEXT_ORDERS_STATUS_PAID, $currencies->format(floatval( ($current_order['order_total'] - $current_order['value']) * $current_order['commission_rate'] )), $referrers[$selected]['customers_firstname'] . ' ' . $referrers[$selected]['customers_lastname']);
        zen_update_orders_history($current_order['orders_id'], $status_comment);
//-eof-a-v2.5.0

      }
    }
//-eof-c-v2.1.0

    send_notification_email (
      $referrers[$selected], 
      EMAIL_SUBJECT_PAID,
      sprintf(EMAIL_MESSAGE_PAID_TEXT, $currencies->format(floatval($commission_paid)), zen_catalog_href_link(FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link(FILENAME_CONTACT_US, '', 'NONSSL')),
      sprintf(EMAIL_MESSAGE_PAID_HTML, $currencies->format(floatval($commission_paid)), zen_catalog_href_link(FILENAME_LOGIN, '', 'SSL'), zen_catalog_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'), zen_catalog_href_link(FILENAME_CONTACT_US, '', 'NONSSL'))); /*v2.1.0c*/

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
</style>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css" media="print" href="includes/stylesheet_print.css">
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script type="text/javascript" src="includes/menu.js"></script>
<script type="text/javascript" src="includes/general.js"></script>
<script type="text/javascript">
  <!--
  function init()
  {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
      var kill = document.getElementById('hoverJS');
      kill.disabled = true;
    }
  }
  // -->
</script>
</head>
<body onload="init();">

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
        <td class="dataTableHeadingContent"><?php echo HEADING_WEBSITE; ?></td>
        <td class="dataTableHeadingContent"><?php echo HEADING_APPROVED; ?></td>
        <td class="dataTableHeadingContent"><?php echo HEADING_BANNED; ?></td>
        <td class="dataTableHeadingContent"><?php echo HEADING_UNPAID_TOTAL; ?></td>
        <td class="dataTableHeadingContent"><?php echo HEADING_COMMISSION_RATE; ?></td>
       </tr>
<?php
foreach ($referrers as $referrer) {
  $current_selection = ($referrers[$selected] == $referrer);
?>
       <tr class="dataTableRow<?php echo ($current_selection) ? 'Selected' : ''; ?>" onmouseover="rowOverEffect(this);" onmouseout="rowOutEffect(this);" onclick="document.location.href='<?php echo zen_href_link(FILENAME_REFERRERS, zen_get_all_get_params(array('action')) . 'referrer=' . $referrer['customers_id'] . '&amp;mode=details', 'NONSSL'); ?>'">
        <td class="dataTableContent"><?php echo $referrer['customers_lastname']; ?></td>
        <td class="dataTableContent"><?php echo $referrer['customers_firstname']; ?></td>
        <td class="dataTableContent"><?php echo $referrer['referrer_homepage']; ?></td>
        <td class="dataTableContent"><?php echo ($referrer['referrer_approved'] == 1) ? TEXT_YES : '<span class="alert">' . TEXT_NO . '</span>'; ?></td>
        <td class="dataTableContent"><?php echo ($referrer['referrer_banned'] == 1) ? '<span class="alert">' . TEXT_YES . '</span>' : TEXT_NO; ?></td>
        <td class="dataTableContent"><?php echo $currencies->format($referrer['status_breakdown'][0]['unpaid_commission']); /*v2.1.0c*/ ?></td>
        <td class="dataTableContent"><?php echo $referrer['referrer_commission'] * 100 . '%'; ?></td>
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
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_ORDERS_TOTAL . ' ' . $currencies->format($referrers[$selected]['status_breakdown'][0]['total']); /*v2.1.0c*/ ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_UNPAID . ' ' . $currencies->format($referrers[$selected]['status_breakdown'][0]['unpaid_commission']); /*v2.1.0c*/ ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo sprintf(LABEL_EMAIL, $referrers[$selected]['customers_email_address']); ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo sprintf(LABEL_WEBSITE, $referrers[$selected]['referrer_homepage']); ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo LABEL_PHONE . ' ' . $referrers[$selected]['customers_telephone']; ?></td></tr>
       <tr><td class="infoBoxContent"><br /><?php echo ($referrercount > 0) ? ('<a href="' . zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'] . '&amp;mode=details', 'NONSSL') . '">' . zen_image_button('button_details.gif', IMAGE_DETAILS) . '</a>') : '&nbsp;'; /*v2.1.0c*/ ?></td></tr>
      </table>
     </td>
    </tr>
   </table>
  </td>
 </tr>
<?php

} elseif ($mode == 'details' || $mode == TEXT_APPROVE || $mode == TEXT_BAN || $mode == TEXT_UNBAN || $mode == TEXT_PAY || $mode == TEXT_UPDATE) {

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
?>
      <input type="hidden" name="referrer" value="<?php echo $referrers[$selected]['customers_id']; ?>" />
      <input type="hidden" name="mode" value="" />

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
       
      </table>
     </form>
     </td>
    </tr>
    <tr>
     <td width="100%">
      <br>
<?php
echo zen_draw_form('dateform', FILENAME_REFERRERS, '', 'get', '', true);
?>
      <input type="hidden" name="referrer" value="<?php echo $referrers[$selected]['customers_id']; ?>" />
      <input type="hidden" name="mode" value="details" />
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
?>
        <td class="center history<?php echo $toggle; ?>"><?php echo $currencies->format( $commission * $current_total ); ?></td> <?php /*v2.5.0c, add 'center' */ ?>
<?php
}
//-eof-v2.1.0a
?>
        <td class="history<?php echo $toggle; ?>"><?php echo ($order['commission_paid'] == '0000-00-00 00:00:00') ? TEXT_UNPAID : $order['commission_paid']; ?></td>
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
	      <td class="historyFooter">&nbsp;</td>
       </tr>

      </table>
     </td>
    </tr>
    <tr>
     <td width="100%" align='right'>
      <br>
      <?php echo "<a href='" . zen_href_link(FILENAME_REFERRERS, 'referrer=' . $referrers[$selected]['customers_id'], 'NONSSL') . "'>" . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . "</a>"; ?>
     </td>
    </tr>
   </table>
  </td>
 </tr>
<?php
}
?>
</table>
<?php
require(DIR_WS_INCLUDES . 'footer.php');
?>
</body>
</html>
<?php
require('includes/application_bottom.php');