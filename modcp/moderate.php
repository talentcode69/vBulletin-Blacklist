<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.11 Patch Level 1 - Licence Number 3578c1c3
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2008 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 15151 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('thread',	'calendar', 'timezone', 'threadmanage');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_databuild.php');

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'calendarid' => TYPE_INT,
	'forumid'    => TYPE_INT,
));
log_admin_action(iif(!empty($vbulletin->GPC['calendarid']), "calendar id = " . $vbulletin->GPC['calendarid'], iif(!empty($vbulletin->GPC['forumid']), "forum id = " . $vbulletin->GPC['forumid'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['moderation']);

// ###################### Start event moderation #######################
if ($_REQUEST['do'] == 'events')
{
	if (can_moderate())
	{
		$sql = 'OR 1 = 1';
	}
	else
	{
		$calendars = $db->query_read("SELECT calendarid FROM " . TABLE_PREFIX . "calendar");
		$sql = ' OR calendar.calendarid IN(0';
		while ($calendar = $db->fetch_array($calendars))
		{
			if (can_moderate_calendar($calendar['calendarid'], 'canmoderateevents'))
			{
				$sql .= ", $calendar[calendarid]";
			}
		}
		$sql .= ')';
	}

	print_form_header('moderate', 'doevents');
	print_table_header($vbphrase['events_awaiting_moderation']);
	$events = $db->query_read("
		SELECT event.*, event.title AS subject, user.username, calendar.title, IF(dateline_to = 0, 1, 0) AS singleday
		FROM " . TABLE_PREFIX . "event AS event
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(event.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "calendar AS calendar ON(calendar.calendarid = event.calendarid)
		WHERE (1 = 0 $sql) AND visible = 0
	");
	$done = false;
	while ($eventinfo = $db->fetch_array($events))
	{
		if ($done)
		{
			print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
		}
		else
		{
			print_description_row('
				<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
				&nbsp;
				<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />
				&nbsp;
				<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
			', 0, 2, 'thead', 'center');
		}

		if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
		{
			print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&u=$eventinfo[userid]\">$eventinfo[username]</a>");
		}
		else
		{
			print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', '<a href="../' . $vbulletin->config['Misc']['admincpdir']  . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$eventinfo[userid]\">$eventinfo[username]</a>");
		}
		print_label_row('<b>' . $vbphrase['calendar'] . '</b>', '<a href="../calendar.php?' . $vbulletin->session->vars['sessionurl'] . "c=$eventinfo[calendarid]\">$eventinfo[title]</a>");
		print_input_row('<b>' . $vbphrase['subject'] . '</b>', "eventsubject[$eventinfo[eventid]]", $eventinfo['subject']);

		$time1 =  vbdate($vbulletin->options['timeformat'], $eventinfo['dateline_from']);
		$time2 =  vbdate($vbulletin->options['timeformat'], $eventinfo['dateline_to']);

		if ($eventinfo['singleday'])
		{
			print_label_row('<b>' . $vbphrase['date'] . '</b>', vbdate($vbulletin->options['dateformat'], $eventinfo['dateline_from']));
		}
		else if ($eventinfo['dateline_from'] != $eventinfo['dateline_to'])
		{
			$recurcriteria = fetch_event_criteria($eventinfo);
			$date1 = vbdate($vbulletin->options['dateformat'], $eventinfo['dateline_from']);
			$date2 = vbdate($vbulletin->options['dateformat'], $eventinfo['dateline_to']);
			if (!$recurcriteria)
			{
				$recurcriteria = $vbcalendar['word6']; // What is word6?
			}
			print_label_row('<b>' . $vbphrase['time'] . '</b>', construct_phrase($vbphrase['x_to_y'], $time1, $time2));
			print_label_row('<b>' . $vbphrase['timezone'] . '</b>', "<select name=\"eventtimezone[$eventinfo[eventid]]\" tabindex=\"1\" class=\"bginput\">" . construct_select_options(fetch_timezones_array(), $eventinfo['utc']) . '</select>');
			print_label_row('<b>' . $vbphrase['date_range'] . '</b>', $recurcriteria . ' | ' . construct_phrase($vbphrase['x_to_y'], $date1, $date2));
		}
		else
		{
			$date = vbdate($vbulletin->options['dateformat'], $eventinfo['from_date']);
			print_label_row('<b>' . $vbphrase['time'] . '</b>', construct_phrase($vbphrase['x_to_y'], $time1, $time2));
			print_label_row('<b>' . $vbphrase['timezone'] . '</b>', "<select name=\"eventtimezone[$eventinfo[eventid]]\" tabindex=\"1\" class=\"bginput\">" . construct_select_options(fetch_timezones_array(), $eventinfo['utc']) . '</select>');
			print_label_row('<b>' . $vbphrase['date_range'] . '</b>', $date);
		}

		print_textarea_row('<b>' . $vbphrase['event'] . '</b>', "eventtext[$eventinfo[eventid]]", $eventinfo['event'], 15, 70);
		print_label_row($vbphrase['action'], "
			<label for=\"val_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"1\" id=\"val_$eventinfo[eventid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
			<label for=\"del_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"-1\" id=\"del_$eventinfo[eventid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>
			<label for=\"ign_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"0\" id=\"ign_$eventinfo[eventid]\" tabindex=\"1\" checked=\"checked\" /> " . $vbphrase['ignore'] . "</label>
		", '', 'top', 'eventaction');
		$done = true;
	}

	if (!$done)
	{
		print_description_row($vbphrase['no_events_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}
}

// ###################### Start do event moderation #######################
if ($_POST['do'] == 'doevents')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'eventaction'   => TYPE_ARRAY_INT,
		'eventsubject'  => TYPE_ARRAY_STR,
		'eventtext'     => TYPE_ARRAY_STR,
		'eventtimezone' => TYPE_ARRAY_INT,
	));

	foreach ($vbulletin->GPC['eventaction'] AS $eventid => $action)
	{
		$eventid = intval($eventid);
		$getcalendarid = $db->query_first("
			SELECT calendarid
			FROM " . TABLE_PREFIX . "event
			WHERE eventid = $eventid
		");
		if (!can_moderate_calendar($getcalendarid['calendarid'], 'canmoderateevents'))
		{
			continue;
		}

		$eventinfo = array('eventid' => $eventid);
		// init event datamanager class
		$eventdata =& datamanager_init('Event', $vbulletin, ERRTYPE_SILENT);
		$eventdata->set_existing($eventinfo);

		if ($action == 1)
		{ // validate

			$eventdata->verify_datetime = false;
			$eventdata->set('utc', $vbulletin->GPC['eventtimezone']["$eventid"]);
			$eventdata->set('title', $vbulletin->GPC['eventsubject']["$eventid"]);
			$eventdata->set('event', $vbulletin->GPC['eventtext']["$eventid"]);
			$eventdata->set('visible', 1);
			$eventdata->save();
		}
		else if ($action == -1)
		{ // delete

			$eventdata->delete();
		}
	}

	define('CP_REDIRECT', 'moderate.php?do=events');
	print_stop_message('moderated_events_successfully');
}

// ###################### Start thread/post moderation #######################
if ($_REQUEST['do'] == 'posts')
{
	// fetch threads and posts to be moderated from the moderation table
	// this saves a index on visible and a query with about 3 inner joins
	$threadids = array();
	$postids = array();

	$hasdelperm = array();

	$moderated = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "moderation");
	while ($moderate = $db->fetch_array($moderated))
	{
		if ($moderate['type'] == 'thread')
		{
			$threadids[] = $moderate['threadid'];
		}
		else
		{
			$postids[] = $moderate['postid'];
		}
	}
	$db->free_result($moderated);

	$sql = fetch_moderator_forum_list_sql('canmoderateposts');

	print_form_header('moderate', 'doposts', 0, 1, 'threads');
	print_table_header($vbphrase['threads_awaiting_moderation']);

	if (!empty($threadids))
	{
		$threadids = implode(',', $threadids);
		$threads = $db->query_read("
			SELECT thread.threadid, thread.title AS title, thread.notes AS notes,
				thread.forumid AS forumid, thread.postuserid AS userid,
				thread.postusername AS username, thread.dateline, thread.firstpostid, pagetext
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON(thread.firstpostid = post.postid)
			WHERE (1 = 0 $sql) AND thread.threadid IN($threadids)
			ORDER BY thread.lastpost
		");

		$havethreads = false;
		while ($thread = $db->fetch_array($threads))
		{
			if ($thread['firstpostid'] == 0)
			{ // eek potential for disaster
				$post_text = $db->query_first("SELECT pagetext FROM " . TABLE_PREFIX . "post WHERE threadid = $thread[threadid] ORDER BY dateline ASC");
				$thread['pagetext'] = $post_text['pagetext'];
			}

			if ($havethreads)
			{
				print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
			}
			else
			{
				print_description_row('
					<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
					' . ((can_moderate('candeleteposts') OR can_moderate('canremoveposts')) ? '&nbsp;
					<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />' : '') . '
					&nbsp;
					<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
				', 0, 2, 'thead', 'center');
			}
			if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($thread['userid'], '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&u=$thread[userid]\" target=\"_blank\">$thread[username]</a>", $vbphrase['guest']));
			}
			else
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($thread['userid'], '<a href="../' . $vbulletin->config['Misc']['admincpdir'] . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$thread[userid]\" target=\"_blank\">$thread[username]</a>", $vbphrase['guest']));
			}
			print_label_row('<b>' . $vbphrase['forum'] . '</b>', '<a href="../forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$thread[forumid]\" target=\"_blank\">" . $vbulletin->forumcache["$thread[forumid]"]['title'] . "</a>");
			print_input_row($vbphrase['title'], "threadtitle[$thread[threadid]]", $thread['title'], 0, 70);
			print_textarea_row($vbphrase['message'], "threadpagetext[$thread[threadid]]", $thread['pagetext'], 15, 70);
			print_input_row($vbphrase['notes'], "threadnotes[$thread[threadid]]", $thread['notes'], 1, 70);

			if (!isset($hasdelperm["$thread[forumid]"]))
			{
				$hasdelperm["$thread[forumid]"] = (can_moderate($thread['forumid'], 'candeleteposts') OR can_moderate($thread['forumid'], 'canremoveposts'));
			}

			print_label_row($vbphrase['action'], "
				<label for=\"val_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"1\" id=\"val_$thread[threadid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
				" . ($hasdelperm["$thread[forumid]"] ? "<label for=\"del_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"-1\" id=\"del_$thread[threadid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>" : '') . "
				<label for=\"ign_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"0\" id=\"ign_$thread[threadid]\" tabindex=\"1\" checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
			", '', 'top', 'threadaction');

			$havethreads = true;
		}
	}
	if (!$havethreads)
	{
		print_description_row($vbphrase['no_threads_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}

	print_form_header('moderate', 'doposts', 0, 1, 'posts');
	print_table_header($vbphrase['posts_awaiting_moderation'], 2, 0, 'postlist');


	if (!empty($postids))
	{
		$postids = implode(',', $postids);
		$posts = $db->query_read("
			SELECT postid, pagetext, post.dateline, post.userid, post.title AS post_title,
			thread.title AS thread_title, thread.forumid AS forumid, username, thread.threadid
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE (1 = 0 $sql) AND postid IN($postids)
			ORDER BY dateline
		");
		$haveposts = false;
		while ($post = $db->fetch_array($posts))
		{
			if ($haveposts)
			{
				print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
			}
			else
			{
				print_description_row('
					<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
					' . ((can_moderate('candeleteposts') OR can_moderate('canremoveposts')) ? '&nbsp;
					<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />' : '') . '
					&nbsp;
					<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
				', 0, 2, 'thead', 'center');
			}
			if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($post['userid'], '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&u=$post[userid]\" target=\"_blank\">$post[username]</a>", $vbphrase['guest']));
			}
			else
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($post['userid'], '<a href="../' . $vbulletin->config['Misc']['admincpdir'] . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$post[userid]\" target=\"_blank\">$post[username]</a>", $vbphrase['guest']));
			}
			print_label_row('<b>' . $vbphrase['thread'] . '</b>', '<a href="../showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$post[threadid]\" target=\"_blank\">$post[thread_title]</a>");
			print_label_row('<b>' . $vbphrase['forum'] . '</b> ', '<a href="../forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$post[forumid]\" target=\"_blank\">" . $vbulletin->forumcache["$post[forumid]"]['title'] . "</a>");
			print_input_row($vbphrase['title'], "posttitle[$post[postid]]", $post['post_title'], 0, 70);
			print_textarea_row($vbphrase['message'], "postpagetext[$post[postid]]", $post['pagetext'], 15, 70);

			if (!isset($hasdelperm["$post[forumid]"]))
			{
				$hasdelperm["$post[forumid]"] = (can_moderate($post['forumid'], 'candeleteposts') OR can_moderate($post['forumid'], 'canremoveposts'));
			}

			print_label_row($vbphrase['action'], "
				<label for=\"val_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"1\" id=\"val_$post[postid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
				" . ($hasdelperm["$post[forumid]"] ? "<label for=\"del_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"-1\" id=\"del_$post[postid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>" : '') . "
				<label for=\"ign_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"0\" id=\"ign_$post[postid]\" tabindex=\"1\"  checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
			", '', 'top', 'postaction');

			$haveposts = true;
		}
	}
	if (!$haveposts)
	{
		print_description_row($vbphrase['no_posts_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}

}

// ###################### Start do thread/post moderation #######################
if ($_POST['do'] == 'doposts')
{

	// As of 3.5 user post counts are not incremented when a moderated thread/post is inserted
	// So when a post is accepted, posts are incremented. When deleted, nothing is done to posts

	$updateforum = array();
	$updatethread = array();
	$notified = array();
	$threadids = array();
	$postids = array();

	$hasdelperm = array();

	$vbulletin->input->clean_array_gpc('p', array(
		'threadaction'   => TYPE_ARRAY_INT,
		'threadtitle'    => TYPE_ARRAY_STR,
		'threadnotes'    => TYPE_ARRAY_STR,
		'threadpagetext' => TYPE_ARRAY_STR,
		'postpagetext'   => TYPE_ARRAY_STR,
		'postaction'     => TYPE_ARRAY_INT,
		'posttitle'      => TYPE_ARRAY_STR,
	));

	vbmail_start();

	$userbyuserid = array();

	if (!empty($vbulletin->GPC['threadaction']))
	{
		if ($vbulletin->options['similarthreadsearch'])
		{
			require_once(DIR . '/includes/functions_search.php');
		}
		$modlog = array();
		foreach ($vbulletin->GPC['threadaction'] AS $threadid => $action)
		{
			$threadid = intval($threadid);
			// check whether moderator of this forum
			$threadinfo = fetch_threadinfo($threadid);
			if (!can_moderate($threadinfo['forumid'], 'canmoderateposts'))
			{
				continue;
			}

			$countposts = $vbulletin->forumcache["$threadinfo[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts'];
			if ($action == 1)
			{ // validate
				// do queries
				$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($threadinfo);
				$threadman->set_info('skip_first_post_update', true);
				$threadman->set('visible', 1);
				$threadman->set('title', $vbulletin->GPC['threadtitle']["$threadid"]);
				$threadman->set('notes', $vbulletin->GPC['threadnotes']["$threadid"]);
				if ($vbulletin->options['similarthreadsearch'])
				{
					$threadman->set('similar', fetch_similar_threads($vbulletin->GPC['threadtitle']["$threadid"], $threadinfo['threadid']));
				}
				$threadman->save();
				unset($threadman);

				$post = $db->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "post
					WHERE threadid = $threadid
					ORDER BY dateline
					LIMIT 1
				");
				$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$postman->set_existing($post);
				$postman->set('visible', 1); // This should already be visible
				$postman->set('title', $vbulletin->GPC['threadtitle']["$threadid"]);
				$postman->set('pagetext', $vbulletin->GPC['threadpagetext']["$threadid"], true, false); // bypass the verify_pagetext call
				$postman->save();
				unset($postman);

				// This needs to be converted into a one query CASE statement
				if ($countposts)
				{
					// Increment post count of all visible posts in thread
					$posts = $vbulletin->db->query_read("
						SELECT userid
						FROM " . TABLE_PREFIX . "post
						WHERE threadid = $threadid AND visible = 1
					");
					$userbyuserid = array();
					while ($post = $vbulletin->db->fetch_array($posts))
					{
						if (!isset($userbyuserid["$post[userid]"]))
						{
							$userbyuserid["$post[userid]"] = 1;
						}
						else
						{
							$userbyuserid["$post[userid]"]++;
						}
					}
				}

				$threadids[] = $threadid;
				$npostids[] = $post['postid'];
				$updateforum["$threadinfo[forumid]"] = 1;

				$modlog[] = array(
					'userid'   => $vbulletin->userinfo['userid'],
					'forumid'  => $threadinfo['forumid'],
					'threadid' => $threadinfo['threadid'],
				);

			}
			else if ($action == -1)
			{
				// delete
				if (!isset($hasdelperm["$threadinfo[forumid]"]))
				{
					$hasdelperm["$threadinfo[forumid]"] = (can_moderate($threadinfo['forumid'], 'candeleteposts') OR can_moderate($threadinfo['forumid'], 'canremoveposts'));
				}
				if (!$hasdelperm["$threadinfo[forumid]"])
				{
					// doesn't have permission to delete in this forum
					continue;
				}

				$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($threadinfo);
				$threadman->delete($countposts, can_moderate($threadinfo['forumid'], 'canremoveposts'));
				unset($threadman);

				$updateforum["$threadinfo[forumid]"] = 1;
			}
		}

		if (!empty($threadids))
		{
			$threadids = implode(',', $threadids);
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE threadid IN($threadids) AND type = 'thread'
			");
		}

		if (!empty($modlog))
		{
			require_once(DIR . '/includes/functions_log_error.php');
			log_moderator_action($modlog, 'approved_thread');
		}
	}

	if (!empty($vbulletin->GPC['postaction']))
	{
		require_once(DIR . '/includes/functions_newpost.php');
		$modlog = array();
		foreach ($vbulletin->GPC['postaction'] AS $postid => $action)
		{
			$postid = intval($postid);

			if (!$postinfo = $db->query_first("
				SELECT post.*, thread.forumid
				FROM " . TABLE_PREFIX . "post AS post
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
				WHERE post.postid = $postid
			"))
			{
				continue;
			}

			if (!can_moderate($postinfo['forumid'], 'canmoderateposts'))
			{
				continue;
			}

			$countposts = $vbulletin->forumcache["$postinfo[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts'];

			if ($post['visible'] != 0)
			{
				// this post should not be in the moderation queue
				$postids[] = $postid;
				continue;
			}

			if ($action == 1)
			{
				// validate
				$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$postman->set_existing($postinfo);
				$postman->set('visible', 1);
				$postman->set('pagetext', $vbulletin->GPC['postpagetext']["$postid"], true, false); // bypass the verify_pagetext call
				$postman->set('title', $vbulletin->GPC['posttitle']["$postid"]);
				$postman->save();

				if ($countposts)
				{
					if (!isset($userbyuserid["$postinfo[userid]"]))
					{
						$userbyuserid["$postinfo[userid]"] = 1;
					}
					else
					{
						$userbyuserid["$postinfo[userid]"]++;
					}
				}

				// send notification
				if (!$notified["$postinfo[threadid]"])
				{
					$message = $vbulletin->GPC['postpagetext']["$postid"];
					exec_send_notification($postinfo['threadid'], $postinfo['userid'], $postid);
					$notified["$postinfo[threadid]"] = true;
				}

				$postids[] = $postid;
				$updatethread["$postinfo[threadid]"] = 1;
				$updateforum["$postinfo[forumid]"] = 1;

				$modlog[] = array(
					'userid'   => $vbulletin->userinfo['userid'],
					'forumid'  => $postinfo['forumid'],
					'threadid' => $postinfo['threadid'],
					'postid'   => $postid,
				);

			}
			else if ($action == -1)
			{
				// delete

				if (!isset($hasdelperm["$postinfo[forumid]"]))
				{
					$hasdelperm["$postinfo[forumid]"] = (can_moderate($postinfo['forumid'], 'candeleteposts') OR can_moderate($postinfo['forumid'], 'canremoveposts'));
				}
				if (!$hasdelperm["$postinfo[forumid]"])
				{
					// doesn't have permission to delete in this forum
					continue;
				}

				$postids[] = $postid;

				$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$postman->set_existing($postinfo);
				$postman->delete($countposts, $postinfo['threadid'], can_moderate($postinfo['forumid'], 'canremoveposts'));
				unset($postman);

				$updatethread["$postinfo[threadid]"] = 1;
				$updateforum["$postinfo[forumid]"] = 1;
			}
		}
		if (!empty($postids))
		{
			$postids = implode(',', $postids);
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE postid IN($postids) AND type = 'reply'
			");
		}

		if (!empty($modlog))
		{
			require_once(DIR . '/includes/functions_log_error.php');
			log_moderator_action($modlog, 'approved_post');
		}
	}

	vbmail_end();

	// Update post counts
	unset($userbyuserid[0]); // skip any guest posts
	if (!empty($userbyuserid))
	{
		$userbypostcount = array();
		foreach ($userbyuserid AS $postuserid => $postcount)
		{
			$alluserids .= ",$postuserid";
			$userbypostcount["$postcount"] .= ",$postuserid";
		}
		foreach($userbypostcount AS $postcount => $userids)
		{
			$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
		}

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET posts = posts +
			CASE
				$casesql
				ELSE 0
			END
			WHERE userid IN (0$alluserids)
		");
	}

	// update counters
	if (!empty($updatethread))
	{
		foreach ($updatethread AS $threadid => $null)
		{
			build_thread_counters($threadid);
		}
	}
	if (!empty($updateforum))
	{
		foreach ($updateforum AS $forumid => $null)
		{
			build_forum_counters($forumid);
		}
	}

	define('CP_REDIRECT', 'moderate.php?do=posts');
	print_stop_message('moderated_posts_successfully');

}


// ###################### Start attachment moderation #######################
if ($_REQUEST['do'] == 'attachments')
{
	$sql = fetch_moderator_forum_list_sql('canmoderateattachments');

	print_form_header('moderate', 'doattachments');
	print_table_header($vbphrase['attachments_awaiting_moderation']);

	$attachments = $db->query_read("
		SELECT user.username, post.username AS postusername, attachment.filename, attachment.postid, thread.forumid, thread.threadid, attachment.thumbnail_dateline,
			attachment.attachmentid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize, attachment.filesize, attachment.dateline
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid = user.userid)
		WHERE attachment.visible = 0 AND attachment.postid <> 0
			AND (1 = 0 $sql)
	");
	$done = false;
	while ($attachment = $db->fetch_array($attachments))
	{
		if ($done)
		{
			print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
		}
		else
		{
			print_description_row('
				<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '"
				/>&nbsp;<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '"
				/>&nbsp;<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
			', 0, 2, 'thead', 'center');
		}
		print_label_row($vbphrase['attachment'], '<b> ' . '<a href="../attachment.php?' . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;d=$attachment[dateline]\" target=\"_blank\">" . htmlspecialchars_uni($attachment['filename']) . '</a></b>' . ' (' . vb_number_format($attachment['filesize'], 1, true) . ')');

		$extension = strtolower(file_extension($attachment['filename']));
		if ($extension == 'gif' OR $extension == 'jpg' OR $extension == 'jpe' OR $extension == 'jpeg' OR $extension == 'png' OR $extension == 'bmp')
		{
			if ($attachment['hasthumbnail'])
			{
				print_label_row($vbphrase['thumbnail'], '<a href="../attachment.php?' . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;stc=1&amp;d=$attachment[thumbnail_dateline]\" target=\"_blank\"><img src=\"../attachment.php?" . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;thumb=1&amp;d=$attachment[dateline]\" border=\"0\" style=\"border: outset 1px #AAAAAA\" alt=\"\" /></a>");
			}
			else
			{
				print_label_row($vbphrase['image'], '<img src="../attachment.php?' . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;d=$attachment[dateline]\" border=\"0\" />");
			}
		}
		print_label_row($vbphrase['posted_by'], iif($attachment['username'], $attachment['username'], $attachment['postusername']). ' ' . construct_link_code($vbphrase['view_post'], '../showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$attachment[postid]", 1));
		print_label_row($vbphrase['action'], "
			<label for=\"val_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"1\" id=\"val_$attachment[attachmentid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
			<label for=\"del_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"-1\" id=\"del_$attachment[attachmentid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>
			<label for=\"ign_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"0\" id=\"ign_$attachment[attachmentid]\" tabindex=\"1\" checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
		", '', 'top', 'attachaction');
		$done = true;
	}

	if (!$done)
	{
		print_description_row($vbphrase['no_attachments_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}
}


// ###################### Start do attachment moderation #######################
if ($_POST['do'] == 'doattachments')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'attachaction' => TYPE_ARRAY_INT
	));

	$deleteids = '';
	$approvedids = '';
	$finalapproveids = '';
	foreach ($vbulletin->GPC['attachaction'] AS $attachmentid => $action)
	{
		if ($action == 0)
		{ // no point in checking the permission if they dont want to do anything to the attachment
			continue;
		}

		$attachmentid = intval($attachmentid);

		if ($action == 1)
		{ // validate
			$approveids .= ',' . $attachmentid;
		}
		else if ($action == -1)
		{ // delete
			$deleteids .= ',' . $attachmentid;
		}
	}

	if (!empty($approveids))
	{
		$ids = $db->query_read("
			SELECT attachmentid, forumid
			FROM " . TABLE_PREFIX . "attachment AS attachment
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid = post.postid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			WHERE attachmentid IN (-1$approveids)
		");
		while ($id = $db->fetch_array($ids))
		{
			if (can_moderate($id['forumid'], 'canmoderateattachments'))
			{
				$finalapproveids .= ",$id[attachmentid]";
			}
		}
		if (!empty($finalapproveids))
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "attachment SET
					visible = 1
				WHERE attachmentid IN (0$finalapproveids)
			");
		}
	}

	if (!empty($deleteids))
	{
		$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_CP);
		$attachdata->condition = "attachmentid IN (0$deleteids)";
		$attachdata->delete();
		unset($attachdata);
	}

	define('CP_REDIRECT', 'moderate.php?do=attachments');
	print_stop_message('moderated_attachments_successfully');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 11:43, Mon Sep 8th 2008
|| # CVS: $RCSfile$ - $Revision: 15151 $
|| ####################################################################
\*======================================================================*/
?>
