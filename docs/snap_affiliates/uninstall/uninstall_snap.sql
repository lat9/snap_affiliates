#
# Run this SQL script using your Zen Cart Tools->Install SQL Patches AFTER you have
# deleted the referrer-related PHP files from your admin directories.
#
DROP TABLE referrers;
DROP TABLE commission;

DELETE FROM configuration WHERE configuration_key LIKE 'SNAP_%';
DELETE FROM configuration_group WHERE configuration_group_title = 'Affiliate Program';
DELETE FROM admin_pages WHERE page_key = 'configurationAffiliates';
DELETE FROM admin_pages WHERE page_key = 'customersReferrers';
DELETE FROM query_builder WHERE query_name = 'All Affiliates';