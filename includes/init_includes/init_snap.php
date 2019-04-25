<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
//
/*-----
** Initialize the session's referrer key.
*/
if (!defined('SNAP_COOKIE_LIFETIME')) {
    define ('SNAP_COOKIE_LIFETIME', 60*60*24*365);
}

if (isset($_GET['referrer'])) {
    if (strpos($_GET['referrer'], SNAP_KEY_PREFIX) === 0 && strlen($_GET['referrer']) < 24) {
        $_SESSION['referrer_key'] = $_GET['referrer'];
        setcookie("referrer_key", $_GET['referrer'], time() + SNAP_COOKIE_LIFETIME, "/");
    }
} elseif (!empty($_COOKIE['referrer_key'])) {
    if (strpos($_COOKIE['referrer_key'], SNAP_KEY_PREFIX) === 0 && strlen($_COOKIE['referrer_key']) < 24) {
        $_SESSION['referrer_key'] = $_COOKIE['referrer_key'];
    }
}