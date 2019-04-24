<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.  Note, for versions
// of SNAP prior to v4.1.0, this processing was provided by /admin/includes/functions/extra_functions/init_referrers.php.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
   die('Illegal Access');
}

class SnapAffiliatesAdminObserver extends base 
{
    function __construct() 
    {
        $this->attach(
            $this, 
            array( 
                /* Issued by /admin/orders.php */
                'NOTIFY_ADMIN_ORDERS_MENU_LEGEND', 
                'NOTIFY_ADMIN_ORDERS_SHOW_ORDER_DIFFERENCE',
                
                /* Issued by /admin/customers.php */
                'NOTIFIER_ADMIN_ZEN_CUSTOMERS_DELETE_CONFIRM',
            )
        );
    }
  
    function update(&$class, $eventID, $p1, &$p2, &$p3, &$p4) 
    {
        switch ($eventID) {
            // -----
            // Issued by Customers->Orders during the order-listing phase, allows us to identify
            // the icon used to identify any orders associated with an affiliate referral.
            //
            // On entry:
            //
            // $p2 ... (r/w) Contains a string, to which additional order "legend" icons can be appended.
            //
            case 'NOTIFY_ADMIN_ORDERS_MENU_LEGEND':
                $p2 .= snap_affiliates_image();
                break;
          
            // -----
            // Issued by Customers->Orders, for each listed order, allows us to identify whether the
            // order is associated with an affiliate's referral.
            //
            // On entry:
            //
            // $p2 ... (r/w) A copy of the order's database fields.
            // $p3 ... (r/w) A reference to the "show_difference" string
            // $p4 ... (r/w) A reference to the "extra action icons" string (not used by this processing).
            //
            case 'NOTIFY_ADMIN_ORDERS_SHOW_ORDER_DIFFERENCE':
                $p3 .= snap_affiliates_image($p2['orders_id']);
                break;
                
            // -----
            // Issued by Customers->Customers when a customer is being deleted.  Gives us the opportunity
            // to remove any referrers-table record associated with the customer.
            //
            // On entry:
            //
            // $p1 ... (r/o) An associative array containing the to-be-removed customers_id in a like-named index.
            //
            case 'NOTIFIER_ADMIN_ZEN_CUSTOMERS_DELETE_CONFIRM':
                if (is_array($p1) && !empty($p1['customers_id'])) {
                    $customers_id = (int)$p1['customers_id'];
                    $GLOBALS['db']->Execute(
                        "DELETE FROM " . TABLE_REFERRERS . "
                          WHERE referrer_customers_id = $customers_id
                          LIMIT 1"
                    );
                }
                break;
                
            default:
                break;
        }      
    }
}