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
define('THIS_SCRIPT', 'award.php');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_bbcode.php');
$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

$this_script = 'award';

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['awards']);

// ************************************************************
// start functions

// ###################### Start showimage #######################
function construct_img_html($imagepath, $align = 'middle')
{
	// returns an image based on imagepath
	return '<img src="' . iif(substr($imagepath, 0, 7) != 'http://' AND substr($imagepath, 0, 1) != '/', '../', '') . "$imagepath\" alt=\"$imagepath\" align=\"$align\" border=\"0\"/>";
}

// ###################### Start getAwardCategoryParentOptions #######################

function fetch_award_parent_options($thisitem = '', $parentid = -1, $depth = 1)
{
	global $db, $iawcache, $parentoptions;

	if (!is_array($iawcache))
	{
	// check to see if we have already got the results from the database
		$iawcache = array();
		$awcats = $db->query_read("
			SELECT award_cat_id, award_cat_title, award_cat_parentid
			FROM " . TABLE_PREFIX . "award_cat
		");
		
		while ($awcat = $db->fetch_array($awcats))
		{
			$iawcache["$awcat[award_cat_parentid]"]["$awcat[award_cat_id]"] = $awcat;
		}
		$db->free_result($awcats);
	}

	if (!is_array($parentoptions))
	{
		$parentoptions = array();
	}

	foreach($iawcache["$parentid"] AS $cat)
	{
		if ($cat['award_cat_id'] != $thisitem)
		{
			$parentoptions["$cat[award_cat_id]"] = str_repeat('--', $depth) . ' ' . $cat['award_cat_title'];
			if (is_array($iawcache["$cat[award_cat_id]"]))
			{
				fetch_award_parent_options($thisitem, $cat['award_cat_id'], $depth + 1);
			}
		}
	}
}

// ###################### Start get award_cat_cache #######################

function cache_award_cats($award_cat_id = -1, $depth = 0, $display_award_cat_id=0)
{
	// returns an array of award cats with correct parenting and depth information

	global $db, $award_cat_cache, $count;
	static $fcache, $i;
	
	if (!is_array($fcache))
	{
	// check to see if we have already got the results from the database
		$fcache = array();
		$award_cats = $db->query_read("
			SELECT * FROM " . TABLE_PREFIX . "award_cat
			" . iif($display_award_cat_id, "WHERE award_cat_id = $display_award_cat_id", '') . "
			ORDER BY award_cat_displayorder
		");
		while ($award_cat = $db->fetch_array($award_cats))
		{
			if ($display_award_cat_id)
			{
			$award_cat[award_cat_parentid] = -1;
			}
			$fcache["$award_cat[award_cat_parentid]"]["$award_cat[award_cat_displayorder]"]["$award_cat[award_cat_id]"] = $award_cat;
		}
	}

	// database has already been queried
	if (is_array($fcache["$award_cat_id"]))
	{
		foreach ($fcache["$award_cat_id"] AS $holder)
		{
			foreach ($holder AS $award_cat)
			{
				$award_cat_cache["$award_cat[award_cat_id]"] = $award_cat;
				$award_cat_cache["$award_cat[award_cat_id]"]['depth'] = $depth;
				unset($fcache["$award_cat_id"]);
				cache_award_cats($award_cat['award_cat_id'], $depth + 1, $display_award_cat_id);
			} // end foreach ($val1 AS $key2 => $forum)
		} // end foreach ($fcache["$forumid"] AS $key1 => $val1)
	} // end if (found $fcache["$forumid"])
}

// end functions
// ************************************************************

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'manage';
}

// ###################### Start insert #######################
if ($_POST['do'] == 'insert')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'award_name' => TYPE_STR, 
		'award_desc' => TYPE_STR, 
		'award_pointvalue' => TYPE_INT, 
		'award_icon_url' => TYPE_STR, 
		'award_img_url' => TYPE_STR,
		'award_cat_id' => TYPE_INT,
		'award_displayorder' => TYPE_INT,	
		'award_allowrequest' => TYPE_INT,
		'award_allowrecommend' => TYPE_INT,
		'award_active' => TYPE_INT,
	));

	if (empty($vbulletin->GPC['award_name']))
	{
		print_stop_message('invalid_award_name_specified');
	}

	$db->query_write("
	INSERT INTO " . TABLE_PREFIX . "award 
		(award_name, award_desc, award_icon_url, award_img_url, award_cat_id, award_displayorder, award_allowrequest, award_allowrecommend, award_pointvalue, award_active)	
	VALUES 
		('" . addslashes($vbulletin->GPC['award_name']) . "',
			'" . addslashes($vbulletin->GPC['award_desc']) . "',
			'" . addslashes($vbulletin->GPC['award_icon_url']) . "',
			'" . addslashes($vbulletin->GPC['award_img_url']) . "',
			'" . intval($vbulletin->GPC['award_cat_id']) . "',
			'" . intval($vbulletin->GPC['award_displayorder']) . "',
			'" . intval($vbulletin->GPC['award_allowrequest']) . "',
			'" . intval($vbulletin->GPC['award_allowrecommend']) . "',
			'" . intval($vbulletin->GPC['award_pointvalue']) . "',
			'" . intval($vbulletin->GPC['award_active']) . "'
		)");

	define('CP_REDIRECT', 'award.php?do=manage');
	print_stop_message('saved_award_named_x_successfully', $title);
}

// ###################### Start add #######################
if ($_REQUEST['do'] == 'add')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_cat_id' => TYPE_INT,
	));
	
	print_form_header('award', 'insert');
	print_table_header($vbphrase['add_new_award']);

	print_input_row($vbphrase['award_name'], 'award_name');
	print_textarea_row($vbphrase['award_description'], 'award_desc', '', 3, 33);
	
	print_input_row($vbphrase['award_pointvalue'],'award_pointvalue',0);
	
	print_input_row($vbphrase['award_icon_url'], 'award_icon_url');
	print_input_row($vbphrase['award_image_url'], 'award_img_url');

	$parentoptions = array('-1' => $vbphrase["no_one"]);
	fetch_award_parent_options($category['award_cat_id']);

	print_select_row($vbphrase["award_category"], 'award_cat_id', $parentoptions,  $vbulletin->GPC['award_cat_id']);
	print_input_row($vbphrase['display_order'],'award_displayorder',1);

	print_yes_no_row($vbphrase['award_allowrequest'], 'award_allowrequest');
	print_yes_no_row($vbphrase['award_allowrecommend'], 'award_allowrecommend');
	print_yes_no_row($vbphrase['award_active'], 'award_active');

	print_submit_row($vbphrase['save']);
}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
	'award_id'  => TYPE_INT, 
	'award_name' => TYPE_STR, 
	'award_desc' => TYPE_STR, 


	'award_pointvalue' => TYPE_INT, 
	'award_icon_url' => TYPE_STR, 
	'award_img_url' => TYPE_STR,
	'award_cat_id' => TYPE_INT,
	'award_displayorder' => TYPE_INT,
	'award_allowrequest' => TYPE_INT,
	'award_allowrecommend' => TYPE_INT,
	'award_active' => TYPE_INT,
	));

	if (empty($vbulletin->GPC['award_name']))
	{
		print_stop_message('invalid_award_name_specified');
	}
	if (empty($vbulletin->GPC['award_id']))
	{
		print_stop_message('no_awards_defined');
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "award
		SET award_name = '" . addslashes($vbulletin->GPC['award_name']) . "',
		award_desc = '" . addslashes($vbulletin->GPC['award_desc']) . "',
		award_icon_url = '" . addslashes($vbulletin->GPC['award_icon_url']) . "',
		award_img_url = '" . addslashes($vbulletin->GPC['award_img_url']) . "',
		award_cat_id = '" . intval($vbulletin->GPC['award_cat_id']) . "',
		award_displayorder = '" . intval($vbulletin->GPC['award_displayorder']) . "',
		award_allowrequest = '" . intval($vbulletin->GPC['award_allowrequest']) . "',
		award_allowrecommend = '" . intval($vbulletin->GPC['award_allowrecommend']) . "',
		award_active = '" . intval($vbulletin->GPC['award_active']) . "',
		award_pointvalue = '" . intval($vbulletin->GPC['award_pointvalue']) . "'
		WHERE award_id = ". $vbulletin->GPC['award_id'] ."
	");

	define('CP_REDIRECT', 'award.php?do=manage');
	print_stop_message('saved_award_named_x_successfully', $vbulletin->GPC['award_name']);

}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_id' => TYPE_INT
	));

	if (empty($vbulletin->GPC['award_id']))
	{
		print_stop_message('no_awards_defined');
	}
		
	$award = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "award WHERE award_id = ". $vbulletin->GPC['award_id'] ."");

	print_form_header('award', 'doupdate');
	construct_hidden_code('award_id', $vbulletin->GPC['award_id']);

	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['award_name'], $award['award_name'], $vbulletin->GPC['award_id']), 2, 0);
	print_input_row($vbphrase['award_name'], 'award_name', $award['award_name']);
	print_textarea_row($vbphrase['award_description'], 'award_desc', $award['award_desc'], 3, 33);
	print_input_row($vbphrase['award_pointvalue'],'award_pointvalue',$award[award_pointvalue]);
	print_input_row($vbphrase['award_icon_url'], 'award_icon_url', $award['award_icon_url']);
	print_input_row($vbphrase['award_image_url'], 'award_img_url', $award['award_img_url']);
	
	$parentoptions = array('-1' => $vbphrase["no_one"]);
	fetch_award_parent_options($category['award_cat_id']);
	
	print_select_row($vbphrase["award_category"], 'award_cat_id', $parentoptions,  $award[award_cat_id]);
	print_input_row($vbphrase['display_order'],'award_displayorder',$award[award_displayorder]);

	print_yes_no_row($vbphrase['award_allowrequest'], 'award_allowrequest', $award['award_allowrequest']);
	print_yes_no_row($vbphrase['award_allowrecommend'], 'award_allowrecommend', $award['award_allowrecommend']);
	print_yes_no_row($vbphrase['award_active'], 'award_active', $award['award_active']);
	
	print_submit_row($vbphrase['save']);
}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_id' => TYPE_INT
	));
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "award WHERE award_id = ". $vbulletin->GPC['award_id'] ."");
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "award_user WHERE award_id = ". $vbulletin->GPC['award_id'] ."");

	define('CP_REDIRECT', 'award.php?do=manage');
	print_stop_message('deleted_award_successfully');
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_id' => TYPE_INT
	));

	if (!empty($vbulletin->GPC['award_id']))
	{
		$award = $db->query_first("
			SELECT award_id, award_name, award_desc
			FROM " . TABLE_PREFIX . "award
			WHERE award_id = ". $vbulletin->GPC['award_id'] ."
		");
	}
	else
	{
		print_stop_message('no_awards_defined');
	}
	if (empty($award))
	{
		// no award found!
		print_stop_message('no_awards_defined');
	}

	print_form_header('award', 'kill');
	construct_hidden_code('award_id', $vbulletin->GPC['award_id']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_award']);

		print_description_row('<blockquote>' . construct_phrase($vbphrase["are_you_sure_you_want_to_delete_this_award_x_y"], $award['award_name'], $award['award_desc']) . '</blockquote>');
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Start do REMOVE award #######################
if ($_POST['do'] == 'doremoveissuedaward')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issue_id' => TYPE_INT,
		'award_id' => TYPE_INT,
		'validate' => TYPE_ARRAY
	));
	if (!empty($vbulletin->GPC['validate']))
	{
		foreach($vbulletin->GPC['validate'] AS $vbulletin->GPC['issue_id'] => $status)
		{
			if ($status == -1)
			{
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "award_user WHERE issue_id = ". $vbulletin->GPC['issue_id'] ."");
			}
		}
		define('CP_REDIRECT', "award.php?do=awardusers&amp;award_id=" . $vbulletin->GPC['award_id']);
		print_stop_message('removed_award_from_users_successfully');
	}
	else if (!empty($vbulletin->GPC['issue_id']))
	{
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "award_user WHERE issue_id = ". $vbulletin->GPC['issue_id'] ."");
		define('CP_REDIRECT', "award.php?do=awardusers&amp;award_id=" . $vbulletin->GPC['award_id']);
		print_stop_message('removed_award_from_users_successfully');
	}
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'removeissuedaward')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issue_id' => TYPE_INT
	));

	if (!empty($vbulletin->GPC['issue_id']))
	{
		$award = $db->query_first("
			SELECT aw.award_name, aw.award_desc, au.*, u.username
			FROM " . TABLE_PREFIX . "award_user AS au
			LEFT JOIN  " . TABLE_PREFIX . "award AS aw ON (aw.award_id = au.award_id)
			LEFT JOIN  " . TABLE_PREFIX . "user AS u ON (u.userid = au.userid)
			WHERE au.issue_id = ". $vbulletin->GPC['issue_id'] ."
		");
	}
	else
	{
		print_stop_message('no_awards_defined');
	}
	if (empty($award))
	{
		// no award found!
		print_stop_message('no_awards_defined');
	}

	print_form_header('award', 'doremoveissuedaward');
	construct_hidden_code('issue_id', $vbulletin->GPC['issue_id']);
	construct_hidden_code('award_id', $award['award_id']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_award']);
	print_description_row('<blockquote>' . construct_phrase($vbphrase["are_you_sure_you_want_to_remove_this_award_1_2_from_3_reason_4"], $award['award_name'], $award['award_desc'],$award['username'],$award['issue_reason']) . '</blockquote>');
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}
// ###################### Start do edit award #######################
if ($_POST['do'] == 'doeditissuedaward')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issue_id' => TYPE_INT,
		'award_id' => TYPE_INT,
		'awarduserid' => TYPE_INT,
		'awardusername' => TYPE_STR,
		'issue_reason' => TYPE_STR,
		'issue_time'	=> TYPE_ARRAY_INT
	));

	if (!empty($vbulletin->GPC['awarduserid']))
	{
		$user = $db->query_first("
			SELECT userid, username
			FROM " . TABLE_PREFIX . "user
			WHERE userid = ". $vbulletin->GPC['awarduserid'] ."
		");
	}
	else if (!empty($vbulletin->GPC['awardusername']))
	{
		$user = $db->query_first("
			SELECT userid, username
			FROM " . TABLE_PREFIX . "user
			WHERE username = '". $db->escape_string($vbulletin->GPC['awardusername']) ."'
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
		require_once(DIR . '/includes/functions_misc.php');
		$vbulletin->GPC['issue_time'] = vbmktime(intval($vbulletin->GPC['issue_time']['hour']), intval($vbulletin->GPC['issue_time']['minute']), 0, intval($vbulletin->GPC['issue_time']['month']), intval($vbulletin->GPC['issue_time']['day']), intval($vbulletin->GPC['issue_time']['year']));


	$db->query_write("
		UPDATE " . TABLE_PREFIX . "award_user
		SET 
			userid =  '". $user['userid'] ."',
			issue_reason = '" . addslashes($vbulletin->GPC['issue_reason']) . "',
			issue_time = '" . addslashes($vbulletin->GPC['issue_time']) . "'
		WHERE issue_id = ". $vbulletin->GPC['issue_id'] ."
	");

	define('CP_REDIRECT', 'award.php?do=awardusers&amp;award_id=' . $vbulletin->GPC['award_id']);
	print_stop_message('give_award_to_user_x_successfully', $user['username']);
}
// ###################### Start edit #######################
if ($_REQUEST['do'] == 'editissuedaward')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issue_id' => TYPE_INT
	));

	if (empty($vbulletin->GPC['issue_id']))
	{
		print_stop_message('no_awards_defined');
	}
		
	$award = $db->query_first("
		SELECT au.*, aw.* 
		FROM " . TABLE_PREFIX . "award_user AS au
		LEFT JOIN  " . TABLE_PREFIX . "award AS aw ON (aw.award_id = au.award_id)
		WHERE au.issue_id = ". $vbulletin->GPC['issue_id'] ."
	");

   print_form_header();
   print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['award_name'], $award['award_name'], $award['award_id']), 5, 0);

   echo "
   <col align=\"center\" style=\"white-space:nowrap\"></col>
   <col width=\"50%\" align=\"$stylevar[left]\"></col>
   <col align=\"center\" style=\"white-space:nowrap\"></col>
   <col align=\"center\" style=\"white-space:nowrap\"></col>
   <col align=\"center\" style=\"white-space:nowrap\"></col>
   ";

   print_cells_row(array(
           $vbphrase['award_name'],
           $vbphrase['award_description'],
           $vbphrase['award_icon'],
           $vbphrase['award_image'],
           $vbphrase['manage'],
   ), 1, '', -1);
   
  echo "
  <tr>
		<td class=\"$bgclass\"><strong>$award[award_name]</strong></td>
		<td class=\"$bgclass\"><dfn>{$award[award_desc]}</dfn></td>
		<td class=\"$bgclass\" align=\"center\"><img src=\"" . iif(substr($award[award_icon_url], 0, 7) != 'http://' AND substr($award[award_icon_url], 0, 1) != '/', '../', '') . "$award[award_icon_url]\" border=\"0\"></td>
		<td class=\"$bgclass\" align=\"center\"><img src=\"" . iif(substr($award[award_img_url], 0, 7) != 'http://' AND substr($award[award_img_url], 0, 1) != '/', '../', '') . "$award[award_img_url]\" border=\"0\"></td>
		<td class=\"$bgclass\">" .
				construct_link_code(
					$vbphrase['edit'], "award.php?"
					. $vbulletin->session->vars['sessionurl']
					. "do=edit"
					. "&amp;award_id=".$vbulletin->GPC['award_id']
				) .
				construct_link_code(
					$vbphrase['delete'], "award.php?"
					. $vbulletin->session->vars['sessionurl']
					. "do=remove"
					. "&amp;award_id=" . $vbulletin->GPC['award_id']
				) .				
		"</td>

  </tr>";
	print_table_footer();

	print_form_header('award', 'doeditissuedaward');
	construct_hidden_code('issue_id', $vbulletin->GPC['issue_id']);
	construct_hidden_code('award_id', $award['award_id']);
	print_table_header("$vbphrase[edit] $vbphrase[user_awards]", 2, 0);
	print_input_row($vbphrase['userid'], 'awarduserid', $award['userid']);
	print_input_row($vbphrase['username'], 'awardusername',$award['username']);	
	print_textarea_row($vbphrase['award_reason'], 'issue_reason', $award['issue_reason'], 3, 33);
	print_time_row($vbphrase['award_time'],'issue_time',$award['issue_time']);
	print_submit_row($vbphrase['save']);
}

// ###################### Start do give award #######################
if ($_POST['do'] == 'dogiveaward')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'award_id' => TYPE_INT,
		'award_name' => TYPE_STR,
		'award_img_url' => TYPE_STR,
		'awarduserid' => TYPE_INT,
		'awardusername' => TYPE_STR,
		'issue_reason' => TYPE_STR,
		'award_sendpm' => TYPE_INT,
		'award_sendemail' => TYPE_INT,
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
			SELECT userid, username
			FROM " . TABLE_PREFIX . "user
			WHERE username = '". $db->escape_string($vbulletin->GPC['awardusername']) ."'
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
		VALUES ( '". $vbulletin->GPC['award_id'] ."', '". $user['userid'] ."', '" . addslashes($vbulletin->GPC['issue_reason']) . "', " . time() . ")
	");
	$issue_id = mysql_insert_id(); 
	if ($vbulletin->GPC['award_sendpm'])
	{
		if ($vbulletin->options['award_pm_fromuserid'] != 0)
		{
//		    $fromuser = fetch_userinfo($vbulletin->options['award_pm_fromuserid']);
		    $fromuser = verify_id('user', $vbulletin->options['award_pm_fromuserid']);
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

		vbmail($user['email'], $subject, $message, true, $fromuser['email']);
	}

	define('CP_REDIRECT', 'award.php?do=awardusers&amp;award_id=' . $vbulletin->GPC['award_id']);
	print_stop_message('give_award_to_user_x_successfully', $user['username']);
}

// ###################### Start issue award #######################
if ($_REQUEST['do'] == 'awardusers')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_id' => TYPE_INT,
	));
	
	if (!$vbulletin->GPC['award_id'])
	{
		print_stop_message('no_awards_defined');
	}
	else
	{
		$award = $db->query_first("
			SELECT award_name, award_desc, award_icon_url, award_img_url 
			FROM " . TABLE_PREFIX . "award 
			WHERE award_id = ". $vbulletin->GPC['award_id'] ."
		");
	}
	$award['award_desc'] = $bbcode_parser->parse($award['award_desc']);

	if (empty($award['award_name']))
	{
		print_stop_message('no_awards_defined');
	}

	// print award information	
	print_table_start();

	echo "
	<col align=\"center\" style=\"white-space:nowrap\"></col>
	<col width=\"50%\" align=\"$stylevar[left]\"></col>
	<col align=\"center\" style=\"white-space:nowrap\"></col>
	<col align=\"center\" style=\"white-space:nowrap\"></col>
	<col align=\"center\" style=\"white-space:nowrap\"></col>
	";

	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['award_name'], $award['award_name'], $vbulletin->GPC['award_id']), 5, 0);

	print_cells_row(array(
		$vbphrase['award_name'],
		$vbphrase['award_description'],
		$vbphrase['award_icon'],
		$vbphrase['award_image'],
		$vbphrase['manage']
	), 1, '', -1);
   
	echo "
	<tr>
		<td class=\"$bgclass\"><strong>$award[award_name]</strong></td>
		<td class=\"$bgclass\"><dfn>{$award[award_desc]}</dfn></td>
		<td class=\"$bgclass\" align=\"center\"><img src=\"" . iif(substr($award[award_icon_url], 0, 7) != 'http://' AND substr($award[award_icon_url], 0, 1) != '/', '../', '') . "$award[award_icon_url]\" border=\"0\"></td>
		<td class=\"$bgclass\" align=\"center\"><img src=\"" . iif(substr($award[award_img_url], 0, 7) != 'http://' AND substr($award[award_img_url], 0, 1) != '/', '../', '') . "$award[award_img_url]\" border=\"0\"></td>
		<td class=\"$bgclass\">" .
				construct_link_code(
					$vbphrase['edit'], "award.php?"
					. $vbulletin->session->vars['sessionurl']
					. "do=edit"
					. "&amp;award_id=".$vbulletin->GPC['award_id']
				) .
				construct_link_code(
					$vbphrase['delete'], "award.php?"
					. $vbulletin->session->vars['sessionurl']
					. "do=remove"
					. "&amp;award_id=" . $vbulletin->GPC['award_id']
				) .
		"</td>
	</tr>";
	print_table_footer(2, '', '', false);

// print give award to user block
	print_form_header('award', 'dogiveaward');
	construct_hidden_code('award_id', $vbulletin->GPC['award_id']);
	construct_hidden_code('award_name', $award['award_name']);
	construct_hidden_code('award_img_url', $award['award_img_url']);

	print_table_header("$vbphrase[add] $vbphrase[user_awards]", 2, 0);
	print_description_row($vbphrase[give_user_award_desc]);
	print_input_row($vbphrase['userid'], 'awarduserid');
	print_input_row($vbphrase['username'], 'awardusername');
	print_textarea_row($vbphrase['award_reason'], 'issue_reason', '', 3, 33);
	print_checkbox_row($vbphrase['award_sendpm'], 'award_sendpm');
	print_checkbox_row($vbphrase['award_sendemail'], 'award_sendemail',0);

	print_submit_row($vbphrase['save']);
	
// print remove user's award block
	print_form_header('award', 'doremoveissuedaward');
	construct_hidden_code('award_id', $vbulletin->GPC['award_id']);
	print_table_header($vbphrase['users_with_awards'], 5, 0);
   echo "
   <col align=\"center\" style=\"white-space:nowrap\"></col>
   <col width=\"50%\" align=\"$stylevar[left]\"></col>
   <col align=\"center\" style=\"white-space:nowrap\"></col>
   <col align=\"center\" style=\"white-space:nowrap\"></col>
   <col align=\"center\" style=\"white-space:nowrap\"></col>
   ";
	print_cells_row(array(
		$vbphrase['member'],
		$vbphrase['award_reason'],
		$vbphrase['award_time'],
		$vbphrase['controls'],
		$vbphrase['remove']
		), 1, '', -1);

		$awardusers = $db->query_read("
			SELECT au.*, u.username
			FROM " . TABLE_PREFIX . "award_user AS au
			LEFT JOIN " . TABLE_PREFIX . "user AS u USING (userid)
			WHERE au.award_id=". $vbulletin->GPC['award_id'] ."
		");
		while ($awarduser = $db->fetch_array($awardusers))
		{
				$awarduser['issue_reason'] = $bbcode_parser->parse($awarduser['issue_reason']);

		construct_hidden_code('issue_id', $awarduser[issueid]);
			$cell = array();
			$cell[] = "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$awarduser[userid]\" target=\"_user\"><b>$awarduser[username]</b>";
			$cell[] = "$awarduser[issue_reason]";
			$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['dateformat'], $awarduser['issue_time']) . ' ' . vbdate($vbulletin->options['timeformat'], $awarduser['issue_time']) . '</span>';
			$cell[] = "
				<a href=\"award.php?$session[sessionurl]do=editissuedaward&amp;issue_id=$awarduser[issue_id]\">$vbphrase[edit]</a>
				<a href=\"award.php?$session[sessionurl]do=removeissuedaward&amp;issue_id=$awarduser[issue_id]\">$vbphrase[remove]</a>
			";
			$cell[] = "
				<label for=\"d_$awarduser[issue_id]\"><input type=\"checkbox\" name=\"validate[$awarduser[issue_id]]\" id=\"d_$user[userid]\" value=\"-1\" tabindex=\"1\"/></label>
			";

//		managethread[{$thread[threadid]}][{$thread[forumid]}]	
//				<label for=\"d_$awarduser[issue_id]\"><input type=\"radio\" name=\"validate[$awarduser[issue_id]]\" value=\"-1\" id=\"d_$user[userid]\" tabindex=\"1\" />$vbphrase[delete]</label>
//				<label for=\"i_$awarduser[issue_id]\"><input type=\"radio\" name=\"validate[$awarduser[issue_id]]\" value=\"0\" id=\"i_$user[userid]\" tabindex=\"1\" checked=\"checked\" />$vbphrase[ignore]</label>


			print_cells_row($cell, 0, '', -4);
		}

    print_submit_row($vbphrase['remove'],0,5);
}

// ###################### Start Update Display Order #######################
if ($_POST['do'] == 'displayorder')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'order'			=> TYPE_NOCLEAN,
		'category'	=> TYPE_ARRAY,
		'doorder'		=> TYPE_STR,
		'award_cat_id' => TYPE_INT,
		'massmove'	=> TYPE_INT
	));

	// check that the correct submit button was pressed...
	if ($vbulletin->GPC['doorder'])
	{
		if (!$vbulletin->GPC['massmove'] AND !is_array($vbulletin->GPC['order']))
		{
			print_stop_message('please_complete_required_fields');
		}
		else if ($vbulletin->GPC['massmove'])
		{
			if (is_array($vbulletin->GPC['category']))
			{
				foreach($vbulletin->GPC['category'] AS $id => $vbulletin->GPC['award_cat_id'])
				{
					if ($vbulletin->GPC['award_cat_id'])
					{
						$db->query_write("UPDATE " . TABLE_PREFIX . "award SET award_cat_id = ". $vbulletin->GPC['award_cat_id'] ." WHERE award_id = $id");
					}
				}
			}
		}
		else
		{
			$items = $db->query_read("SELECT award_id,award_displayorder FROM " . TABLE_PREFIX . "award");
			$ordercache = array();
			while ($item = $db->fetch_array($items))
			{
				$ordercache["$item[award_id]"] = $item['award_displayorder'];
			}
			unset($item);
			$db->free_result($items);

			foreach($vbulletin->GPC['order'] AS $id => $award_displayorder)
			{
				$award_displayorder = intval($award_displayorder);
				if ($award_displayorder != $ordercache["$id"])
				{
					$db->query_write("UPDATE " . TABLE_PREFIX . "award SET award_displayorder = $award_displayorder WHERE award_id = $id");
				}
			}
		}
	}

	define('CP_REDIRECT', "award.php?do=manage");
	print_stop_message('saved_display_order_successfully');
}


// ###################### Start manage awards #######################
if ($_REQUEST['do'] == 'manage')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_cat_id' => TYPE_INT,
		'massmove' => TYPE_INT
	));

// check award_cat_id
	if ($vbulletin->GPC['award_cat_id'])
	{
				if (!$check = $db->query_first("
					SELECT award_cat_id 
					FROM " . TABLE_PREFIX . "award_cat 
					WHERE award_cat_id=". $vbulletin->GPC['award_cat_id'] ."
				"))
			{
					print_stop_message('no_awards_defined');
			}
	}
	
	$getawards = $db->query_read("
		SELECT aw.*, aw_c.award_cat_title
		FROM " . TABLE_PREFIX . "award AS aw
		LEFT JOIN " . TABLE_PREFIX . "award_cat AS aw_c USING (award_cat_id)
		" . iif($vbulletin->GPC['award_cat_id'], "WHERE aw.award_cat_id = ".$vbulletin->GPC['award_cat_id']."", '') . "
		ORDER BY aw_c.award_cat_displayorder,aw.award_displayorder
	");
		
	while ($aw = $db->fetch_array($getawards))
	{
		if ($aw['award_cat_id'] == -1)
		{
			$globalaward[] = $aw;
		}
		else
		{
			$awardcache[$aw['award_cat_id']][$aw['award_id']] = $aw;
		}
	}
	$db->free_result($getawards);

	// Obtain list of users of each award
	$allawardusers =  $db->query_read("
		SELECT u.userid, u.username, au.award_id
		FROM " . TABLE_PREFIX . "award_user AS au
		LEFT JOIN " . TABLE_PREFIX . "user AS u ON (u.userid = au.userid)
		GROUP BY u.userid, u.username, au.award_id
		ORDER BY u.userid
	");
	while( $au = $db->fetch_array($allawardusers))
	{
		$awarduserscache[$au['award_id']][$au['userid']] = $au;
	}
	$db->free_result($allawardusers);
	
	cache_award_cats(-1,0,$vbulletin->GPC['award_cat_id']);

	// display category-specific awards
	print_form_header('award', 'displayorder');
	construct_hidden_code('award_catid', $award_catid);
	construct_hidden_code('massmove', $vbulletin->GPC['massmove']);
	
	print_table_header($vbphrase['award_manager'], 6);

	// display global awards (awards has category -1)
	if (is_array($globalaward))
	{
		$award_cat_info =
			"\t\t<b>$vbphrase[unclassified_awards]</b> ".
			construct_link_code($vbphrase['add_new_award'], "award.php?$session[sessionurl]do=add&amp;award_cat_id=-1")
			. "\n";
		print_description_row($award_cat_info, 0, 6);

		print_cells_row(array(
			$vbphrase['award_icon'],
			$vbphrase['award_image'],
			$vbphrase['award_name'],
			$vbphrase['users_with_awards'],
			$vbphrase['display_order'],
			$vbphrase['controls']
			), 1, '', -1);

		foreach($globalaward AS $award_id => $award)
		{
			$cell = array();

				$awarduserslist = '';
				if (is_array($awarduserscache[$award['award_id']]))
				{
					foreach($awarduserscache[$award['award_id']] AS $userid => $awardusers)
					{
						$awarduserslist .= ", <a href=\"user.php?do=edit&amp;userid=$awardusers[userid]\">$awardusers[username]</a>";
					}
				}
				$awarduserslist = substr($awarduserslist , 2); // get rid of initial comma
				$award['award_desc'] = $bbcode_parser->parse($award['award_desc']);

		$cell[] = construct_img_html($award['award_icon_url']);
		$cell[] = construct_img_html($award['award_img_url']);
		$cell[] = "<strong>$award[award_name]<dfn>{$award[award_desc]}</dfn></strong>";
		$cell[] = "$awarduserslist";
		$cell[] = "
					<input style=\"text-align: center\" type=\"text\" class=\"bginput\" name=\"order[" . $award[award_id] . "]\" tabindex=\"1\" value=\"$award[award_displayorder]\" size=\"2\" title=\"" . $vbphrase['display_order'] . "\" class=\"smallfont\" />
		";
		$cell[] = "" .
				construct_link_code($vbphrase['edit'], "award.php?$session[sessionurl]do=edit&amp;award_id=$award[award_id]") .
				construct_link_code($vbphrase['delete'], "award.php?$session[sessionurl]do=remove&amp;award_id=$award[award_id]") .
				construct_link_code($vbphrase['give_user_award'], "award.php?$session[sessionurl]do=awardusers&amp;award_id=$award[award_id]") .
				"";
		print_cells_row($cell, 0, '', 1);
		}
		print_table_footer(6, "\n\t <input type=\"submit\" class=\"button\" name=\"doorder\" value=\"" . $vbphrase['save_display_order'] . "\" tabindex=\"1\" />\n\t",'',false);
		print_table_break();
	}

	foreach($award_cat_cache AS $key => $award_cat)
	{
		$award_cat_info = "<b>" . 
			construct_depth_mark($award_cat['depth'], '- - ', '- - ') 
			. "<a href=\"award.php?$session[sessionurl]do=manage&amp;award_cat_id=$award_cat[award_cat_id]\">$award_cat[award_cat_title]</a></b> <span class=\"normal\">".
			construct_link_code($vbphrase['mass_move'], "award.php?$session[sessionurl]do=manage&amp;massmove=1&amp;award_cat_id=$award_cat[award_cat_id]").
			construct_link_code($vbphrase['edit'], "award_cat.php?$session[sessionurl]do=editcat&amp;award_cat_id=$award_cat[award_cat_id]").
			construct_link_code($vbphrase['delete'], "award_cat.php?$session[sessionurl]do=removecat&amp;award_cat_id=$award_cat[award_cat_id]").
			construct_link_code($vbphrase['add_new_award'], "award.php?$session[sessionurl]do=add&amp;award_cat_id=$award_cat[award_cat_id]").
		"</span>\n";

		print_table_header($award_cat_info, 6, 0,'','center');
		if (!empty($award_cat[award_cat_desc]))
		{
			print_description_row($award_cat[award_cat_desc], 0, 6,'','center');
		}
			if (!$vbulletin->GPC['massmove'])
			{
				$action_title = $vbphrase['display_order'];
			} else {
				$action_title = $vbphrase['mass_move'];
			}		
		print_cells_row(array(
			$vbphrase['award_icon'],
			$vbphrase['award_image'],
			$vbphrase['award_name'],
			$vbphrase['users_with_awards'],
			$action_title,
			$vbphrase['controls']
			), 1, '', -1);

		if (is_array($awardcache[$award_cat['award_cat_id']]))
		{
			foreach($awardcache[$award_cat['award_cat_id']] AS $award_id => $award)
			{
				{
					$cell = array();
				$award['award_desc'] = $bbcode_parser->parse($award['award_desc']);
				$awarduserslist = '';
				if (is_array($awarduserscache[$award['award_id']]))
				{
					foreach($awarduserscache[$award['award_id']] AS $userid => $awardusers)
					{
						$awarduserslist .= ", <a href=\"user.php?do=edit&amp;userid=$awardusers[userid]\">$awardusers[username]</a>";
					}
				}
				$awarduserslist = substr($awarduserslist , 2); // get rid of initial comma
				
				$cell[] = construct_img_html($award['award_icon_url']);
				$cell[] = construct_img_html($award['award_img_url']);
				if ($award['award_active'] == 1)
				{
					$cell[] = "<strong>$award[award_name]<dfn>{$award[award_desc]}</dfn></strong>";
				}
				else
				{
					$cell[] = "<strike><font color=red>$award[award_name]<dfn>{$award[award_desc]}</dfn></font></strike>";
				}
				$cell[] = "$awarduserslist";
				if (!$vbulletin->GPC['massmove'])
				{
							$cell[] = "
										<input style=\"text-align: center\" type=\"text\" class=\"bginput\" name=\"order[" . $award[award_id] . "]\" tabindex=\"1\" value=\"$award[award_displayorder]\" size=\"2\" title=\"" . $vbphrase['display_order'] . "\" class=\"smallfont\" />
							";
				}
					else
				{
					if (!$options)
					{
						$parentoptions = array('-1' => $vbphrase["no_one"]);
						fetch_award_parent_options();
						$options = construct_select_options($parentoptions);
			 		}
					$cell[] = '
						<select name="category[' . $award[award_id] . ']" class="bginput">' . $options . '</select>
					';
				}
				$cell[] = "" .
						construct_link_code($vbphrase['edit'], "award.php?$session[sessionurl]do=edit&amp;award_id=$award[award_id]") .
						construct_link_code($vbphrase['delete'], "award.php?$session[sessionurl]do=remove&amp;award_id=$award[award_id]") .
						construct_link_code($vbphrase['give_user_award'], "award.php?$session[sessionurl]do=awardusers&amp;award_id=$award[award_id]") .
						"";
				print_cells_row($cell, 0, '', 1);
				}
			}
		}
		else {
			print_description_row($vbphrase['no_awards_in_this_category'], 0, 6);
		}
		print_description_row(' ', 0, 6);
	}
	if ($vbulletin->GPC['massmove'])
	{
		$categories = '<select name="selectall" class="bginput" onchange="js_select_all(this.form);">' . $options . '</select>';
		$buttontext = $vbphrase['mass_move'];
	}
	else
	{
		$buttontext = $vbphrase['save_display_order'];
	}

	print_table_footer(6, "\n\t $categories <input type=\"submit\" class=\"button\" name=\"doorder\" value=\"" . $buttontext . "\" tabindex=\"1\" />\n\t");
}

// #############################################################################

print_cp_footer();

?>
