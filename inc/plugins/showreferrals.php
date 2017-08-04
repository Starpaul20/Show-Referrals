<?php
/**
 * Show Referrals
 * Copyright 2017 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(THIS_SCRIPT == 'misc.php')
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'misc_referrals,misc_referrals_no_referrals,misc_referrals_bit';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("misc_start", "showreferrals_run");
$plugins->add_hook("member_profile_start", "showreferrals_profile");
$plugins->add_hook("fetch_wol_activity_end", "showreferrals_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "showreferrals_online_location");

// The information that shows up on the plugin manager
function showreferrals_info()
{
	global $lang;
	$lang->load("showreferrals", true);

	return array(
		"name"				=> $lang->showreferrals_info_name,
		"description"		=> $lang->showreferrals_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"codename"			=> "showreferrals",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is activated.
function showreferrals_activate()
{
	global $db;

	// Insert templates
	$insert_array = array(
		'title'		=> 'misc_referrals',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->referrals_for}</title>
{$headerinclude}
</head>
<body>
{$header}
{$multipage}
	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tr>
			<td class="thead" colspan="2"><strong>{$lang->referrals_for}</strong></td>
		</tr>
		<tr>
			<td class="tcat" align="center"><span class="smalltext"><strong>{$lang->username}</strong></span></td>
			<td class="tcat" width="40%" align="center"><span class="smalltext"><strong>{$lang->date_registered}</strong></span></td>
		</tr>
		{$referrer_bit}
	</table>
{$multipage}
{$footer}
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_referrals_no_referrals',
		'template'	=> $db->escape_string('<tr>
	<td class="trow1" colspan="2" align="center">{$lang->no_referrals}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_referrals_bit',
		'template'	=> $db->escape_string('<tr>
	<td class="{$alt_bg}" align="center"><a href="{$profilelink}">{$referrer[\'username\']}</a></td>
	<td class="{$alt_bg}" align="center">{$regdate}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile_referrals", "#".preg_quote('{$memprofile[\'referrals\']}')."#i", '{$memprofile[\'referrals\']} [<a href="misc.php?action=referrals&amp;uid={$memprofile[\'uid\']}">{$lang->details}</a>]');
}

// This function runs when the plugin is deactivated.
function showreferrals_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('misc_referrals','misc_referrals_no_referrals','misc_referrals_bit')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile_referrals", "#".preg_quote(' [<a href="misc.php?action=referrals&amp;uid={$memprofile[\'uid\']}">{$lang->details}</a>]')."#i", '', 0);
}

// Update 'Disable edit' input from edit page
function showreferrals_run()
{
	global $mybb, $db, $lang, $templates, $theme, $headerinclude, $header, $footer, $multipage, $alt_bg;
	$lang->load("showreferrals");

	if($mybb->input['action'] == "referrals")
	{
		if($mybb->settings['usereferrals'] != 1)
		{
			error($lang->referrals_disabled);
		}

		if($mybb->usergroup['canviewprofiles'] == 0)
		{
			error_no_permission();
		}

		$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
		$user = get_user($uid);
		if(!$user['uid'])
		{
			error($lang->invalid_user);
		}

		$user['username'] = htmlspecialchars_uni($user['username']);

		$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
		$lang->referrals_for = $lang->sprintf($lang->referrals_for, $user['username']);

		add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
		add_breadcrumb($lang->nav_referrals);

		// Figure out if we need to display multiple pages.
		$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
		if(!$perpage)
		{
			if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
			{
				$mybb->settings['threadsperpage'] = 20;
			}

			$perpage = $mybb->settings['threadsperpage'];
		}

		$query = $db->simple_select("users", "COUNT(uid) AS count", "referrer='{$user['uid']}'");
		$result = $db->fetch_field($query, "count");

		if($mybb->input['page'] != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$pages = $result / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}
		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($result, $perpage, $page, "misc.php?action=referrals&uid={$user['uid']}");

		// Fetch the referrers which will be displayed on this page
		$query = $db->simple_select("users", "*", "referrer='{$user['uid']}'", array("order_by" => "regdate", "order_dir" => "desc", "limit_start" => $start, "limit" => $perpage));
		while($referrer = $db->fetch_array($query))
		{
			$alt_bg = alt_trow();
			$regdate = my_date('relative', $referrer['regdate']);
			$profilelink = get_profile_link($referrer['uid']);
			$referrer['username'] = format_name(htmlspecialchars_uni($referrer['username']), $referrer['usergroup'], $referrer['displaygroup']);

			eval("\$referrer_bit .= \"".$templates->get("misc_referrals_bit")."\";");
		}

		if(!$referrer_bit)
		{
			eval("\$referrer_bit = \"".$templates->get("misc_referrals_no_referrals")."\";");
		}

		eval("\$referrals = \"".$templates->get("misc_referrals")."\";");
		output_page($referrals);
	}
}

// Show link on profile page
function showreferrals_profile()
{
	global $lang;
	$lang->load("showreferrals");
}

// Online activity
function showreferrals_online_activity($user_activity)
{
	global $user, $uid_list, $parameters;
	if(my_strpos($user['location'], "misc.php?action=referrals") !== false)
	{
		if(is_numeric($parameters['uid']))
		{
			$uid_list[] = $parameters['uid'];
		}

		$user_activity['activity'] = "misc_referrals";
		$user_activity['uid'] = $parameters['uid'];
	}

	return $user_activity;
}

function showreferrals_online_location($plugin_array)
{
	global $lang, $parameters, $usernames;
	$lang->load("showreferrals");

	if($plugin_array['user_activity']['activity'] == "misc_referrals")
	{
		if($usernames[$parameters['uid']])
		{
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_referrals2, $plugin_array['user_activity']['uid'], $usernames[$parameters['uid']]);
		}
		else
		{
			$plugin_array['location_name'] = $lang->viewing_referrals;
		}
	}

	return $plugin_array;
}
