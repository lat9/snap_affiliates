<?php
// -----
// Provide the staging for addition of the 'updated_by' field in the orders_status_history table
//
//  CHANGE HISTORY:
//  20130730 - 0.0.3:
//    - Modified parameter input order for zen_update_orders_history (moved $message to parm 2).
//    - Made $osh_sql global, to allow admin notifier-observers (pre ZC v1.6.0) to modify the inputs (like Ty Package Tracker).
//    - Added verification of the $customer_notified variable, so the callers don't have to.
//    - Modified the return values to make a distinction between "not updated" and "no order found".
//    - Updated orders_status name always returned "N/A"
//  20130805 - 0.0.4:
//    - Pass the order_id value on the old/new orders status notification
//    - Added ZEN_UPDATE_ORDERS_HISTORY_PRE_EMAIL notifier to allow plugins to add to the email message to be sent to the customer.
//    - "unset" the global variables created by the zen_update_orders_history function
//  20130902 - 1.0.0
//    - Add an optional input to allow an override of the orders-status update email's subject line.
//    - Add an optional input to allow an override of the admin email-copy address.
//    - BUGFIX: Orders-status emails indicated a status change event if the status hasn't changed.
//    - BUGFIX: Orders-status change notifier happened before orders status adjusted.
//    - Added an OSH_ prefix to all language constants used, to make sure that those associated with this plugin are used.
//  20130907 - 1.0.1
//    - BUGFIX: Incorrect variable referenced after email comment notification
//    - Allow email message to be updated by observer prior to processing.
//  20131005 - 1.1.0
//    - Allow admin-only messages to be sent.
//  20150528 - 1.2.1
//    - Need zen_db_prepare_output around order-status comments to properly convert the CRLF combinations.
//
define('OSH_UPDATED_BY_VERSION', '1.2.1');
define('OSH_UPDATED_BY_DATE', '2015-05-28');

// -----
// 1) Add the updated_by column to the orders_status_history table, if it doesn't already exist.
//
if ($sniffer->field_exists(TABLE_ORDERS_STATUS_HISTORY, 'updated_by') === false) {
  $db->Execute("ALTER TABLE " . TABLE_ORDERS_STATUS_HISTORY . " ADD updated_by varchar(45) NOT NULL default ''");

}

// -----
// 2) If not already present, create an instance of the function that provides the "standard" updated_by
//    contents for an admin-related update.
//
if (IS_ADMIN_FLAG === true && !function_exists('zen_updated_by_admin')) {
  function zen_updated_by_admin($admin_id = '') {
    if ($admin_id === '') {
      $admin_id = $_SESSION['admin_id'];
    }
    
    return zen_get_admin_name($admin_id) . " [$admin_id]";
  }
}

// -----
// 3) If not already present, create an instance of the function that provides common handling for processes
//    that want to add an orders_status_history record for a specified order.
//
if (!function_exists('zen_update_orders_history')) {
  // -----
  // Inputs:
  // - $order_id ................ The order for which the status record is to be created
  // - $updated_by .............. If non-null, the specified value will be used for the like-named field.  Otherwise,
  //                              the value will be calculated based on some defaults.
  // - $orders_status ........... The orders_status value for the update.  If set to -1, no change in the status value was detected.
  // - $notify_customer ......... Identifies whether the history record is sent via email and visible to the customer via the "account_history_info" page:
  //                               0 ... No emails sent, customer can view on "account_history_info"
  //                               1 ... Email sent, customer can view on "account_history_info"
  //                              -1 ... No emails sent, comments and status-change hidden from customer view
  //                              -2 ... Email sent only to configured admins; status-change hidden from customer view
  // - $message ................. The comments associated with the history record, if non-blank.
  // - $email_include_message ... Identifies whether (true) or not (false) to include the status message ($message) in any email sent.
  // - $email_subject ........... If specified, overrides the default email subject line.
  // - $send_xtra_mail_to ....... If specified, overrides the "standard" database settings SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_STATUS and
  //                              SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO.
  //
  // Returns:
  // - $osh_id ............ A value > 0 if the record has been written (the orders_status_history_id number)
  //                        -2 if no order record was found for the specified $orders_id
  //                        -1 if no status change was detected (i.e. no record written).
  //
  function zen_update_orders_history($orders_id, $message = '', $updated_by = null, $orders_status = -1, $notify_customer = -1, $email_include_message = true, $email_subject = '', $send_xtra_emails_to = '') {  /*v1.0.0c*/
    global $db, $zco_notifier, $osh_sql;
    global $osh_additional_comments;  /*v0.0.4a*/
    $osh_id = -1;
    $orders_id = (int)$orders_id;
    $osh_info = $db->Execute("SELECT customers_name, customers_email_address, orders_status, date_purchased FROM " . TABLE_ORDERS . " WHERE orders_id = $orders_id" );
    if ( $osh_info->EOF ) {
      $osh_id = -2;
      
//-bof-c-v1.0.1
    } else {
      $osh_additional_comments = '';
      if (IS_ADMIN_FLAG === true && $email_include_message === true) {
        $zco_notifier->notify('ZEN_UPDATE_ORDERS_HISTORY_PRE_EMAIL', array( 'message' => $message ) );
        if ($osh_additional_comments != '') {
          if (zen_not_null($message)) {
            $osh_additional_comments = "\n\n" . $osh_additional_comments;
          }
        }
      }
  
      if (($orders_status != -1 && $osh_info->fields['orders_status'] != $orders_status) || zen_not_null($message)) {
//-eof-c-v1.0.1
        if ($orders_status == -1) {
          $orders_status = $osh_info->fields['orders_status'];
          
        }
        $zco_notifier->notify('ZEN_UPDATE_ORDERS_HISTORY_STATUS_VALUES', array ( /*-bof-a-v0.0.4*/ 'orders_id' => $orders_id, /*-eof-a-v0.0.4*/ 'new' => $orders_status, 'old' => $osh_info->fields['orders_status'] ));  /*v1.0.0m*/
        

        $db->Execute( "UPDATE " . TABLE_ORDERS . " SET orders_status = '" . zen_db_input($orders_status) . "', last_modified = now() WHERE orders_id = $orders_id");
        
        $notify_customer = (isset($notify_customer) && ($notify_customer == 1 || $notify_customer == -1 || $notify_customer == -2)) ? $notify_customer : 0; /*v1.1.0c*/
        
        if (IS_ADMIN_FLAG === true && ($notify_customer == 1 || $notify_customer == -2)) {
          $status_name = $db->Execute("SELECT orders_status_name FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_id = " . (int)$orders_status . " AND language_id = " . (int)$_SESSION['languages_id']);
          $orders_status_name = ($status_name->EOF) ? 'N/A' : $status_name->fields['orders_status_name'];
          $email_comments = ((zen_not_null($message) || zen_not_null ($osh_additional_comments)) && $email_include_message === true) ? (OSH_EMAIL_TEXT_COMMENTS_UPDATE . $message . $osh_additional_comments . "\n\n") : '';

//-bof-a-v1.0.0
          if ($orders_status != $osh_info->fields['orders_status']) {
            $status_text = OSH_EMAIL_TEXT_STATUS_UPDATED;
            $status_value_text = sprintf(OSH_EMAIL_TEXT_STATUS_CHANGE, zen_get_orders_status_name($osh_info->fields['orders_status']), $orders_status_name);
            
          } else {
            $status_text = OSH_EMAIL_TEXT_STATUS_NO_CHANGE;
            $status_value_text = sprintf(OSH_EMAIL_TEXT_STATUS_LABEL, $orders_status_name );
          }
//-eof-a-v1.0.0
          //send emails
          $email_text =
            STORE_NAME . ' ' . OSH_EMAIL_TEXT_ORDER_NUMBER . ' ' . $orders_id . "\n\n" .
            OSH_EMAIL_TEXT_INVOICE_URL . ' ' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $orders_id, 'SSL') . "\n\n" .
            OSH_EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($osh_info->fields['date_purchased']) . "\n\n" .
            strip_tags($email_comments) .
            $status_text . $status_value_text .  /*v1.0.0c*/
            OSH_EMAIL_TEXT_STATUS_PLEASE_REPLY;

          $html_msg['EMAIL_CUSTOMERS_NAME']    = $osh_info->fields['customers_name'];
          $html_msg['EMAIL_TEXT_ORDER_NUMBER'] = OSH_EMAIL_TEXT_ORDER_NUMBER . ' ' . $orders_id;
          $html_msg['EMAIL_TEXT_INVOICE_URL']  = '<a href="' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $orders_id, 'SSL') .'">'.str_replace(':','', OSH_EMAIL_TEXT_INVOICE_URL).'</a>';
          $html_msg['EMAIL_TEXT_DATE_ORDERED'] = OSH_EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($osh_info->fields['date_purchased']);
          $html_msg['EMAIL_TEXT_STATUS_COMMENTS'] = nl2br($email_comments);
          $html_msg['EMAIL_TEXT_STATUS_UPDATED'] = str_replace('\n','', $status_text);  /*v1.0.0c*/
          $html_msg['EMAIL_TEXT_STATUS_LABEL'] = str_replace('\n','', $status_value_text);  /*v1.0.0c*/
          $html_msg['EMAIL_TEXT_NEW_STATUS'] = $orders_status_name;
          $html_msg['EMAIL_TEXT_STATUS_PLEASE_REPLY'] = str_replace('\n','', OSH_EMAIL_TEXT_STATUS_PLEASE_REPLY);
          $html_msg['EMAIL_PAYPAL_TRANSID'] = '';

          if ($notify_customer == 1) {  /*v1.1.0a*/
            zen_mail($osh_info->fields['customers_name'], $osh_info->fields['customers_email_address'], ((zen_not_null($email_subject)) ? $email_subject : (OSH_EMAIL_TEXT_SUBJECT . ' #' . $orders_id)), $email_text, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status');  /*v1.0.0c*/
            
          }  /*v1.1.0a*/

          // PayPal Trans ID, if any
          $sql = "SELECT txn_id, parent_txn_id FROM " . TABLE_PAYPAL . " WHERE order_id = :orderID ORDER BY last_modified DESC, date_added DESC, parent_txn_id DESC, paypal_ipn_id DESC ";
          $sql = $db->bindVars($sql, ':orderID', $orders_id, 'integer');
          $result = $db->Execute($sql);
          if ($result->RecordCount() > 0) {
            $email_text .= "\n\n" . ' PayPal Trans ID: ' . $result->fields['txn_id'];
            $html_msg['EMAIL_PAYPAL_TRANSID'] = $result->fields['txn_id'];
          }

          //send extra emails
          if (zen_not_null($send_xtra_emails_to) || (SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_STATUS == '1' and SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO != '')) {  /*v1.0.0c*/
            zen_mail('', ((zen_not_null($send_xtra_emails_to)) ? $send_xtra_emails_to : SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO), SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_SUBJECT . ' ' . ((zen_not_null($email_subject)) ? $email_subject : (OSH_EMAIL_TEXT_SUBJECT . ' #' . $orders_id)), $email_text, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status_extra');  /*v1.0.0c*/
          }
          
        }
        
        if (!zen_not_null($updated_by)) {
          if (IS_ADMIN_FLAG === true && isset($_SESSION['admin_id'])) {
            $updated_by = zen_updated_by_admin();
            
          } elseif (IS_ADMIN_FLAG === false && isset($_SESSION['customers_id'])) {
            $updated_by = '';
            
          } else {
            $updated_by = 'N/A';
            
          }
        }
        
        $osh_sql = array ( 'orders_id' => $orders_id,
                           'orders_status_id' => zen_db_input($orders_status),
                           'date_added' => 'now()',
                           'customer_notified' => zen_db_input($notify_customer),
                           'comments' => zen_db_input (zen_db_prepare_input ($message)),
                           'updated_by' => zen_db_input($updated_by)
                           );
                           
        $zco_notifier->notify('ZEN_UPDATE_ORDERS_HISTORY_BEFORE_INSERT');
        
        zen_db_perform (TABLE_ORDERS_STATUS_HISTORY, $osh_sql);
        unset($osh_sql);  /*v0.0.4a*/
        $osh_id = $db->Insert_ID();

      }  /*v1.0.1a*/
      
    }
    
    return $osh_id;
  }
}