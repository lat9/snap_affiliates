#
# Run this SQL script using your Zen Cart Tools->Install SQL Patches AFTER you have
# deleted the referrer-related PHP files from your admin directories.
#
DROP TABLE referrers;
DROP TABLE commission;

SELECT @cgi:=configuration_group_id FROM configuration_group WHERE configuration_group_title='Affiliate Program';
DELETE FROM configuration WHERE configuration_group_id=@cgi AND configuration_group_id != 0;
DELETE FROM configuration_group WHERE configuration_group_id=@cgi AND configuration_group_id != 0;
DELETE FROM admin_pages WHERE page_key='configurationAffiliates';
DELETE FROM admin_pages WHERE page_key='customersReferrers';