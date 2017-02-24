<?php

/**
 *
 * @package phpBB Extension - Alf007 Topic Calendar
 * @copyright (c) 2013 phpBB Group
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */
namespace alf007\topiccalendar\includes;

use phpbb\datetime;
class functions_topic_calendar
{
	/* @var \phpbb\config\config */
	protected $config;
	
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\request\request_interface */
	protected $request;
	
	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;
	
	/* @var topic_calendar tables */
	protected $topic_calendar_table_config;
	protected $topic_calendar_table_events;
	
	/**
	* Constructor
	*
	* @param \phpbb\config\config			   $config
	* @param \phpbb\db\driver\driver_interface  $db
	* @param \phpbb\request\request_interface   $request		Request variables
	* @param \phpbb\template\template			$template	Template object
	* @param \phpbb\user						$user
	* @param string 							$table_config  extension config table name
	* @param string 							$table_events  extension events table name
	*/
	public function __construct($config, $db, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, $table_config, $table_events)
	{
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->topic_calendar_table_config = $table_config;
		$this->topic_calendar_table_events = $table_events;
	}

	/**
	 * Retrieve list of forums enabled for events
	 * 
	 * @return false if empty list
	 */
	public function get_enabled_forums()
	{
		$sql = 'SELECT forum_ids FROM ' . $this->topic_calendar_table_config;
		$result = $this->db->sql_query($sql);
		if ($result)
		{
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			return count($row['forum_ids']) > 0 ? $row['forum_ids'] : false; 
		}
		return false;
	}

	/**
	 * Format datetime from input
	 * 
	 * @param string $date as input format (dd/mm/yyyy)
	 * @return string ISO datetime
	 */
	public function get_input_isodate($date)
	{
		// convert the user format of the date into something we can parse to make a IsoDate string
		// we need to pad with placeholders to get the rigth offset
		$cf = preg_replace('/m/i', 'mm', $this->user->lang['DATE_INPUT_FORMAT']);
		$cf = preg_replace('/d/i', 'dd', $cf);
		$cf = preg_replace('/Y/i', 'yyyy', $cf);
		$cf_yOffset = stripos($cf, 'y');
		$cf_mOffset = stripos($cf, 'm');
		$cf_dOffset = stripos($cf, 'd');
		return sprintf('%d-%02d-%02d 12:00:00', substr($date, $cf_yOffset, 4), substr($date, $cf_mOffset, 2), substr($date, $cf_dOffset, 2));
	}

	/**
	 * Initialize a datetime
	 * 
	 * @param \phpbb\user
	 * @param string $date in ISO format (as from DB)
	 * @return \phpbb\datetime
	 */
	public static function get_datetime($user, $date)
	{
		return $user->create_datetime(sprintf('%d-%02d-%02d 12:00:00', substr($date, 0, 4), substr($date, 4, 2), substr($date, 6, 2)));
	}

	/**
	 * Calculate ending date
	 *
	 * @param \phpbb\datetime date for start
	 * @param $repeat number
	 * @param $interval to repeat
	 * @param $interval unit (days, weeks, months or years)
	 * @return phpbb\datetime end date
	 */
	public static function get_date_end($datetime, $repeat, $interval, $interval_unit)
	{
		if ($repeat > 0)
		{
			$temp_date = clone $datetime;
			$interval_num = $interval * $repeat;
			$interval_format = '';
			switch ($interval_unit)
			{
				case 0: // DAY
					$interval_format = $interval_num . 'D';
					break;
						
				case 1: // WEEK
					if ($interval_num > 4)
					{
						$interval_format = sprintf('%dM', $interval_num / 4);
						$interval_num = $interval_num % 4;
					}
					$interval_format .= $interval_num . 'W';
					break;
						
				case 2: // MONTH
					if ($interval_num > 12)
					{
						$interval_format = sprintf('%dY', $interval_num / 12);
						$interval_num = $interval_num % 12;
					}
					$interval_format .= $interval_num . 'M';
					break;
						
				case 3: // YEAR
					$interval_format = $interval_num . 'Y';
					break;
			}
			return $temp_date->add(new \DateInterval('P' . $interval_format));
		}
		// else it is just a single event
		return false;
	}
	
	/**
	 * Check if this is an event forum
	 *
	 * Query the forums table and determine if the forum requested
	 * allows the handling of calendar events. The results are cache
	 * as a static variable.
	 *
	 * @param int $forum_id			
	 *
	 * @access public
	 * @return boolean
	 */
	public function forum_check($forum_id)
	{
		// use static variable for caching results
		static $events_forums;
		// if we are not given a forum_id then return false
		if (is_null($forum_id) || $forum_id === '')
		{
			return false;
		}
		if (!isset($events_forums))
		{
			$forum_ids = $this->get_enabled_forums();
			if ($forum_ids)
			{
				$events_forums[$forum_id] = in_array($forum_id, explode(',', $forum_ids));
			}
		}
		return $events_forums[$forum_id] ? $events_forums[$forum_id] : false;
	}
	
	/**
	 * Build array for sql operation on datetime
	 * 
	 * @param string date field name
	 * @param \phpbb\datetime
	 * @return array (year, month, day, hour, minute)
	 */
	public function build_datetime_aray($name, $datetime)
	{
		return $datetime ? array($name => intval($datetime->format('Ymd'))) : array();
	}
	
	/**
	 * Enter/delete/modifies the event in the topiccalendar table
	 *
	 * Depending on whether the user chooses new topic or edit post, we
	 * make a modification on the topiccalendar table to insert or update the event
	 *
	 * @param string	$mode			whether we are editing or posting new topic
	 * @param int		$forum_id		id of the forum
	 * @param int		$topic_id		id of the topic
	 * @param int		$post_id		id of the post
	 * @param array 	$data			date data
	 *
	 * @access public
	 * @return void
	 */
	public function submit_event($mode, $forum_id, $topic_id, $post_id, $data)
	{
		// Do nothing for a reply/quote
		if ($mode == 'reply' || $mode == 'quote')
		{
			return;
		}
				
		$repeat = 1;
		// coax the interval to a positive integer
		$interval = ($tmp_interval = abs($data['cal_interval'])) ? $tmp_interval : 1;
		
		// setup defaults
		$start_date = $this->user->create_datetime($this->get_input_isodate($data['cal_date']));
		$end_date = $data['date_end'] != $this->user->lang['NO_DATE'] ? $this->user->create_datetime($this->get_input_isodate($data['date_end'])) : null;
		// make sure the end is not before the beginning, if so swap
		if ($start_date && $end_date && $end_date < $start_date)
		{
			$tmp = $end_date;
			$end_date = $start_date;
			$start_date = $tmp;
		}
		
		if ($start_date && $data['interval_date'] && ($end_date || $data['repeat_always']))
		{
			if ($data['repeat_always'])
			{
				$repeat = 0;
			} else
			{
				// get the number of repeats between the two dates of the interval
				$inter = $start_date->diff($end_date, true);
				switch ($data['interval_unit'])
				{
				case 0:	// DAY
					$repeat = $inter->format('%a') / $interval;
					break;

				case 1:	// WEEK
					$repeat = $inter->format('%a') / (7 * $interval);
					break;

				case 2:	// MONTH
					$repeat = $inter->format('%m') / $interval;
					break;

				case 3:	// YEAR
					$repeat = $inter->format('%y') / $interval;
					break;
				}
				$temp_date = functions_topic_calendar::get_date_end($start_date, $repeat, $interval, $data['interval_unit']);
				if ($temp_date)
				{
					if ($temp_date <= $end_date)
					{
						$end_date = $temp_date;
					}
					else
					{
						$repeat--;
						$end_date = functions_topic_calendar::get_date_end($start_date, $repeat, $interval, $data['interval_unit']);
					}
				}
			}
		}
		
		// if this is a new topic and we can post a date to it (do we have to check this) and
		// we have specified a date, then go ahead and enter it
		if ($mode == 'post' && $start_date && $this->forum_check($forum_id))
		{
			$sql = 'INSERT INTO ' . $this->topic_calendar_table_events . ' ' .
				 $this->db->sql_build_array('INSERT', array_merge(array(
							'forum_id'  => (int)$forum_id,
							'topic_id'  => (int)$topic_id,
							'cal_interval' => $interval,
							'cal_repeat' => $repeat,
							'interval_unit' => $data['interval_unit'],
						),
				 		$this->build_datetime_aray('date', $start_date),
				 		$this->build_datetime_aray('end_date', $end_date)
		 		)); 
			$result = $this->db->sql_query($sql);
		} // if we are editing a post, we either update, insert or delete, depending on if date is set
		  // and whether or not a date was specified, so we have to check all that stuff
		else if ($mode == 'edit' && $this->forum_check($forum_id))
		{
			// check if not allowed to edit the calendar event since this is not the first post
			if (!$this->first_post($topic_id, $post_id))
			{
				return;
			}
			
			$sql = 'SELECT topic_id FROM ' . $this->topic_calendar_table_events . '
				WHERE ' . $this->db->sql_build_array('SELECT', array(
					'topic_id' => (int) $topic_id 
					));
			$result = $this->db->sql_query($sql);
			
			// if we have an event in the calendar for this topic and this is the first post,
			// then we will affect the entry depending on if a date was provided
			if ($this->db->sql_fetchrow($result))
			{
				// we took away the calendar date (no start date, no date)
				if (!$start_date)
				{
					$sql = 'DELETE FROM ' . $this->topic_calendar_table_events . ' WHERE ' . $this->db->sql_build_array('SELECT', array(
						'topic_id' => (int) $topic_id 
					));
					$this->db->sql_query($sql);
				}
				else
				{
					$sql = 'UPDATE ' . $this->topic_calendar_table_events . ' SET ' .
					 	$this->db->sql_build_array('UPDATE', array_merge(array(
							'cal_interval' => $interval,
							'cal_repeat' => $repeat,
							'interval_unit' => $data['interval_unit']
						),
						$this->build_datetime_aray('date', $start_date),
				 		$this->build_datetime_aray('end_date', $end_date)
						)) . ' WHERE ' . $this->db->sql_build_array('SELECT', array(
						'topic_id' => (int) $topic_id 
					));
					$this->db->sql_query($sql);
				}
			} // insert the new entry if a date was provided
			else if ($start_date)
			{
				$sql = 'INSERT INTO ' . $this->topic_calendar_table_events . ' ' .
					$this->db->sql_build_array('INSERT', array_merge(array(
						'forum_id'  => (int)$forum_id,
						'topic_id'  => (int)$topic_id,
						'cal_interval' => $interval,
						'cal_repeat' => $repeat,
						'interval_unit' => $data['interval_unit']
					),
					$this->build_datetime_aray('date', $start_date),
				 	$this->build_datetime_aray('end_date', $end_date)
				));
				$this->db->sql_query($sql);
			}
			$this->db->sql_freeresult($result);
		}
	}
	
	/**
	 * Generate the event date info for topic header view
	 *
	 * This public function will generate a string for the first post in a topic
	 * that declares an event date, but only if the event date has a reference
	 * to a forum which allows events to be used. In the case of a reoccuring/block date,
	 * the display will be such that it explains this attribute.
	 *
	 * @param int $topic_id			identifier of the topic
	 * @param int $post_id			identifier of the post, used to determine if this is the leading post (or 0 for forum view)
	 *			
	 * @access public
	 * @return string body message
	 */
	public function show_event($topic_id, $post_id)
	{
		$format = $this->user->lang['DATE_FORMAT'];
		$info = '';
		$forum_ids = $this->get_enabled_forums();
		$sql_where = array(
			't.topic_id' => (int) $topic_id,
		);
		if ($post_id != 0)
		{
			$sql_where['t.topic_first_post_id'] = (int) $post_id;
		}
		$sql_array = array(
			'SELECT' => 'e.* ',
			'FROM' => array(
				$this->topic_calendar_table_events => 'e',
				TOPICS_TABLE => 't',
			),
			'WHERE' => $this->db->sql_build_array('SELECT', $sql_where) . ' 
				AND e.topic_id = t.topic_id
				AND e.forum_id IN (' . $forum_ids . ')' 
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if ($row)
		{	// we found a calendar event, so let's append it
			$date = functions_topic_calendar::get_datetime($this->user, $row['date']);
			$date_f = $date->format($format);
			$interval = $row['cal_interval'];
			$interval_unit = $row['interval_unit'];
			$repeat = $row['cal_repeat'];
			$event['message'] = '<i>' . $date_f . '</i>';
			// if this is more than a single date event, then dig deeper
			if ($repeat != 1 || $row['end_date'] != 0)
			{
				// if this is a repeating or block event (repeat > 1), show end date!
				$date_end = functions_topic_calendar::get_date_end($date, $repeat, $interval, $interval_unit);
				if ($date_end)
				{
					$event['message'] .= ' - <i>' . $date_end->format($format) . '</i>';
				}
			
				// we have a non-continuous interval or a 'forever' repeating event, so we will explain it
				if ($interval_unit > 0 || $interval != 1 || $repeat == 0)
				{
					$units = $interval == 1 ? $this->user->lang['INTERVAL'][$interval_unit] : $this->user->lang['INTERVAL'][$interval_unit . 'S'];
					$repeat = $repeat ? $repeat . 'x' : $this->user->lang['CAL_REPEAT_FOREVER'];
					$event['message'] .= '<br /><b>' .  $this->user->lang['SEL_INTERVAL'] . $this->user->lang['COLON'] . '</b> ' .  $interval .  ' ' .  $units .  '<br /><b>' .  $this->user->lang['CALENDAR_REPEAT'] . $this->user->lang['COLON'] .  '</b> ' .  $repeat;
				}
			}
			$info = $event['message'];
		}
		return $info;
	}
	
	/**
	* Search events for a date
	* 
	* @param \phpbb\auth\auth	auth
	* @param array	ex_fid_ary		Array of excluded forum ids
	* @param \phpbb\datetime	date	Date for search
	* 
	* @return array topic ids with event at this date
	*/
	public function search_events(\phpbb\auth\auth $auth, $ex_fid_ary, \phpbb\datetime $date)
	{
		global $phpbb_container;
		//	Build our query and get our own result
		$phpbb_content_visibility = $phpbb_container->get('content.visibility');
		$m_approve_topics_fid_sql = $phpbb_content_visibility->get_global_visibility_sql('topic', $ex_fid_ary, 't.');
		
		$auth_view_forums = implode(', ', array_keys($auth->acl_getf('f_list', true)));
		//	Control viewable links for queries
		$cal_auth_sql = ($auth_view_forums != '') ? ' AND t.forum_id IN (' . $auth_view_forums . ') ' : '';
		
		$date_int = intval($date->format('Ymd'));
		// get the events
		$sql_array = array(
				'SELECT'	=> 'e.*, t.topic_title',
				'FROM'		=> array(
						$this->topic_calendar_table_events	=> 'e',
						TOPICS_TABLE		=> 't',
				),
				'WHERE'		=> 'e.topic_id = t.topic_id
					AND (e.date = ' . $date_int . '
						OR (e.date < ' . $date_int . '
						AND e.end_date >= ' . $date_int . '))' . $cal_auth_sql . '
					AND ' . $m_approve_topics_fid_sql . '
					' . ((sizeof($ex_fid_ary)) ? 'AND ' . $this->db->sql_in_set('t.forum_id', $ex_fid_ary, true) : ''),
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query_limit($sql, 1001);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$begin_date = $this->user->create_datetime($row['date']);
			$total_days = $date->diff($begin_date)->format('%a');
			if (days_info::is_day_in_interval($this->user, $row['date'], $row['end_date'], $date_int, $row['cal_interval'], $row['interval_unit']))
			{
				$topic_ids[] = (int) $row['topic_id'];
			}
		}
		$this->db->sql_freeresult($result);
		return $topic_ids;
	}
	
	/**
	 * Print out the selection box for selecting date
	 *
	 * When a new topic is added or the first post in topic is edited, the poster
	 * will be presented with an event date selection box if posting to an event forum
	 *
	 * @param array $post_data
	 * @param string $mode
	 * @param int $topic_id
	 * @param int $post_id
	 * @param int $forum_id
	 *
	 * @return array template data
	 */
	public function generate_entry($post_data, $mode, $forum_id, $topic_id, $post_id)
	{
		// if this is a reply/quote or not an event forum or if we are editing and it is not the first post, just return
		if ($mode == 'reply' || $mode == 'quote' || !$this->forum_check($forum_id) || ($mode == 'edit' && !$this->first_post($topic_id, $post_id)))
		{
			return;
		}

		// set up defaults first in case we don't find any event information (such as a new post)
		$date = isset($post_data['cal_date']) ? $post_data['cal_date'] : $this->user->lang['NO_DATE'];
		$interval = isset($post_data['cal_interval']) ? $post_data['cal_interval'] : 1;
		$interval_unit = isset($post_data['interval_unit']) ? $post_data['interval_unit'] : 0;
		$repeat_always = isset($post_data['repeat_always']) ? $post_data['repeat_always'] : '';
		$date_end = isset($post_data['date_end']) ? $post_data['date_end'] : $this->user->lang['NO_DATE'];
		
		// okay we are starting an edit on the post, let's get the required info from the tables
		if ($date == $this->user->lang['NO_DATE'] && $mode == 'edit')
		{
			// grab the event info for this topic
			$sql = 'SELECT * ' .
				'FROM ' . $this->topic_calendar_table_events . ' ' .
				'WHERE ' . $this->db->sql_build_array('SELECT', array(
								'topic_id' => (int) $topic_id 
							));
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			if ($row)
			{
				$dt = functions_topic_calendar::get_datetime($this->user, $row['date']);
				$date = $dt->format($this->user->lang['DATE_INPUT_FORMAT']);
				$interval_unit = $row['interval_unit'];
				$interval = $row['cal_interval'];
				$repeat = $row['cal_repeat'];
				if (intval($row['end_date']) != 0)
				{
					$dt = functions_topic_calendar::get_datetime($this->user, $row['end_date']);
					$date_end = $dt->format($this->user->lang['DATE_INPUT_FORMAT']);
				}
				else
				{
					// only if the repeat is more than 1 day (meaning it actually repeats) do we get the end date
					// else it is just a single event
					if ($repeat != 1 || $row['end_date'] != 0)
					{
						$date_end = functions_topic_calendar::get_date_end($dt, $repeat, $interval, $interval_unit);
						if ($date_end)
						{
							$date_end = $date_end->format($this->user->lang['DATE_INPUT_FORMAT']);
						}
						else
						{
							$date_end = $this->user->lang['NO_DATE'];
						}
					} else if ($repeat == 1)
					{
						$interval = 1;
						$interval_unit = 0;
					} else
					{
						$repeat_always = 'checked="checked"';
					}
				}
			}
		}
		$interval_unit_options = '';
		for ($i = 0; $i < 4; $i++)
		{
			$interval_unit_options .= '<option value="' . $i . '"';
			if ($interval_unit == $i)
			{
				$interval_unit_options .= ' selected="selected"';
			}
			$interval_unit_options .= '>' . $this->pluralize($this->user->lang['INTERVAL'][$i], $this->user->lang['INTERVAL'][$i . 'S']) . '</option>';
		}
		
		$template_data = array(
			'S_SHOW_CALENDAR_BOX' 		=> true,
			'CAL_DATE'					=> $date,
			'CAL_DATE_END'				=> $date_end,
			'CAL_ADVANCED_FORM'			=> ($date_end != $this->user->lang['NO_DATE'] || $repeat_always) ? '' : 'none',
			'CAL_ADVANCED_FORM_ON'		=> ($date_end != $this->user->lang['NO_DATE'] || $repeat_always) ? 'checked="checked"' : '',
			'CAL_NO_DATE'				=> $this->user->lang['NO_DATE'], 
			'CAL_INTERVAL'				=> $interval,
			'CAL_INTERVAL_UNIT_OPTIONS'	=> $interval_unit_options,
			'CAL_REPEAT_ALWAYS'			=> $repeat_always,
		);
		$this->template->assign_vars($template_data);
		$this->base_calendar();
		return $template_data;
	}

	public function base_calendar()
	{
		// generate a list of months for the current language so javascript can pass it up to the calendar
		$monthList = array();
		foreach (array(
			'January',
			'February',
			'March',
			'April',
			'May',
			'June',
			'July',
			'August',
			'September',
			'October',
			'November',
			'December' 
		) as $month)
		{
			$monthList[ ] = '\'' . $this->user->lang['datetime'][$month] . '\'';
		}
		$monthList = implode(',', $monthList);
		
		// Same for week-days
		$weekdays = '';
		$weekdays_long = '';
		for ($i = 0; $i < 7; $i++)
		{
			if ($weekdays != '')
			{
				$weekdays .= ', ';
			}
			$weekdays .= '"' . $this->user->lang['datetime'][date('D', strtotime("Sunday +{$i} days"))] . '"';
			if ($weekdays_long != '')
			{
				$weekdays_long .= ', ';
			}
			$weekdays_long .= '"' . $this->user->lang['datetime'][date('l', strtotime("Sunday +{$i} days"))] . '"';
		}
		
		$this->template->assign_vars(array(
			'CAL_DATE_FORMAT' => $this->user->lang['DATE_INPUT_FORMAT'],
			'CAL_MONTH_LIST' => $monthList,
			'CAL_WEEKDAY_LIST' => $weekdays_long,
			'CAL_WEEKDAY_SHORT_LIST' => $weekdays,
			'S_WEEKDAY_START' => $this->user->lang['WEEKDAY_START'],
		));
	}
	
	/**
	 * Determine if this post is the first post in a topic
	 *
	 * Simply query the topics table and determine if this post is
	 * the first post in the topic...important since calendar events
	 * can only be attached to the first post
	 *
	 * @param	int topic_id
	 * @param	int post_id
	 * @return boolean is first post
	 */
	public function first_post($topic_id, $post_id)
	{
		$sql = 'SELECT ' . $this->db->sql_build_array('SELECT', array(
								'topic_first_post_id' => (int) $post_id 
							)) . ' as first_post' .
				' FROM ' . TOPICS_TABLE .
				' WHERE ' . $this->db->sql_build_array('SELECT', array(
								'topic_id' => (int) $topic_id 
							));
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row['first_post'] != false;
	}
	
	/**
	 * Universal single/plural option generator
	 *
	 * This function will take a singular word and its plural counterpart and will
	 * combine them by either appending a (s) to the singular word if the plural word
	 * is formed this way, or will slash separate the singular and plural words.
	 * Example: week(s), country/countries
	 *
	 * @param string $in_singular singular word
	 * @param string $in_plural plural word
	 * @return string combined singular/plural contruct
	 */
	public function pluralize($in_singular, $in_plural)
	{
		if (stristr($in_plural, $in_singular))
		{
			return substr($in_plural, 0, strlen($in_singular)) . ((strlen($in_plural) > strlen($in_singular)) ? '(' . substr($in_plural, strlen($in_singular)) . ')' : '');
		}
		return $in_singular . '/' . $in_plural;
	}

	/**
	 * Fill template with week days
	 */
	public function apply_weekdays()
	{
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
	}
	
	public function apply_links($month, $year)
	{
		$previous_month = $this->user->create_datetime(sprintf('%04d-%02d-01', $month == 1 ? $year - 1 : $year, $month == 1 ? 12 : $month - 1));
		$next_month = $this->user->create_datetime(sprintf('%04d-%02d-01', $month == 12 ? $year + 1 : $year, $month == 12 ? 1 : $month + 1));
		$this->template->assign_vars(array(
				'S_PREVIOUS_MONTH' => $previous_month->format('F'),
				'S_NEXT_MONTH' => $next_month->format('F'),
				'S_PREVIOUS_YEAR'=> $year - 1,
				'S_NEXT_YEAR'=> $year + 1,
			)
		);
	}
}
?>