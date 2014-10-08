<?php
/*======================================================================*\
|| #################################################################### ||
|| # Yet Another Award System v4.0.3 © by HacNho                      # ||
|| # Copyright (C) 2005-2007 by HacNho, All rights reserved.          # ||
|| # ---------------------------------------------------------------- # ||
|| # For use with vBulletin Version 4.1.12                             # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| # Discussion and support available at                              # ||
|| # http://www.vbulletin.org/forum/showthread.php?t=94836            # ||
|| # ---------------------------------------------------------------- # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'award_cat.php');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
// require_once('./includes/adminfunctions_profilefield.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// make sure we are dealing with avatars,smilies or icons

print_cp_header($vbphrase['awards']);

// ************************************************************
// start functions

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

function cache_award_cats($award_cat_id = -1, $depth = 0)
{
	// returns an array of award cats with correct parenting and depth information

	global $db, $award_cat_cache, $count;
	static $awcache, $i;
	
	if (!is_array($awcache))
	{
	// check to see if we have already got the results from the database
		$awcache = array();
		$award_cats = $db->query_read("
			SELECT aw_c.*, COUNT(aw.award_id) AS items
			FROM " . TABLE_PREFIX . "award_cat AS aw_c
			LEFT JOIN " . TABLE_PREFIX . "award AS aw USING(award_cat_id)
			GROUP BY award_cat_id
			ORDER BY award_cat_displayorder
		");	
		while ($award_cat = $db->fetch_array($award_cats))
		{
			$awcache["$award_cat[award_cat_parentid]"]["$award_cat[award_cat_displayorder]"]["$award_cat[award_cat_id]"] = $award_cat;
		}
		$db->free_result($award_cats);
	}

	// database has already been queried
	if (is_array($awcache["$award_cat_id"]))
	{
		foreach ($awcache["$award_cat_id"] AS $holder)
		{
			foreach ($holder AS $award_cat)
			{
				$award_cat_cache["$award_cat[award_cat_id]"] = $award_cat;
				$award_cat_cache["$award_cat[award_cat_id]"]['depth'] = $depth;
				unset($awcache["$award_cat_id"]);
				cache_award_cats($award_cat['award_cat_id'], $depth + 1);
			} // end foreach ($val1 AS $key2 => $award_cat)
		} // end foreach ($awcache["$award_cat_id"] AS $key1 => $val1)
	} // end if (found $awcache["$award_cat_id"])
}

// end functions
// ************************************************************

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modifycat';
}

// #############################################################################

// ###################### Start Kill Category #######################
if ($_POST['do'] == 'killcat')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'award_cat_id' => TYPE_INT,
		'destinationid' => TYPE_INT,
		'deleteitems' => TYPE_INT
	));

	if ($vbulletin->GPC['deleteitems'] == 1)
	{
			// get awards belong to the category
		$awards =  $db->query_read("
			SELECT award_id
			FROM " . TABLE_PREFIX . "award
			WHERE award_cat_id = ". $vbulletin->GPC['award_cat_id'] ."
		");
			while( $aw = $db->fetch_array($awards))
			{
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "award_user WHERE award_id = $aw[award_id]");
			}
	
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "award WHERE award_cat_id = ". $vbulletin->GPC['award_cat_id'] ."");
		$extra = "vbphrase[awards_deleted]";
	}
	else
	{
		$dest = $db->query_first("
			SELECT award_cat_title
			FROM " . TABLE_PREFIX . "award_cat
			WHERE award_cat_id = ". $vbulletin->GPC['award_cat_id'] ."
		");
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "award
			SET award_cat_id = ". $vbulletin->GPC['destinationid'] ."
			WHERE award_cat_id = ". $vbulletin->GPC['award_cat_id'] ."
		");
		$extra = "vbphrase[awards_deleted]";
	}

	$db->query_write("
			UPDATE " . TABLE_PREFIX . "award_cat
			SET award_cat_parentid = '-1'
			WHERE award_cat_parentid = ". $vbulletin->GPC['award_cat_id'] ."
		");
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "award_cat WHERE award_cat_id = ". $vbulletin->GPC['award_cat_id'] ."");

	define('CP_REDIRECT', "award_cat.php?do=modifycat");
	print_stop_message('deleted_category_successfully');
}

// ###################### Start Remove Category #######################
if ($_REQUEST['do'] == 'removecat')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_cat_id' => TYPE_INT
	));

	$categories = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "award_cat
		ORDER BY award_cat_displayorder
	");
	if ($db->num_rows($categories) < 2)
	{
		print_stop_message('cant_remove_last_x_category',$vbphrase['awards']);
	}
	else
	{
		$category = array();
		$destcats = array();
		$destcats[-1] = "$vbphrase[no_one]";
		while ($tmp = $db->fetch_array($categories))
		{
			if ($tmp['award_cat_id'] == $vbulletin->GPC['award_cat_id'])
			{
				$category = $tmp;
			}
			else
			{
				$destcats[$tmp['award_cat_id']] = $tmp['award_cat_title'];
			}
		}
		unset($tmp);
		$db->free_result($categories);

		echo "<p>&nbsp;</p><p>&nbsp;</p>\n";

		print_form_header('award_cat', 'killcat');
		construct_hidden_code('award_cat_id', $category['award_cat_id']);
		print_table_header(construct_phrase($vbphrase['confirm_deletion_of_x_y'],$vbphrase['awards'], $category['award_cat_title']));
		print_description_row('<blockquote>' . construct_phrase($vbphrase["are_you_sure_you_want_to_delete_the_award_category_called_x"], $category['award_cat_title'], construct_select_options($destcats)) . '</blockquote>');
		print_submit_row($vbphrase['delete'], '', 2, $vbphrase['go_back']);
	}
}

// ###################### Start Update Category #######################
if ($_POST['do'] == 'insertcat')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_cat_title' => TYPE_NOHTML,
		'award_cat_desc' => TYPE_NOHTML,
		'award_cat_displayorder' => TYPE_INT,
		'award_cat_parentid' => TYPE_INT
	));

	$db->query_write("INSERT INTO " . TABLE_PREFIX . "award_cat (
		award_cat_id,award_cat_title,award_cat_desc,award_cat_displayorder, award_cat_parentid
	) VALUES (
		NULL, '" . addslashes($vbulletin->GPC['award_cat_title']) . "','" . addslashes($vbulletin->GPC['award_cat_desc']) . "','". intval($vbulletin->GPC['award_cat_displayorder']) ."', '". intval($vbulletin->GPC['award_cat_parentid']) ."'
	)");

	define('CP_REDIRECT', "award_cat.php?do=modifycat");
	print_stop_message('saved_category_x_successfully', $vbulletin->GPC['award_cat_title']);
}

// ###################### Start Add Category #######################
if ($_REQUEST['do'] == 'addcat')
{
	print_form_header('award_cat', 'insertcat');
	print_table_header($vbphrase["add_new_award_category"]);
	print_input_row($vbphrase['title'], 'award_cat_title');
	print_input_row($vbphrase['description'],'award_cat_desc');

	$parentoptions = array('-1' => $vbphrase["no_one"]);
	fetch_award_parent_options($category['award_cat_id']);

	print_select_row($vbphrase['award_cat_parent'], 'award_cat_parentid', $parentoptions,'-1');
	print_input_row($vbphrase['display_order'], 'award_cat_displayorder',1);
	print_submit_row($vbphrase['save']);
}

// ###################### Start Update Category #######################
if ($_POST['do'] == 'updatecat')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'award_cat_id' => TYPE_INT,
		'award_cat_title' => TYPE_NOHTML,
		'award_cat_desc' => TYPE_NOHTML,
		'award_cat_displayorder' => TYPE_INT,
		'award_cat_parentid' => TYPE_INT
	));

	if ($vbulletin->GPC['award_cat_id'] == $vbulletin->GPC['award_cat_parentid'])
	{
		print_stop_message('cant_parent_x_to_self', $vbulletin->GPC['award_cat_title']);
	}
	else
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "award_cat SET
			award_cat_title = '" . addslashes($vbulletin->GPC['award_cat_title']) . "',
			award_cat_desc = '" . addslashes($vbulletin->GPC['award_cat_desc']) . "',
			award_cat_displayorder = ". $vbulletin->GPC['award_cat_displayorder'] .",
			award_cat_parentid = ". $vbulletin->GPC['award_cat_parentid'] ."
			WHERE award_cat_id = ". $vbulletin->GPC['award_cat_id'] ."
		");
		define('CP_REDIRECT', "award_cat.php?do=modifycat");
		print_stop_message('saved_category_x_successfully', $vbulletin->GPC['award_cat_title']);
	}

}

// ###################### Start Edit Category #######################
if ($_REQUEST['do'] == 'editcat')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_cat_id' => TYPE_INT
	));

	$category = $db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "award_cat
		WHERE award_cat_id = ". $vbulletin->GPC['award_cat_id'] ."
	");

	print_form_header('award_cat', 'updatecat');
	construct_hidden_code('award_cat_id', $category['award_cat_id']);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['award_category'], $category['award_cat_title'], $category['award_cat_id']));
	print_input_row($vbphrase['award_cat_title'], 'award_cat_title', $category['award_cat_title'], 0);
	print_input_row($vbphrase['description'],'award_cat_desc',$category['award_cat_desc']);

	$parentoptions = array('-1' => $vbphrase["no_one"]);
	fetch_award_parent_options($category['award_cat_id']);

	print_select_row($vbphrase["award_cat_parent"], 'award_cat_parentid', $parentoptions, $category['award_cat_parentid']);
	print_input_row($vbphrase['display_order'], 'award_cat_displayorder', $category['award_cat_displayorder']);
	print_submit_row();
}

// ###################### Start Update Award Category Display Order #######################
if ($_REQUEST['do'] == 'updateorder')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'order' => TYPE_NOCLEAN,
	));

	if (is_array($vbulletin->GPC['order']))
	{
		$categories = $db->query_read("
			SELECT award_cat_id,award_cat_displayorder
			FROM " . TABLE_PREFIX . "award_cat
		");
		while ($category = $db->fetch_array($categories))
		{
			$award_cat_displayorder = intval($vbulletin->GPC['order']["$category[award_cat_id]"]);
			if ($category['award_cat_displayorder'] != $award_cat_displayorder)
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "award_cat
					SET award_cat_displayorder = $award_cat_displayorder
					WHERE award_cat_id = $category[award_cat_id]
				");
			}
		}
	}

	define('CP_REDIRECT', "award_cat.php?do=modifycat");
	print_stop_message('saved_display_order_successfully');
}


// ###################### Start Modify Categories #######################
if ($_REQUEST['do'] == 'modifycat')
{

// ============================ 
 	cache_award_cats();
 	if (empty($award_cat_cache))
 	{
		print_stop_message("no_x_categories_found","$vbphrase[awards]", "award_cat.php?$session[sessionurl]do=addcat");
	}
	print_form_header('award_cat', 'updateorder');
	print_table_header($vbphrase['award_cat_manager'], 4);
	print_cells_row(array($vbphrase['title'], $vbphrase['awards'], $vbphrase['display_order'], $vbphrase['controls']), 1, '', -1);

	// display individual category awards
	foreach($award_cat_cache AS $key => $award_cat)
	{

		$cell = array();
		$cell[] = "<strong>" . construct_depth_mark($award_cat['depth'], '- - ', '- - ') . "<a href=\"award.php?$session[sessionurl]do=manage&amp;award_cat_id=$award_cat[award_cat_id]\">$award_cat[award_cat_title]</a></strong><div style=\"padding-left: 16px\">{$award_cat[award_cat_desc]}</div>";

			$cell[] = vb_number_format($award_cat['items']) . ' ' . "$vbphrase[awards]";
			$cell[] = "<input type=\"text\" class=\"bginput\" name=\"order[$award_cat[award_cat_id]]\" value=\"$award_cat[award_cat_displayorder]\" tabindex=\"1\" size=\"3\" />";
			$cell[] =
				construct_link_code($vbphrase['mass_move'], "award.php?$session[sessionurl]do=manage&amp;massmove=1&amp;award_cat_id=$award_cat[award_cat_id]") .
				construct_link_code($vbphrase['view'], "award.php?$session[sessionurl]do=manage&amp;award_cat_id=$award_cat[award_cat_id]") .
				construct_link_code($vbphrase['edit'], "award_cat.php?$session[sessionurl]do=editcat&amp;award_cat_id=$award_cat[award_cat_id]").
				construct_link_code($vbphrase['delete'], "award_cat.php?$session[sessionurl]do=removecat&amp;award_cat_id=$award_cat[award_cat_id]");
				
		print_cells_row($cell, 0, '', -1);
	}
		print_submit_row($vbphrase['save_display_order'], NULL, 4);
		echo "<p align=\"center\">" . construct_link_code($vbphrase['add_new_award_category'], "award_cat.php?$session[sessionurl]do=addcat") . construct_link_code($vbphrase['show_all_awards'], "award.php?$session[sessionurl]do=manage")."</p>";
		
	print_table_footer();
}


// #############################################################################
print_cp_footer();

?>
