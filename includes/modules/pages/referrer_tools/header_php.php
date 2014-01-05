<?php
// +----------------------------------------------------------------------+
// |Snap Affiliates for Zen Cart                                          |
// +----------------------------------------------------------------------+
// | Copyright (c) 2013, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// |                                                                      |
// | Original: Copyright (c) 2009 Michael Burke                           |
// | http://www.filterswept.com                                           |
// |                                                                      |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.       |
// +----------------------------------------------------------------------+

require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));

define('DIR_WS_IMAGES_REFERRERS', DIR_WS_IMAGES . 'referrers/');
function get_image_src($name) {
  global $filename2;
  $filename = DIR_WS_IMAGES_REFERRERS . $name;
  clearstatcache();
  if (!file_exists($filename)) {
    $filename = '';
  }
  return $filename;
}

if (!$_SESSION['customer_id']) {
  $_SESSION['navigation']->set_snapshot();
  $messageStack->add_session('login', CAUTION_NEED_LOGIN, 'caution');
  zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$is_logged_in = true;

$query = "SELECT * FROM " . TABLE_REFERRERS ." WHERE referrer_customers_id = " . (int)$_SESSION['customer_id'];
$referrer = $db->Execute($query);

if ($referrer->EOF) {
  zen_redirect(zen_href_link(FILENAME_REFERRER_SIGNUP, '', 'SSL'));
}

if (((int)$referrer->fields['referrer_approved']) == 0) {
  zen_redirect(zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'));
}

$snap_banners = array();
if (SNAP_AFFILIATE_IMAGES != '') {
  $snap_banner_files = explode('/', SNAP_AFFILIATE_IMAGES);
  foreach ($snap_banner_files as $current_file ) {
    if (zen_not_null($current_file)) {
      $fileinfo = explode(',', $current_file);
      $width = $fileinfo[0];
      $height = $fileinfo[1];
      $extension = $fileinfo[2];
      $filename = get_image_src("ref.$width.$height.$extension");
      if ($filename != '') {
        $snap_banners[] = array ( 'name' => $filename, 'width' => $width, 'height' => $height );
      }
    }
  }
}

// include template specific file name defines
$define_page = zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] . '/html_includes/', FILENAME_DEFINE_REFERRAL_TOOLS, 'false');
$breadcrumb->add(NAVBAR_TITLE);
