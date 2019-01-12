<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);


/*$cookie_expire = $user->time_now - 31536000;
$user->set_cookie('u', '', $cookie_expire);
$user->set_cookie('k', '', $cookie_expire);
$user->set_cookie('sid', '', $cookie_expire);
unset($cookie_expire);

$cookie_expire = $user->time_now + (($config['max_autologin_time']) ? 86400 * (int) $config['max_autologin_time'] : 31536000);

$user->set_cookie('u', $user->cookie_data['u'], $cookie_expire);
$user->set_cookie('k', $user->cookie_data['k'], $cookie_expire);
$user->set_cookie('sid', '070c6f50f18a680b317e6aa89cfcf98e', $cookie_expire);

unset($cookie_expire);*/

// Start session management
$user->session_begin_custom();
$auth->acl($user->data);
$user->setup('viewforum');

// Mark notifications read
if (($mark_notification = $request->variable('mark_notification', 0)))
{
	if ($user->data['user_id'] == ANONYMOUS)
	{
		if ($request->is_ajax())
		{
			trigger_error('LOGIN_REQUIRED');
		}
		login_box('', $user->lang['LOGIN_REQUIRED']);
	}

	if (check_link_hash($request->variable('hash', ''), 'mark_notification_read'))
	{
		/* @var $phpbb_notifications \phpbb\notification\manager */
		$phpbb_notifications = $phpbb_container->get('notification_manager');

		$notification = $phpbb_notifications->load_notifications('notification.method.board', array(
			'notification_id'	=> $mark_notification,
		));

		if (isset($notification['notifications'][$mark_notification]))
		{
			$notification = $notification['notifications'][$mark_notification];

			$notification->mark_read();

			if ($request->is_ajax())
			{
				$json_response = new \phpbb\json_response();
				$json_response->send(array(
					'success'	=> true,
				));
			}

			if (($redirect = $request->variable('redirect', '')))
			{
				redirect(append_sid($phpbb_root_path . $redirect));
			}

			redirect($notification->get_redirect_url());
		}
	}
}

display_forums('', $config['load_moderators']);

$order_legend = ($config['legend_sort_groupname']) ? 'group_name' : 'group_legend';
// Grab group details for legend display
if ($auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel'))
{
	$sql = 'SELECT group_id, group_name, group_colour, group_type, group_legend
		FROM ' . GROUPS_TABLE . '
		WHERE group_legend > 0
		ORDER BY ' . $order_legend . ' ASC';
}
else
{
	$sql = 'SELECT g.group_id, g.group_name, g.group_colour, g.group_type, g.group_legend
		FROM ' . GROUPS_TABLE . ' g
		LEFT JOIN ' . USER_GROUP_TABLE . ' ug
			ON (
				g.group_id = ug.group_id
				AND ug.user_id = ' . $user->data['user_id'] . '
				AND ug.user_pending = 0
			)
		WHERE g.group_legend > 0
			AND (g.group_type <> ' . GROUP_HIDDEN . ' OR ug.user_id = ' . $user->data['user_id'] . ')
		ORDER BY g.' . $order_legend . ' ASC';
}
$result = $db->sql_query($sql);

/** @var \phpbb\group\helper $group_helper */
$group_helper = $phpbb_container->get('group_helper');

$legend = array();
while ($row = $db->sql_fetchrow($result))
{
	$colour_text = ($row['group_colour']) ? ' style="color:#' . $row['group_colour'] . '"' : '';
	$group_name = $group_helper->get_name($row['group_name']);

	if ($row['group_name'] == 'BOTS' || ($user->data['user_id'] != ANONYMOUS && !$auth->acl_get('u_viewprofile')))
	{
		$legend[] = '<span' . $colour_text . '>' . $group_name . '</span>';
	}
	else
	{
		$legend[] = '<a' . $colour_text . ' href="' . append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group&amp;g=' . $row['group_id']) . '">' . $group_name . '</a>';
	}
}
$db->sql_freeresult($result);

$legend = implode($user->lang['COMMA_SEPARATOR'], $legend);

// Generate birthday list if required ...
$birthdays = $birthday_list = array();
if ($config['load_birthdays'] && $config['allow_birthdays'] && $auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel'))
{
	$time = $user->create_datetime();
	$now = phpbb_gmgetdate($time->getTimestamp() + $time->getOffset());

	// Display birthdays of 29th february on 28th february in non-leap-years
	$leap_year_birthdays = '';
	if ($now['mday'] == 28 && $now['mon'] == 2 && !$time->format('L'))
	{
		$leap_year_birthdays = " OR u.user_birthday LIKE '" . $db->sql_escape(sprintf('%2d-%2d-', 29, 2)) . "%'";
	}

	$sql_ary = array(
		'SELECT' => 'u.user_id, u.username, u.user_colour, u.user_birthday',
		'FROM' => array(
			USERS_TABLE => 'u',
		),
		'LEFT_JOIN' => array(
			array(
				'FROM' => array(BANLIST_TABLE => 'b'),
				'ON' => 'u.user_id = b.ban_userid',
			),
		),
		'WHERE' => "(b.ban_id IS NULL OR b.ban_exclude = 1)
			AND (u.user_birthday LIKE '" . $db->sql_escape(sprintf('%2d-%2d-', $now['mday'], $now['mon'])) . "%' $leap_year_birthdays)
			AND u.user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')',
	);

	/**
	* Event to modify the SQL query to get birthdays data
	*
	* @event core.index_modify_birthdays_sql
	* @var	array	now			The assoc array with the 'now' local timestamp data
	* @var	array	sql_ary		The SQL array to get the birthdays data
	* @var	object	time		The user related Datetime object
	* @since 3.1.7-RC1
	*/
	$vars = array('now', 'sql_ary', 'time');
	extract($phpbb_dispatcher->trigger_event('core.index_modify_birthdays_sql', compact($vars)));

	$sql = $db->sql_build_query('SELECT', $sql_ary);
	$result = $db->sql_query($sql);
	$rows = $db->sql_fetchrowset($result);
	$db->sql_freeresult($result);

	foreach ($rows as $row)
	{
		$birthday_username	= get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
		$birthday_year		= (int) substr($row['user_birthday'], -4);
		$birthday_age		= ($birthday_year) ? max(0, $now['year'] - $birthday_year) : '';

		$birthdays[] = array(
			'USERNAME'	=> $birthday_username,
			'AGE'		=> $birthday_age,
		);

		// For 3.0 compatibility
		$birthday_list[] = $birthday_username . (($birthday_age) ? " ({$birthday_age})" : '');
	}

	/**
	* Event to modify the birthdays list
	*
	* @event core.index_modify_birthdays_list
	* @var	array	birthdays		Array with the users birthdays data
	* @var	array	rows			Array with the birthdays SQL query result
	* @since 3.1.7-RC1
	*/
	$vars = array('birthdays', 'rows');
	extract($phpbb_dispatcher->trigger_event('core.index_modify_birthdays_list', compact($vars)));

	$template->assign_block_vars_array('birthdays', $birthdays);
}

// Assign index specific vars
$template->assign_vars(array(
	'TOTAL_POSTS'	=> $user->lang('TOTAL_POSTS_COUNT', (int) $config['num_posts']),
	'TOTAL_TOPICS'	=> $user->lang('TOTAL_TOPICS', (int) $config['num_topics']),
	'TOTAL_USERS'	=> $user->lang('TOTAL_USERS', (int) $config['num_users']),
	'NEWEST_USER'	=> $user->lang('NEWEST_USER', get_username_string('full', $config['newest_user_id'], $config['newest_username'], $config['newest_user_colour'])),

	'LEGEND'		=> $legend,
	'BIRTHDAY_LIST'	=> (empty($birthday_list)) ? '' : implode($user->lang['COMMA_SEPARATOR'], $birthday_list),

	'FORUM_IMG'				=> $user->img('forum_read', 'NO_UNREAD_POSTS'),
	'FORUM_UNREAD_IMG'			=> $user->img('forum_unread', 'UNREAD_POSTS'),
	'FORUM_LOCKED_IMG'		=> $user->img('forum_read_locked', 'NO_UNREAD_POSTS_LOCKED'),
	'FORUM_UNREAD_LOCKED_IMG'	=> $user->img('forum_unread_locked', 'UNREAD_POSTS_LOCKED'),

	'S_LOGIN_ACTION'			=> append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login'),
	'U_SEND_PASSWORD'           => ($config['email_enable']) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=sendpassword') : '',
	'S_DISPLAY_BIRTHDAY_LIST'	=> ($config['load_birthdays']) ? true : false,
	'S_INDEX'					=> true,

	'U_MARK_FORUMS'		=> ($user->data['is_registered'] || $config['load_anon_lastread']) ? append_sid("{$phpbb_root_path}index.$phpEx", 'hash=' . generate_link_hash('global') . '&amp;mark=forums&amp;mark_time=' . time()) : '',
	'U_MCP'				=> ($auth->acl_get('m_') || $auth->acl_getf_global('m_')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=main&amp;mode=front', true, $user->session_id) : '')
);

$page_title = ($config['board_index_text'] !== '') ? $config['board_index_text'] : $user->lang['INDEX'];

/**
* You can use this event to modify the page title and load data for the index
*
* @event core.index_modify_page_title
* @var	string	page_title		Title of the index page
* @since 3.1.0-a1
*/
$vars = array('page_title');
extract($phpbb_dispatcher->trigger_event('core.index_modify_page_title', compact($vars)));

// Output page
page_header($page_title, true);

$template->set_filenames(array(
	'body' => 'index_body.html')
);

/*-------------------------------------- TYTICS BATCH AND TITLE ASSIGNING BEGINS HERE ------------------------------------------------*/

// getting likes count from database
$post_table = "tytics_posts";
$likes_table = "tytics_posts_likes";

$sql = 'SELECT COUNT(*) AS tot FROM '.$post_table. "," .$likes_table. " WHERE ".$post_table.".post_id = ".$likes_table.".post_id AND ".$post_table.".poster_id = ".$user->data['user_id'];
$result = $db->sql_query($sql);
$row1 = $db->sql_fetchrow($result);
$likes_count = (int)$row1['tot'];

$user_batch = null;


// selecting the batch according to the user likes
if($likes_count){
    foreach ($batches as $value) {
        if($likes_count >= $value['limit']){
            $user_batch = $value['id'];
        }
    }
}

//getting post count by the current user
$sql2 = "SELECT COUNT(*) AS tot FROM ".$post_table." WHERE poster_id = ".$user->data['user_id'];
$result2 = $db->sql_query($sql2);
$row2 = $db->sql_fetchrow($result2);
$post_count = (int)$row2['tot'];

$user_title = null;

// selecting the title according to the user likes
if($post_count){
    foreach ($titles as $value) {
        if($post_count >= $value['limit']){
            $user_title = $value['id'];
        }
    }
}

$title_table = "tytics_user_earned_titles";

if($user_title){
    $sql3 = "SELECT * FROM ".$title_table." WHERE title_id = ".$user_title." AND user_id = ".$user->data['user_id'];
    $result3 = $db->sql_query($sql3);
    $row3 = $db->sql_fetchrow($result3);

    if(!$row3){
        $sql4 = "INSERT INTO ".$title_table." (user_id, title_id) VALUES (".$user->data['user_id'].",".$user_title.")";
        $result4 = $db->sql_query($sql4);
    }
}

$batch_table = "tytics_user_earned_batches";

if($user_batch){
    $sql5 = "SELECT * FROM ".$batch_table." WHERE batch_id = ".$user_batch." AND user_id = ".$user->data['user_id'];
    $result5 = $db->sql_query($sql5);
    $row5 = $db->sql_fetchrow($result5);

    if(!$row5){
        $sql6 = "INSERT INTO ".$batch_table." (user_id, batch_id) VALUES (".$user->data['user_id'].",".$user_batch.")";
        $result6 = $db->sql_query($sql6);
    }
}

//setting batch title at Tytics side
$url = $tytics_api_url.'user_earned_batches';
$fields = array(
    'user_id' =>$user->data['user_id'],
    'batch_id' => $user_batch
);
foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
rtrim($fields_string, '&');
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POST, count($fields));
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
$result = curl_exec($ch);
curl_close($ch);


//sending title details to tytics side
$url = $tytics_api_url.'user_earned_titles';
$fields = array(
    'user_id' =>$user->data['user_id'],
    'title_id' => $user_title
);
foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
rtrim($fields_string, '&');
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POST, count($fields));
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
$result = curl_exec($ch);
curl_close($ch);

/*-------------------------------------- TYTICS BATCH AND TITLE ASSIGNING ENDS HERE ------------------------------------------------*/


page_footer();