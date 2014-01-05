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
?>
<div class="centerColumn" id="referrerSignupDefault">
<?php
if (zen_not_null($referrer) && !$referrer->EOF) {
?>
  <div id="refSignupLinks"><a href="<?php echo zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL');?>"><?php echo TEXT_ORDERS_PAYMENTS; ?></a> | <a href="<?php echo zen_href_link(FILENAME_REFERRER_TOOLS, '', 'SSL');?>"><?php echo TEXT_MARKETING_TOOLS; ?></a> | <?php echo TEXT_REFERRER_TERMS; ?></div>
<?php
}

if (!$is_logged_in) {
?>
  <p><?php echo sprintf(TEXT_NOT_LOGGED_IN, zen_href_link(FILENAME_LOGIN, "", "SSL"), zen_href_link(FILENAME_CREATE_ACCOUNT, "", "SSL")); ?></p>
<?php
} elseif (!$show_terms) {
  echo zen_draw_form('referral_signup', zen_href_link(FILENAME_REFERRER_SIGNUP, 'action=signup', 'SSL'), 'post', '');
?>

    <p><?php echo TEXT_SIGN_UP; ?></p>

<?php
  if ($error != '') {
?>
    <p class="alert"><?php echo $error; ?></p>
<?php
  }
?>
    <div id="refSignupInput">
      <label class="inputLabel" for="url"><?php echo TEXT_HOMEPAGE_URL; ?></label>
      <?php echo zen_draw_input_field('url', '', 'size="50"'); ?>
      <?php echo zen_draw_hidden_field('action', 'signup'); ?>
    
      <br class="clearBoth" />
      <div class="buttonRow forward"><?php echo zen_image_submit(BUTTON_IMAGE_SUBMIT, BUTTON_SUBMIT_ALT); ?></div>
    </div>

  </form>
  <hr class="clearBoth" />
<?php

}  // is_logged_in: show signup form
?>

  <h1><?php echo HEADING_REFERRER_TERMS; ?></h1>
  <div id="referrerTermsMainContent" class="content"><?php require($define_terms); ?></div>
<?php 
 if ($show_terms) {
?>
  <div class="buttonRow back"><?php echo zen_back_link() . zen_image_button(BUTTON_IMAGE_BACK, BUTTON_BACK_ALT) . '</a>'; ?></div>
<?php
}
?>
</div>