<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
?>
<div class="centerColumn" id="referrerMainDefault">
<?php
if ($messageStack->size('referrer_main') > 0) {
    echo $messageStack->output('referrer_main');
}

if (!$approved) {
?>
    <p><?php echo TEXT_REFERRAL_SUBMITTED; ?></p>
<?php
} elseif ($banned) {
?>
    <p><?php echo sprintf(TEXT_REFERRAL_BANNED, zen_href_link(FILENAME_CONTACT_US, '', 'SSL')); ?></p>
<?php
} else {
?>
    <div id="refSignupLinks"><?php echo TEXT_ORDERS_PAYMENTS; ?> | <a href="<?php echo zen_href_link(FILENAME_REFERRER_TOOLS, '', 'SSL');?>"><?php echo TEXT_MARKETING_TOOLS; ?></a> | <a href="<?php echo zen_href_link(FILENAME_REFERRER_SIGNUP, 'terms', 'SSL');?>"><?php echo TEXT_REFERRER_TERMS; ?></a></div>

    <hr />
    <h2><?php echo HEADING_REFERRER_INFO; ?>&nbsp;&nbsp;<a href="<?php echo zen_href_link(FILENAME_REFERRER_EDIT, '', 'SSL'); ?>"><?php echo zen_image_button(BUTTON_IMAGE_EDIT_SMALL, BUTTON_EDIT_SMALL_ALT); ?></a></h2>
    <table id="referrerMainInfo" class="snap snap-tab">
        <tr>
            <td><?php echo TEXT_REFERRER_ID; ?></td>
            <td><?php echo $referrer->fields['referrer_key']; ?></td>
        </tr>

        <tr>
            <td><?php echo TEXT_MY_WEBSITE; ?></td>
            <td><?php echo $referrer->fields['referrer_homepage']; ?></td>
        </tr>

        <tr>
            <td><?php echo TEXT_MY_PAYMENT_TYPE; ?></td>
            <td><?php echo $payment_types[$referrer->fields['referrer_payment_type']]['text'] . (($referrer->fields['referrer_payment_type_detail'] != '') ? (' (' . $referrer->fields['referrer_payment_type_detail'] . ')') : ''); ?></td>
        </tr>
        
        <tr>
            <td><?php echo TEXT_LAST_PAYMENT_MADE; ?></td>
            <td><?php echo ($last_payout_timestamp == 0) ? TEXT_NO_PAYMENTS : date('F j, Y', $last_payout_timestamp); ?></td>
        </tr>
        
        <tr>
            <td><?php echo TEXT_COMMISSION_RATE; ?></td>
            <td><?php printf("%u%%", 100 * $referrer->fields['referrer_commission']); ?></td>
        </tr>
    </table>
    <br />
    <hr />
  
    <h2><?php echo TEXT_SALES_SUMMARY; ?></h2>
    <table id="referrerMainSummary" class="snap snap-tab">
        <tr>
            <td><?php echo TEXT_CURRENT_SALES; ?></td>
            <td><?php echo $currencies->format($unpaid_total); ?></td>
        </tr>
        <tr>
            <td><?php echo TEXT_UNPAID_COMMISSION; ?></td>
            <td><?php echo $currencies->format($unpaid_commission, 2); ?></td>
        </tr>
        <tr>
            <td><?php echo TEXT_YTD_SALES; ?></td>
            <td><?php echo $currencies->format($yearly_total, 2); ?></td>
        </tr>
        <tr>
            <td><?php echo TEXT_YTD_COMMISSION; ?></td>
            <td><?php echo $currencies->format($yearly_commission, 2); ?></td>
       </tr>
    </table>
    <br />
    <hr />

    <h2><?php echo TEXT_ACTIVITY; ?></h2>
<?php
    echo zen_draw_form('referral_main', zen_href_link(FILENAME_REFERRER_MAIN, '', 'SSL'), 'get', '') . zen_draw_hidden_field('main_page', FILENAME_REFERRER_MAIN);
?>
        <div id="snap-dates"><?php echo TEXT_FROM . snap_get_date_dropdown('start', $start_mon, $start_year) . TEXT_TO . snap_get_date_dropdown('end', $end_mon, $end_year) . '&nbsp;&nbsp;' . zen_image_submit('button_go.gif', TEXT_CHOOSE); ?></div>
    </form>
<?php
    if (count($activity) == 0) {
?>
    <p id="no-activity"><?php echo TEXT_NO_ACTIVITY; ?></p>
<?php
    } else {
?>
    <table id="referrerMainHistory" class="snap">
        <tr>
          <th><?php echo HEADING_PURCHASE_DATE; ?></th>
          <th><?php echo HEADING_AMOUNT; ?></th>
          <th><?php echo HEADING_COMMISSION_RATE; ?></th>
          <th><?php echo HEADING_COMMISSION_CALCULATED; ?></th>
          <th><?php echo HEADING_COMMISSION_PAID; ?></th>
          <th><?php echo HEADING_COMMISSION_PAY_DATE; ?></th>
          <th><?php echo HEADING_COMMISSION_PAY_TYPE; ?></th>
        </tr>

<?php
        foreach ($activity as $entry) {
            $nice_date = ($entry['paid'] == 0) ? TEXT_UNPAID : date('F j, Y', $entry['paid']);
?>
        <tr>
          <td><?php echo date('F j, Y', $entry['date']); ?></td>
          <td><?php echo $currencies->format($entry['amount']); ?></td>
          <td><?php echo number_format($entry['commission'] * 100, 0) . '%'; ?></td>
          <td><?php echo $currencies->format($entry['commission_calculated']); ?></td>
          <td><?php echo $currencies->format($entry['commission_paid']); ?></td>
          <td><?php echo $nice_date; ?></td>
          <td><?php echo $entry['payment_type'] . ' ' . $entry['payment_type_detail']; ?></td>
        </tr>   
<?php
        }
?>
        <tr class="snap-totals">
          <td><?php echo HEADING_TOTALS; ?></td>
          <td><?php echo $currencies->format($activity_total); ?></td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td><?php echo $currencies->format($activity_commission); ?></td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
    </table>
    <p><?php echo TEXT_COMMISSION_PAID; ?></p>
<?php
    }
}  // Signed in, submitted, approved and not banned

if (!$approved || $banned) {
?>
    <div class="buttonRow back"><a href="<?php echo zen_href_link(FILENAME_ACCOUNT, '', 'SSL'); ?>"><?php echo zen_image_button(BUTTON_IMAGE_BACK, BUTTON_BACK_ALT); ?></a></div>
<?php
}  // Not approved or banned
?>
</div>
