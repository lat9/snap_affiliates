<?php
// -----
// Part of the "Orders Status History - Updated By (v2)" plugin.
// Copyright 2013-2016, Vinos de Frutas Tropicales (http://vinosdefrutastropicales.com)
//
define('OSH_EMAIL_SEPARATOR', '------------------------------------------------------');
define('OSH_EMAIL_TEXT_SUBJECT', 'Order Update');
define('OSH_EMAIL_TEXT_ORDER_NUMBER', 'Order Number:');
define('OSH_EMAIL_TEXT_INVOICE_URL', 'Detailed Invoice:');
define('OSH_EMAIL_TEXT_DATE_ORDERED', 'Date Ordered:');
define('OSH_EMAIL_TEXT_COMMENTS_UPDATE', '<em>The comments for your order are: </em>' . "\n\n");
define('OSH_EMAIL_TEXT_STATUS_UPDATED', 'Your order\'s status has been updated:' . "\n");
define('OSH_EMAIL_TEXT_STATUS_NO_CHANGE', 'Your order\'s status has not changed:' . "\n");
define('OSH_EMAIL_TEXT_STATUS_LABEL', '<strong>Current status: </strong> %s' . "\n\n");
define('OSH_EMAIL_TEXT_STATUS_CHANGE', '<strong>Old status:</strong> %1$s, <strong>New status:</strong> %2$s' . "\n\n");
define('OSH_EMAIL_TEXT_STATUS_PLEASE_REPLY', 'Please reply to this email if you have any questions.' . "\n");

// -----
// Used by orders.php, so that the orders.php language file doesn't require change!
//
define('TABLE_HEADING_UPDATED_BY', 'Updated By');