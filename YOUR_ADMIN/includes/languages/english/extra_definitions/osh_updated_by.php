<?php
/**
 * @package admin
 * @copyright Copyright 2003-2007 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: orders.php 6214 2007-04-17 02:24:25Z ajeh $
 */
define('OSH_EMAIL_SEPARATOR', '------------------------------------------------------');
define('OSH_EMAIL_TEXT_SUBJECT', 'Order Update');
define('OSH_EMAIL_TEXT_ORDER_NUMBER', 'Order Number:');
define('OSH_EMAIL_TEXT_INVOICE_URL', 'Detailed Invoice:');
define('OSH_EMAIL_TEXT_DATE_ORDERED', 'Date Ordered:');
define('OSH_EMAIL_TEXT_COMMENTS_UPDATE', '<em>The comments for your order are: </em>' . "\n\n");
define('OSH_EMAIL_TEXT_STATUS_UPDATED', 'Your order\'s status has been updated:' . "\n");  /*v1.0.0c*/
define('OSH_EMAIL_TEXT_STATUS_NO_CHANGE', 'Your order\'s status has not changed:' . "\n");  /*v1.0.0a*/
define('OSH_EMAIL_TEXT_STATUS_LABEL', '<strong>Current status: </strong> %s' . "\n\n");  /*v1.0.0c*/
define('OSH_EMAIL_TEXT_STATUS_CHANGE', '<strong>Old status:</strong> %1$s, <strong>New status:</strong> %2$s' . "\n\n");  /*v1.0.0c*/
define('OSH_EMAIL_TEXT_STATUS_PLEASE_REPLY', 'Please reply to this email if you have any questions.' . "\n");