<?php

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
require_once(DIR . '/includes/class_bbcode.php');
$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

$this_script = 'award_requests';

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

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'display';
}

if ($_REQUEST['do'] == 'display')
{
		
	// Grab List of Requests
	$awardRequests = $db->query_read("
	SELECT a.*, award.award_name, vb.username AS UserFor, vb2.username AS UserFrom
FROM " . TABLE_PREFIX . "award_requests AS a, " . TABLE_PREFIX . "award AS award, " . TABLE_PREFIX . "user AS vb, " . TABLE_PREFIX . "user AS vb2
WHERE a.award_req_aid = award.award_id AND a.award_rec_uid = vb.userid AND a.award_req_uid = vb2.userid
	");
	
	// Construct Table Header
	print_form_header('', '');
	print_table_header($vbphrase['award_requests'], 6);
	print_cells_row(array(
			$vbphrase['award_request_time'],
			$vbphrase['award_request_user_from'],
			$vbphrase['award_request_user_for'],
			$vbphrase['award_request_award_name'],
			$vbphrase['award_request_reason'],
			$vbphrase['controls']
			), 1, '', -1);
			
		while ($celldata = $db->fetch_array($awardRequests))
		{
		
		$cell = array();
		
		$cell[] = $celldata[award_req_timestamp];
		$cell[] = $celldata[UserFrom];
		$cell[] = $celldata[UserFor];
		$cell[] = $celldata[award_name];
		$cell[] = $celldata[award_req_reason];
		$cell[] = "<a href='award.php?do=awardusers&award_id=$celldata[award_req_aid]&user_id=$celldata[award_rec_uid]&issue_reason=$celldata[award_req_reason]'>Grant</a><br /><a href='award_requests.php?do=delete&taskid=$celldata[award_req_id]'>Delete</a>";
		
	print_cells_row($cell, 0, '', 1);
	}
	 print_table_footer(6, '', '', 0);  
}

if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "award_requests WHERE (award_req_id = '$_GET[taskid]')");
	define('CP_REDIRECT', 'award_requests.php?do=display');
	print_stop_message('award_request_task_deleted');
}

############################################################################

print_cp_footer();

?>