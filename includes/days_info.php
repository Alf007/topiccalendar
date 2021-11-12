<?php
/**
*
* @package phpBB Extension - Alf007 Topic Calendar
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace alf007\topiccalendar\includes;

class days_info
{
	//	list of forums with enabled events
	var $enabled_forum_ids;
	//	maximum number of incoming events to display in mini calendar
	var $max_events;
	// Limits the number of days ahead in which time upcoming events will be shown (0 = unlimited)
	var $days_ahead;

	protected $today;
	public $monthView;
	protected $cal_auth_sql;
	protected $cal_auth_read_sql;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\content_visibility */
	protected $content_visibility;
	/** @var string phpBB root path */
	protected $root_path;
	/** @var string PHP extension */
	protected $phpEx;
	
	protected $table_events;
	
	/**
	* Constructor
	*
	* @param \phpbb\auth\auth					$auth
	* @param \phpbb\db\driver\driver_interface	$db
	* @param string							 	$root_path	  phpbb root path
	* @param string								$phpEx	  php file extension
	* @param string 							$table_config  extension config table name
	* @param string 							$table_events  extension events table name
	* @param \phpbb\content_visibility			$content_visibility (optional)
	* @access public
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, $root_path, $phpEx, $table_config, $table_events, \phpbb\content_visibility $content_visibility = null)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->root_path = $root_path;
		$this->phpEx = $phpEx;
		$this->table_events = $table_events;
		$this->content_visibility = $content_visibility;

		// initialise our forums auth list
		$auth_view_forums = array_keys($this->auth->acl_getf('f_list', true));
		$auth_read_forums = array_keys($this->auth->acl_getf('f_read', true));
		
		//	Control viewable links for queries
		$this->cal_auth_read_sql = count($auth_read_forums) > 0 ? ', f.forum_name, ' . $this->db->sql_in_set('t.forum_id', $auth_read_forums) . ' as cal_read' : '';
		$this->cal_auth_sql = count($auth_view_forums) > 0 ? ' AND ' . $this->db->sql_in_set('t.forum_id', $auth_view_forums) : '';
		
		//	Get list of forum ids and other configuration infos
		$sql = 'SELECT * FROM ' . $table_config;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		// configuration data
		$this->enabled_forum_ids = explode(',', $row['forum_ids']);
		$this->max_events = $row['minical_max_events'];
		$this->days_ahead = $row['minical_days_ahead'];
	}

	/**
	 * Generate days infos
	 * 
	 * @param \phpbb\user $user
	 * @param $year
	 * @param $month
	 * @param $for_minical boolean [optional]
	 * 
	 * @return array of days info
	 */
	public function get_days_info(\phpbb\user $user, $year, $month, $for_minical = false)
	{
		$user->add_lang_ext('alf007/topiccalendar', 'controller');

		// determine the information for the current date, from user context
		$this->today = $user->create_datetime();
		if ($year == 0)
		{
			$s_month = $user->create_datetime();
			if ($month != 0)
			{
				$s_month->modify(sprintf('%d month%s', $month, abs($month) > 1 ? 's' : ''));
			}
			$month = (int)$s_month->format('m');
			$year = (int)$s_month->format('Y');
		}
		else if ($month == 0)
		{
			$month = (int)$this->today->format('m');
		}
		$first_day = $user->create_datetime(sprintf('%d-%02d-01', $year, $month));
		
		// setup the current view information
		$this->monthView = array(
			'monthName' => $first_day->format('F'),
			'year' => $first_day->format('Y'),
			'month' => $first_day->format('m'),
			'numDays' => $first_day->format('t'),
			'offset' => $first_day->format('w'),
		);

		// is this going to give us a negative number ever??
		if ($user->lang['WEEKDAY_START'] != 1)
		{
			$this->monthView['offset']++;
		}

		$cal_today = $this->today->format('Ymd');

		for ($i = 1, $last_i = $this->monthView['numDays'] + 1; $i < $last_i; $i++)
		{
			$stamp = strtotime($i . ' ' . $this->monthView['monthName'] . ' ' . $this->monthView['year']);
			$day[] = array(
					$i,
					strftime('%a', $stamp),
					strftime('%A', $stamp),
					strftime("%B", $stamp),
					$this->monthView['month'],
					$this->monthView['year'],
					$stamp,
					date('w', $stamp),
					strftime('%j', $stamp),
					strftime('%U', $stamp),
					"?stamp=$stamp", 
					date("Y-m-d", $stamp)
			);
		}
	
		// Check for birthdays
		$birthday_rows = $this->get_birthdays($this->monthView['year']); 

		// Check events for the month
		$date_min = $this->monthView['year'] . $this->monthView['month'] . '01';
		$date_max = $this->monthView['year'] . $this->monthView['month'] . '31';
		$sql_array = array(
				'SELECT' => 'e.*, t.*, pt.post_text, pt.bbcode_uid, pt.bbcode_bitfield' . $this->cal_auth_read_sql,
				'FROM' => array(
						$this->table_events	=> 'e',
						TOPICS_TABLE		=> 't',
						FORUMS_TABLE		=> 'f',
						POSTS_TABLE			=> 'pt'
				),
				'WHERE' => 'e.forum_id = f.forum_id
					AND ' . $this->db->sql_in_set('f.forum_id', $this->enabled_forum_ids) . " 
					AND e.topic_id = t.topic_id 
					$this->cal_auth_sql 
					AND e.date <= " . $this->db->cast_expr_to_bigint($date_max) . ' 
					AND ((e.cal_repeat = 1
						AND e.date >= ' . $this->db->cast_expr_to_bigint($date_min) . ')
						OR (e.cal_repeat <> 1
						AND e.end_date >= ' . $this->db->cast_expr_to_bigint($date_min) . '))
 					AND pt.post_id = t.topic_first_post_id',
				'ORDER_BY' => 'e.date ASC'
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);
		$events = array();
		while ($row = $this->db->sql_fetchrow($result))
		{	// store results
			$events[] = $row;
		}
		$this->db->sql_freeresult($result);
		
		$topicCache = array();
		// output the days for the current month 
		$cal_days = array();
		for ($a_day = 0; $a_day < $this->monthView['numDays']; $a_day++) 
		{
			// is this a valid weekday?
			$cal_this_day = $day[$a_day][0];
			$d_cal_today = sprintf('%d%02d%02d', $this->monthView['year'], $this->monthView['month'], $cal_this_day);
			$cal_days[$a_day]['class'] = $a_day % 2 ? 'bg1 row1' : 'bg2 row2';
			if ($cal_today == $d_cal_today)
			{
				$cal_days[$a_day]['class'] .= ' today';
			}
			$cal_days[$a_day]['day'] = $a_day + 1;
			$cal_days[$a_day]['omo'] = '';
			$cal_days[$a_day]['events'] = array();
			$current_isodate = sprintf('%d-%02d-%02d', $this->monthView['year'], $this->monthView['month'], $cal_this_day);
			
			//	Insert birthday(s)
			$cal_days[$a_day]['birthdays'] = '';	// for calendar page
			$title = ''; 		// for mini calendar
			for ($b = 0, $last_b = sizeof($birthday_rows); $b < $last_b; $b ++)
			{
				if ($birthday_rows[$b]['check_date'] == $current_isodate)
				{
					if ($for_minical)
					{
						if ($title != '')
							$title .= ', ';
						$title .= $birthday_rows[$b]['username'];
					} else
					{
						if ($birthday_rows[$b]['colour'])
						{
							$user_colour = ' style="color:#' . $birthday_rows[$b]['colour'] . '"';
						}
						else
						{
							$user_colour = '';
						}
						if ($cal_days[$a_day]['birthdays'] == '')
						{
							$cal_days[$a_day]['birthdays'] = '<span class="username-coloured">' . $user->lang['BIRTHDAYS'] . $user->lang['COLON'] . ' </span>';
						} else
						{
							$cal_days[$a_day]['birthdays'] .= ', ';
						}
						$cal_days[$a_day]['birthdays'] .= '<a' . $user_colour . ' href="' . append_sid("{$this->root_path}memberlist.$this->phpEx", 'mode=viewprofile&amp;u=' . $birthday_rows[$b]['id']) . '">' . $birthday_rows[$b]['username'] . '</a>';
					}
					$birth_year = (int)substr($birthday_rows[$b]['birthday'], -4);
					if ($birth_year > 0)
					{
						if ($for_minical)
						{
							$title .= ' (' . ($this->monthView['year'] - $birth_year) . ')';
						} else
						{
							$cal_days[$a_day]['birthdays'] .= ' (' . ((int)$this->monthView['year'] - $birth_year) . ')';
						} 
					}
				}
			}
			$has_event = false;
			$event_index = 0;
			foreach ($events as $event)
			{
				// only events for this day, or within repeated 
				$this_day = intval($this->monthView['year'] . $this->monthView['month'] . sprintf('%02d', $cal_this_day));
				if (!days_info::is_day_in_interval($user, $event['date'], $event['end_date'], $this_day, $event['cal_interval'], $event['interval_unit']))
					continue;
				// Check if user can read target topic
				if (array_key_exists('cal_read', $event))
				{
					$has_event = true;
					if ($for_minical)
					{
						if ($title != '')
							$title .= ', ';
						$title .= $event['cal_read'] == '1' ? $event['topic_title'] : $event['forum_name'];
					} else
					{
						$forum_id = $event['forum_id'];
						$can_view = $this->auth->acl_get('f_list', $forum_id);
						$can_read = $can_view && $this->auth->acl_get('f_read', $forum_id); 
						$topic_id = $event['topic_id'];
						
						// prepare the first post text if it has not already been cached
						if ($can_view && !isset($topicCache[$topic_id]))
						{
							$post_text = $event['post_text'];
							decode_message($post_text);
		
							// if we are spilling over, reduce size...[!] should be configurable [!]
							if (strlen($post_text) > 200)
							{
								$post_text = substr($post_text, 0, 199) . '...';
							}
							if ($event['bbcode_bitfield'])
							{
								$parse_flags = ($event['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0);
								$post_text = generate_text_for_display($post_text, $event['bbcode_uid'], $event['bbcode_bitfield'], $parse_flags, true);
							}
							$post_text = bbcode_nl2br($post_text);
							$post_text = smiley_text($post_text);
		
							$replies = $this->content_visibility->get_count('topic_posts', $event, $forum_id) - 1;
							
							// prepare the popup text, escaping quotes for javascript
							$title_text = '<b>' . $user->lang['TOPIC'] . ':</b> ' . $event['topic_title'] . '<br /><b>' . $user->lang['FORUM'] . ':</b> <i>' . $event['forum_name'] . '</i><br /><b>' . $user->lang['VIEWS'] . ':</b> ' . $event['topic_views'] . '<br /><b>' . $user->lang['REPLIES'] . ':</b> ' . $replies;
		
							$title_text .= '<br />' . bbcode_nl2br($post_text);
							$title_text = str_replace('"', '\'', $title_text);
		
							// make the url for the topic
							$topic_url = append_sid("{$this->root_path}viewtopic.$this->phpEx", "f=$forum_id&amp;t=$topic_id");
							$topicCache[$topic_id] = array(
								'first_post' => $title_text,
								'topic_url'=> $topic_url,
							);
						}
		
						$topic_text = strlen($event['topic_title']) > 148 ? substr($event['topic_title'], 0, 147) . '...' : $event['topic_title'];
		
						$cal_days[$a_day]['events'][$event_index]['event'] = isset($topicCache[$topic_id]['first_post']) ? $topicCache[$topic_id]['first_post'] : '';
						$cal_days[$a_day]['events'][$event_index]['link'] = ($can_read ? '<a class="event" href="' . $topicCache[$topic_id]['topic_url'] . "\">" : '<i>') . $topic_text;
						$cal_days[$a_day]['events'][$event_index]['end_event'] = $can_read ? '</a>' : '</i>';
						$cal_days[$a_day]['events'][$event_index]['block_begin'] = $event['end_date'] != 0 && $event['date'] == $this_day; 
						$cal_days[$a_day]['events'][$event_index]['block_end'] = $event['end_date'] != 0 && $event['end_date'] == $this_day; 
						$cal_days[$a_day]['events'][$event_index]['in_block'] = $event['end_date'] != 0 && $event['cal_interval'] == 1 && $event['date'] < $this_day && $event['end_date'] > $this_day; 
						$event_index++;
					}
				}
			}	//while($event)
			if ($has_event)
			{
				$cal_days[$a_day]['class'] .= ' event';
				if ($for_minical)
				{
					$cal_days[$a_day]['day'] = '<a href="' . append_sid("{$this->root_path}search.$this->phpEx", "search_id=cal_events&amp;d=" . $d_cal_today) . '" alt="' . $title . '" title="' . $title . '">' . ( $cal_this_day ) . '</a>';
					$cal_days[$a_day]['omo'] = 'onmouseover="highlight_event(' . $d_cal_today . ', 0);" onmouseout="highlight_event(' . $d_cal_today . ', 1);"';
				}
			}
			elseif ($for_minical && $title != '')
			{
				$cal_days[$a_day]['day'] = '<abbr title="' . $title . '">' . ( $cal_this_day ) . '</abbr></a>';
			}
		}	//	for($a_day)
		
		return $cal_days;
	}

	/**
	 * Check if a date is part of an interval
	 * 
	 * @param \phpbb\user $user
	 * @param string $start date
	 * @param string $end date
	 * @param string $date to check
	 * @param int $interval between valid dates
	 * @param int $interval_unit (day, week, month, year)
	 * @return boolean
	 */
	public static function is_day_in_interval(\phpbb\user $user, $start, $end, $date, $interval, $interval_unit)
	{
		// The starting date is always valid
		if ($date == $start)
			return true;
		// No range, or out of range
		if ($end == 0 || $date < $start || $end < $date)
			return false;
		// No interval
		if ($interval == 1)
			return true;

		//	Check if date fall on interval item
		$begin_date = $user->create_datetime($start);
		$today_date = $user->create_datetime($date);
		switch ($interval_unit)
		{
		case 0:	// DAY
			return $today_date->diff($begin_date)->format('%a') % $interval == 0;
	
		case 1:	// WEEK
			return $today_date->diff($begin_date)->format('%a') % ($interval * 7) == 0;
	
		case 2:	// MONTH
			return $today_date->format('j') == $begin_date->format('j') && (intval($today_date->format('n')) - intval($begin_date->format('n'))) % interval == 0;
			break;
	
		case 3:	// YEAR
			return $today_date->format('j') == $begin_date->format('j') && $today_date->format('n') == $begin_date->format('n') && (intval($today_date->format('Y')) - intval($begin_date->format('Y'))) % interval == 0;
			break;
		}
		return false;
	}
	
	/**
	 * Get array of birthdays data
	 *
	 * @param int $year
	 * @return array $birthdays of ['username', 'check_date', 'birthday', 'id', 'show_age', 'colour']
	 */
	public function get_birthdays($year)
	{
		$birthdays = array();
		$users = array(USER_NORMAL, USER_FOUNDER);
		$sql = 'SELECT *
					FROM ' . USERS_TABLE . "
					WHERE user_birthday NOT LIKE '%- 0-%'
						AND user_birthday NOT LIKE '0-%'
						AND	user_birthday NOT LIKE '0- 0-%'
						AND	user_birthday NOT LIKE ''
						AND " . $this->db->sql_in_set('user_type', $users);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			 array_push($birthdays, array(
					'username' => $row['username'],
					'check_date' => $year . '-' . sprintf('%02d', substr($row['user_birthday'], 3, 2)) . '-' . sprintf('%02d', substr($row['user_birthday'], 0, 2)),
					'birthday' => $row['user_birthday'],
					'id' => $row['user_id'],
					'show_age' => (isset($row['user_show_age'])) ? $row['user_show_age'] : 0,
					'colour' => $row['user_colour'],
			));
		}
		$this->db->sql_freeresult($result);
		sort($birthdays);
		return $birthdays;
	}
	
	public function get_days_ahead()
	{
		$date_ahead = clone $this->today;
		$date_ahead->add(new \DateInterval('P' . $this->days_ahead . 'D'));
		$after_date_min = 'e.date >= ' . $this->db->cast_expr_to_bigint($this->today->format('Ymd'));
		$before_date_max = $this->days_ahead > 0 ? 'e.date <= ' . $this->db->cast_expr_to_bigint($date_ahead->format('Ymd')) : true;
		
		// get the incoming events
		$sql_array = array(
				'SELECT'	=> 'e.*, t.topic_title' . $this->cal_auth_read_sql,
				'FROM' => array(
						$this->table_events	=> 'e',
						TOPICS_TABLE => 't',
						FORUMS_TABLE => 'f'
				),
				'WHERE' => "e.forum_id = f.forum_id
					AND e.topic_id = t.topic_id
					AND $after_date_min
					AND $before_date_max
					$this->cal_auth_sql",
				'ORDER_BY' => 'e.date ASC'
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		return $this->db->sql_query_limit($sql, $this->max_events);
	}
}
