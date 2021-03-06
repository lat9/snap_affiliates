<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v155 and later.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
define('NAVBAR_TITLE', 'Affiliate Main');
define('HEADING_TITLE', 'My Affiliate Statistics');

define('TEXT_ORDERS_PAYMENTS', 'Orders and Payments');
define('TEXT_MARKETING_TOOLS', 'Marketing Tools');
define('TEXT_REFERRER_TERMS', 'Referrer Terms');

define('TEXT_REFERRAL_SUBMITTED', 'Thanks for your interest in our referrer program.  Your application is under consideration.  As soon as we\'ve looked it over and made a decision, we\'ll contact you via email.');
define('TEXT_REFERRAL_BANNED', '<em>Your referrer account has been suspended.</em>  If you believe this to be an error, please <a href="%s">contact us</a>.');

define('TEXT_PLEASE_LOGIN', 'Please <a href="%s">login</a> to access your referral statistics.');
define('TEXT_REFERRER_SIGNUP', 'If you don\'t have a referral account and would like to open one, please proceed to our <a href="%s">signup page</a>.');
define('TEXT_CHOOSE', 'Choose');

define('HEADING_REFERRER_INFO', 'My Referrer Information');
define('TEXT_REFERRER_ID', 'My Referrer ID:');
define('TEXT_MY_WEBSITE', 'My Website:');
define('TEXT_LAST_PAYMENT_MADE', 'Last Payment Made On:');
define('TEXT_NO_PAYMENTS', 'No payments have been made yet');
define('TEXT_COMMISSION_RATE', 'My Commission Rate:');
define('TEXT_MY_PAYMENT_TYPE', 'My Payment Type:');

define('TEXT_SALES_SUMMARY', 'Sales Summary');
define('TEXT_CURRENT_SALES', 'Current Sales:');
define('TEXT_UNPAID_COMMISSION', 'Unpaid Commission:');
define('TEXT_YTD_SALES', 'Year-to-date Sales:');
define('TEXT_YTD_COMMISSION', 'Year-to-date Commission:');

define('TEXT_ACTIVITY', 'Commission-Payment Activity');
define('TEXT_NO_ACTIVITY', 'No commissionable orders were received during the requested period.');
define('TEXT_TO', 'To: ');
define('TEXT_FROM', 'From: ');
define('TEXT_UNPAID', 'Unpaid');
define('HEADING_PURCHASE_DATE', 'Date of Purchase');
define('HEADING_AMOUNT', 'Amount');
define('HEADING_COMMISSION_RATE', 'Commission Rate');
define('HEADING_COMMISSION_CALCULATED', 'Calculated Commission');
define('HEADING_COMMISSION_PAID', '<sup>*</sup>Commission Paid');
define('HEADING_COMMISSION_PAY_DATE', 'Commission Paid On');
define('HEADING_COMMISSION_PAY_TYPE', 'Commission Paid Via');
  define ('TEXT_UNKNOWN', 'Unknown');
define('HEADING_TOTALS', 'Totals');

define('TEXT_COMMISSION_PAID', '<strong><sup>*</sup>Commission Paid</strong> amounts include any refunds or returns that have being deducted from the final commission amount. For more information on how
commissions are calculated please visit our <a href="' . zen_href_link(FILENAME_REFERRER_SIGNUP, 'terms', 'SSL') . '">Referrer Terms and Conditions</a>.');
