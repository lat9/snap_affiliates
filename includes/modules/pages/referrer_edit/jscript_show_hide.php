<?php
// +---------------------------------------------------------------------------+
// | Snap Affiliates for Zen Cart                                              |
// +---------------------------------------------------------------------------+
// | Copyright (c) 2015, Vinos de Frutas Tropicales (lat9) for ZC 1.5.0+       |
// +---------------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license.            |
// +---------------------------------------------------------------------------+
$details = $details_text = '';
foreach ($payment_types as $type_id => $type_info) {
    $details .= ", $type_id: '" . $type_info['text_details'] . "'";
}
?>
<script type="text/javascript">
  var detailsInfo = {<?php echo substr($details, 1); ?>};
  function showHideDetails () {
    var e = document.getElementById('payment-type');
    if (detailsInfo[e.options[e.selectedIndex].value] == '') {
      document.getElementById('payment-details').style.display = 'none';
      document.getElementById('payment-details-name').innerHTML = '&nbsp;';
    } else {
      document.getElementById('payment-details').style.display = 'block';
      document.getElementById('payment-details-name').innerHTML = detailsInfo[e.options[e.selectedIndex].value];
    }
  }
</script>