<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
//
// This module is included by the store's tpl_account_default.php to provide the
// SNAP-related links.  For previous SNAP versions, this content was added directly
// to that template file.
//
// -----
// Quick return if no $referrer object is available, implying that some/all of the
// SNAP-related modules are not available.
//
if (empty($referrer)) {
    return;
}
?>
<h2><?php echo REFERRER_MAIN_TITLE; ?></h2>
<ul id="myAffiliateAccount" class="list">
<?php
if (!is_object($referrer) || $referrer->EOF) {
?>
    <li><a href="<?php echo zen_href_link(FILENAME_REFERRER_SIGNUP, '', 'SSL');?>"><?php echo REFERRER_SIGN_UP; ?></a></li>
<?php
} else {
?>
    <li><a href="<?php echo zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL');?>"><?php echo REFERRER_ORDER_PAYMENT; ?></a></li>
<?php
    if ($referrer->fields['referrer_approved'] != 0) {
?>
    <li><a href="<?php echo zen_href_link(FILENAME_REFERRER_TOOLS, '', 'SSL');?>"><?php echo REFERRER_TOOLS; ?></a></li>
<?php
    }
}
?>
</ul>
<?php
