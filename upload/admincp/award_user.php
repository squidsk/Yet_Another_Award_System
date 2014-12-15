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
define('THIS_SCRIPT', 'YAAS_USER_AWARD_ADMIN');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cpuser', 'user');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// put this before print_cp_header() so we can use an HTTP header
if ($_REQUEST['do'] == 'find')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'user'			=> TYPE_ARRAY,
		'limitstart'	=> TYPE_UINT,
		'limitnumber'	=> TYPE_UINT,
		'direction'		=> TYPE_STR,
	));

	$user = $vbulletin->GPC['user'];
	$condition = '1=1';
	$condition .= iif($user['username'] AND !$user['exact'], " AND user.username LIKE '%" . $vbulletin->db->escape_string_like(htmlspecialchars_uni($user['username'])) . "%'");
	$condition .= iif($user['exact'], " AND user.username = '" . $vbulletin->db->escape_string(htmlspecialchars_uni($user['username'])) . "'");
	$condition .= iif($user['usergroupid'] != -1 AND $user['usergroupid'], " AND user.usergroupid = " . intval($user['usergroupid']));

	if (is_array($user['membergroup']))
	{
		foreach ($user['membergroup'] AS $id)
		{
			$condition .= " AND FIND_IN_SET(" . intval($id) . ", user.membergroupids)";
		}
	}

	if ($vbulletin->GPC['direction'] != 'DESC')
	{
		$vbulletin->GPC['direction'] = 'ASC';
	}

	if (empty($vbulletin->GPC['limitstart']))
	{
		$vbulletin->GPC['limitstart'] = 0;
	}
	else
	{
		$vbulletin->GPC['limitstart']--;
	}

	if (empty($vbulletin->GPC['limitnumber']) OR $vbulletin->GPC['limitnumber'] == 0)
	{
		$vbulletin->GPC['limitnumber'] = 25;
	}

	$searchquery = "
		SELECT user.userid, username, email, joindate, posts, lastactivity, count(award_user.award_id) as awards
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "award_user AS award_user USING(userid)
		WHERE $condition
		GROUP BY user.userid
		ORDER BY user.username " . $db->escape_string($vbulletin->GPC['direction']) . "
		LIMIT " . $vbulletin->GPC['limitstart'] . ", " . $vbulletin->GPC['limitnumber']
	;

	$users = $db->query_read($searchquery);
	$countusers = $db->found_rows();

	if ($countusers == 1)
	{
		// show a user if there is just one found
		$user = $db->fetch_array($users);
		// instant redirect
		exec_header_redirect("award_user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$user[userid]");
	}
	else if ($countusers == 0)
	{
		// no users found!
		print_stop_message('no_users_matched_your_query');
	}

	define('DONEFIND', true);
	$_REQUEST['do'] = 'find2';
}

// #############################################################################

print_cp_header($vbphrase['awards']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'search';
}
$vbulletin->input->clean_array_gpc('r', array(
	'issue_ids' => TYPE_ARRAY_UINT,
	'userid' => TYPE_UINT,
));

$extra_log_info = iif($vbulletin->GPC['userid'] != 0, 'userid = ' . $vbulletin->GPC['userid'] . iif(!empty($vbulletin->GPC['issue_ids']), ', award_ids = ' . implode(',', $vbulletin->GPC['issue_ids'])));

log_admin_action($extra_log_info);

// ###################### Start search #######################
if($_REQUEST['do'] == 'search')
{
	print_form_header('award_user', 'find');
	print_table_header($vbphrase['search']);
	print_description_row('<img src="../' . $vbulletin->options['cleargifurl'] . '" alt="" width="1" height="2" />', 0, 2, 'thead');
	print_label_row($vbphrase['username'], "
		<input type=\"text\" class=\"bginput\" name=\"user[username]\" tabindex=\"1\" size=\"35\"
		/><input type=\"image\" src=\"../" . $vbulletin->options['cleargifurl'] . "\" width=\"1\" height=\"1\"
		/><input type=\"submit\" class=\"button\" value=\"$vbphrase[exact_match]\" tabindex=\"1\" name=\"user[exact]\" />
	", '', 'top', 'user[username]');
	print_chooser_row($vbphrase['primary_usergroup'], 'user[usergroupid]', 'usergroup', -1, '-- ' . $vbphrase['all_usergroups'] . ' --');
	print_membergroup_row($vbphrase['additional_usergroups'], 'user[membergroup]', 2);
	print_table_break();

	print_table_header($vbphrase['sorting_options']);
	print_label_row($vbphrase['yaas_sort_order'], '
		<select name="direction" tabindex="1" class="bginput">
		<option value="">' . $vbphrase['ascending'] . '</option>
		<option value="DESC">' . $vbphrase['descending'] . '</option>
		</select>
	', '', 'top', 'orderby');
	print_input_row($vbphrase['starting_at_result'], 'limitstart', 1);
	print_input_row($vbphrase['maximum_results'], 'limitnumber', 50);

	print_submit_row($vbphrase['find'], $vbphrase['reset'], 2, '', '<input type="submit" class="button" value="' . $vbphrase['exact_match'] . '" tabindex="1" name="user[exact]" />');
}

// ###################### Start find #######################
if ($_REQUEST['do'] == 'find2' AND defined('DONEFIND'))
{
	// carries on from do == find at top of script

	$limitfinish = $vbulletin->GPC['limitstart'] + $vbulletin->GPC['limitnumber'];

	// display the column headings
	$header = array();
	$header[] = $vbphrase['username'];
	$header[] = $vbphrase['email'];
	$header[] = $vbphrase['join_date'];
	$header[] = $vbphrase['last_activity'];
	$header[] = $vbphrase['post_count'];
	$header[] = $vbphrase['awards'];

	// get number of cells for use in 'colspan=' attributes
	$colspan = sizeof($header);

	print_form_header('award_user', 'find');
	print_table_header(
		construct_phrase(
			$vbphrase['showing_users_x_to_y_of_z'],
			($vbulletin->GPC['limitstart'] + 1),
			iif($limitfinish > $countusers['users'], $countusers['users'], $limitfinish),
			$countusers['users']
		), $colspan);
	print_cells_row($header, 1);

	// cache usergroups if required to save querying every single one...
	if ($vbulletin->GPC['display']['usergroup'] AND !is_array($groupcache))
	{
		$groupcache = array();
		$groups = $db->query_read("SELECT usergroupid, title FROM " . TABLE_PREFIX . "usergroup");
		while ($group = $db->fetch_array($groups))
		{
			$groupcache["$group[usergroupid]"] = $group['title'];
		}
		$db->free_result($groups);
	}

	// now display the results
	while ($user = $db->fetch_array($users))
	{

		$cell = array();
		$cell[] = "<a href=\"award_user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$user[userid]\"><b>$user[username]</b></a>&nbsp;";
		$cell[] = "<a href=\"mailto:$user[email]\">$user[email]</a>";
		$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['dateformat'], $user['joindate']) . '</span>';
		$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['dateformat'], $user['lastactivity']) . '</span>';
		$cell[] = vb_number_format($user['posts']);
		$cell[] = vb_number_format($user['awards']);
		print_cells_row($cell);
	}

	construct_hidden_code('limitnumber', $vbulletin->GPC['limitnumber']);
	construct_hidden_code('orderby', $vbulletin->GPC['orderby']);
	construct_hidden_code('direction', $vbulletin->GPC['direction']);

	if ($vbulletin->GPC['limitstart'] == 0 AND $countusers['users'] > $vbulletin->GPC['limitnumber'])
	{
		construct_hidden_code('limitstart', $vbulletin->GPC['limitstart'] + $vbulletin->GPC['limitnumber'] + 1);
		print_submit_row($vbphrase['next_page'], 0, $colspan);
	}
	else if ($limitfinish < $countusers['users'])
	{
		construct_hidden_code('limitstart', $vbulletin->GPC['limitstart'] + $vbulletin->GPC['limitnumber'] + 1);
		print_submit_row($vbphrase['next_page'], 0, $colspan, $vbphrase['prev_page'], '', true);
	}
	else if ($vbulletin->GPC['limitstart'] > 0 AND $limitfinish >= $countusers['users'])
	{
		print_submit_row($vbphrase['first_page'], 0, $colspan, $vbphrase['prev_page'], '', true);
	}
	else
	{
		print_table_footer();
	}
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid'	=> TYPE_UINT
	));

	if (!$vbulletin->GPC['userid'])
	{
		print_stop_message('invalid_user_specified');
	}
	else
	{
		$user = $db->query_first("
			SELECT user.username, user.userid
			FROM " . TABLE_PREFIX . "user as user
			WHERE user.userid = " . $vbulletin->GPC['userid']
		);

		if (!$user)
		{
			print_stop_message('invalid_user_specified');
		}

		$awards = $db->query("
			SELECT award.award_id, award.award_name, award.award_desc, award.award_icon_url, award.award_img_url, award_user.*
			FROM " . TABLE_PREFIX . "award_user as award_user
			LEFT JOIN " . TABLE_PREFIX . "award as award USING (award_id)
			WHERE award_user.userid = " . $vbulletin->GPC['userid']
		);

		$countawards = $db->found_rows();
		if($countawards == 0) {
			print_stop_message("yaas_no_user_awards_found", $user['username']);
		}

		print_form_header('award_user', 'deletem');
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user'], $user['username'], $vbulletin->GPC['userid']), 7);
		echo "<tr><td style=\"text-align:left;\">$vbphrase[award_name]</td><td style=\"text-align:center;\">$vbphrase[award_icon]</td><td style=\"text-align:center;\">$vbphrase[award_image]</td><td style=\"text-align:left;\">$vbphrase[award_reason]</td><td style=\"text-align:center;\">$vbphrase[award_time]</td><td style=\"text-align:center;\">$vbphrase[controls]</td><td style=\"text-align:center;\">$vbphrase[remove]</td></tr>";
		while($award = $db->fetch_array($awards))
		{
			echo "<tr>
			<td class=\"alt1\" style=\"white-space:nowrap; text-align:left;\"><a href=\"award.php?$session[sessionurl]do=edit&amp;award_id=$award[award_id]\"><b>$award[award_name]</b></a></td>
			<td class=\"alt1\" style=\"text-align:center;\"><img src=\"" . iif(substr($award[award_icon_url], 0, 7) != 'http://' AND substr($award[award_icon_url], 0, 1) != '/', '../', '') . "$award[award_icon_url]\" border=\"0\" alt=\"\"></td>
			<td class=\"alt1\" style=\"text-align:center;\"><img src=\"" . iif(substr($award[award_img_url], 0, 7) != 'http://' AND substr($award[award_img_url], 0, 1) != '/', '../', '') . "$award[award_img_url]\" border=\"0\" alt=\"\"></td>
			<td class=\"alt1\" style=\"text-align:left;\">$award[issue_reason]</td>
			<td class=\"alt1\" style=\"text-align:center;\"><span class=\"smallfont\">" . vbdate($vbulletin->options['dateformat'], $award['issue_time']) . ", " . vbdate($vbulletin->options['timeformat'], $award['issue_time']) . "</span></td>
			<td class=\"alt1\" style=\"text-align:center;\">" .
			construct_link_code(
				$vbphrase['edit'], "award.php?"
				. $vbulletin->session->vars['sessionurl']
				. "do=editissuedaward&amp;issue_id=$award[issue_id]"
			) .
			construct_link_code(
				$vbphrase['delete'], "award.php?"
				. $vbulletin->session->vars['sessionurl']
				. "do=removeissuedaward&amp;issue_id=$award[issue_id]&amp;awarduserid=$user[userid]"
			) .
			"</td>
			<td class=\"alt1\" style=\"text-align:center;\"><label for=\"d_$award[issue_id]\"><input type=\"checkbox\" name=\"issue_ids[$award[issue_id]]\" id=\"d_$award[issue_id]\" value=\"$award[award_id]\" tabindex=\"1\"/></label></td>
			</tr>";

		}
		construct_hidden_code("username", $user['username']);
		construct_hidden_code("userid", $user['userid']);
		print_submit_row($vbphrase['remove'],0,7);
	}
}

// ###################### Start deletem #######################
if ($_REQUEST['do'] == 'deletem')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issue_ids'	=> TYPE_ARRAY_UINT,
		'username'	=> TYPE_NOHTML,
		'userid'	=> TYPE_UINT
	));
	if (!$vbulletin->GPC_exists['issue_ids']){
		print_stop_message('no_awards_defined');
	}
	else
	{
		print_form_header('award_user', 'dodeletem');
		print_table_header($vbphrase['confirm_deletion']);
		construct_hidden_code("username", $vbulletin->GPC['username']);
		construct_hidden_code("userid", $vbulletin->GPC['userid']);

		$award_issue_ids = array_keys($vbulletin->GPC['issue_ids']);
		$awards = $db->query_read("
			SELECT award.award_id, award.award_name, award.award_desc, award_user.issue_id, award_user.issue_reason
			FROM " . TABLE_PREFIX . "award_user as award_user
			LEFT JOIN " . TABLE_PREFIX . "award as award USING(award_id)
			WHERE award_user.issue_id IN (" . implode(',', $award_issue_ids) . ")
			AND userid = " . $vbulletin->GPC['userid']
		);
		$award_details = '<ul style="list-style:none;">';
		while($award = $db->fetch_array($awards)){
			construct_hidden_code("issue_ids[$award[issue_id]]", $award['award_id']);
			$award_details .= '<li>' . construct_phrase($vbphrase["yaas_award_details_1_2_from_3_reason_4"], $award['award_name'], $award['award_desc'],$vbulletin->GPC['username'],$award['issue_reason']) . '</li>';
		}
		$award_details .= '</ul>';
		print_description_row("<blockquote>$vbphrase[yaas_confirm_multiple_deletes]$award_details$vbphrase[yaas_cannot_be_undone]</blockquote>");
	}
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Start dodeletem #######################
if ($_REQUEST['do'] == 'dodeletem')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issue_ids'	=> TYPE_ARRAY_UINT,
		'username'	=> TYPE_NOHTML,
		'userid'	=> TYPE_UINT
	));
	if (!$vbulletin->GPC_exists['issue_ids']){
		print_stop_message('no_awards_defined');
	}
	else
	{
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "award_user
			WHERE issue_id IN (" . implode(',', array_keys($vbulletin->GPC['issue_ids'])) . ")
			AND userid=" . $vbulletin->GPC['userid']
		);
		define('CP_REDIRECT', "award_user.php?do=search");
		print_stop_message('removed_awards_from_user_successfully', $vbulletin->GPC['username']);
	}
}

print_cp_footer();

