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
    
    protected $topic_calendar_table;
    
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
    * @param string $ext_table  extension table name
    */
    public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\content_visibility $content_visibility, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, $root_path, $phpEx, $ext_table)
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
        $this->topic_calendar_table = $ext_table;
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
                'SELECT'	=> "c.*, t.*, pt.post_text, pt.bbcode_uid, pt.bbcode_bitfield, f.forum_name",
                'FROM'		=> array(
                    $this->topic_calendar_table	=> 'c',
                    TOPICS_TABLE		=> 't',
                    FORUMS_TABLE		=> 'f',
                    POSTS_TABLE			=> 'pt'
                ),
                'WHERE'		=> 	"c.forum_id = f.forum_id 
                    AND c.topic_id = t.topic_id 
                    AND f.enable_events > 0 
                    AND pt.post_id = t.topic_first_post_id
                    AND '$current_isodate' = cal_date",
                'ORDER_BY'	=> 'cal_date ASC'
            );
            $sql = $this->db->sql_build_query('SELECT', $sql_array);
            $this->db->sql_return_on_error(true);
            $result = $this->db->sql_query($sql);
            $this->db->sql_return_on_error(false);
            if (!$result)
            {
                trigger_error(implode(', ', $this->db->get_sql_error_returned()) . '<br/>' . implode(', ', $sql_array));//'TOPIC_CALENDAR_CantQueryDate');
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
}
