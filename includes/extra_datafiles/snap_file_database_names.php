<?php
/*
** Database tables and filenames associated with the Snap Affiliates v2.0 plugin.
*/
define('TABLE_REFERRERS', DB_PREFIX . 'referrers');
define('TABLE_COMMISSION', DB_PREFIX . 'commission');


define('FILENAME_REFERRER_SIGNUP', 'referrer_signup');
define('FILENAME_REFERRER_MAIN', 'referrer_main');
define('FILENAME_REFERRER_TOOLS', 'referrer_tools');
define('FILENAME_REFERRER_EDIT', 'referrer_edit'); /*v2.2.0a*/

// -----
// These files can be modified using your admin's Tools->Define Pages Editor
// -----
// The terms associated with your referral program, used in the referrer_signup page.
define('FILENAME_DEFINE_REFERRAL_TERMS', 'define_referral_terms');
define('FILENAME_DEFINE_REFERRAL_TOOLS', 'define_referral_tools');