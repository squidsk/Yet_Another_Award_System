<?php
/*======================================================================*\
|| #################################################################### ||
|| # Yet Another Award System v4.0.5 © by HacNho                      # ||
|| # Copyright (C) 2005-2007 by HacNho, All rights reserved.          # ||
|| # ---------------------------------------------------------------- # ||
|| # For use with vBulletin Version 4.1.12                            # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| # Discussion and support available at                              # ||
|| # http://www.vbulletin.org/forum/showthread.php?t=232684           # ||
|| # ---------------------------------------------------------------- # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'award_version_info.php');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

$this_script = 'award_version_info';

global $vbulletin;

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################
print_cp_header($vbphrase['awards']);

// Get Latest Version from vBulletin.org
$productVerCheckURL = "http://www.vbulletin.org/forum/misc.php?do=productcheck&amp;pid=YaAS4-40";
$latestVersion = file_get_contents($productVerCheckURL);
$latestVersion = ereg_replace("[^A-Za-z0-9.]", "", $latestVersion );
$latestVersion = substr($latestVersion, 23);
$latestVersion = ereg_replace("[^0-9.]", "", $latestVersion );

// Get Current Version
$array = $db->query_first("SELECT version FROM " . TABLE_PREFIX . "product WHERE productid = 'yet_another_award_system' LIMIT 1");  
$currentVersion = $array[version];

// Begin Output
echo "<h2>Yet Another Awards System v$currentVersion</h2>";
echo "<hr>";
echo "<br />";

if ( $currentVersion > $latestVersion ) {
	echo "This is a beta build.";
	echo "<br />";
	echo "Current Released version is $latestVersion";
	}
	
if ( $currentVersion == $latestVersion ) {
	echo "This build is current.";
	}
	
if ( $currentVersion < $latestVersion ) {
	echo "An updated version has been released.";
	echo "<br />";
	echo "[ <a href='http://www.vbulletin.org/forum/showthread.php?t=232684'>Download Update</a> ]";
	}
echo "<br />";



// #############################################################################

print_cp_footer();

?>
