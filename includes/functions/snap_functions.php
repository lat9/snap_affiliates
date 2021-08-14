<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
//
// Copyright (c) 2013-2021, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//

// -----
// Determines whether/not a non-guest customer is currently logged in.
//
function snap_is_logged_in()
{
    return (zen_is_logged_in() && !zen_in_guest_checkout());
}

// -----
// Returns an HTML select group containing a month and year selection.
//
function snap_get_date_dropdown($prefix, $selected_month, $selected_year)
{
    $month_array = [];
    for ($mon = 1; $mon <= 12; $mon++) {
        $month_array[] = ['id' => $mon, 'text' => date('F', mktime(0, 0, 0, $mon, 10))];
    }

    $today = getdate();
    $selected_month = (int)$selected_month;
    if ($selected_month < 1 || $selected_month > 12) {
        $selected_month = $today['mon'];
    }

    $min_year = snap_get_min_commission_year($today);
    $year_array = [];
    $current_year = $min_year;
    while ($today['year'] >= $current_year) {
        $year_array[] = ['id' => $current_year, 'text' => $current_year];
        $current_year++;
    }
    $selected_year = (int)$selected_year;
    if ($selected_year < $min_year || $selected_year > $today['year']) {
        $selected_year = $today['year'];
    }
    
    return zen_draw_pull_down_menu($prefix . '_mon', $month_array, $selected_month) . '&nbsp;&nbsp;' . zen_draw_pull_down_menu($prefix . '_year', $year_array, $selected_year);
}

function snap_get_min_commission_year($today)
{
    $min_year = $today['year'];
    if (!empty($_SESSION['customer_id'])) {
        $date_check = $GLOBALS['db']->Execute(
            "SELECT c.commission_paid
               FROM " . TABLE_REFERRERS . " r
                    INNER JOIN " . TABLE_COMMISSION . " c
                        ON c.commission_referrer_key = r.referrer_key
              WHERE r.referrer_customers_id = " . (int)$_SESSION['customer_id'] . "
              ORDER BY c.commission_paid ASC
              LIMIT 1"
        );
        if (!$date_check->EOF) {
            $min_year = substr($date_check->fields['commission_paid'], 0, 4);
        }
    }
    return $min_year;
}
