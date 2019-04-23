<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
?>
<div class="centerColumn" id="referrerToolsDefault">
    <div id="refSignupLinks">
        <a href="<?php echo zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL');?>"><?php echo TEXT_ORDERS_PAYMENTS; ?></a> | <?php echo TEXT_MARKETING_TOOLS; ?> | <a href="<?php echo zen_href_link(FILENAME_REFERRER_SIGNUP, 'terms', 'SSL');?>"><?php echo TEXT_REFERRER_TERMS; ?></a>
    </div>

    <h3><?php echo HEADING_SITE_LINK; ?></h3>
    <p><?php echo TEXT_SITE_LINK; ?></p>
<?php
$referrer_href_link = zen_href_link(FILENAME_DEFAULT, "referrer=$referrer_key", 'NONSSL', false);
$referrer_link = '<a href="' . $referrer_href_link . '">%s</a>';
?>
    <p class="centered"><?php echo sprintf($referrer_link, TEXT_MARKETING_TEXT); ?></p>
    <textarea rows="3" cols="1"><?php echo htmlspecialchars(sprintf($referrer_link, TEXT_MARKETING_TEXT), ENT_COMPAT, CHARSET); ?></textarea>

    <h3><?php echo HEADING_DEEP_LINK; ?></h3>
    <?php require $define_page; ?>

    <textarea rows="3" cols="1" onfocus="removeTip(this);" onblur="addTipIfBlank(this);" id="referrerLinkDump"><?php echo TEXT_PASTE_LINK_HERE; ?></textarea>
    <input type="button" onclick="transform();" value="<?php echo BUTTON_TRANSFORM; ?>" />

<?php
if (is_array($snap_banners) && count($snap_banners) != 0) {
?>
    <br /><br />
    <h3><?php echo HEADING_BANNERS; ?></h3>
    <p><?php echo TEXT_BANNERS; ?></p>
<?php 
    $alt = TEXT_IMAGE_ALT_TEXT;
    foreach ($snap_banners as $current_banner) {
        $width = $current_banner['width'];
        $height = $current_banner['height'];
        $filename = (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG . $current_banner['name'];
        $current_image = '<img class="referrer_image" src="' . $filename . '" width="' . $width . '" height="' . $height . '" alt="' . $alt . '" />';
        $current_image_link = '<a href="' . $referrer_href_link . '">' . $current_image . '</a>';
?>
    <div class="imagewrap">
        <div class="imagetitle"><?php echo sprintf(TEXT_X_BY_Y_PIXELS, $width, $height); ?></div>
        <?php echo $current_image; ?><br />
        <textarea rows="3" cols="1"><?php echo htmlspecialchars($current_image_link, ENT_COMPAT, CHARSET); ?></textarea>
    </div>
<?php
    }
}
?>
</div>
