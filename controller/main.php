<?php
/**
*
* @package phpBB Extension - Alf007 Topic Calendar
* @copyright (c) 2015 Alf007
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace alf007\topiccalendar\controller;

use alf007\topiccalendar\includes\functions_topic_calendar;

class main
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	
	/** @var \phpbb\content_visibility */
	protected $content_visibility;
	
	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string PHP extension */
	protected $phpEx;
	
	protected $topic_calendar_table_config;
	protected $topic_calendar_table_events;

	/** list of forums with enabled events */
	private $enabled_forum_ids;
	private $today;
	private $monthView;
	private $cal_auth_sql;
	private $cal_auth_read_sql;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth				   $auth
	* @param \phpbb\config\config			   $config
	* @param \phpbb\db\driver\driver_interface  $db
	* @param \phpbb\controller\helper		   $helper
	* @param \phpbb\template\template		   $template
	* @param \phpbb\user						$user
	* @param string							 $root_path	  phpbb root path
	* @param string							 $phpEx		  php file extension
	* @param string 							$table_config  extension config table name
	* @param string 							$table_events  extension events table name
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\content_visibility $content_visibility, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, $root_path, $phpEx, $table_config, $table_events)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->content_visibility = $content_visibility;
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->phpEx = $phpEx;
		$this->topic_calendar_table_config = $table_config;
		$this->topic_calendar_table_events = $table_events;
	}

	/**
	* Controller for route /topiccalendar/{month, year}
	* Display main calendar page
	*
	* @param int		$month
	* @param int		$year
	* @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
	*/
	public function handle($month = 0, $year = 0)
	{
		$this->user->add_lang_ext('alf007/topiccalendar', 'controller');

		//	Get list of forum ids
		$sql = 'SELECT forum_ids FROM ' . $this->topic_calendar_table_config;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->enabled_forum_ids = $row['forum_ids'];
		
		$cal_days = $this->initialize($month, $year);

		if ($this->monthView['month'] == '12')
		{
			$nextmonth = 1;
			$nextyear = $this->monthView['year'] + 1; 
		} else
		{
			$nextmonth = sprintf('%02d', $this->monthView['month'] + 1);
			$nextyear = $this->monthView['year'];
		}

		if ($this->monthView['month'] == '01')
		{
			$previousmonth = '12';
			$previousyear = $this->monthView['year'] - 1;
		} else
		{
			$previousmonth = sprintf('%02d', $this->monthView['month'] - 1); 
			$previousyear = $this->monthView['year'];
		}

		// prepare images and links for month navigation
		$url_prev_month = $this->helper->route('alf007_topiccalendar_controller', array('month' => $previousmonth, 'year' => $previousyear));
		$url_next_month = $this->helper->route('alf007_topiccalendar_controller', array('month' => $nextmonth, 'year' => $nextyear));

		$url_prev_year = $this->helper->route('alf007_topiccalendar_controller', array('month' => $this->monthView['month'], 'year' => $this->monthView['year'] - 1));
		$url_next_year = $this->helper->route('alf007_topiccalendar_controller', array('month' => $this->monthView['month'], 'year' => $this->monthView['year'] + 1));

		$this->template->assign_vars(array(
			'U_PREV_MONTH' => $url_prev_month,
			'U_NEXT_MONTH' => $url_next_month,
			'U_PREV_YEAR'=> $url_prev_year,
			'U_NEXT_YEAR'=> $url_next_year,
			)
		);
		
		$start_day = date('w', mktime(0, 0, 0, $this->monthView['month'], 1, $this->monthView['year'])) - (int)$this->user->lang['WEEKDAY_START'];
		if ($start_day > 0)
		{
			$start_day--;
			$this->template->assign_var('START_DAY_LINK', true);
		} else if ($start_day < 0)
		{
			$start_day += 7;
		}
		for ($i = 0; $i < $start_day; $i++)
		{
			$this->template->assign_block_vars('before_first_day', array());
		}
				
		for ($day = 0, $last = count($cal_days); $day < $last; $day ++)
		{
			$this->template->assign_block_vars('day_infos', array(
					'DAY_CLASS' => $cal_days[$day]['class'],
					'DAY_INFO' => $day + 1,
				)
			);
			for ($e = 0, $last_e = count($cal_days[$day]['events']); $e < $last_e; $e ++)
			{
				$this->template->assign_block_vars('day_infos.date_event', array(
						'POPUP'				=> $cal_days[$day]['events'][$e]['event'],
						'U_EVENT'			=> $cal_days[$day]['events'][$e]['link'],
						'U_EVENT_END'		=> $cal_days[$day]['events'][$e]['end_event'], 
					)
				);
			}
			if ($cal_days[$day]['birthdays'] != '')
			{
				$this->template->assign_block_vars('day_infos.date_birthday', array(
						'U_EVENT' => $cal_days[$day]['birthdays']
				));
			}
		}
		
		if (($day + $start_day) % 7 != 6)
		{
			$this->template->assign_var('END_DAY_LINK', true);
		}
		
		$this->template->assign_var('S_IN_TOPIC_CALENDAR', true);

		return $this->helper->render('topic_calendar_body.html');
	}
	
	/**
	 * Initialize days infos
	 * 
	 * @param $month
	 * @param $year
	 * @param $for_minical [optional]
	 * 
	 * @return array of days info
	 */
	function initialize($month, $year, $for_minical = false)
	{
		$this->user->add_lang_ext('alf007/topiccalendar', 'controller');

		// determine the information for the current date, from user context
		$this->today = $this->user->create_datetime();
		if ($year == 0)
		{
			$s_month = $this->user->create_datetime();
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
		$first_day = $this->user->create_datetime(sprintf('%d-%02d-01', $year, $month));
		
		// setup the current view information
		$this->monthView = array(
			'monthName'	=> $first_day->format('F'),
			'month'		=> $first_day->format('m'),
			'year'		=> $first_day->format('Y'),
			'numDays'	=> $first_day->format('t'),
			'offset'	=> $first_day->format('w'),
		);
		
		$this->template->assign_var('S_MONTH', $this->monthView['monthName']);
		$this->template->assign_var('S_YEAR', $this->monthView['year']);

		// is this going to give us a negative number ever??
		if ($this->user->lang['WEEKDAY_START'] != 1)
		{
			$this->monthView['offset']++;
		}

		$cal_today = $this->today->format('Ymd');

		// initialise our forums auth list
		$auth_view_forums = implode(', ', array_keys($this->auth->acl_getf('f_list', true)));
		$auth_read_forums = implode(', ', array_keys($this->auth->acl_getf('f_read', true)));
		$auth_post_forums = implode(', ', array_keys($this->auth->acl_getf('f_post', true)));

		//	Control viewable links for queries
		$this->cal_auth_read_sql = ($auth_read_forums != '') ? ', f.forum_name, (t.forum_id IN (' . $auth_read_forums . ')) as cal_read' : '';
		$this->cal_auth_sql = ($auth_view_forums != '') ? ' AND t.forum_id IN (' . $auth_view_forums . ') ' : '';

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

		$weekday = (int)$this->user->lang['WEEKDAY_START'];
		for ($i = 0; $i < 7; $i++)
		{
			$this->template->assign_block_vars('day_headers', array(
					'DAY_LONG' => $this->user->lang['datetime'][date('l', strtotime("Sunday +{$weekday} days"))],
					'DAY_SHORT' => $this->user->lang['datetime'][date('D', strtotime("Sunday +{$weekday} days"))],
				)
			);
			if ($weekday < 6)
			{
				$weekday++;
			} else
			{
				$weekday = 0;
			}
		} 
	
		// Check for birthdays
		$birthday_rows = functions_topic_calendar::getbirthdays($this->db, $this->monthView['year']); 
			
		$topicCache = array();
		// output the days for the current month 
		// if CAL_DATE_SEARCH = POSTS then hyperlink any days which have already past
		// if CAL_DATE_SEARCH = EVENTS then hyperkink any which have events
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
							$cal_days[$a_day]['birthdays'] = '<span class="username-coloured">' . $this->user->lang['HAPPY'] . '</span>';
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
			$sql_array = array(
				'SELECT'	=> 'e.*, t.*' . $this->cal_auth_read_sql,
				'FROM'		=> array(
					$this->topic_calendar_table_events	=> 'e',
					TOPICS_TABLE		=> 't',
					FORUMS_TABLE		=> 'f',
				),
				'WHERE'		=> 	'e.forum_id = f.forum_id 
					AND f.forum_id IN (' . $this->enabled_forum_ids . ') 
					AND e.topic_id = t.topic_id ' .
					$this->cal_auth_sql . '
					AND e.year = ' . (int)$this->monthView['year'] . '
					AND e.month = ' . (int)$this->monthView['month'] . '
					AND e.day = ' . (int)$cal_this_day,
				'ORDER_BY'	=> 'e.year ASC, e.month ASC, e.day ASC'
			);
			if (!$for_minical)
			{
				$sql_array['SELECT'] .= ', pt.post_text, pt.bbcode_uid, pt.bbcode_bitfield';
				$sql_array['FROM'][POSTS_TABLE] = 'pt';
				$sql_array['WHERE'] .= ' AND pt.post_id = t.topic_first_post_id';
			}
			$sql = $this->db->sql_build_query('SELECT', $sql_array);
			$result = $this->db->sql_query($sql);
			$has_event = false;
			$event = 0;
			while ($row = $this->db->sql_fetchrow($result))
			{
				if (array_key_exists('cal_read', $row))
				{
					$has_event = true;
					if ($for_minical)
					{
						if ($title != '')
							$title .= ', ';
						$title .= $row['cal_read'] == '1' ? $row['topic_title'] : $row['forum_name'];
					} else
					{
						$forum_id = $row['forum_id'];
						$can_view = $this->auth->acl_get('f_list', $forum_id);
						$can_read = $can_view && $this->auth->acl_get('f_read', $forum_id); 
						$topic_id = $row['topic_id'];
						
						// prepare the first post text if it has not already been cached
						if ($can_view && !isset($topicCache[$topic_id]))
						{
							$post_text = $row['post_text'];
		
							// if we are spilling over, reduce size...[!] should be configurable [!]
							if (strlen($post_text) > 200)
							{
								$post_text = substr($post_text, 0, 199) . '...';
							}
							if ($row['bbcode_bitfield'])
							{
								$parse_flags = ($row['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0);
								$post_text = generate_text_for_display($post_text, $row['bbcode_uid'], $row['bbcode_bitfield'], $parse_flags, true);
							}
							$post_text = bbcode_nl2br($post_text);
							$post_text = smiley_text($post_text);
		
							$replies = $this->content_visibility->get_count('topic_posts', $row, $forum_id) - 1;
							
							// prepare the popup text, escaping quotes for javascript
							$title_text = '<b>' . $this->user->lang['TOPIC'] . ':</b> ' . $row['topic_title'] . '<br /><b>' . $this->user->lang['FORUM'] . ':</b> <i>' . $row['forum_name'] . '</i><br /><b>' . $this->user->lang['VIEWS'] . ':</b> ' . $row['topic_views'] . '<br /><b>' . $this->user->lang['REPLIES'] . ':</b> ' . $replies;
		
							$title_text .= '<br />' . bbcode_nl2br($post_text);
							$title_text = str_replace('"', '\'', $title_text);
		
							// make the url for the topic
							$topic_url = append_sid("{$this->root_path}viewtopic.$this->phpEx", "f=$forum_id&amp;t=$topic_id");
							$topicCache[$topic_id] = array(
								'first_post' => $title_text,
								'topic_url'=> $topic_url,
							);
						}
		
						$topic_text = strlen($row['topic_title']) > 148 ? substr($row['topic_title'], 0, 147) . '...' : $row['topic_title'];
		
						$cal_days[$a_day]['events'][$event]['event'] = isset($topicCache[$topic_id]['first_post']) ? $topicCache[$topic_id]['first_post'] : '';
						$cal_days[$a_day]['events'][$event]['link'] = ($can_read ? '<a class="event" href="' . $topicCache[$topic_id]['topic_url'] . "\">" : '<i>') . $topic_text;
						$cal_days[$a_day]['events'][$event]['end_event'] = $can_read ? '</a>' : '</i>';
					}
				}
				$event ++;
			}	//while($row)
			$this->db->sql_freeresult($result);
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

			/*if ($for_minical && MINI_CAL_DATE_SEARCH != 'EVENTS')
			{
				$nix_cal_today = mktime(0, 0, 0, $this->monthView['month'], $cal_this_day, $this->monthView['year']);
				if ($title != '')
				{
					$cal_days[$a_day]['day'] = ( $cal_today >= $d_cal_today ) ? '<a href="' . append_sid("{$this->root_path}search.$this->phpEx", "search_id=mini_cal&amp;d=" . $nix_cal_today) . '" alt="' . $title . '" title="' . $title . '">' . ( $cal_this_day ) . '</a>' : '<abbr title="' . $title . '">' . ( $cal_this_day ) . '</abbr></a>';
				} else
				{
					$cal_days[$a_day]['day'] = ( $cal_today >= $d_cal_today ) ? '<a href="' . append_sid("{$this->root_path}search.$this->phpEx", "search_id=mini_cal&amp;d=" . $nix_cal_today) . '">' . ( $cal_this_day ) . '</a>' : $cal_this_day;
				}
			}*/
		}	//	for($a_day)

		$previous_month = $this->user->create_datetime(sprintf('%d-%02d-01', $month == 1 ? $year - 1 : $year, $month == 1 ? 12 : $month - 1));
		$next_month = $this->user->create_datetime(sprintf('%d-%02d-01', $month == 12 ? $year + 1 : $year, $month == 12 ? 1 : $month + 1)); 
		$this->template->assign_vars(array(
			'S_PREVIOUS_MONTH' => $previous_month->format('F'),
			'S_NEXT_MONTH' => $next_month->format('F'),
			'S_PREVIOUS_YEAR'=> $year - 1,
			'S_NEXT_YEAR'=> $year + 1,
			)
		);
		
		return $cal_days;
	}

	/**
	 * Displaying a mini calendar on site index page
	 *  
	 * @param number $month
	 * @param number $year
	 */
	public function display_mini_calendar($month = 0, $year = 0)
	{
		define('DATE_FORMAT', 'Y-m-d H:i:s');

		// Defines what type of search happens when a user clicks on a date in the calendar
		// can be either:
		//	  POSTS   - will return all posts posted on that date
		//	  EVENTS  - will return all events happening on that date
		define('MINI_CAL_DATE_SEARCH', 'EVENTS');

		//	Get list of forum ids and other configuration infos
		$sql = 'SELECT * FROM ' . $this->topic_calendar_table_config;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->enabled_forum_ids = $row['forum_ids'];

		// Limits the number of events shown on the mini cal
		$max_events = $row['minical_max_events'];
		// Limits the number of days ahead in which time upcoming events will be shown
		// set to 0 (zero) for unlimited
		$days_ahead = $row['minical_days_ahead'];
		
		// setup template
		$this->template->set_filenames(array(
				'mini_cal_body' => 'mini_cal_body.html')
		);
		$this->template->assign_var('S_IN_MINI_CAL', true);
		
		$cal_days = $this->initialize($month, $year, true);

		$start_day = date('w', mktime(0, 0, 0, $this->monthView['month'], 1, $this->monthView['year'])) - (int)$this->user->lang['WEEKDAY_START'];
		if ($start_day > 0)
		{
			$start_day--;
			$this->template->assign_var('START_DAY_LINK', true);
		} else if ($start_day < 0)
		{
			$start_day += 7;
		}
		for ($i = 0; $i < $start_day; $i++)
		{
			$this->template->assign_block_vars('before_first_day', array());
		}
		
		for ($i = 0; $i < count($cal_days); $i ++)
		{
			$this->template->assign_block_vars('day_infos', array(
					'DAY_CLASS' => $cal_days[$i]['class'],
					'DAY_INFO' => $cal_days[$i]['day'],
					'DAY_OMO' => $cal_days[$i]['omo'],
				)
			);
		}

		if (($i + $start_day) % 7 != 6)
		{
			$this->template->assign_var('END_DAY_LINK', true);
		}
		
		$display_date_ahead = $this->today;
		$display_date_ahead->add(new \DateInterval('P' . $days_ahead . 'D')); 

		// initialise some sql bits
		$days_ahead_sql = ($days_ahead > 0) ? ' AND (e.year <= ' . $display_date_ahead->format('Y') . ' AND e.month <= ' . $display_date_ahead->format('n') . ' AND e.day <= ' . $display_date_ahead->format('j') . ') ' : '';

		// get the events
		$sql_array = array(
				'SELECT'	=> 'e.*, t.topic_title' . $this->cal_auth_read_sql,
				'FROM'		=> array( 
					$this->topic_calendar_table_events	=> 'e',
					TOPICS_TABLE		=> 't',
					FORUMS_TABLE		=> 'f'
				),
				'WHERE'		=> 'e.forum_id = f.forum_id
					AND e.topic_id = t.topic_id
					AND e.year >= ' . $this->today->format('Y') . '
					AND e.month >= ' . $this->today->format('n') . '
					AND e.day >= ' . $this->today->format('j') . $days_ahead_sql . $this->cal_auth_sql,
				'ORDER_BY'	=> 'e.year ASC, e.month ASC, e.day ASC'
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query_limit($sql, $max_events);
		if ($result)
		{
			$short_months = array('Jan', 'Feb', 'Mar', 'Apr',
				'May_short',	// Short representation of "May". May_short used because in English the short and long date are the same for May.
				'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
			// ok we've got Topic Calendar
			// initialise out date formatting patterns
			$cal_date_pattern = array('/%a/', '/%b/', '/%c/', '/%d/', '/%e/', '/%m/', '/%y/', '/%Y/', '/%H/', '/%k/', '/%h/', '/%l/', '/%i/', '/%s/', '/%p/');
			// output our events in the given date format for the current language
			$prev_cal_date = '';
			$prev_cal_text = '';
			$prev_cal_url = '';
			$prev_cal_urltext = '';
			$prev_cal_multi = 0;
			while ($row = $this->db->sql_fetchrow($result))
			{
				$eventdate = \DateTime::createFromFormat(DATE_FORMAT, sprintf('%d-%02d-%02d %02d:%02d:%02d', $row['year'], $row['month'], $row['day'], $row['hour'], $row['min'], 0));
				$cal_date_replace = array( 
					$this->user->lang['datetime'][date('D', strtotime('Sunday +' . ($eventdate->format('w') - 1) . ' days'))], 
					$this->user->lang['datetime'][$short_months[$eventdate->format('m') - 1]], 
					$eventdate->format('m'), 
					$eventdate->format('d'), 
					$eventdate->format('d'), 
					$eventdate->format('m'), 
					$eventdate->format('y'),
					$eventdate->format('Y'),
					$eventdate->format('H'),
					$eventdate->format('G'),
					$eventdate->format('h'),
					$eventdate->format('g'),
					$eventdate->format('i'),
					$eventdate->format('s'),
					$eventdate->format('A')
				);
				$cal_date = preg_replace($cal_date_pattern, $cal_date_replace, $this->user->lang['MINI_CAL_DATE_FORMAT']);
				if ($prev_cal_date != '' && $prev_cal_date != $cal_date)
				{
					$this->template->assign_block_vars('events', array(
									'EVENT_CLASS' => $prev_class,
									'EVENT_ID' => $prev_cal_id,
									'EVENT_DATE' => $prev_cal_date,
									'EVENT_URLTEXT' => $prev_cal_urltext
								)
					);
					$prev_cal_urltext = '';
				}
				$prev_class = '';
				$prev_cal_date = $cal_date;
				$prev_cal_id = preg_replace($cal_date_pattern, $cal_date_replace, '%Y%m%d');
				$prev_cal_text = $row['topic_title'];
				$prev_cal_url = append_sid("{$this->root_path}viewtopic.$this->phpEx", "t={$row['topic_id']}");
				$see_url = (array_key_exists('cal_read', $row) && $row['cal_read'] == '1');
				if ($prev_cal_urltext != '')
				{
					$prev_cal_urltext .= ', ';
				}
				if ($see_url)
				{
					$prev_cal_urltext .= '<a href="' . $prev_cal_url . '">' . $prev_cal_text . '</a>';
				} else
				{
					$prev_class = 'noview';
					if (array_key_exists('forum_name', $row))
					{
						$prev_cal_urltext .= $row['forum_name'];
					}
				}
			}	// while($row)
			if ($prev_cal_date != '')
			{
				$this->template->assign_var('HAS_EVENTS', true);
				$this->template->assign_block_vars('events', array(
								'EVENT_CLASS' => $prev_class,
								'EVENT_ID' => $prev_cal_id,
								'EVENT_DATE' => $prev_cal_date,
								'EVENT_URLTEXT' => $prev_cal_urltext
							)
				);
			} else
			{	// no events :(
				$this->template->assign_var('HAS_EVENTS', false);
			}
			$this->db->sql_freeresult($result);
		} else
		{
			$this->template->assign_var('HAS_EVENTS', false);
		}

		// output our general calendar bits
		if ($month == 12)
		{
			$nextmonth = 1;
			$nextyear = $year + 1; 
		} else
		{
			$nextmonth = $month + 1;
			$nextyear = $year;
		}

		if ($month == 1)
		{
			$previousmonth = 12;
			$previousyear = $year - 1;
		} else
		{
			$previousmonth = $month - 1; 
			$previousyear = $year;
		}
		$url_prev_month = append_sid("{$this->root_path}index.$this->phpEx", 'month=' . $previousmonth, '&amp;year=' . $previousyear); 
		$url_next_month = append_sid("{$this->root_path}index.$this->phpEx", 'month=' . $nextmonth, '&amp;year=' . $nextyear);
		$this->template->assign_vars(array(
				'U_TOPIC_CALENDAR' => $this->helper->route('alf007_topiccalendar_controller'),
				'U_PREV_MONTH' => $url_prev_month,
				'U_NEXT_MONTH' => $url_next_month,
				)
		);
	}
}
