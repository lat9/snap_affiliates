<?php
// +----------------------------------------------------------------------+
// | Snap Affiliates for Zen Cart                                         |
// +----------------------------------------------------------------------+
// | Copyright (c) 2013, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// |                                                                      |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.       |
// +----------------------------------------------------------------------+

$query = "SELECT * FROM " . TABLE_REFERRERS . " WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'];
$referrer = $db->Execute($query);