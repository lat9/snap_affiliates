<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: account.php 3595 2006-05-07 06:39:23Z drbyte $
 */
define('NAVBAR_TITLE', 'Affiliate Signup');
define('HEADING_TITLE', 'Affiliate Program Signup');

define('TEXT_ORDERS_PAYMENTS', 'Orders and Payments');
define('TEXT_MARKETING_TOOLS', 'Marketing Tools');
define('TEXT_REFERRER_TERMS', 'Referrer Terms');

define('HEADING_REFERRER_TERMS', 'Our Referrer Terms and Conditions');
define('TEXT_SIGN_UP', 'Interested in signing up for our referral program?  Enter the URL you\'d like to promote us from.  We\'ll take a look and let you know what we think.');
define('TEXT_NOT_LOGGED_IN', 'Interested in signing up for our referral program?  Please begin by <a href="%s">logging in to your account</a>. If you don\'t already have an account, you can create one <a href="%s">here</a>.');

define('TEXT_HOMEPAGE_URL', 'Homepage URL:');
define('ERROR_NO_URL', 'Please enter your homepage URL (for example, www.mysite.com) to sign up for our referral program.');

define('EMAIL_SUBJECT', STORE_NAME . ' Referrer Program Signup Request');
define('EMAIL_BODY', 'The customer named %1$s %2$s (customer ID %3$u) has applied to the ' . STORE_NAME . ' referral program. You can review their submission by using your Zen Cart admin\'s Customers->Referrers page.');