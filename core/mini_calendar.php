<?php
/**
*
* @package phpBB Extension - Alf007 Topic Calendar
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace alf007\topiccalendar\core;

class mini_calendar
{
    /** @var \phpbb\auth\auth */
    protected $auth;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\request\request_interface */
    protected $request;

    /* @var \phpbb\template\template */
    protected $template;

    /* @var \phpbb\user */
    protected $user;

    /** @var string phpBB root path */
    protected $root_path;

    /**
    * Constructor
    *
    * @param \phpbb\auth\auth                   $auth
    * @param \phpbb\db\driver\driver_interface  $db
    * @param \phpbb\request\request_interface   $request        Request variables
    * @param \phpbb\template\template           $this->template->
    * @param \phpbb\user                        $user
    * @param string                         root_path           phpbb root path
    */
    public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, $root_path)
    {
        $this->auth = $auth;
        $this->db = $db;
        $this->request = $request;
        $this->template = $template;
        $this->user = $user;
        $this->root_path = $root_path;
    }
    
    public function display_mini_calendar()
    {
        $this->user->add_lang_ext('alf007/topiccalendar', 'controller');

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

        // get the month/year offset from the get variables, or else use first day of this month
        $month = $this->request->variable('month', 0);
        $year = $this->request->variable('year', 0);
        if ($month == 0 && $year != 0)
        {
                $month = 12;
                $year--;
        } else if ($month > 12)
        {
                $month = 1;
                $year++;
        }
        if ($month != 0 && $year != 0)
        {
                $view_isodate = sprintf('%04d', $year) . '-' . sprintf('%02d', $month) . '-01 00:00:00';
        } else
        {	// get the first day of the month as an isodate
                $view_isodate = $today['year'] . '-' . $today['month'] . '-01 00:00:00';
        }

        // setup the current view information
        $sql = "SELECT
                MONTHNAME('$view_isodate') as monthName,
                DATE_FORMAT('$view_isodate', '%m') as month,
                YEAR('$view_isodate') as year,
                DATE_FORMAT(CONCAT(YEAR('$view_isodate'), '-', MONTH('$view_isodate' + INTERVAL 1 MONTH), '-01') - INTERVAL 1 DAY, '%e') as numDays,
                WEEKDAY('$view_isodate') as offset";
        $result = $this->db->sql_query($sql);
        $monthView = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        $monthView['monthName'] = $this->user->lang['datetime'][$monthView['monthName']];

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
        $monthStart = (int)date("w", $stamp);

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
        $birthdays = array();
        $sql = 'SELECT *
                    FROM ' . USERS_TABLE . "
                    WHERE user_birthday NOT LIKE '%- 0-%'
                        AND user_birthday NOT LIKE '0-%'
                        AND	user_birthday NOT LIKE '0- 0-%'
                        AND	user_birthday NOT LIKE ''
                        AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $birthdays[] = array(
                            'username'		=> $row['username'], 
                            'check_date'	=> $monthView['year'] . '-' . sprintf('%02d', substr($row['user_birthday'], 3, 2)) . '-' . sprintf('%02d', substr($row['user_birthday'], 0, 2)), 
                            'birthday' 		=> $row['user_birthday'], 
                            'id'		=> $row['user_id'], 
                            'show_age'		=> (isset($row['user_show_age'])) ? $row['user_show_age'] : 0, 
                            'colour'		=> $row['user_colour']
                        );
        }
        $this->db->sql_freeresult($result);
        sort($birthdays);

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
                // set the isodate for our current mark in the calendar (padding day appropriately)
                $current_isodate .= ' 00:00:00';
                $sql_array = array(
                        'SELECT'	=> "c.*, (cal_interval_units = 'DAY' && cal_interval = 1 && '$current_isodate' = INTERVAL (cal_interval * (cal_repeat - 1)) DAY + cal_date) as block_end, t.topic_title" .$mini_cal_auth_read_sql,
                        'FROM'		=> array(
                            TOPIC_CALENDAR_TABLE	=> 'c',
                            TOPICS_TABLE		=> 't',
                            FORUMS_TABLE		=> 'f'
                        ),
                        'WHERE'		=> 	"c.forum_id = f.forum_id 
                            AND c.topic_id = t.topic_id
                            $mini_cal_auth_sql
                            AND f.enable_events > 0 
                            AND '$current_isodate' >= cal_date
                            AND
                            (
                                cal_repeat = 0 
                                OR
                                (
                                    cal_repeat > 0 
                                    AND
                                    (
                                        (cal_interval_units = 'DAY' AND ('$current_isodate' <= INTERVAL (cal_interval * (cal_repeat - 1)) DAY + cal_date))
                                        OR (cal_interval_units = 'WEEK' AND ('$current_isodate' <= INTERVAL ((cal_interval * (cal_repeat - 1)) * 7) DAY + cal_date))
                                        OR (cal_interval_units = 'MONTH' AND ('$current_isodate' <= INTERVAL (cal_interval * (cal_repeat - 1)) MONTH + cal_date))
                                        OR (cal_interval_units = 'YEAR' AND ('$current_isodate' <= INTERVAL (cal_interval * (cal_repeat - 1)) YEAR + cal_date))
                                    )
                                )
                            )
                            AND
                            (
                                (
                                    cal_interval_units = 'DAY' 
                                    AND (TO_DAYS('$current_isodate') - TO_DAYS(cal_date)) % cal_interval = 0
                                ) 
                                OR
                                (
                                    cal_interval_units = 'WEEK' 
                                    AND (TO_DAYS('$current_isodate') - TO_DAYS(cal_date)) % (7 * cal_interval) = 0
                                )
                                OR
                                (
                                    cal_interval_units = 'MONTH' 
                                    AND DAYOFMONTH(cal_date) = DAYOFMONTH('$current_isodate') 
                                    AND PERIOD_DIFF(DATE_FORMAT('$current_isodate', '%Y%m'), DATE_FORMAT(cal_date, '%Y%m')) % cal_interval = 0
                                )
                                OR 
                                (
                                    cal_interval_units = 'YEAR' 
                                    AND DATE_FORMAT(cal_date, '%m%d') = DATE_FORMAT('$current_isodate', '%m%d') 
                                    AND (YEAR('$current_isodate') - YEAR(cal_date)) % cal_interval = 0
                                )
                            )",
                        'ORDER_BY'	=> 'cal_interval_units ASC, cal_date ASC, cal_repeat DESC'
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
                        $mini_cal_day = '<a href="' . append_sid("{$this->root_path}search.$phpEx", "search_id=mini_cal_events&amp;d=" . $d_mini_cal_today) . '" alt="' . $title . '" title="' . $title . '">' . ( $mini_cal_day ) . '</a>';
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
                    $mini_cal_day = ( $mini_cal_today >= $d_mini_cal_today ) ? '<a href="' . append_sid("{$this->root_path}search.$phpEx", "search_id=mini_cal&amp;d=" . $nix_mini_cal_today) . '" alt="' . $title . '" title="' . $title . '">' . ( $mini_cal_day ) . '</a>' : '<abbr title="' . $title . '">' . ( $mini_cal_day ) . '</abbr></a>';
                } else
                {
                    $mini_cal_day = ( $mini_cal_today >= $d_mini_cal_today ) ? '<a href="' . append_sid("{$this->root_path}search.$phpEx", "search_id=mini_cal&amp;d=" . $nix_mini_cal_today) . '">' . ( $mini_cal_day ) . '</a>' : $mini_cal_day;
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
        $days_ahead_sql = (MINI_CAL_DAYS_AHEAD > 0) ? " AND (c.cal_date <= DATE_ADD(CURDATE(), INTERVAL " . MINI_CAL_DAYS_AHEAD . " DAY)) " : '';

        // get the events 
        $sql_array = array(
                'SELECT'	=> 'c.topic_id, c.cal_date, c.forum_id, MONTH(c.cal_date) as cal_month, DAYOFWEEK(c.cal_date) as cal_weekday, DAYOFMONTH(c.cal_date) as cal_monthday, YEAR(c.cal_date) as cal_year, HOUR(c.cal_date) as cal_hour, MINUTE(c.cal_date) as cal_min, SECOND(c.cal_date) as cal_sec, t.topic_title' . $mini_cal_auth_read_sql,
                'FROM'		=> array( 
                    TOPIC_CALENDAR_TABLE	=> 'c',
                    TOPICS_TABLE		=> 't',
                    FORUMS_TABLE		=> 'f'
                ),
                'WHERE'		=> 'c.forum_id = f.forum_id 
                    AND c.topic_id = t.topic_id 
                    AND (c.cal_date >= CURDATE())' . $days_ahead_sql . $mini_cal_auth_sql,
                'ORDER_BY'	=> 'c.cal_date ASC'
        );
        $sql = $this->db->sql_build_query('SELECT', $sql_array);
        $this->db->sql_return_on_error(true);
        $result = $this->db->sql_query_limit($sql, MINI_CAL_LIMIT);
        $this->db->sql_return_on_error(false);
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
                $cal_date_replace = array( 
                    $this->user->lang['MINICAL']['DAY']['SHORT'][$row['cal_weekday'] - 1], 
                    $this->user->lang['MINICAL']['MONTH']['SHORT'][$row['cal_month'] - 1], 
                    $row['cal_month'], 
                    ((strlen($row['cal_monthday']) < 2) ? '0' : '') . $row['cal_monthday'], 
                    $row['cal_monthday'], 
                    ((strlen($row['cal_month']) < 2) ? '0' : '') . $row['cal_month'], 
                    substr($row['cal_year'], -2),
                    $row['cal_year'],
                    ((strlen($row['cal_hour']) < 2) ? '0' : '') . $row['cal_hour'],
                    $row['cal_hour'],
                    ((strlen($row['cal_hour']) < 2) ? '0' : '') . (($row['cal_hour'] > 12) ? $row['cal_hour'] - 12 : $row['cal_hour']),
                    ($row['cal_hour'] > 12) ? $row['cal_hour'] - 12 : $row['cal_hour'],
                    $row['cal_min'],
                    $row['cal_sec'],
                    ($row['cal_hour'] < 12) ? 'AM' : 'PM'
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
                $prev_cal_url = append_sid("{$this->root_path}viewtopic.$phpEx", "t={$row['topic_id']}");
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
        $prev_month = append_sid("{$this->root_path}index.$phpEx", 'month=' . ($month - 1));
        $next_month = append_sid("{$this->root_path}index.$phpEx", 'month=' . ($month + 1));
        $this->template->assign_vars(array(
                'U_MINI_CAL_CALENDAR' => append_sid("{$this->root_path}topiccalendar.$phpEx"),
                'U_PREV_MONTH' => $prev_month,
                'U_NEXT_MONTH' => $next_month,
                )
        );
    }
}
