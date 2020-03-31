<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.  Note, for versions
// of SNAP prior to v4.1.0, this processing was provided by /admin/includes/functions/extra_functions/init_referrers.php.
//
// Copyright (c) 2013-2020, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}
define('SNAP_MODULE_CURRENT_VERSION', '4.1.2-beta1');
define('SNAP_MODULE_UPDATE_DATE', '2020-03-31');

// -----
// Wait until an admin is logged in to perform any operations, so that any generated
// messages will be seen.
//
if (empty($_SESSION['admin_id'])) {
    return;
}

//----
// Create the Configuration->Affiliate Program item, if it's not already there.
//
$configurationGroupTitle = 'Affiliate Program';
$currentVersion = SNAP_MODULE_CURRENT_VERSION;
$currentDescription = SNAP_MODULE_UPDATE_DATE . ', <a href="https://vinosdefrutastropicales.com" target="_blank" rel="noreferrer">Vinos de Frutas Tropicales</a>';
$configuration = $db->Execute(
    "SELECT configuration_group_id 
       FROM " . TABLE_CONFIGURATION_GROUP . " 
      WHERE configuration_group_title = '$configurationGroupTitle' 
      ORDER BY configuration_group_id ASC"
);
if ($configuration->EOF) {
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION_GROUP . " 
            (configuration_group_title, configuration_group_description, sort_order, visible) 
         VALUES 
            ('$configurationGroupTitle', 'Set Affiliate Program Options', 1, 1)"
    );
    $cgi = $db->Insert_ID();
  
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION_GROUP . " 
            SET sort_order = $cgi 
          WHERE configuration_group_id = $cgi 
          LIMIT 1"
    );
} elseif ($configuration->RecordCount() != 1) {
    $cgi = $configuration->fields['configuration_group_id'];
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION . " 
            SET configuration_group_id = $cgi 
          WHERE configuration_key LIKE 'SNAP%'"
    );
    $db->Execute(
        "DELETE FROM " . TABLE_CONFIGURATION_GROUP . " 
          WHERE configuration_group_id != $cgi 
            AND configuration_group_title = '$configurationGroupTitle'"
    );
    zen_deregister_admin_pages('configurationAffiliates');
} else {
    $cgi = $configuration->fields['configuration_group_id'];
}

//-----
// If this is a new install, record the plugin's base/initial configuration settings
// and create its required database tables.
//
if (!defined('SNAP_MODULE_VERSION')) {
    // -----
    // Insert the plugin's original configuration items into the database; these will be filled-in
    // during the upgrade processing step to include the more-recently-added items.
    //
    $db->Execute(
        "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) 
         VALUES
            ('Module Version', 'SNAP_MODULE_VERSION', '0.0.0', '{$currentDescription}', $cgi, 10, now(), NULL, 'trim('),
            
            ('Default Commission', 'SNAP_DEFAULT_COMMISSION', '0.1', 'The default commission rate for your store\'s Affiliate Program.  The value should be specified as a floating-point number in the range 0.0 to 1.0.  The default value (<strong>0.1</strong>) represents a 10% commission rate.<br />', $cgi, 12, now(), NULL, NULL),
            
            ('Order Total Exclusions', 'SNAP_ORDER_TOTALS_EXCLUSIONS', 'ot_shipping,ot_tax', 'Exclude these Order Totals classes from an affiliate\'s commission. Enter the values as a comma-separated list with no intervening blanks.<br /><br />Default: <b>ot_shipping,ot_tax</b>.', $cgi, 14, now(), NULL, NULL),
            
            ('Affiliate Key Prefix', 'SNAP_KEY_PREFIX', 'CNWR_', 'Enter the prefix value to use for affiliate keys associated with your store\'s Affiliate Program. <strong>Note:</strong> If you change this value after you have started your program, existing affiliates will no longer earn their commissions!<br /><br />Default: <strong>CNWR_</strong>', $cgi, 16, now(), NULL, NULL),
            
            ('Send Affiliate Emails To', 'SNAP_ADMIN_EMAIL', 'Enter email address here', 'Enter the email address to which affiliate-related sign-up emails should be sent.<br />', $cgi, 18, now(), NULL, NULL),
            
            ('Affiliate Program Images', 'SNAP_AFFILIATE_IMAGES', '', 'Identify the images that your affiliates can use in their back-links.  Each file must be present in your store\'s /images/referrers directory and be named <em>ref.ww.hh.ext</em> where <em>ww</em> is the image width, <em>hh</em> is the image height and <em>ext</em> is the image extension (gif or jpg).<br /><br />Use the format /ww,hh,ext/[ww,hh,ext/...] to identify the files.  For example, if your store uses the files named ref.60.60.gif and ref.120.60.jpg for your program, you will enter this field as <b>/60,60,gif/120,60,jpg/</b><br />', $cgi, 19, now(), NULL, 'zen_cfg_textarea('), 
            
            ('Include in Information Sidebox?', 'SNAP_INFORMATION_SIDEBOX', 'false', 'Identifies whether (\'true\') or not (\'false\') to include a link to your Affiliate Program in the Information sidebox.<br /><br />Default: <strong>\'false\'</strong>.', $cgi, 20, now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')"
    );

    //----
    // Create each of the database tables for the referrers plugin, if they don't already exist.
    //
    $db->Execute(
        "CREATE TABLE IF NOT EXISTS " . TABLE_REFERRERS . " (
            referrer_customers_id int(11) not null primary key,
            referrer_key varchar(32) not null,
            referrer_homepage text not null,
            referrer_approved tinyint(4) not null,
            referrer_banned tinyint(4) not null,
            referrer_commission float not null
        )"
    );
    $db->Execute(
        "CREATE TABLE IF NOT EXISTS " . TABLE_COMMISSION . " (
            commission_id int(11) not null auto_increment primary key,
            commission_orders_id int(11) not null,
            commission_referrer_key varchar(96) not null,
            commission_rate float not null,
            commission_paid datetime not null,
            commission_paid_amount float not null,
            commission_manual tinyint(1) NOT NULL default 0
        )"
    );
    define('SNAP_MODULE_VERSION', '0.0.0');
}

// -----
// Now, update the various elements, if the currently-recorded version is
// different than the plugin's current version.
//
if (SNAP_MODULE_VERSION != SNAP_MODULE_CURRENT_VERSION) {
    // -----
    // During an update, handle some table-restructuring, first.
    //
    if (!$sniffer->field_exists(TABLE_COMMISSION, 'commission_id')) {
        $db->Execute("ALTER TABLE " . TABLE_COMMISSION . " DROP PRIMARY KEY , ADD COLUMN commission_id int(11) NOT NULL auto_increment PRIMARY KEY");
    }
    if (!$sniffer->field_exists(TABLE_COMMISSION, 'commission_paid_amount')) {
        $db->Execute("ALTER TABLE " . TABLE_COMMISSION . " ADD COLUMN commission_paid_amount float NOT NULL");
    }
    if (!$sniffer->field_exists(TABLE_COMMISSION, 'commission_manual')) {
        $db->Execute("ALTER TABLE " . TABLE_COMMISSION . " ADD COLUMN commission_manual tinyint(1) NOT NULL default 0");
    }
    if (!$sniffer->field_exists(TABLE_REFERRERS, 'referrer_payment_type')) {
        $db->Execute("ALTER TABLE " . TABLE_REFERRERS . " ADD COLUMN referrer_payment_type char(2) NOT NULL default 'CM', ADD COLUMN referrer_payment_type_detail varchar(255) NOT NULL default ''");
    }
    if (!$sniffer->field_exists(TABLE_COMMISSION, 'commission_payment_type')) {
        $db->Execute("ALTER TABLE " . TABLE_COMMISSION . " ADD COLUMN commission_payment_type char(2) NOT NULL default 'CM', ADD COLUMN commission_payment_type_detail varchar(255) NOT NULL default ''");
    }
    
    // -----
    // Now, deal with any configuration-related updates based on the previously-installed
    // version, noting that the updates cascade down to the switch's default.
    //
    switch (true) {
        case version_compare(SNAP_MODULE_VERSION, '2.1.0', '<'):
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) 
                 VALUES
                    ('Affiliate Display Count', 'SNAP_MAX_REFERRER_DISPLAY', '50', 'Specifies the maximum number of affiliates to show on each page of your admin\'s <em>Customers-&gt;Referrers</em>.<br /><br />Default: <strong>50</strong><br /><br />', $cgi, 22, now(), NULL, NULL),
                    
                    ('Allow Self-Commissions', 'SNAP_AFFILIATE_KEY_USE', 'false', 'Identifies whether (\'true\') or not (\'false\') an affiliate receives commission for purchases made using their own <em>affiliate key</em>.<br /><br />Default: <strong>\'false\'</strong>.', $cgi, 24, now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                    
                    ('Order Status Exclusions', 'SNAP_ORDER_STATUS_EXCLUSIONS', '', 'Exclude orders with the following <em>Order Status</em> values from affiliate commissions. Specify the values as a packed (i.e. no spaces) comma-separated list.<br /><br />Default: <br /><br />', $cgi, 26, now(), NULL, NULL)"
            );

        case version_compare(SNAP_MODULE_VERSION, '2.2.0', '<'):    //-Fall-through from above ...
            $snap_cookie_default = 60 * 60 * 24 * 365;
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) 
                 VALUES
                    ('Cookie Lifetime', 'SNAP_COOKIE_LIFETIME', '{$snap_cookie_default}', 'Specify the lifetime, <strong>in seconds</strong>, of the cookie that is set when a customer enters your store via an affiliate\'s link.  As long as this cookie is set in the customer\'s browser cache, the affiliate (if approved and not banned) will receive a commission for any purchase made by the customer.<br /><br />Default: <strong>{$snap_cookie_default} (60*60*24*365, i.e. one year)</strong><br /><br />', $cgi, 30, now(), NULL, NULL)"
            );
            
        case version_compare(SNAP_MODULE_VERSION, '2.3.0', '<'):    //-Fall-through from above ...
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) 
                 VALUES
                    ('Purchases Per Cookie', 'SNAP_AFFILIATE_COOKIE_PURCHASES', 'All', 'Choose the number of purchases that a customer can make on a single affiliate cookie, either <em>All</em> or <em>One</em>. If you choose <em>All</em>, then all purchases by the customer within the \"Cookie Lifetime\" will result in a commission to the associated affiliate. If you choose <em>One</em>, the customer\'s affiliate cookie is deleted upon completion of their first purchase within the affiliate cookie\'s lifetime.<br /><br />Default: <strong>All</strong>.', $cgi, 35, now(), NULL, 'zen_cfg_select_option(array(\'All\', \'One\'),')"
            );
            
        case version_compare(SNAP_MODULE_VERSION, '2.4.0', '<'):    //-Fall-through from above ...
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) 
                 VALUES
                    ('Combine Exclusions on Referrers Page', 'SNAP_AFFILIATE_COMBINE_EXCLUSIONS', 'No', 'If your store has a number of <em>Order Status Exclusions</em>, the display on a <em>Customers-&gt;Referrers</em> details page can get overloaded. To combine the \"Order Total\" and \"Commission Total\" values associated with <strong>all</strong> the <em>Order Status Exclusions</em> into a single column, choose <b>Yes</b>.  Choose <b>No</b> to display every \"Order Status\" value in a separate column.<br /><br />Default: <strong>No</strong>.', $cgi, 36, now(), NULL, 'zen_cfg_select_option(array(\'Yes\', \'No\'),')"
            );
            
        case version_compare(SNAP_MODULE_VERSION, '3.0.0', '<'):    //-Fall-through from above ...
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) 
                 VALUES
                    ('Enable PayPal&reg; Commission Payment Method?', 'SNAP_ENABLE_PAYMENT_CHOICE_PAYPAL', 'No', 'Do you want to enable your referrers to choose to receive their commission payments via PayPal? If set to <em>Yes</em>, a referrer will be prompted to enter their PayPal account email address upon making that selection.<br /><br /><b>Note:</b> PayPal payments are <em>not</em> paid automatically.  You must sign into your PayPal account to send money to your referrers\' accounts.<br /><br />Default: <strong>No</strong>.', $cgi, 50, now(), NULL, 'zen_cfg_select_option(array(\'Yes\', \'No\'),')"
            );
            
        case version_compare(SNAP_MODULE_VERSION, '4.0.1', '<'):    //-Fall-through from above ...
            // -----
            // Previous versions of SNAP affiliates stored '0000-00-00 00:00:00' as an unpaid commission's date.
            // That's not going to work with more recent (and stricter) MySql installations, so any such dates will
            // be changed to '0001-01-01 00:00:00'.  Note that this IS NOT a downwardly-compatible change!
            //
            $db->Execute(
                "UPDATE " . TABLE_COMMISSION . "
                    SET commission_paid = '0001-01-01 00:00:00'
                  WHERE CAST(commission_paid AS CHAR(20)) = '0000-00-00 00:00:00'"
            );
            if ($db->link->affected_rows != 0) {
                zen_record_admin_activity('One or more entries in SNAP Affiliates\' ' . TABLE_COMMISSIONS . ' table were updated for more recent versions of MySql.', 'warning');
            }
            
        case version_compare(SNAP_MODULE_VERSION, '4.1.0', '<'):    //-Fall-through from above ...
            $snap_default = (SNAP_MODULE_VERSION == '0.0.0') ? 'false' : 'true';
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) 
                 VALUES
                    ('Enable Storefront Processing?', 'SNAP_ENABLED', '$snap_default', 'Should the affiliates\' handling be enabled for the storefront?<br /><br />', $cgi, 11, now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')"
            );
            $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION . "
                    SET configuration_description = 'Exclude orders with the following <em>Order Status</em> <b>values</b> from affiliate commissions. Specify the values as a comma-separated list (intervening blanks are OK), e.g. <code>1, 5</code>.<br />'
                  WHERE configuration_key = 'SNAP_ORDER_STATUS_EXCLUSIONS'
                  LIMIT 1"
            );
            $snap_query = $db->Execute(
                "SELECT *
                   FROM " . TABLE_QUERY_BUILDER . "
                  WHERE query_name = 'All Affiliates'
                  LIMIT 1"
            );
            if ($snap_query->EOF) {
                $snap_qb_query = 
                    'SELECT c.customers_email_address, c.customers_lastname, c.customers_firstname ' .
                      'FROM TABLE_REFERRERS r ' .
                           'INNER JOIN TABLE_CUSTOMERS c ' .
                                'ON referrer_customers_id = c.customers_id ' .
                     'WHERE referrer_approved = 1 ' .
                       'AND referrer_banned = 0';
                $db->Execute(
                    "INSERT INTO " . TABLE_QUERY_BUILDER . "
                        (query_category, query_name, query_description, query_string, query_keys_list)
                     VALUES
                        ('email,newsletters', 'All Affiliates', 'For sending emails or newsletters to currently-subscribed SNAP Affiliates that have been approved and not banned.', '$snap_qb_query', '')"
                );
            }
            
        default:                                                    //-Fall-through from above ...
            break;
    }
    
    // -----
    // Update the configuration's version information to the most recent and let the currently signed-in
    // admin know the status.
    //
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION . " 
            SET configuration_value = '$currentVersion', 
                configuration_description = '$currentDescription', 
                last_modified = now() 
          WHERE configuration_key = 'SNAP_MODULE_VERSION' 
          LIMIT 1"
    );
    if (SNAP_MODULE_VERSION == '0.0.0') {
        $snap_message = sprintf(TEXT_SNAP_INSTALLED, SNAP_MODULE_CURRENT_VERSION);
    } else {
        $snap_message = sprintf(TEXT_SNAP_UPDATED, SNAP_MODULE_VERSION, SNAP_MODULE_CURRENT_VERSION);
    }
    $messageStack->add($snap_message, 'success');
    zen_record_admin_activity($snap_message, 'warning');
    
    // -----
    // Perform a little clean-up, making sure that any 'referrers' table entries that no longer have
    // a matching 'customers' record are removed.
    //
    $db->Execute(
        "DELETE FROM " . TABLE_REFERRERS . "
            WHERE referrer_customers_id NOT IN (
                SELECT customers_id FROM " . TABLE_CUSTOMERS . "
            )"
    );
}

//----
// Register the Affiliate Program configuration and the Referrers tool into the admin menu structure.
//
if (!zen_page_key_exists('configurationAffiliates')) {
    zen_register_admin_page('configurationAffiliates', 'BOX_CONFIGURATION_AFFILIATES', 'FILENAME_CONFIGURATION', "gID=$cgi", 'configuration', 'Y');
}
if (!zen_page_key_exists('customersReferrers')) {
    zen_register_admin_page('customersReferrers', 'BOX_CUSTOMERS_REFERRERS', 'FILENAME_REFERRERS', '', 'customers', 'Y');
}
