<?php
/**
*
* @package phpBB Extension - Alf007 Topic Calendar
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace alf007\topiccalendar\controller;

class main
{
    /** @var \phpbb\auth\auth */
    protected $auth;

    /* @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

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
    */
    public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, $root_path, $phpEx)
    {
        $this->auth = $auth;
        $this->config = $config;
        $this->db = $db;
        $this->helper = $helper;
        $this->template = $template;
        $this->user = $user;
        $this->root_path = $root_path;
        $this->phpEx = $phpEx;
    }

    /**
    * Controller for route /topiccalendar/{month, year}
    *
    * @param int		$month
    * @param int		$year
    * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
    */
    public function handle($month = 0, $year = 0)
    {
        $this->user->add_lang_ext('alf007/topiccalendar', 'controller');
        
        // Define the information for the current date
        list($today['year'], $today['month'], $today['day']) = explode('-', $user->format_date(time(), 'Y-m-d'));

        if ($month != 0 && $year != 0)
        {
            $view_isodate = sprintf('%04d', $year) . '-' . sprintf('%02d', $month) . '-01 00:00:00';
        } else
        {   // get the first day of the month as an isodate
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
        $url_prev_month = append_sid($this->root_path . $this_file, "month=$previousmonth&amp;year=$previousyear");
        $url_next_month = append_sid($this->root_path . $this_file, "month=$nextmonth&amp;year=$nextyear");

        $url_prev_year = append_sid($this->root_path . $this_file, 'month=' . $monthView['month'] . '&amp;year=' . ($monthView['year'] - 1));
        $url_next_year = append_sid($this->root_path . $this_file, 'month=' . $monthView['month'] . '&amp;year=' . ($monthView['year'] + 1));

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
        $ucbirthdayrow = array();
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
            $ucbirthdayrow[] = array(
                'username'	=> $row['username'], 
                'check_date'	=> $monthView['year'] . '-' . sprintf('%02d', substr($row['user_birthday'], 3, 2)) . '-' . sprintf('%02d', substr($row['user_birthday'], 0, 2)), 
                'birthday'	=> $row['user_birthday'], 
                'id'		=> $row['user_id'], 
                'show_age'	=> (isset($row['user_show_age'])) ? $row['user_show_age'] : 0, 
                'colour'	=> $row['user_colour']);
        }
        $this->db->sql_freeresult($result);
        sort($ucbirthdayrow);

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
            $current_isodate .= ' 00:00:00';
            $sql_array = array(
                'SELECT'	=> "c.*, t.topic_title, pt.post_text, pt.bbcode_uid, pt.bbcode_bitfield, t.topic_views, t.topic_replies, f.forum_name, (cal_interval_units = 'DAY' && cal_interval = 1 && '$current_isodate' = INTERVAL (cal_interval * (cal_repeat - 1)) DAY + cal_date) as block_end",
                'FROM'		=> array(
                    TOPIC_CALENDAR_TABLE	=> 'c',
                    TOPICS_TABLE		=> 't',
                    FORUMS_TABLE		=> 'f',
                    POSTS_TABLE			=> 'pt'
                ),
                'WHERE'		=> 	"c.forum_id = f.forum_id 
                    AND c.topic_id = t.topic_id 
                    AND f.enable_events > 0 
                    AND pt.post_id = t.topic_first_post_id
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
            $this->db->sql_return_on_error(true);
            $result = $this->db->sql_query($sql);
            $this->db->sql_return_on_error(false);
            if (!$result)
            {
                trigger_error('TOPIC_CALENDAR_CantQueryDate');
            }

            $numEvents = 0;
            while ($topic = $this->db->sql_fetchrow($result))
            {
                $forum_id = $topic['forum_id'];
                $can_view = $auth->acl_get('f_list', $forum_id);
                $can_read = $can_view && $auth->acl_get('f_read', $forum_id); 
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
                        if (!class_exists('bbcode'))
                        {
                            include($this->root_path . 'includes/bbcode.' . $this->phpEx);
                        }
                        $bbcode = new bbcode($topic['bbcode_bitfield']);
                        $bbcode->bbcode_second_pass($post_text, $topic['bbcode_uid'], $topic['bbcode_bitfield']);
                    }
                    $post_text = bbcode_nl2br($post_text);
                    $post_text = smiley_text($post_text);

                    // prepare the popup text, escaping quotes for javascript
                    $title_text = '<b>' . $this->user->lang['TOPIC'] . ':</b> ' . $topic['topic_title'] . '<br /><b>' . $this->user->lang['FORUM'] . ':</b> <i>' . $topic['forum_name'] . '</i><br /><b>' . $this->user->lang['VIEWS'] . ':</b> ' . $topic['topic_views'] . '<br /><b>' . $this->user->lang['REPLIES'] . ':</b> ' . $topic['topic_replies'];

                    // tack on the interval and repeat if this is a repeated event
                    if ($topic['cal_repeat'] != 1)
                    {
                        $title_text .= '<br /><b>' . $this->user->lang['SEL_INTERVAL'] . ':</b> ' . $topic['cal_interval'] . ' ' . (($topic['cal_interval'] == 1) ? $this->user->lang['INTERVAL'][strtoupper($topic['cal_interval_units'])] : $this->user->lang['INTERVAL'][strtoupper($topic['cal_interval_units']) . 'S']). '<br /><b>' . $this->user->lang['CALENDAR_REPEAT'] . ':</b> ' . ($topic['cal_repeat'] ? $topic['cal_repeat'] . 'x' : 'always');
                    }
                    $title_text .= '<br />' . bbcode_nl2br($post_text);
                    $title_text = str_replace('\'', '\\\'', htmlspecialchars($title_text));

                    // make the url for the topic
                    $topic_url = append_sid("{$this->root_path}viewtopic.$this->phpEx", "f=$forum_id&amp;t=$topic_id");
                    $topicCache[$topic_id] = array(
                        'first_post' => $title_text,
                        'topic_url'=> $topic_url,
                    );
                }

                // if we have a block event running (interval = 1 day) with this topic ID, then output our line
                if (isset($eventStack[$topic_id]))
                {
                    $first_date = '';
                    if ($topic['block_end'])
                    {
                        $block = 2;
                    } else
                    {
                        $block = 1;
                    }
                    // we have to determine if we are in the right row...which is the value
                    // in the eventStack array
                    $offset = $eventStack[$topic_id] - $numEvents;

                    // if this block was running in a position other than the first, we need
                    // to correct the offset so the line keeps running along the same axis..
                    // even though the upper block has stopped.We are going to get a 
                    // cascading effect from this until all overlapping block events stop
                    if ($offset > 0)
                    {
                        foreach (range(1, $offset) as $offsetCount)
                        {
                            $this->template->assign_block_vars('day_infos.date_event', array(
                                    'U_EVENT' => '<br />',
                                    'DAY_BLOCK_BEGIN' => false,
                                    'DAY_BLOCK_END' => false,
                                    'U_EVENT_END' => '', 
                                )
                            );
                        }
                    }
                    $topic_text = '';
                } else
                {	// this is either a single day event or the start of a new block event
                    $first_date = ' ';
                    $topic_text = strlen($topic['topic_title']) > 148 ? substr($topic['topic_title'], 0, 147) . '...' : $topic['topic_title'];
                    $block = 0;
                }
                $event = isset($topicCache[$topic_id]['first_post']) ? $topicCache[$topic_id]['first_post'] : '';
                $link = $can_read ? '<a href="' . $topicCache[$topic_id]['topic_url'] . "\">" : '<i>'; 
                $this->template->assign_block_vars('day_infos.date_event', array(
                        'POPUP'				=> $event,
                        'U_EVENT'			=> $first_date . $link . $topic_text,
                        'DAY_BLOCK_BEGIN'	=> ($block == 1),
                        'DAY_BLOCK_END'		=> ($block == 2),
                        'U_EVENT_END'		=> $can_read ? '</a>' : '</i>', 
                    )
                );
                $numEvents++;

                // Here I use a stack of sorts to keep track of block events which are
                // still running...I sort the block start dates by date, so the overlaps
                // will always appear in the same order...if a block ends while a lower block
                // continues, I keep a place holder so that the line continues along the same path

                // we are at the end of a block event
                if ($topic['block_end'])
                {
                    unset($eventStack[$topic_id]);
                }
                // we place an entry in the event stack, key as the topic, value as the row
                // number the event should fall in, for visual block events (interval = 1 day)
                else if (!isset($eventStack[$topic_id]) && $topic['cal_interval_units'] == 'DAY' && $topic['cal_interval'] == 1)
                {
                    $eventStack[$topic_id] = empty($eventStack) ? 0 : sizeof($eventStack);
                }
            }	//	while ($this->db->sql_fetchrow($result))
        }	// for ($day <= $monthView['numDays'])

        if (($day + $start_day) % 7 != 0)
        {
            $this->template->assign_var('END_DAY_LINK', true);
        }

        $this->template->assign_var('S_IN_TOPIC_CALENDAR3', true);

        return $this->helper->render('topiccalendar_body.html');
    }
}
