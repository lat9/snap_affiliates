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
<div class="centerColumn" id="referrerMainDefault">
<?php
if ($messageStack->size('referrer_main') > 0) echo $messageStack->output('referrer_main');  /*v2.4.0-a*/

if (!$is_logged_in) {
?>
  <p id="refMainNeedLogin"><?php echo sprintf(TEXT_PLEASE_LOGIN, zen_href_link(FILENAME_LOGIN, '', 'SSL')); ?></p>
  
<?php
} else {
  if (!$submitted) {
?>
  <p id="refMainNotSubmitted"><?php echo sprintf(TEXT_REFERRER_SIGNUP, zen_href_link(FILENAME_REFERRER_SIGNUP, '', 'SSL')); ?></p>
  
<?php
  } else {
    if (!$approved) {
?>
  <p><?php echo TEXT_REFERRAL_SUBMITTED; ?></p>
<?php
    } else {
      if ($banned) {    
?>
  <p><?php echo sprintf(TEXT_REFERRAL_BANNED, zen_href_link(FILENAME_CONTACT_US, '', 'NONSSL')); ?></p>
<?php
      } else {
        echo zen_draw_form('referral_main', zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'), 'get', ''); /*v2.4.0-c*/
?>
  <input type="hidden" name="main_page" value="<?php echo FILENAME_REFERRER_MAIN; ?>" />
  <div id="refSignupLinks"><?php echo TEXT_ORDERS_PAYMENTS; ?> | <a href="<?php echo zen_href_link(FILENAME_REFERRER_TOOLS, '', 'SSL');?>"><?php echo TEXT_MARKETING_TOOLS; ?></a> | <a href="<?php echo zen_href_link(FILENAME_REFERRER_SIGNUP, 'terms', 'SSL');?>"><?php echo TEXT_REFERRER_TERMS; ?></a></div>

  <hr />
  <h3><?php echo HEADING_REFERRER_INFO; ?></h3>
  <div id="referrerMainInfo" class="table">
    <div class="item_outer">
      <div class="item c1"><?php echo TEXT_REFERRER_ID; ?></div>
      <div class="item c2"><?php echo $referrer->fields['referrer_key']; ?></div>
    </div>
<?php //-bof-v2.2.0a ?>
    <div class="item_outer">
      <div class="item c1"><?php echo TEXT_MY_WEBSITE; ?></div>
      <div class="item c2"><?php echo $referrer->fields['referrer_homepage'] . '<a href="' . /*-bof-c-v2.4.0*/ zen_href_link(FILENAME_REFERRER_EDIT, '', 'SSL') /*-eof-c-v2.4.0*/ . '">&nbsp;&nbsp;' . zen_image_button(BUTTON_IMAGE_EDIT_SMALL, BUTTON_EDIT_SMALL_ALT) . '</a>';; ?></div>
    </div>
<?php //-eof-v2.2.0a ?>
    <div class="item_outer">
      <div class="item c1"><?php echo TEXT_LAST_PAYMENT_MADE; ?></div>
      <div class="item c2"><?php echo ($last_payout == 0) ? TEXT_NO_PAYMENTS : date("F j, Y", $last_payout); /*v2.5.0c*/ ?></div>
    </div>
    <div class="item_outer">
      <div class="item c1"><?php echo TEXT_COMMISSION_RATE; ?></div>
      <div class="item c2"><?php printf("%u%%", 100 * $referrer->fields['referrer_commission']); ?></div>
    </div>
  </div>
  <br />
  <hr />
  
  <h3><?php echo TEXT_SALES_SUMMARY; ?></h3>
  <div id="referrerMainSummary" class="table">
    <div class="item_outer">
      <div class="item c1"><?php echo TEXT_CURRENT_SALES; ?></div>
      <div class="item c2"><?php echo $currencies->format($unpaid_total); ?></div>
    </div>
    <div class="item_outer">
      <div class="item c1"><?php echo TEXT_UNPAID_COMMISSION; ?></div>
      <div class="item c2"><?php echo $currencies->format($unpaid_commission, 2); ?></div>
    </div>
    <div class="item_outer">
      <div class="item c1"><?php echo TEXT_YTD_SALES; ?></div>
      <div class="item c2"><?php echo $currencies->format($yearly_total, 2); ?></div>
    </div>
    <div class="item_outer">
      <div class="item c1"><?php echo TEXT_YTD_COMMISSION; ?></div>
      <div class="item c2"><?php echo $currencies->format($yearly_commission, 2); ?></div>
   </div>
  </div>
  <br />
  <hr />

  <h3 class="back"><?php echo TEXT_ACTIVITY; ?></h3>
  <div class="forward" style="margin-top: 12px;">

    <?php echo TEXT_FROM; ?>
    <input type="hidden" name="start" value="<?php echo $activity_begin; ?>" />
    <select onchange="document.referral_main.start.value = this.options[this.selectedIndex].value; document.referral_main.submit();">
<?php
  $begin = getdate($activity_begin);
  $end = getdate($activity_end);

  $today = getdate();
  $bound = ( $begin['year'] == $today['year'] ) ? $today['mon'] : 12;

  for( $i = 1; $i <= $bound; ++$i ) {
    printf('<option value="%u"%s>%s</option>' . "\n", mktime(0, 0, 0, $i, 1, $begin['year']), ($i == $begin['mon']) ? ' selected="selected"' : '', date ('F', mktime(0,0,0, $i)));
  }
?>
    </select>
    <select onchange="document.referral_main.start.value = this.options[this.selectedIndex].value; document.referral_main.submit();">
<?php
  for ($i = $today['year'] - 9; $i <= $today['year']; $i++) {
    printf('<option value="%u"%s>%s</option>' ."\n", mktime(0, 0, 0, $begin['mon'], 1, $i), (($i == $begin['year']) ? ' selected="selected"' : ''), date( "Y", mktime(0,0,0, $begin['mon'], 1, $i)));
  }
?> 
    </select>
    <?php echo TEXT_TO; ?>
    <input type="hidden" name="end" value="<?php echo $activity_end; ?>" />
    <select onchange="document.referral_main.end.value = this.options[this.selectedIndex].value; document.referral_main.submit();">
  <?php
  $bound = ( $end['year'] == $today['year'] ) ? $today['mon'] : 12;

  for( $i = 1; $i <= $bound; ++$i ) {
    printf('<option value="%u"%s>%s</option>' . "\n", mktime(0, 0, 0, $i + 1, 0, $end['year']), (($i == $end['mon']) ? ' selected="selected"' : ''), date('F', mktime(0,0,0, $i)));
  }
  ?>
    </select>
    <select onchange="document.referral_main.end.value = this.options[this.selectedIndex].value; document.referral_main.submit();">
  <?php
  for( $i = $begin['year']; $i <= $today['year']; ++$i) {
    printf('<option value="%u"%s>%s</option>' . "\n", mktime(0, 0, 0, $end['mon'] + 1, 0, $i), (($i == $end['year'] ) ? ' selected="selected"' : ''), date('Y', mktime(0,0,0, $end['mon'] + 1, 0, $i)));
  }
  ?>
    </select>
  </div>
  <br class="clearBoth" />

  <div id="referrerMainHistory" class="table">
    <div class="head_outer">
      <div class="thead c1"><?php echo HEADING_PURCHASE_DATE; ?></div>
      <div class="thead c2"><?php echo HEADING_AMOUNT; ?></div>
      <div class="thead c1"><?php echo HEADING_COMMISSION_RATE; ?></div>
      <div class="thead c2"><?php echo HEADING_COMMISSION_CALCULATED; ?></div>
      <div class="thead c2"><?php echo HEADING_COMMISSION_PAID; ?></div>
      <div class="thead c1"><?php echo HEADING_COMMISSION_PAY_DATE; ?></div>
    </div>

<?php
  $toggle = false;  

  foreach ($activity as $entry) {
    $nice_date = ($entry['paid'] == 0) ? TEXT_UNPAID : date('F j, Y', $entry['paid']);  /*v2.5.0c*/
?>
    <div class="item_outer <?php echo ($toggle) ? 'odd' : 'even'; ?>">
      <div class="item c1a"><?php echo date('F j, Y', $entry['date']); ?></div>
      <div class="item c2"><?php echo $currencies->format($entry['amount']); ?></div>
      <div class="item c3"><?php echo number_format($entry['commission'] * 100, 0) . '%'; ?></div>
      <div class="item c4"><?php echo $currencies->format($entry['commission_calculated']); ?></div>
      <div class="item c4a"><?php echo $currencies->format($entry['commission_paid']); ?></div>
      <div class="item c5"><?php echo $nice_date; ?></div>
    </div>   
<?php
    $toggle = !$toggle;
  }
?>
    <div class="item_outer totals">
      <div class="item c1"><?php echo HEADING_TOTALS; ?></div>
      <div class="item c2"><?php echo $currencies->format($activity_total); ?></div>
      <div class="item c3"><?php echo '&nbsp;'; ?></div>
      <div class="item c3a"><?php echo '&nbsp;'; ?></div>
      <div class="item c4"><?php echo $currencies->format($activity_commission); ?></div>
      <div class="item c5"><?php echo '&nbsp;'; ?></div>
    </div>
  </div>
  </form>
  
  <p><?php echo TEXT_COMMISSION_PAID; ?></p>
<?php
      }  // Signed in, submitted, approved and not banned
    }  // Signed in, submitted but not approved
    
    if (!$approved || $banned) {
?>
  <div class="buttonRow back"><?php echo zen_back_link() . zen_image_button(BUTTON_IMAGE_BACK, BUTTON_BACK_ALT) . '</a>'; ?></div>
<?php
    }
  }  // Signed in but not submitted 
}  // Signed in
?>
</div>