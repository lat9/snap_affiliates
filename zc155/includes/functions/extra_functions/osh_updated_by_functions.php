<?php
// -----
// Provide the staging for addition of the 'updated_by' field in the orders_status_history table
//
define('OSH_UPDATED_BY_VERSION', '2.0.0');
define('OSH_UPDATED_BY_DATE', '2016-08-08');

// -----
// 1) Add the updated_by column to the orders_status_history table, if it doesn't already exist.
//
if (is_object ($sniffer) && $sniffer->field_exists(TABLE_ORDERS_STATUS_HISTORY, 'updated_by') === false) {
  $db->Execute("ALTER TABLE " . TABLE_ORDERS_STATUS_HISTORY . " ADD updated_by varchar(45) NOT NULL default ''");

}

// -----
// 2) If not already present, create an instance of the functions that provides the "common" updated_by
//    contents for either an admin-related or store-front update.
//
if (!function_exists('zen_updated_by_admin')) {
  function zen_updated_by_admin($admin_id = '') {
    if ($admin_id === '') {
      $admin_id = $_SESSION['admin_id'];
    }
    
    return zen_get_admin_name($admin_id) . " [$admin_id]";
  }
}

if (!function_exists ('zen_get_orders_status_name')) {
  function zen_get_orders_status_name($orders_status_id, $language_id = '') {
    global $db;

    if (!$language_id) $language_id = $_SESSION['languages_id'];
    $orders_status = $db->Execute("select orders_status_name
                                   from " . TABLE_ORDERS_STATUS . "
                                   where orders_status_id = '" . (int)$orders_status_id . "'
                                   and language_id = '" . (int)$language_id . "' LIMIT 1");
    if ($orders_status->EOF) return '';
    return $orders_status->fields['orders_status_name'];
  }
}

if (!function_exists ('zen_catalog_href_link') && function_exists ('zen_href_link')) {
  function zen_catalog_href_link ($page = '', $parameters = '', $connection = 'NONSSL') {
    return zen_href_link ($page, $parameters, $connection, false);
    
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
  function zen_update_orders_history($orders_id, $message = '', $updated_by = null, $orders_status = -1, $notify_customer = -1, $email_include_message = true, $email_subject = '', $send_xtra_emails_to = '') {
    global $db, $zco_notifier, $osh_sql;
    global $osh_additional_comments;
    $osh_id = -1;
    $orders_id = (int)$orders_id;
    $osh_info = $db->Execute("SELECT customers_name, customers_email_address, orders_status, date_purchased FROM " . TABLE_ORDERS . " WHERE orders_id = $orders_id" );
    if ( $osh_info->EOF ) {
      $osh_id = -2;
      
    } else {
      $message = stripslashes ($message);
      if (IS_ADMIN_FLAG === true && $email_include_message === true) {
        $osh_additional_comments = '';
        $zco_notifier->notify('ZEN_UPDATE_ORDERS_HISTORY_PRE_EMAIL', array( 'message' => $message ) );
        if ($osh_additional_comments != '') {
          if (zen_not_null($message)) {
            $message .= "\n\n";
          }
          $message .= $osh_additional_comments;
        }
        unset($osh_additional_comments);
      }
  
      if (($orders_status != -1 && $osh_info->fields['orders_status'] != $orders_status) || zen_not_null($message)) {
        if ($orders_status == -1) {
          $orders_status = $osh_info->fields['orders_status'];
        }
        $zco_notifier->notify('ZEN_UPDATE_ORDERS_HISTORY_STATUS_VALUES', array ( 'orders_id' => $orders_id, 'new' => $orders_status, 'old' => $osh_info->fields['orders_status'] ));
        
        $db->Execute( "UPDATE " . TABLE_ORDERS . " SET orders_status = '" . zen_db_input($orders_status) . "', last_modified = now() WHERE orders_id = $orders_id" );
        
        $notify_customer = (isset($notify_customer) && ($notify_customer == 1 || $notify_customer == -1 || $notify_customer == -2)) ? $notify_customer : 0;
        
        if ($notify_customer == 1 || $notify_customer == -2) {
          $status_name = $db->Execute("SELECT orders_status_name FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_id = " . (int)$orders_status . " AND language_id = " . (int)$_SESSION['languages_id']);
          $orders_status_name = ($status_name->EOF) ? 'N/A' : $status_name->fields['orders_status_name'];
          $email_comments = (zen_not_null($message) && $email_include_message === true) ? (OSH_EMAIL_TEXT_COMMENTS_UPDATE . $message . "\n\n") : '';

          if ($orders_status != $osh_info->fields['orders_status']) {
            $status_text = OSH_EMAIL_TEXT_STATUS_UPDATED;
            $status_value_text = sprintf(OSH_EMAIL_TEXT_STATUS_CHANGE, zen_get_orders_status_name($osh_info->fields['orders_status']), $orders_status_name);
            
          } else {
            $status_text = OSH_EMAIL_TEXT_STATUS_NO_CHANGE;
            $status_value_text = sprintf(OSH_EMAIL_TEXT_STATUS_LABEL, $orders_status_name );
          }
          //send emails
          $email_text =
            STORE_NAME . ' ' . OSH_EMAIL_TEXT_ORDER_NUMBER . ' ' . $orders_id . "\n\n" .
            OSH_EMAIL_TEXT_INVOICE_URL . ' ' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $orders_id, 'SSL') . "\n\n" .
            OSH_EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($osh_info->fields['date_purchased']) . "\n\n" .
            strip_tags($email_comments) .
            $status_text . $status_value_text .
            OSH_EMAIL_TEXT_STATUS_PLEASE_REPLY;
           
          $html_msg['EMAIL_CUSTOMERS_NAME']    = $osh_info->fields['customers_name'];
          $html_msg['EMAIL_TEXT_ORDER_NUMBER'] = OSH_EMAIL_TEXT_ORDER_NUMBER . ' ' . $orders_id;
          $html_msg['EMAIL_TEXT_INVOICE_URL']  = '<a href="' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $orders_id, 'SSL') .'">'.str_replace(':','', OSH_EMAIL_TEXT_INVOICE_URL).'</a>';
          $html_msg['EMAIL_TEXT_DATE_ORDERED'] = OSH_EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($osh_info->fields['date_purchased']);
          $html_msg['EMAIL_TEXT_STATUS_COMMENTS'] = nl2br($email_comments);
          $html_msg['EMAIL_TEXT_STATUS_UPDATED'] = str_replace('\n','', $status_text);
          $html_msg['EMAIL_TEXT_STATUS_LABEL'] = str_replace('\n','', $status_value_text);
          $html_msg['EMAIL_TEXT_NEW_STATUS'] = $orders_status_name;
          $html_msg['EMAIL_TEXT_STATUS_PLEASE_REPLY'] = str_replace('\n','', OSH_EMAIL_TEXT_STATUS_PLEASE_REPLY);
          $html_msg['EMAIL_PAYPAL_TRANSID'] = '';

          if ($notify_customer == 1) { 
            zen_mail($osh_info->fields['customers_name'], $osh_info->fields['customers_email_address'], ((zen_not_null($email_subject)) ? $email_subject : (OSH_EMAIL_TEXT_SUBJECT . ' #' . $orders_id)), $email_text, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status');
            
          } 

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
                           'orders_status_id' => (int)$orders_status,
                           'date_added' => 'now()',
                           'customer_notified' => (int)$notify_customer,
                           'comments' => $message,
                           'updated_by' => $updated_by
                           );
                           
        $zco_notifier->notify('ZEN_UPDATE_ORDERS_HISTORY_BEFORE_INSERT');
        
        zen_db_perform (TABLE_ORDERS_STATUS_HISTORY, $osh_sql);
        unset($osh_sql); 
        $osh_id = $db->Insert_ID();

      }    
    }
    return $osh_id;
    
  }
}