<?php
/*======================================================================*\
|| #################################################################### ||
|| # Yet Another Award System v4.0.8 © by HacNho                      # ||
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
define('THIS_SCRIPT', 'YAAS_DISPLAY');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'AWARDS',
	'awards_categorybit',
	'awards_category',
	'awards_awardbit',
	'awards_awardusers_bit',
	'awards_viewaward',
	'awards_postbit_display'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_bbcode.php');
$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ###################### Start get award_cat_cache #######################

function cache_award_cats($award_cat_id = -1, $depth = 0, $display_award_cat_id=0)
{
	// returns an array of award cats with correct parenting and depth information
	// see makeforumchooser for an example of usage

	global $db, $award_cat_cache, $count;
	static $fcache, $i;
	
	if (!is_array($fcache))
	{
	// check to see if we have already got the results from the database
		$fcache = array();
		$award_cats = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "award_cat
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

// ###################### Start makedepthmark #######################
if(!function_exists('yaas_construct_depth_mark'))
{
	function yaas_construct_depth_mark($depth, $depthchar, $depthmark = '')
	{
	// repeats the supplied $depthmark for the number of times supplied by $depth
	// and appends it onto $depthmark
		for ($i = 0; $i < $depth; $i++)
		{
			$depthmark .= $depthchar;
		}
		return $depthmark;
	}
}
// end functions
// ************************************************************
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'viewall';
}

if ($_REQUEST['do'] == 'viewall')
{
$vbulletin->input->clean_array_gpc('r', array(
		'award_cat_id' => TYPE_UINT
	));

// work out total columns
$totalcols = $vbulletin->options['aw_showicon']+ $vbulletin->options['aw_showimage'] + $vbulletin->options['aw_showdesc'] + $vbulletin->options['aw_showmembers'] + $vbulletin->options['aw_actioncolumn'];
	
	//	echo "award_cat_id $award_cat_id";
	$getawards = $db->query_read("
		SELECT award.*, award_cat.award_cat_title
		FROM " . TABLE_PREFIX . "award AS award
		LEFT JOIN " . TABLE_PREFIX . "award_cat AS award_cat USING (award_cat_id)
		" . iif($vbulletin->GPC['award_cat_id'], "WHERE award.award_cat_id = ". $vbulletin->GPC['award_cat_id'] ."", '') . "
		ORDER BY award_cat.award_cat_displayorder,award.award_displayorder
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
			SELECT u.userid, u.username, au.award_id, IF(u.displaygroupid = 0, u.usergroupid, u.displaygroupid) AS displaygroupid
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

	foreach($award_cat_cache AS $key => $award_cat)
	{
//		$award_categories = '';
		$award = array();
		$awardsbits = '';
		if (is_array($awardcache[$award_cat['award_cat_id']]))
		{
			foreach($awardcache[$award_cat['award_cat_id']] AS $award_id => $award)
			{
				if ($award['award_active'] == 1)
				{
					
					$awarduserslist = '';
					$award['award_desc'] = $bbcode_parser->parse($award['award_desc']);
					if (is_array($awarduserscache[$award['award_id']]))
					{
						$aw_ui = 0;
						foreach($awarduserscache[$award['award_id']] AS $userid => $awardusers)
						{
							$aw_ui++;
							if (($vbulletin->options['aw_display_memberlimit'] == 0) OR ($aw_ui <= $vbulletin->options['aw_display_memberlimit']))
							{
								//- VB3 -// eval('$awarduserslist .= ", ' . fetch_template('awards_awardusers_bit') . '";');
								//- BEGIN VB4 -//
								fetch_musername($awardusers);
								$templater = vB_Template::create('awards_awardusers_bit');
									$templater->register('awardusers', $awardusers);
								$awarduserslist .= $templater->render();
								//- END VB4 -//
							}
						}
						$indexOfFirstComma = strpos($awarduserslist, ',');
						if($indexOfFirstComma !== false)
							$awarduserslist =  substr($awarduserslist , 0, $indexOfFirstComma) . substr($awarduserslist , $indexOfFirstComma+2); // get rid of initial comma

						if (($vbulletin->options['aw_display_memberlimit'] > 0) AND ($aw_ui > $vbulletin->options['aw_display_memberlimit']))
						{
							$awarduserslist .= "<br> <div align=\"right\"><font size=\"-1\"><a href=\"awards.php?do=viewaward&amp;award_id=$award[award_id]\">$vbphrase[aw_more_users]</a></font></div>";
						}
					}
					exec_switch_bg();
					//- VB3 -// eval('$awardsbits .= "' . fetch_template('awards_awardbit') . '";');
					//- BEGIN VB4 -//
					$templater = vB_Template::create('awards_awardbit');
						$templater->register('award', $award);
						$templater->register('awarduserslist', $awarduserslist);
						$templater->register('aw_ui', $aw_ui);
						$templater->register('award_cat', $award_cat);
						$templater->register('awardsbits', $awardsbits);
					$awardsbits .= $templater->render();
					//- END VB4 -//
					
				}
			} //foreach $awardcache
		} //if is_array

		$templater = vB_Template::create('awards_categorybit');
			$templater->register('award_cat', $award_cat);
		$award_categotybit .= $templater->render();
		$award_categotybit = yaas_construct_depth_mark($award_cat['depth'], '- - ', '');

		$templater = vB_Template::create('awards_category');
			$templater->register('award', $award);
			$templater->register('award_cat', $award_cat);
			$templater->register('awarduserslist', $awarduserslist);
			$templater->register('aw_ui', $aw_ui);
			$templater->register('award_categotybit', $award_categotybit);
			$templater->register('awardsbits', $awardsbits);
		$award_categories .= $templater->render();
	} //foreach $award_cat_cache


$navbits = construct_navbits(array('' => $vbphrase['awards']));
	
	//- VB3 -// eval('$navbar = "' . fetch_template('navbar') . '";');
	//- BEGIN VB4 -//
	$navbar = render_navbar_template($navbits); 
	//- END VB4 -//

	//- VB3 -// construct_forum_jump();

	//- VB3 -// eval('print_output("' . fetch_template('AWARDS') . '");');
	//- BEGIN VB4 -//
	$templater = vB_Template::create('AWARDS'); 
		$templater->register_page_templates(); 
		$templater->register('navbar', $navbar); 
		$templater->register('award_categories', $award_categories); 
		$templater->register('awversion', $awversion);
	print_output($templater->render()); 
	//- END VB4 -//
}

if ($_REQUEST['do'] == 'viewaward')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'award_id' => TYPE_UINT
	));

	if ($vbulletin->GPC['award_id'] == 0)
	{
			eval(standard_error(fetch_error('invalidid', "awardid", $vbulletin->options['contactuslink'])));
	}

			// Obtain list of users of each award
			$allawardusers =  $db->query_read("
			SELECT u.userid, u.username, au.award_id, IF(u.displaygroupid = 0, u.usergroupid, u.displaygroupid) AS displaygroupid
			FROM " . TABLE_PREFIX . "award_user AS au
			LEFT JOIN " . TABLE_PREFIX . "user AS u ON (u.userid = au.userid)
			WHERE au.award_id = " . $vbulletin->GPC['award_id'] . "
			GROUP BY u.userid, u.username, au.award_id
			ORDER BY u.userid
			");
			while( $au = $db->fetch_array($allawardusers))
			{
				$awarduserscache[$au['award_id']][$au['userid']] = $au;
			}
			$db->free_result($allawardusers);

			$award = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "award WHERE award_id = ".$vbulletin->GPC['award_id'] ."");

				if ($award['award_active'] == 1)
				{
					$award['award_desc'] = $bbcode_parser->parse($award['award_desc']);
					
					$awarduserslist = '';
					if (is_array($awarduserscache[$award['award_id']]))
					{
						$aw_ui = 0;
						foreach($awarduserscache[$award['award_id']] AS $userid => $awardusers)
						{
								$aw_ui++;
								//- VB3 -// eval('$awarduserslist .= ", ' . fetch_template('awards_awardusers_bit') . '";');
								//- BEGIN VB4 -//
								fetch_musername($awardusers);
								$newTemplate = vB_Template::create('awards_awardusers_bit');
									$newTemplate->register('awardusers', $awardusers);
								$awarduserslist .= $newTemplate->render();  	
								//- END VB4 -//
								
						}
					}
					$indexOfFirstComma = strpos($awarduserslist, ',');
					if($indexOfFirstComma !== false)
						$awarduserslist =  substr($awarduserslist , 0, $indexOfFirstComma) . substr($awarduserslist , $indexOfFirstComma+2); // get rid of initial comma
				}

	$navbits = construct_navbits(array('' => $vbphrase['awards']));
	//- VB3 -// eval('$navbar = "' . fetch_template('navbar') . '";');
	//- BEGIN VB4 -//
	$navbar = render_navbar_template($navbits); 
	//- END VB4 -//
	
	//- VB3 -// construct_forum_jump();
	//- VB3 -// eval('print_output("' . fetch_template('awards_viewaward') . '");');
	
	//- BEGIN VB4 -//
	$templater = vB_Template::create('awards_viewaward'); 
		$templater->register_page_templates(); 
		$templater->register('awarduserslist', $awarduserslist); 
		$templater->register('award', $award); 
		$templater->register('aw_ui', $aw_ui); 
		$templater->register('navbar', $navbar); 
	print_output($templater->render()); 
	//- END VB4 -//
}
?>
