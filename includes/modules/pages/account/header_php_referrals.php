<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
//
if (defined('TABLE_REFERRERS') && defined('SNAP_MODULE_VERSION')) {
    $referrer = $db->Execute(
        "SELECT *
           FROM " . TABLE_REFERRERS . "
          WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'] . "
          LIMIT 1"
    );
}
