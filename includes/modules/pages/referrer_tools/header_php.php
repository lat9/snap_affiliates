<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
$zco_notifier->notify('NOTIFY_START_REFERRER_TOOLS');

require DIR_WS_FUNCTIONS . 'snap_functions.php';
require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

define('DIR_WS_IMAGES_REFERRERS', DIR_WS_IMAGES . 'referrers/');

if (!snap_is_logged_in()) {
    $_SESSION['navigation']->set_snapshot();
    $messageStack->add_session('login', CAUTION_NEED_LOGIN, 'caution');
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$referrer = $db->Execute(
    "SELECT * 
       FROM " . TABLE_REFERRERS . " 
      WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'] . "
      LIMIT 1"
);
if ($referrer->EOF) {
    zen_redirect(zen_href_link(FILENAME_REFERRER_SIGNUP, '', 'SSL'));
}
if ($referrer->fields['referrer_approved'] == 0 || $referrer->fields['referrer_banned'] == 1) {
    zen_redirect(zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'));
}

$referrer_key = $referrer->fields['referrer_key'];

$snap_banners = array();
if (SNAP_AFFILIATE_IMAGES != '') {
    $snap_banner_files = explode('/', SNAP_AFFILIATE_IMAGES);
    foreach ($snap_banner_files as $current_file ) {
        if (zen_not_null($current_file)) {
            $fileinfo = explode(',', $current_file);
            $width = $fileinfo[0];
            $height = $fileinfo[1];
            $extension = $fileinfo[2];
            $filenames = glob(DIR_WS_IMAGES_REFERRERS . "ref*.$width.$height.$extension");
            if ($filenames !== false) {
                foreach ($filenames as $filename) {
                    $snap_banners[] = array(
                        'name' => $filename, 
                        'width' => $width, 
                        'height' => $height 
                    );
                }
            }
        }
    }
}

// include template specific file name defines
$define_page = zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] . '/html_includes/', FILENAME_DEFINE_REFERRAL_TOOLS, 'false');
$breadcrumb->add(NAVBAR_TITLE);

$zco_notifier->notify('NOTIFY_END_REFERRER_TOOLS');
