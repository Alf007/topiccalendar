<?php

/**
 *
 * @package phpBB Extension - Alf007 Topic Calendar
 * @copyright (c) 2013 phpBB Group
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */
namespace alf007\topiccalendar\includes;

class Date
{
	public $year,
		$month,
		$day,
		$hour,
		$minute;
	
	public function __contstruct($year, $month, $day, $hour = 0, $minute = 0)
	{
		$this->year = $year;
		$this->month = $month;
		$this->day = $day;
		$this->hour = $hour;
		$this->minute = $minute;
	}
	
	/**
	 * Convert from the date used in the form, but
	 * do it based on the user preference for date formats
	 *
	 * @param	string date (all numbers < 10 must be '0' padded at this point)
	 * @param	array	language table
	 *			
	 * @access public
	 * 
	 * @return object date or false
	 */
	public static function convert($in_stringDate, $lang)
	{
		if ($in_stringDate == '??????' || $in_stringDate == $lang['NO_DATE'])
		{
			return false;
		}
		// find the first punctuation character, which will be our delimiter
		$tmp_format = str_replace(array('y', 'm', 'd'), array('yyyy', 'mm', 'dd'), strtolower($lang['DATE_INPUT_FORMAT']));
		$tmp_yOffset = strpos($tmp_format, 'y');
		$tmp_mOffset = strpos($tmp_format, 'm');
		$tmp_dOffset = strpos($tmp_format, 'd');
		//$tmp_hOffset = strpos($tmp_format, 'H');
		//$tmp_mOffset = strpos($tmp_format, 'i');

		$date = new Date( 
			intval(substr($in_stringDate, $tmp_yOffset, 4)),
			intval(substr($in_stringDate, $tmp_mOffset, 2)),
			intval(substr($in_stringDate, $tmp_dOffset, 2))
			//intval(substr($in_stringDate, $tmp_hOffset, 2))
			//intval(substr($in_stringDate, $tmp_mOffset, 2))
		);
		if ($date->year < 2000 or $date->month < 1 or $date->month > 12 or $date->day < 1 or $date->day > 31)
		{
			return false;
		}
		return $date;
	}
	
	/**
	 *
	 * @return string 	ISO date format
	 */
	public function __toString()
	{
		return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $this->year, $this->month, $this->day, $this->hour, $this->minute, 0);
	}

	/**
	 * Build a DateTime object from this
	 * 
	 * @return \DateTime
	 */
	public function getDateTime()
	{
		return (new \DateTime())->setDate($year, $month, $day)->setTime($hour, $min);
	}
	
	/**
	 * Calculate interval bewteen two dates
	 * 
	 * @param Date $a
	 * @param Date $b
	 * 
	 * @return \DateInterval
	 */
	public static function getInterval(Date $a, Date $b)
	{
		$dta = $a->getDateTime();
		$dtb = $b->getDateTime();
		return $dta->diff($dtb, true);
	}
};

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
		
		return $forum_id > 0 && $forum_id < count($events_forums) && $events_forums[$forum_id] ? $events_forums[$forum_id] : false;
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
	 * @param string 	$date
	 * @param int		$repeat
	 * @param boolean	$interval_date
	 * @param string 	$date_end
	 * @param boolean	$repeat_always
	 * @param int		$interval
	 * @param int		$interval_units
	 *
	 * @access public
	 * @return void
	 */
	public function submit_event($mode, $forum_id, $topic_id, $post_id, $date, $repeat, $interval_date, $date_end, $repeat_always, $interval, $interval_units)
	{
		// Do nothing for a reply/quote
		if ($mode == 'reply' || $mode == 'quote')
		{
			return;
		}
		
		// setup defaults
		$start_date = Date::convert($date, $this->user->lang);
		
		if ($start_date && $interval_date && ($date_end != $this->user->lang['NO_DATE'] || $repeat_always))
		{
			// coax the interval to a positive integer
			$interval = ($tmp_interval = abs($interval)) ? $tmp_interval : 1;
			if ($repeat_always)
			{
				$repeat = 0;
			} else if ($date_end != $this->user->lang['NO_DATE'])
			{
				$end_date = Date::convert($date_end, $this->user->lang);
				// make sure the end is not before the beginning, if so swap
				if ($end_date < $start_date)
				{
					$tmp = $end_date;
					$end_date = $start_date;
					$start_date = $tmp;
				}
		
				// get the number of repeats between the two dates of the interval
				$inter = Date::getInterval($start_date, $end_date);
				switch ($interval_units)
				{
				case 0:	// DAY
					$repeat = $inter->format('%a') / $interval + 1;
					break;

				case 1:	// WEEK
					$repeat = $inter->format('%a') / (7 * $interval) + 1;
					break;

				case 2:	// MONTH
					$repeat = ($inter->format('%y') * 12 + $inter->format('%m')) / $interval + 1;
					break;

				case 3:	// YEAR
					$repeat = $inter->format('%y') / $interval + 1;
					break;
				}
			}
		}
		
		// if this is a new topic and we can post a date to it (do we have to check this) and
		// we have specified a date, then go ahead and enter it
		if ($mode == 'post' && $cal_start_date && forum_check($forum_id))
		{
			$sql = 'INSERT INTO ' . $this->topic_calendar_table_events . ' ' . $this->db->sql_build_array('INSERT', array(
					'forum_id'  => (int)$forum_id,
					'topic_id'  => (int)$topic_id,
					'year' => $start_date->year,
					'month' => $start_date->month,
					'day' => $start_date->day,
					'hour' => $start_date->hour,
					'min' => $start_date->minute,
					'cal_interval' => $interval,
					'cal_repeat' => $repeat,
					'interval_unit' => $intercval_units,
			));
			$result = $this->db->sql_query($sql);
		} // if we are editing a post, we either update, insert or delete, depending on if date is set
		  // and whether or not a date was specified, so we have to check all that stuff
		else if ($mode == 'edit' && forum_check($forum_id))
		{
			// check if not allowed to edit the calendar event since this is not the first post
			if (!$this->first_post($topic_id, $post_id))
			{
				return;
			}
			
			$sql_array = array(
				'SELECT' => 'topic_id',
				'FROM' => $this->topic_calendar_table_events,
				'WHERE' => $this->db->sql_build_array('SELECT', array(
					'topic_id' => (int) $topic_id 
				)) 
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_array);
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
					$sql = 'UPDATE ' . $this->topic_calendar_table_events . ' SET ' . $this->db->sql_build_array('UPDATE', array(
							'year' => $start_date->year,
							'month' => $start_date->month,
							'day' => $start_date->day,
							'hour' => $start_date->hour,
							'min' => $start_date->minute,
							'cal_interval' => $interval,
							'cal_repeat' => $repeat,
							'interval_unit' => $intercval_units
					)) . ' WHERE ' . $this->db->sql_build_array('SELECT', array(
						'topic_id' => (int) $topic_id 
					));
					$this->db->sql_query($sql);
				}
			} // insert the new entry if a date was provided
			else if ($start_date)
			{
				$sql = 'INSERT INTO ' . $this->topic_calendar_table_events . ' ' . $this->db->sql_build_array('INSERT', array(
					'forum_id'  => (int)$forum_id,
					'topic_id'  => (int)$topic_id,
					'year' => $start_date->year,
					'month' => $start_date->month,
					'day' => $start_date->day,
					'hour' => $start_date->hour,
					'min' => $start_date->minute,
					'cal_interval' => $interval,
					'cal_repeat' => $repeat,
					'interval_unit' => $intercval_units
				));
				$this->db->sql_query($sql);
			}
			$this->db->sql_freeresult($result);
		}
	}
	
	public function move_event($new_forum_id, $topic_id, $leave_shadow = false)
	{
		// if we are not leaving a shadow and the new forum doesn't do events,
		// then delete to event and return
		if (!$leave_shadow && !forum_check($new_forum_id))
		{
			if (!$leave_shadow)
			{
				// delete_event($topic_id, null, false);
				return;
			}
		}
		else
		{
			$sql = 'UPDATE ' . $this->topic_calendar_table_events . ' SET ' . $this->db->sql_build_array('UPDATE', array(
				'forum_id' => (int) $new_forum_id 
			)) . ' WHERE ' . $this->db->sql_build_array('SELECT', array(
				'topic_id' => (int) $topic_id 
			));
			$this->db->sql_query($sql);
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
			'e.topic_id' => (int) $topic_id 
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
				AND e.forum_id IN (' . $forum_ids . ')' 
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);
		// we found a calendar event, so let's append it
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if ($row)
		{
			$date = $this->user->create_datetime(sprintf('%d-%02d-01 %02d:%02d:00', $row['year'], $row['month'], $row['day'], $row['hour'], $row['min']));
			$date_f = $date->format($format);
			$interval = $row['cal_interval']; 
			$repeat = $row['cal_repeat'];
			$event['message'] = '<i>' . $date_f . '</i>';
			// if this is more than a single date event, then dig deeper
			if ($row['cal_repeat'] != 1)
			{
				// if this is a repeating or block event (repeat > 1), show end date!
				if ($row['cal_repeat'] > 1)
				{
					$interval_num = $interval * $repeat;
					switch ($row['interval_units'])
					{
					case 0: // DAY
						$interval_format = $interval_num . 'D';
						break;
					
					case 1: // WEEK
						if ($interval_num > 4)
						{
							$interval_format = (int)($interval_num / 4) . 'M';
							$interval_num = $interval_num % 4;
						}
						else
							$interval_format = '';
						$interval_format = $interval_num . 'W';
						break;
					
					case 2: // MONTH
						if ($interval_num > 12)
						{
							$interval_format = (int)($interval_num / 12) . 'Y';
							$interval_num = $interval_num % 12;
						}
						else
							$interval_format = '';
						$interval_format .= $interval_num . 'M';
						break;
					
					case 3: // YEAR
						$interval_format = $interval_num . 'Y';
						break;
					}
					;
					$date_end = $date->getDateTime()->add(new \DateInterval('P' . $interval_format));
					$event['message'] .= ' - <i>' . $date_end->format($format) . '</i>';
				}
			
				// we have a non-continuous interval or a 'forever' repeating event, so we will explain it
				if (!($row['interval_units'] == 0 && $interval == 1 && $repeat != 0))
				{
					$units = ($row['cal_interval'] == 1) ? $this->user->lang['INTERVAL'][$row['interval_units']] : $this->user->lang['INTERVAL'][$row['interval_units'] . 'S'];
					$repeat = $row['cal_repeat'] ? $row['cal_repeat'] . 'x' : $this->user->lang['CAL_REPEAT_FOREVER'];
					$event['message'] .= '<br /><b>' .  $this->user->lang['SEL_INTERVAL'] .  '</b> ' .  $row['cal_interval'] .  ' ' .  $units .  '<br /><b>' .  $this->user->lang['CALENDAR_REPEAT'] .  '</b> ' .  $repeat;
				}
			}
			$info = $event['message'];
		}
		return $info;
	}
	
	/**
	 * Print out the selection box for selecting date
	 *
	 * When a new post is added or the first post in topic is edited, the poster
	 * will be presented with an event date selection box if posting to an event forum
	 *
	 * @param string $mode			
	 * @param int $topic_id			
	 * @param int $post_id			
	 * @param int $forum_id
	 * @param string $date			
	 *
	 * @access private
	 * @return void
	 */
	public function generate_entry($mode, $forum_id, $topic_id, $post_id, $date)
	{
		// if this is a reply/quote or not an event forum or if we are editing and it is not the first post, just return
		if ($mode == 'reply' || $mode == 'quote' || !$this->forum_check($forum_id) || ($mode == 'edit' && !$this->first_post($topic_id, $post_id)))
		{
			return;
		}
		
		// okay we are starting an edit on the post, let's get the required info from the tables
		if ($date == $this->user->lang['NO_DATE'] && $mode == 'edit')
		{
			// setup the format used for the form
			$format = str_replace(array('m', 'd', 'y'), array('%m', '%d', '%Y'), strtolower($this->user->lang['DATE_INPUT_FORMAT']));
			
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
				$date = (new Date($row['year'], $row['month'], $row['day'], $row['hour'], $row['min']))->getDateTime()->format($format);
			}
		}
		
		$this->template->assign_vars(array(
			'S_TOPIC_CALENDAR' => true,
			'CAL_DATE' => $date,
			'CAL_NO_DATE' => $this->user->lang['NO_DATE'] 
		));
		$this->base_calendar();
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
			$weekdays .= '"' . $this->user->lang['MINICAL']['DAY']['SHORT'][$i] . '"';
			if ($weekdays_long != '')
			{
				$weekdays_long .= ', ';
			}
			$weekdays_long .= '"' . $this->user->lang['MINICAL']['DAY']['LONG'][$i] . '"';
		}
		
		$this->template->assign_vars(array(
			'CAL_DATE_FORMAT' => $this->user->lang['DATE_INPUT_FORMAT'],
			'CAL_MONTH_LIST' => $monthList,
			'CAL_WEEKDAY_LIST' => $weekdays_long,
			'CAL_WEEKDAY_SHORT_LIST' => $weekdays 
		));
	}
	
	/**
	 * Determine if this post is the first post in a topic
	 *
	 * Simply query the topics table and determine if this post is
	 * the first post in the topic...important since calendar events
	 * can only be attached to the first post
	 *
	 * @param
	 *			int topic_id
	 * @param
	 *			int post_id
	 *			
	 * @access public
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
		// if this is not the first post, then get out of here
		if (!$row['first_post'])
		{
			return false;
		}
		return true;
	}
	
	/**
	 * Universal single/plural option generator
	 *
	 * This function will take a singular word and its plural counterpart and will
	 * combine them by either appending a (s) to the singular word if the plural word
	 * is formed this way, or will slash separate the singular and plural words.
	 * Example: week(s), country/countries
	 *
	 * @param string $in_singular
	 *			singular word
	 * @param string $in_plural
	 *			plural word
	 *			
	 * @access public
	 * @author moonbase
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
	
	public static function getbirthdays($db, $year)
	{
		$birthdays = array();
		$sql = 'SELECT *
					FROM ' . USERS_TABLE . "
					WHERE user_birthday NOT LIKE '%- 0-%'
						AND user_birthday NOT LIKE '0-%'
						AND	user_birthday NOT LIKE '0- 0-%'
						AND	user_birthday NOT LIKE ''
						AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$birthdays[] = array(
					'username'		=> $row['username'],
					'check_date'	=> $year . '-' . sprintf('%02d', substr($row['user_birthday'], 3, 2)) . '-' . sprintf('%02d', substr($row['user_birthday'], 0, 2)),
					'birthday' 		=> $row['user_birthday'],
					'id'		=> $row['user_id'],
					'show_age'		=> (isset($row['user_show_age'])) ? $row['user_show_age'] : 0,
					'colour'		=> $row['user_colour']
			);
		}
		$db->sql_freeresult($result);
		sort($birthdays);
		return $birthdays;
	}
}
?>