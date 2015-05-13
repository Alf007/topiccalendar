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
        
    /**
    * Constructor
    *
    * @param \phpbb\auth\auth                   $auth
    * @param \phpbb\config\config               $config
    * @param \phpbb\db\driver\driver_interface  $db
    * @param \phpbb\controller\helper           $helper
    * @param \phpbb\template\template           $template
    * @param \phpbb\user                        $user
    * @param string                             $root_path      phpbb root path
    * @param string                             $phpEx          php file extension
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
    	
    	// Define the information for the current date
        list($today['year'], $today['month'], $today['day']) = explode('-', $this->user->format_date(time(), 'Y-m-d'));
		// get the first day of the month
       	$display_date = new \DateTime($today['year'] . '-' . $today['month'] . '-01');
        if ($month != 0 && $year != 0)
        {
        	$display_date->setDate($year, $month, 1);
        }

        // setup the current view information
		$monthView = array(
			'monthName'	=>	$this->user->lang['datetime'][$display_date->format('F')],
			'month'	=>	$display_date->format('m'),
			'year'	=>	$display_date->format('Y'),
			'numDays'	=>	$display_date->format('t'),
			'offset'	=>	$display_date->format('w'),
		);
		
        // [*] is this going to give us a negative number ever?? [*]
        if ($this->user->lang['WEEKDAY_START'] != 1)
        {
            $monthView['offset']++;
        }

        if ($monthView['month'] == '12')
        {
            $nextmonth = 1;
            $nextyear = $monthView['year'] + 1; 
        } else
        {
            $nextmonth = sprintf('%02d', $monthView['month'] + 1);
            $nextyear = $monthView['year'];
        }

        if ($monthView['month'] == '01')
        {
            $previousmonth = '12';
            $previousyear = $monthView['year'] - 1;
        } else
        {
            $previousmonth = sprintf('%02d', $monthView['month'] - 1); 
            $previousyear = $monthView['year'];
        }

        // prepare images and links for month navigation
        $url_prev_month = $this->helper->route('alf007_topiccalendar_controller', array('month' => $previousmonth, 'year' => $previousyear));
        $url_next_month = $this->helper->route('alf007_topiccalendar_controller', array('month' => $nextmonth, 'year' => $nextyear));

        $url_prev_year = $this->helper->route('alf007_topiccalendar_controller', array('month' => $monthView['month'], 'year' => $monthView['year'] - 1));
        $url_next_year = $this->helper->route('alf007_topiccalendar_controller', array('month' => $monthView['month'], 'year' => $monthView['year'] + 1));

        //	Output Week days from first day of week
        $weekday = $this->user->lang['WEEKDAY_START']; 
        for ($i = 0; $i < 7; $i++)
        {
            $this->template->assign_block_vars('weekdays', array(
                'WEEKDAY' => $this->user->lang['MINICAL']['DAY']['LONG'][$weekday],
                )
            );
            $weekday++;
            if ($weekday > 6)
                $weekday = 0; 
        }

        $start_day = (int)$monthView['offset'];
        if ($start_day > 0)
        {
            $start_day--;
            $this->template->assign_var('START_DAY_LINK', true);
        }

        $this->template->assign_vars(array(
            'S_MONTH_YEAR' => $monthView['monthName'] . '&nbsp;' . $monthView['year'],
            'U_PREV_MONTH' => $url_prev_month,
            'U_NEXT_MONTH' => $url_next_month,
            'U_PREV_YEAR'=> $url_prev_year,
            'U_NEXT_YEAR'=> $url_next_year,
            )
        );

        for ($i = 0; $i < $start_day; $i++)
        {
            $this->template->assign_block_vars('before_first_day', array());
        }

        // Check for birthdays
        $ucbirthdayrow = functions_topic_calendar::getbirthdays($this->db, $monthView['year']); 

        //	Get list of forum ids
        $sql = 'SELECT forum_ids FROM ' . $this->topic_calendar_table_config;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $enabled_forum_ids = $row['forum_ids'];
        
        // prepare the loops for running through the calendar for the current month
        $eventStack = array();
        $topicCache = array();
        for ($day = 1; $day <= $monthView['numDays']; $day++) 
        {
            // alternate 'day' look
            $day_class = $day % 2 ? 'bg1 row1' : 'bg2 row2';
            // allow the template to handle how to treat the day
            if ($today['day'] == $day && $today['month'] == $monthView['month'] && $today['year'] == $monthView['year'])
            {
                $day_class .= ' today';
            }
            $this->template->assign_block_vars('day_infos', array(
                    'DAY_CLASS' => $day_class,
                    'DAY_INFO' => $day,
                )
            );
            // set the isodate for our current mark in the calendar (padding day appropriately)
            
            $current_isodate = $monthView['year'] . '-' . $monthView['month'] . '-' . sprintf('%02d', $day);
            //	Insert birthday	
            $birthdays = '';
            for ($i = 0, $end = sizeof($ucbirthdayrow); $i < $end; $i ++)
            {
                if ($ucbirthdayrow[$i]['check_date'] == $current_isodate)
                {
                    if ($ucbirthdayrow[$i]['colour'])
                    {
                        $user_colour = ' style="color:#' . $ucbirthdayrow[$i]['colour'] . '"';
                    }
                    else
                    {
                        $user_colour = '';
                    }
                    if ($birthdays == '')
                    {
                        $birthdays = '<span style="color: #0099FF;" class="username-coloured">' . $this->user->lang['HAPPY'] . '</span>';
                    } else
                    {
                        $birthdays .= ', ';
                    }
                    $birthdays .= '<a' . $user_colour . ' href="' . append_sid("{$this->root_path}memberlist.$this->phpEx", 'mode=viewprofile&amp;u=' . $ucbirthdayrow[$i]['id']) . '">' . $ucbirthdayrow[$i]['username'] . '</a>';
                    $birth_year = (int)substr($ucbirthdayrow[$i]['birthday'], -4);
                    if ($birth_year > 0)
                    {
                        $birthdays .= ' (' . ((int)$monthView['year'] - $birth_year) . ')'; 
                    }
                }
            }
            $this->template->assign_block_vars('day_infos.date_birthday', array(
                    'U_EVENT' => $birthdays
                )
            );
            $sql_array = array(
                'SELECT'	=> 'e.*, t.*, pt.post_text, pt.bbcode_uid, pt.bbcode_bitfield, f.forum_name',
                'FROM'		=> array(
                    $this->topic_calendar_table_events	=> 'e',
                	TOPICS_TABLE		=> 't',
                    FORUMS_TABLE		=> 'f',
                    POSTS_TABLE			=> 'pt'
                ),
                'WHERE'		=> 	'e.forum_id = f.forum_id 
                    AND e.topic_id = t.topic_id 
                    AND f.forum_id IN (' . $enabled_forum_ids . ') 
                    AND pt.post_id = t.topic_first_post_id
                    AND e.year = ' . (int)$monthView['year'] . '
                    AND e.month = ' . (int)$monthView['month'] . '
                    AND e.day = ' . (int)$monthView['day'],
                'ORDER_BY'	=> 'e.year ASC, e.month ASC, e.day ASC'
            );
            $sql = $this->db->sql_build_query('SELECT', $sql_array);
            $this->db->sql_return_on_error(true);
            $result = $this->db->sql_query($sql);
            $this->db->sql_return_on_error(false);
            if (!$result)
            {
                $this->helper->error(implode(', ', $this->db->get_sql_error_returned()) . '<br/>' . implode(', ', $sql_array));
            }

            $numEvents = 0;
            while ($topic = $this->db->sql_fetchrow($result))
            {
                $forum_id = $topic['forum_id'];
                $can_view = $this->auth->acl_get('f_list', $forum_id);
                $can_read = $can_view && $this->auth->acl_get('f_read', $forum_id); 
                $topic_id = $topic['topic_id'];

                // prepare the first post text if it has not already been cached
                if ($can_view && !isset($topicCache[$topic_id]))
                {
                    $post_text = $topic['post_text'];

                    // if we are spilling over, reduce size...[!] should be configurable [!]
                    if (strlen($post_text) > 200)
                    {
                        $post_text = substr($post_text, 0, 199) . '...';
                    }
                    if ($topic['bbcode_bitfield'])
                    {
						$parse_flags = ($topic['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0);
						$post_text = generate_text_for_display($post_text, $topic['bbcode_uid'], $topic['bbcode_bitfield'], $parse_flags, true);
                    }
                    $post_text = bbcode_nl2br($post_text);
                    $post_text = smiley_text($post_text);

                    $replies = $this->content_visibility->get_count('topic_posts', $topic, $forum_id) - 1;
                    
                    // prepare the popup text, escaping quotes for javascript
                    $title_text = '<b>' . $this->user->lang['TOPIC'] . ':</b> ' . $topic['topic_title'] . '<br /><b>' . $this->user->lang['FORUM'] . ':</b> <i>' . $topic['forum_name'] . '</i><br /><b>' . $this->user->lang['VIEWS'] . ':</b> ' . $topic['topic_views'] . '<br /><b>' . $this->user->lang['REPLIES'] . ':</b> ' . $replies;

                    $title_text .= '<br />' . bbcode_nl2br($post_text);
                    $title_text = str_replace('\'', '\\\'', htmlspecialchars($title_text));

                    // make the url for the topic
                    $topic_url = append_sid("{$this->root_path}viewtopic.$this->phpEx", "f=$forum_id&amp;t=$topic_id");
                    $topicCache[$topic_id] = array(
                        'first_post' => $title_text,
                        'topic_url'=> $topic_url,
                    );
                }

                $topic_text = strlen($topic['topic_title']) > 148 ? substr($topic['topic_title'], 0, 147) . '...' : $topic['topic_title'];

                $event = isset($topicCache[$topic_id]['first_post']) ? $topicCache[$topic_id]['first_post'] : '';
                $link = $can_read ? '<a href="' . $topicCache[$topic_id]['topic_url'] . "\">" : '<i>'; 
                $this->template->assign_block_vars('day_infos.date_event', array(
                        'POPUP'				=> $event,
                        'U_EVENT'			=> $link . $topic_text,
                        'U_EVENT_END'		=> $can_read ? '</a>' : '</i>', 
                    )
                );
                $numEvents++;
            }	//	while ($this->db->sql_fetchrow($result))
        }	// for ($day <= $monthView['numDays'])

        if (($day + $start_day) % 7 != 0)
        {
            $this->template->assign_var('END_DAY_LINK', true);
        }

        $this->template->assign_var('S_IN_TOPIC_CALENDAR', true);

        return $this->helper->render('topic_calendar_body.html');
    }
    
    /**
     * Displaying a mini calendar on site index page
     *  
     * @param number $month
     * @param number $year
     */
    public function display_mini_calendar($month = 0, $year = 0)
    {
        $this->user->add_lang_ext('alf007/topiccalendar', 'controller');

		define('DATE_FORMAT', 'Y-m-d H:i:s');

        // Limits the number of events shown on the mini cal
        define('MINI_CAL_LIMIT', 5);

        // Limits the number of days ahead in which time upcoming events will be shown
        // set to 0 (zero) for unlimited
        define('MINI_CAL_DAYS_AHEAD', 0);

        // Defines what type of search happens when a user clicks on a date in the calendar
        // can be either:
        //	  POSTS   - will return all posts posted on that date
        //	  EVENTS  - will return all events happening on that date
        define('MINI_CAL_DATE_SEARCH', 'EVENTS');

        // determine the information for the current date
        list($today['year'], $today['month'], $today['day']) = explode('-', $this->user->format_date(time(), 'Y-m-d'));

        if ($month == 0 && $year != 0)
        {
			$month = 12;
			$year--;
        } else if ($month > 12)
        {
			$month = 1;
			$year++;
        }
       	$display_date = new \DateTime($today['year'] . '-' . $today['month'] . '-01');
        if ($month != 0 && $year != 0)
        {
        	$display_date->setDate($year, $month, 1);
        }
		$display_date_ahead = new \DateTime();
		$display_date_ahead->add(new \DateInterval('P' . MINI_CAL_DAYS_AHEAD . 'D')); 

        // setup the current view information
		$monthView = array(
			'monthName'	=>	$this->user->lang['datetime'][$display_date->format('F')],
			'month'	=>	$display_date->format('m'),
			'year'	=>	$display_date->format('Y'),
			'numDays'	=>	$display_date->format('t'),
			'offset'	=>	$display_date->format('w'),
		);

        // [*] is this going to give us a negative number ever?? [*]
        if ($this->user->lang['WEEKDAY_START'] != 1)
        {
            $monthView['offset']++;
        }

        // setup our mini_cal template
        $this->template->set_filenames(array(
            'mini_cal_body' => 'mini_cal_body.html')
        );

        $this->template->assign_var('S_IN_MINI_CAL', true);

        // initialise some variables
        $cal_day = $this->user->format_date(time(), 'd');
        $mini_cal_today = $this->user->format_date(time(), 'Ymd');

        $s_cal_month = ($month != 0) ? (intval($cal_day) <= 28 ? '' : '-3 day ') . $month . ' month' : $mini_cal_today;
        $stamp = strtotime($s_cal_month);

        $dateYYYY = (int)date("Y", $stamp);
        $dateMM = (int)date("n", $stamp);
        $ext_dateMM = date("F", $stamp);
        $daysMonth = (int)date("t", $stamp);

        $mini_cal_count = (int)$this->user->lang['WEEKDAY_START'];
        $mini_cal_this_year = $dateYYYY;
        $mini_cal_this_month = $dateMM;
        $mini_cal_month_days = $daysMonth;

        // initialise our forums auth list
        $auth_view_forums = implode(', ', array_keys($this->auth->acl_getf('f_list', true)));
        $auth_read_forums = implode(', ', array_keys($this->auth->acl_getf('f_read', true)));
        $auth_post_forums = implode(', ', array_keys($this->auth->acl_getf('f_post', true)));

        //	Control viewable links for queries
        $mini_cal_auth_read_sql = ($auth_read_forums != '') ? ', f.forum_name, (t.forum_id IN (' . $auth_read_forums . ')) as cal_read' : '';
        $mini_cal_auth_sql = ($auth_view_forums != '') ? ' AND t.forum_id IN (' . $auth_view_forums . ') ' : '';

        for ($i = 1; $i < $daysMonth + 1; $i++)
        {
            $stamp = strtotime("$i $ext_dateMM $dateYYYY");
            $day[] = array(
                    $i,
                    strftime('%a', $stamp),
                    strftime('%A', $stamp),
                    strftime("%B", $stamp),
                    $dateMM,
                    $dateYYYY,
                    $stamp,
                    date('w', $stamp),
                    strftime('%j', $stamp),
                    strftime('%U', $stamp),
                    "?stamp=$stamp", 
                    date("Y-m-d", $stamp)
            );
        }

        $weekday = $mini_cal_count;
        for ($i = 0; $i < 7; $i++)
        {
            $this->template->assign_block_vars('day_headers', array(
                    'DAY_LONG' => $this->user->lang['MINICAL']['DAY']['LONG'][$weekday],
                    'DAY_SHORT' => $this->user->lang['MINICAL']['DAY']['SHORT'][$weekday],
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

        $start_day = date('w', mktime(0, 0, 0, $mini_cal_this_month, 1, $dateYYYY)) - $mini_cal_count;
        if ($start_day > 0)
        {
            $start_day--;
            $this->template->assign_var('START_DAY_LINK', true);
        } else if ($start_day < 0)
        {
            $start_day += 7;
        }
        $this->template->assign_var('S_MONTH_YEAR', $this->user->lang['MINICAL']['MONTH']['LONG'][$mini_cal_this_month - 1] . '&nbsp;' . $dateYYYY);

        for ($i = 0; $i < $start_day; $i++)
        {
            $this->template->assign_block_vars('before_first_day', array());
        }

        // Check for birthdays
        $birthdays = functions_topic_calendar::getbirthdays($this->db, $monthView['year']); 
        
        //	Get list of forum ids
        $sql = 'SELECT forum_ids FROM ' . $this->topic_calendar_table_config;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $enabled_forum_ids = $row['forum_ids'];
        
        // output the days for the current month 
        // if MINI_CAL_DATE_SEARCH = POSTS then hyperlink any days which have already past
        // if MINI_CAL_DATE_SEARCH = EVENTS then hyperkink any which have events
        for ($i = 0; $i < $mini_cal_month_days; $i++) 
        {
            // is this a valid weekday?
            $mini_cal_this_day = $day[$i][0];
            $d_mini_cal_today = sprintf('%d%02d%02d', $mini_cal_this_year, $mini_cal_this_month, $mini_cal_this_day);
            $mini_cal_day = $mini_cal_this_day;
            $day_class = $i % 2 ? 'bg1 row1' : 'bg2 row2';
            $onmouseover = '';
            $current_isodate = sprintf('%d-%02d-%02d', $mini_cal_this_year, $mini_cal_this_month, $mini_cal_this_day);
            //	Insert birthday
            $title = '';	
            for ($b = 0, $end = sizeof($birthdays); $b < $end; $b ++)
            {
                if ($birthdays[$b]['check_date'] == $current_isodate)
                {
                    if ($title != '')
                        $title .= ', '; 
                    $title .= $birthdays[$b]['username'];
                    $birth_year = (int)substr($birthdays[$b]['birthday'], -4);
                    if ($birth_year > 0)
                    {
                        $title .= ' (' . ($mini_cal_this_year - $birth_year) . ')';
                    }
                }
            }
            if (MINI_CAL_DATE_SEARCH == 'EVENTS')
            {
	            $sql_array = array(
	                'SELECT'	=> 'e.*, t.topic_title' . $mini_cal_auth_read_sql,
	                'FROM'		=> array(
	                    $this->topic_calendar_table_events	=> 'e',
	                	TOPICS_TABLE		=> 't',
                    	FORUMS_TABLE		=> 'f',
	                ),
	                'WHERE'		=> 	'e.forum_id IN (' . $enabled_forum_ids . ') 
	                    AND e.topic_id = t.topic_id
                        $mini_cal_auth_sql
	            		AND e.year = ' . (int)$mini_cal_this_year . '
	                    AND e.month = ' . (int)$mini_cal_this_month . '
	                    AND e.day = ' . (int)$mini_cal_this_day,
	                'ORDER_BY'	=> 'e.year ASC, e.month ASC, e.day ASC'
	            );
	            $sql = $this->db->sql_build_query('SELECT', $sql_array);
                $result = $this->db->sql_query($sql);
                $row = $this->db->sql_fetchrow($result);
                $this->db->sql_freeresult($result);
                if ($row && array_key_exists('cal_read', $row))
                {
                    $day_class .= ' event';
                    if ($title != '')
                        $title .= ', '; 
                    $title .=  $row['cal_read'] == '1' ? $row['topic_title'] : $row['forum_name'];
                    while ($row = $this->db->sql_fetchrow($result))
                    {
                        if ($row['cal_read'] == '1')
                        {
                            $title .= ', ' . $row['topic_title'];
                        } else
                        {
                            $title .= ', ' . $row['forum_name'];
                        }
                    }
                    if ($row['cal_read'] == '1')
                    {
                        $mini_cal_day = '<a href="' . append_sid("{$this->root_path}search.$this->phpEx", "search_id=mini_cal_events&amp;d=" . $d_mini_cal_today) . '" alt="' . $title . '" title="' . $title . '">' . ( $mini_cal_day ) . '</a>';
                    } else
                    {
                        $mini_cal_day = '<abbr title="' . $title . '">' . ( $mini_cal_day ) . '</abbr></a>';
                    }
                    $onmouseover = 'onmouseover="highlight_event(' . $d_mini_cal_today . ', 0);" onmouseout="highlight_event(' . $d_mini_cal_today . ', 1);"';
                } elseif ($title != '')
                {
                    $day_class .= ' event';
                    $mini_cal_day = '<abbr title="' . $title . '">' . ( $mini_cal_day ) . '</abbr></a>';
                }
            } else	//	!(MINI_CAL_DATE_SEARCH == 'EVENTS')
            {
                $nix_mini_cal_today = mktime(0, 0, 0, $mini_cal_this_month, $mini_cal_this_day, $mini_cal_this_year);
                if ($title != '')
                {
                    $mini_cal_day = ( $mini_cal_today >= $d_mini_cal_today ) ? '<a href="' . append_sid("{$this->root_path}search.$this->phpEx", "search_id=mini_cal&amp;d=" . $nix_mini_cal_today) . '" alt="' . $title . '" title="' . $title . '">' . ( $mini_cal_day ) . '</a>' : '<abbr title="' . $title . '">' . ( $mini_cal_day ) . '</abbr></a>';
                } else
                {
                    $mini_cal_day = ( $mini_cal_today >= $d_mini_cal_today ) ? '<a href="' . append_sid("{$this->root_path}search.$this->phpEx", "search_id=mini_cal&amp;d=" . $nix_mini_cal_today) . '">' . ( $mini_cal_day ) . '</a>' : $mini_cal_day;
                }
            }
            if ($mini_cal_today == $d_mini_cal_today)
            {
                $day_class .= ' today';
            }
            $this->template->assign_block_vars('day_infos', array(
                    'DAY_CLASS' => $day_class,
                    'DAY_INFO' => $mini_cal_day,
                    'DAY_OMO' => $onmouseover,
                )
            );
        }	//	for($i)

        if (($i + $start_day) % 7 != 6)
        {
            $this->template->assign_var('END_DAY_LINK', true);
        }

        // initialise some sql bits
        $days_ahead_sql = (MINI_CAL_DAYS_AHEAD > 0) ? " AND (c.cal_date <= " . $display_date_ahead->format(DATE_FORMAT) . ") " : '';

        // get the events
        $now = date(DATE_FORMAT);
        $sql_array = array(
                'SELECT'	=> 'c.topic_id, c.cal_date, c.forum_id, c.cal_date, t.topic_title' . $mini_cal_auth_read_sql,
                'FROM'		=> array( 
                    $this->topic_calendar_table_events	=> 'c',
                    TOPICS_TABLE		=> 't',
                    FORUMS_TABLE		=> 'f'
                ),
                'WHERE'		=> "c.forum_id = f.forum_id 
                    AND c.topic_id = t.topic_id 
                    AND (c.cal_date >= '" . $now . "')" . $days_ahead_sql . $mini_cal_auth_sql,
                'ORDER_BY'	=> 'c.cal_date ASC'
        );
        $sql = $this->db->sql_build_query('SELECT', $sql_array);
        //$this->db->sql_return_on_error(true);
        $result = $this->db->sql_query_limit($sql, MINI_CAL_LIMIT);
        //$this->db->sql_return_on_error(false);
        // did we get a result? 
        // if not then the user does not have Topic Calendar installed
        // so just die quielty don't bother to output an error message
        if ($result)
        {
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
            	$eventdate = \DateTime::createFromFormat(DATE_FORMAT, $row['cal_date']);
                $cal_date_replace = array( 
                    $this->user->lang['MINICAL']['DAY']['SHORT'][(int)$eventdate->format('w') - 1], 
                    $this->user->lang['MINICAL']['MONTH']['SHORT'][(int)$eventdate->format('m') - 1], 
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
                $cal_date = preg_replace($cal_date_pattern, $cal_date_replace, $this->user->lang['Mini_Cal_date_format']);
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
        $url_prev_month = $this->helper->route('alf007_topiccalendar_controller_minical', array('month' => $previousmonth, 'year' => $previousyear));
        $url_next_month = $this->helper->route('alf007_topiccalendar_controller_minical', array('month' => $nextmonth, 'year' => $nextyear));
        $this->template->assign_vars(array(
                'U_TOPIC_CALENDAR' => $this->helper->route('alf007_topiccalendar_controller'),
                'U_PREV_MONTH' => $url_prev_month,
                'U_NEXT_MONTH' => $url_next_month,
                )
        );
    }
}
