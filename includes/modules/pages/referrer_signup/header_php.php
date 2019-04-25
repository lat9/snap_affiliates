<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
$zco_notifier->notify('NOTIFY_START_REFERRER_SIGNUP');

require DIR_WS_FUNCTIONS . 'snap_functions.php';
require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

// include template specific file name defines
$define_terms = zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] . '/html_includes/', FILENAME_DEFINE_REFERRAL_TERMS, 'false');
$breadcrumb->add(NAVBAR_TITLE);

$referrer = null;
$is_logged_in = snap_is_logged_in();

$show_terms = isset($_GET['terms']);
$error = '';

if ($is_logged_in) {
    if (isset($_POST['action']) && $_POST['action'] == 'signup') {
        // -----
        // Ensure that a URL is provided and that it matches the format of a URL.
        //
        $url = $_POST['url'];
        if ($url == '' || !preg_match("/^(?:(?:https?):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]$/i", $url)) {
            $error = ERROR_NO_URL;
        } else {
            $url = zen_db_input(zen_db_prepare_input($url));
            $tag = SNAP_KEY_PREFIX . $_SESSION['customer_id'] . time();
            $commission = (float)SNAP_DEFAULT_COMMISSION;
            $db->Execute(
                "INSERT INTO " . TABLE_REFERRERS . " 
                    (referrer_customers_id, referrer_key, referrer_homepage, referrer_approved, referrer_banned, referrer_commission) 
                 VALUES 
                    (" . (int)$_SESSION['customer_id'] . ", '$tag', '$url', 0, 0, $commission)"
            );


            if (defined('SNAP_ADMIN_EMAIL') && zen_validate_email(SNAP_ADMIN_EMAIL)) { 
                $customer = $db->Execute(
                    "SELECT customers_firstname, customers_lastname 
                       FROM " . TABLE_CUSTOMERS . " 
                      WHERE customers_id = " . (int)$_SESSION['customer_id'] . "
                      LIMIT 1"
                );
                if (!$customer->EOF) {
                    $html_msg = array();
                    $subject = EMAIL_SUBJECT;
                    $html_msg['EMAIL_SUBJECT'] = $subject;

                    $email_text = sprintf(EMAIL_BODY, $customer->fields['customers_firstname'], $customer->fields['customers_lastname'], (int)$_SESSION['customer_id']);
                    $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_text);
                    zen_mail('', SNAP_ADMIN_EMAIL, $subject, $email_text, STORE_NAME, EMAIL_FROM, $html_msg);
                }
            }
      
            $_SESSION['navigation']->remove_current_page();
            zen_redirect (zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'));
        }
    
    } else {
        $referrer = $db->Execute(
            "SELECT * 
               FROM " . TABLE_REFERRERS . " 
              WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'] . "
              LIMIT 1"
        );
        if (!$referrer->EOF && !$show_terms ) {
            zen_redirect(zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'));
        }
    }
}

$zco_notifier->notify('NOTIFY_END_REFERRER_SIGNUP');
