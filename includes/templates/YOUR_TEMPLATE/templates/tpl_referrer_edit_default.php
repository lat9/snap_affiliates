<?php
// +----------------------------------------------------------------------+
// |Snap Affiliates for Zen Cart                                          |
// +----------------------------------------------------------------------+
// | Copyright (c) 2013, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.       |
// +----------------------------------------------------------------------+
?>
<div class="centerColumn" id="referrerEdit">
<?php echo zen_draw_form('referrer_edit', zen_href_link(FILENAME_REFERRER_EDIT, '', 'SSL'), 'post') . zen_draw_hidden_field('action', 'update'); ?>

<fieldset>
<legend><?php echo HEADING_TITLE; ?></legend>
<div class="alert forward"><?php echo FORM_REQUIRED_INFORMATION; ?></div> 
<br class="clearBoth" />

<?php if ($messageStack->size('referrer_edit') > 0) echo $messageStack->output('referrer_edit'); ?>

<label class="inputLabel" for="homepage"><?php echo TEXT_MY_WEBSITE; ?></label>
<?php echo zen_draw_input_field('url', isset($_POST['url']) ? $_POST['url'] : $referrer->fields['referrer_homepage'], 'id="homepage"') . '<span class="alert">*</span>'; ?>
<br class="clearBoth" />

</fieldset>

 <div class="buttonRow forward"><?php echo zen_image_submit(BUTTON_IMAGE_SUBMIT, BUTTON_SUBMIT_ALT); ?></div>
 <div class="buttonRow back"><?php echo '<a href="' . zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL') . '">' . zen_image_button(BUTTON_IMAGE_BACK, BUTTON_BACK_ALT) . '</a>'; ?></div>

</form>
</div>