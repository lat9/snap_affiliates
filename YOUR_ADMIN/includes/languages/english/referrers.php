<?php
// +----------------------------------------------------------------------+
// | Snap Affiliates for Zen Cart v1.5.0+                                 |
// +----------------------------------------------------------------------+
// | Copyright (c) 2013-2015, Vinos de Frutas Tropicales (lat9)           |
// |                                                                      |
// | Original: Copyright (c) 2009 Michael Burke                           |
// | http://www.filterswept.com                                           |
// |                                                                      |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.       |
// +----------------------------------------------------------------------+

define('TEXT_REFERRERS', 'Referrers');

define('ERROR_INVALID_PERCENTAGE', 'The "Current Commission Rate" value must be a number in the range 0 to 100.'); /*v2.1.0a*/

// Language file for the Customers->Referrers Tool
define('HEADING_FIRST_NAME', 'First Name');
define('HEADING_LAST_NAME', 'Last Name');
define('HEADING_EMAIL_ADDRESS', 'Email Address');
define('HEADING_WEBSITE', 'Website');
define('HEADING_APPROVED', 'Approved');
define('HEADING_BANNED', 'Banned');
define('HEADING_UNPAID_TOTAL', 'Unpaid Total');
define('HEADING_COMMISSION_RATE', 'Commission Rate');
define('HEADING_EMAIL', 'Email Address');
define('HEADING_PAYMENT_TYPE', 'Payment Type');
  define('PAYMENT_TYPE_CHECK_MONEYORDER', 'Check/Money-order');
  define('PAYMENT_TYPE_PAYPAL', 'PayPal');
    define('PAYMENT_TYPE_PAYPAL_DETAILS', 'PayPal Account Email:');
  define('PAYMENT_TYPE_UNKNOWN', 'Unknown');

define('LABEL_REFERRER_ID', 'Referrer ID:');
define('LABEL_HOME_PAGE_LINK', 'Home-page Link:');
define('LABEL_ORDERS_TOTAL', 'Orders Total:');
define('LABEL_WEBSITE', HEADING_WEBSITE . ':  <a href="http://%1$s" target="_blank" rel="noreferrer">%1$s</a>' . "\n");
define('LABEL_EMAIL', HEADING_EMAIL . ': <a href="mailto:%1$s">%1$s</a>' . "\n");
define('LABEL_PHONE', 'Phone:');
define('LABEL_NAME_ADDRESS', 'Name and Address:');
define('LABEL_APPROVED', HEADING_APPROVED . ':');
define('LABEL_BANNED', HEADING_BANNED . ':');
define('TEXT_UNPAID', 'Unpaid');
define('LABEL_UNPAID', TEXT_UNPAID . ':');
define('LABEL_ADDRESS', 'Address:');
define('LABEL_PAYMENT_TYPE', HEADING_PAYMENT_TYPE . ':');
define('LABEL_UNPAID_COMMISSION', 'Unpaid Commission:');
define('LABEL_CURRENT_COMMISSION_RATE', 'Current Commission Rate:');

define('TEXT_EMAIL_WILL_BE_SENT', '<b>Note:</b> An email will be sent to the customer identifying any change.');

define('TEXT_REFERRER_INFO', 'Referrer Information');
define('TEXT_STATUS', 'Status');
define('TEXT_ORDER_HISTORY', 'Order History');
define('TEXT_TO', 'To:');
define('TEXT_FROM', 'From:');
define('TEXT_CHOOSE', 'Choose');
define('TEXT_APPROVE', 'Approve');
define('TEXT_BAN', 'Ban');
define('TEXT_UNBAN', 'Unban');
define('TEXT_PAY', 'Pay');
define('TEXT_UPDATE', 'Update');
define('TEXT_UPDATE_PAYMENT_TYPE', 'Update Payment Type');
define('TEXT_DISPLAY_SPLIT', 'Displaying %1$u to %2$u (of %3$u referrers)');

define('TEXT_PAY_SELECTED', 'Pay Selected');
define('TEXT_CHOOSE_COMMISSIONS', 'Choose Unpaid Commissions to be Paid');
define('HEADING_CHOOSE', 'Select');
define('HEADING_CALCULATED_COMMISSION', 'Calculated Commission');
define('HEADING_COMMISSION_TO_PAY', 'Commission to Pay');
define('ERROR_COMMISSION_CANT_BE_ZERO', 'A commission payment must be greater than 0.');
define('ERROR_CHOOSE_COMMISSION_TO_PAY', 'Please choose at least one commission to be paid.');
define('SUCCESS_PAYMENT_MADE', 'Your commission payment of %1$s to %2$s %3$s has been recorded.');
define('ERROR_PAYMENT_DETAILS_MISSING', 'The field <em>%s</em> is required and cannot be blank.  Please re-enter.');

define('TEXT_NONCOMMISSIONABLE', ' (Non-Commissionable)'); 

define('HEADING_ORDER_ID', 'Order ID');
define('HEADING_ORDER_DATE', 'Order Date');
define('HEADING_ORDER_TOTAL', 'Order Total');
define('HEADING_COMMISSION_TOTAL', 'Commission Total');
define('HEADING_COMMISSION_PAY_DATE', 'Commission Paid On');
define('HEADING_COMMISSION_PAID_VIA', 'Commission Paid Via');
define('HEADING_TOTALS', 'Totals');

/* ----
** The email subject and message when an affiliate account is approved.  The message is created by passing three parameters:
** 1) The link to the store's login page
** 2) The link to the store's referrer_tools page
** 3) The link to the store's contact_us page
*/
define('EMAIL_SUBJECT_APPROVED', 'Approved: ' . STORE_NAME . ' referrer account');
define('EMAIL_MESSAGE_APPROVED_HTML', 'Congratulations!  Your referrer account with <strong>' . STORE_NAME . '</strong> has been approved.  You can log into your account <a href="%1$s">here</a>.  Once logged in, you can access your referrer statistics and tools <a href="%2$s">here</a>.<br /><br />Remember to take a few minutes to familiarize yourself with our referrer terms.  If you have any questions, don\'t hesitate to <a href="%3$s">contact us</a>.<br /><br />Sincerely,<br /><br />' . STORE_OWNER); /*v2.1.0c*/
define('EMAIL_MESSAGE_APPROVED_TEXT', 'Congratulations!  Your referrer account with ' . STORE_NAME . ' has been approved.  You can log into your account using this link: %1$s.  Once logged in, you can access your referrer statistics and tools using this link: %2$s.' . "\n\n" . 'Remember to take a few minutes to familiarize yourself with our referrer terms.  If you have any questions, don\'t hesitate to contact us using this link: %3$s.' . "\n\nSincerely,\n\n" . STORE_OWNER);

/* ----
** The email subject and message when an affiliate account is banned/suspended.
*/
define('EMAIL_SUBJECT_BANNED', 'Suspended: ' . STORE_NAME . ' referrer account');
define('EMAIL_MESSAGE_BANNED_HTML', 'Your referrer account with <strong>' . STORE_NAME . '</strong> has been suspended. If you feel this is in error, please contact us. We\'ll be happy to review your case and re-instate your account if a mistake has been made.<br /><br />Sincerely,<br /><br />' . STORE_OWNER);
define('EMAIL_MESSAGE_BANNED_TEXT', 'Your referrer account with ' . STORE_NAME . ' has been suspended.  If you feel this is in error, please contact us. We\'ll be happy to review your case and re-instate your account if a mistake has been made.' . "\n\nSincerely,\n\n" . STORE_OWNER);

/* ----
** The email subject and message when a payment is made to an affiliate.  The message is created by passing four parameters:
** 1) The formatted payment amount
** 2) The link to the store's login page
** 3) The link to the store's referrer_tools page
** 4) The link to the store's contact_us page
*/
define('EMAIL_SUBJECT_PAID', 'Payment: ' . STORE_NAME . ' referrer account');  //-v2.7.0c (added leading space)
define('EMAIL_MESSAGE_PAID_HTML', 'A commission payment was recently made for your ' . STORE_NAME . ' referral account. Your total earnings this period were <strong>%1$s</strong>.<br /><br />To view your complete order history, you can <a href="%2$s">login</a> and view your referrer <a href="%3$s">statistics</a>. If you have any questions, don\'t hesitate to <a href="%4$s">contact us</a>.<br /><br />Sincerely,<br /><br />' . STORE_OWNER);
define('EMAIL_MESSAGE_PAID_TEXT', 'A commission payment was recently made for your ' . STORE_NAME . ' referral account. Your total earnings this period were %1$s.' . "\n\n" . 'To view your complete order history, you can login (%2$s) and view your referrer statistics (%3$s).  If you have any questions, don\'t hesitate to contact us using this link: %4$s.' . "\n\nSincerely,\n\n" . STORE_OWNER);

/* ----
** The orders_status_history comment created when an affiliate is paid.
**
** %1$s - The commission payment amount
** %2$s - The referrer's first name
** %3$s - The referrer's last name
** %4$s - The commission payment type information
*/
define('TEXT_ORDERS_STATUS_PAID', 'Commission payment of %1$s paid to %2$s %3$s via %4$s.');
