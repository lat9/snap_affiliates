<?php
// +----------------------------------------------------------------------+
// |Snap Affiliates for Zen Cart                                          |
// +----------------------------------------------------------------------+
// | Copyright (c) 2013, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+  |
// |                                                                      |
// | Original: Copyright (c) 2009 Michael Burke                           |
// | http://www.filterswept.com                                           |
// |                                                                      |
// | This source file is subject to version 2.0 of the GPL license.       |
// +----------------------------------------------------------------------+

function get_referrer_link($base) {
  global $referrer;
  $result = '';

  if (!$referrer->EOF) {
    $result = zen_href_link($base, 'referrer=' . $referrer->fields['referrer_key'], 'NONSSL'); /*v2.1.1c*/
  }
  return $result;
}

function get_referrer_image($width, $height, $filename) {
  global $request_type;  /*v2.5.1a*/
  $image_html = '';
  $alt = TEXT_IMAGE_ALT_TEXT;
  $filename = (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG . $filename;  /*v2.3.0a,v2.5.1c*/
  $image_html .= '<div class="imagewrap">';
  $image_html .= '<div class="imagetitle">' . sprintf(TEXT_X_BY_Y_PIXELS, $width, $height) . '</div>';
  $image_html .= "<img class=\"referrer_image\" src=\"$filename\" width=\"$width\" height=\"$height\" alt=\"$alt\" /><br />";
  $image_html .= '<textarea rows="3" cols="1">&lt;a href="' . get_referrer_link(FILENAME_DEFAULT) . "\"&gt;&lt;img src=\"$filename\" width=\"$width\" height=\"$height\" alt=\"$alt\" /&gt;&lt;/a&gt;</textarea><br />";
  $image_html .= '</div>';

  return $image_html;
}
?>
<div class="centerColumn" id="referrerToolsDefault">

<div id="refSignupLinks">
  <a href="<?php echo zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL');?>"><?php echo TEXT_ORDERS_PAYMENTS; ?></a> | <?php echo TEXT_MARKETING_TOOLS; ?> | <a href="<?php echo zen_href_link(FILENAME_REFERRER_SIGNUP, 'terms', 'SSL');?>"><?php echo TEXT_REFERRER_TERMS; ?></a>
</div>

<h3><?php echo HEADING_SITE_LINK; ?></h3>
<p><?php echo TEXT_SITE_LINK; ?></p>
<p class="centered"><a href="<?php echo get_referrer_link(FILENAME_DEFAULT); ?>"><?php echo TEXT_MARKETING_TEXT; ?></a></p>
<textarea rows="3" cols="1">&lt;a href="<?php echo get_referrer_link(FILENAME_DEFAULT); ?>"&gt;<?php echo TEXT_MARKETING_TEXT; ?>&lt;/a&gt;</textarea>

<h3><?php echo HEADING_DEEP_LINK; ?></h3>
<?php require($define_page); ?>

<textarea rows="3" cols="1" style="color: grey;" onfocus="removeTip(this);" onblur="addTipIfBlank(this);" id="referrerLinkDump"><?php echo TEXT_PASTE_LINK_HERE; ?></textarea>
<input type="button" onclick="transform();" value="Transform" />

<?php
if (is_array($snap_banners) && count($snap_banners) != 0) {
?>
<br /><br />
<h3><?php echo HEADING_BANNERS; ?></h3>
<p><?php echo TEXT_BANNERS; ?></p>
<?php 
  foreach ($snap_banners as $current_banner) {
    echo get_referrer_image($current_banner['width'], $current_banner['height'], $current_banner['name']);
  }
}
?>
</div>