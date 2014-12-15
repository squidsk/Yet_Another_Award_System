<?php
/*======================================================================*\
|| #################################################################### ||
|| # Yet Another Award System v4.0.9 © by HacNho                      # ||
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
define('THIS_SCRIPT', 'YAAS_AWARD_REQUEST_ADMIN');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_bbcode.php');
$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

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

$vbulletin->input->clean_array_gpc('p', array(
	'award_id'		=> TYPE_UINT,
	'awarduserid'	=> TYPE_UINT
));

log_admin_action(iif($vbulletin->GPC['award_id'] != 0 AND $vbulletin->GPC['awarduserid'] != 0, "award_id = " . $vbulletin->GPC['award_id'] . ", userid = " . $vbulletin->GPC['awarduserid']));

if ($_REQUEST['do'] == 'display')
{
	// Grab List of Requests
	$awardRequests = $db->query_read("
		SELECT a.*, award.award_name, vb.username AS UserFor, vb2.username AS UserFrom
		FROM " . TABLE_PREFIX . "award_requests AS a
		LEFT JOIN " . TABLE_PREFIX . "award AS award on (a.award_req_aid = award.award_id) 
		LEFT JOIN " . TABLE_PREFIX . "user AS vb on (a.award_rec_uid = vb.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS vb2 on (a.award_req_uid = vb2.userid)
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
	), 1, '', 1);

	while ($celldata = $db->fetch_array($awardRequests))
	{
		$cell = array();
		
		$cell[] = $celldata[award_req_timestamp];
		$cell[] = ($celldata[award_req_uid] == 0 ? $vbphrase['guest'] : $celldata[UserFrom]);
		$cell[] = $celldata[UserFor];
		$cell[] = $celldata[award_name];
		$cell[] = $celldata[award_req_reason];
		$cell[] = construct_link_code(
					$vbphrase['yaas_grant'], "award_requests.php?"
					. $vbulletin->session->vars['sessionurl']
					. "do=grant&amp;taskid=$celldata[award_req_id]"
				) .
				construct_link_code(
					$vbphrase['delete'], "award_requests.php?"
					. $vbulletin->session->vars['sessionurl']
					. "do=delete&amp;taskid=$celldata[award_req_id]"
				);

		print_cells_row($cell, 0, '', 1);
	}
	print_table_footer(6, '', '', 0);  
}

if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'taskid'	=> TYPE_INT,
	));

	if (!$vbulletin->GPC['taskid'])
	{
		print_stop_message('yaas_no_task_defined');
	}
	else
	{
		$result = $db->query_write("DELETE FROM " . TABLE_PREFIX . "award_requests WHERE award_req_id = " . $vbulletin->GPC['taskid'] );
		define('CP_REDIRECT', 'award_requests.php?do=display');
		if(!$vbulletin->db->affected_rows()){
			print_stop_message('yaas_invalid_award_request');
		} else {
			print_stop_message('award_request_task_deleted');
		}
	}
}

if ($_GET['do'] == 'grant')
{
	global $stylevar;

	$vbulletin->input->clean_array_gpc('r', array(
		'taskid' => TYPE_INT,
	));

	if (!$vbulletin->GPC['taskid'])
	{
		print_stop_message('yaas_no_task_defined');
	}
	else
	{
		$award_request = $db->query_first("
			SELECT a.*, award.award_name, award.award_desc, award.award_icon_url, award.award_img_url, vb.username AS UserFor, vb2.username AS UserFrom
			FROM " . TABLE_PREFIX . "award_requests AS a
			LEFT JOIN " . TABLE_PREFIX . "award AS award on (a.award_req_aid = award.award_id) 
			LEFT JOIN " . TABLE_PREFIX . "user AS vb on (a.award_rec_uid = vb.userid)
			LEFT JOIN " . TABLE_PREFIX . "user AS vb2 on (a.award_req_uid = vb2.userid)
			WHERE a.award_req_id = " . $vbulletin->GPC['taskid']
		);
	}

	if (empty($award_request['award_name']))
	{
		print_stop_message('no_awards_defined');
	}

	$award_request['award_desc'] = $bbcode_parser->parse($award_request['award_desc']);

	// print award information	
	print_table_start();

	echo "<colgroup>
	<col align=\"center\" style=\"white-space:nowrap\"></col>
	<col width=\"50%\"></col>
	<col align=\"center\" style=\"white-space:nowrap\"></col>
	<col align=\"center\" style=\"white-space:nowrap\"></col>
	<col align=\"center\" style=\"white-space:nowrap\"></col>
	</colgroup>";

	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['award_name'], $award_request['award_name'], $award_request['award_req_aid']), 5, 0);
	
	print_cells_row(array(
		$vbphrase['award_name'],
		$vbphrase['award_description'],
		$vbphrase['award_icon'],
		$vbphrase['award_image'],
		$vbphrase['manage']
	), 1, '', -1);

	echo "
	<tr>
		<td class=\"$bgclass\"><strong>$award_request[award_name]</strong></td>
		<td class=\"$bgclass\"><dfn>{$award_request[award_desc]}</dfn></td>
		<td class=\"$bgclass\" align=\"center\"><img src=\"" . iif(substr($award_request[award_icon_url], 0, 7) != 'http://' AND substr($award_request[award_icon_url], 0, 1) != '/', '../', '') . "$award_request[award_icon_url]\" border=\"0\" alt=\"\" /></td>
		<td class=\"$bgclass\" align=\"center\"><img src=\"" . iif(substr($award_request[award_img_url], 0, 7) != 'http://' AND substr($award_request[award_img_url], 0, 1) != '/', '../', '') . "$award_request[award_img_url]\" border=\"0\" alt=\"\" /></td>
		<td class=\"$bgclass\" align=\"center\">" .
			construct_link_code(
				$vbphrase['edit'], "award.php?"
				. $vbulletin->session->vars['sessionurl']
				. "do=edit"
				. "&amp;award_id=" . $award_request['award_req_aid']
			) .
			construct_link_code(
				$vbphrase['delete'], "award.php?"
				. $vbulletin->session->vars['sessionurl']
				. "do=remove"
				. "&amp;award_id=" . $award_request['award_req_aid']
			) .
		"</td>
	  </tr>";
	print_table_footer(2, '', '', false);

	// print give award to user block
	print_form_header('award_requests', 'dogrant');
	construct_hidden_code('award_id', $award_request['award_req_aid']);
	construct_hidden_code('award_name', $award_request['award_name']);
	construct_hidden_code('award_img_url', $award_request['award_img_url']);
	construct_hidden_code('taskid', $vbulletin->GPC['taskid']);

	print_table_header("$vbphrase[add] $vbphrase[user_awards]", 2, 0);
	print_description_row($vbphrase[give_user_award_desc]);
	print_input_row($vbphrase['userid'], 'awarduserid', $award_request['award_rec_uid']);
	print_input_row($vbphrase['username'], 'awardusername', $award_request['UserFor']);
	print_textarea_row($vbphrase['award_reason'], 'issue_reason', $award_request['award_req_reason'], 3, 33);
	print_checkbox_row($vbphrase['award_sendpm'], 'award_sendpm');
	print_checkbox_row($vbphrase['award_sendemail'], 'award_sendemail',0);

	print_submit_row($vbphrase['save']);
}

if($_POST['do'] == 'dogrant')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'award_id'			=> TYPE_INT,
		'award_name'		=> TYPE_STR,
		'award_img_url'		=> TYPE_STR,
		'awarduserid'		=> TYPE_INT,
		'awardusername'		=> TYPE_STR,
		'issue_reason'		=> TYPE_STR,
		'award_sendpm'		=> TYPE_INT,
		'award_sendemail'	=> TYPE_INT,
		'taskid'			=> TYPE_INT
	));

	if (!empty($vbulletin->GPC['awarduserid']))
	{
		$user = $db->query_first("
			SELECT userid, username, email
			FROM " . TABLE_PREFIX . "user
			WHERE userid = ". $vbulletin->GPC['awarduserid'] ."
		");
	}
	else if (!empty($vbulletin->GPC['awardusername']))
	{
		$user = $db->query_first("
			SELECT userid, username, email
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . $db->escape_string($vbulletin->GPC['awardusername']) ."'
		");
	}
	else
	{
		print_stop_message('please_complete_required_fields');
	}

	if (empty($user))
	{
		// no users found!
		print_stop_message('no_users_matched_your_query');
	}

	if (empty($vbulletin->GPC['award_id']))
	{
		// no users found!
		print_stop_message('no_awards_defined');
	}

	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "award_user
		(award_id, userid, issue_reason, issue_time) 
		VALUES ( '". $vbulletin->GPC['award_id'] ."', '". $user['userid'] ."', '" . $db->escape_string($vbulletin->GPC['issue_reason']) . "', " . time() . ")
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "award_requests WHERE award_req_id = " . $vbulletin->GPC['taskid']
	);

	if ($vbulletin->GPC['award_sendpm'])
	{
		if ($vbulletin->options['award_pm_fromuserid'] != 0)
		{
			$fromuser = verify_id('user', $vbulletin->options['award_pm_fromuserid'], true, true);
		}
		else
		{
			$fromuser['userid'] = $vbulletin->userinfo['userid'];
			$fromuser['username'] = $vbulletin->userinfo['username'];
		}

		$username = unhtmlspecialchars($user['username']);
		$award_id = $vbulletin->GPC['award_id'];
		$award_name = $vbulletin->GPC['award_name'];
		$award_img_url = $vbulletin->GPC['award_img_url'];
		$issue_reason = $vbulletin->GPC['issue_reason'];

		eval(fetch_email_phrases('award_pm'));

		//relative urls are converted to absolute so that the [img] bbcode works
		$message = preg_replace("#(\[img\])(?=(?!http)(?!https))([^\s]+)(\[/img\])#", '$1' . $vbulletin->options['bburl'] . '/$2$3', $message);

		$pmdm =& datamanager_init('PM', $vbulletin, ERRTYPE_ARRAY);
		$pmdm->set('fromuserid', $fromuser['userid']);
		$pmdm->set('fromusername', $fromuser['username']); 
		$pmdm->set('title', $subject);
		$pmdm->set('message', $message);
		$pmdm->set_recipients($user['username'], $null);
		$pmdm->set('dateline', TIMENOW);
		$pmdm->pre_save();
		
		// process errors if there are any
		$errors = $pmdm->errors;
		
		if (!empty($errors))
		{
			require_once(DIR . '/includes/functions_newpost.php');
			$error = construct_errors($errors); // this will take the preview's place
			eval(standard_error($error));
		}
		else
		{
			// everything's good!
			$pmdm->save();
			unset($pmdm);
		}
	}

	if ($vbulletin->GPC['award_sendemail'])
	{
		if ($vbulletin->options['award_pm_fromuserid'] != 0)
		{
			$fromuser = verify_id('user', $vbulletin->options['award_pm_fromuserid'],'true','true');
		}
		else
		{
			$fromuser['email'] = $vbulletin->userinfo['email'];
		}

		$username = unhtmlspecialchars($user['username']);
		$award_id = $vbulletin->GPC['award_id'];
		$award_name = $vbulletin->GPC['award_name'];
		$award_img_url = $vbulletin->GPC['award_img_url'];
		$issue_reason = $vbulletin->GPC['issue_reason'];

		eval(fetch_email_phrases('award_pm'));

		//relative urls are converted to absolute so that the [img] bbcode works
		$message = preg_replace("#(\[img\])(?=(?!http)(?!https))([^\s]+)(\[/img\])#", '$1' . $vbulletin->options['bburl'] . '/$2$3', $message);

		vbmail($user['email'], $subject, $message, true, $fromuser['email']);
	}

	define('CP_REDIRECT', 'award_requests.php?do=display');
	print_stop_message('give_award_to_user_x_successfully', $user['username']);
}

############################################################################

print_cp_footer();

?>
