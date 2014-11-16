<?php
/**
*
* @package phpBB Extension - Alf007 Topic Calendar
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace alf007\topiccalendar\includes;

class functions_topic_calendar
{
    /**
     * Check if this is an event forum
     *
     * Query the forums table and determine if the forum requested
     * allows the handling of calendar events.  The results are cache
     * as a static variable.
     *
     * @param  int $forum_id
     *
     * @access public
     * @return boolean
     */
    public function forum_check($forum_id) 
    {
            global $db;

            // use static variable for caching results
            static $events_forums;

            // if we are not given a forum_id then return false
            if (is_null($forum_id) || $forum_id === '')
            {
                    return false;
            }

            if (!isset($events_forums))
            {
                    $sql = 'SELECT forum_id, enable_events
                                    FROM ' . FORUMS_TABLE; 
                    $result = $db->sql_query($sql);
                    while ($row = $db->sql_fetchrow($result))
                    {
                            $events_forums[$row['forum_id']] = $row['enable_events'];
                    }
                    $db->sql_freeresult($result);
            }

            return $forum_id < count($events_forums) && $events_forums[$forum_id] ? $events_forums[$forum_id] : false;
    }

    /**
     * Enter/delete/modifies the event in the topiccalendar table
     *
     * Depending on whether the user chooses new topic or edit post, we
     * make a modification on the topiccalendar table to insert or update the event
     *
     * @param  string $mode whether we are editing or posting new topic
     * @param  int	$forum_id sql id of the forum
     * @param  int	$topic_id sql id of the topic
     * @param  int	$post_id sql id of the post
     *
     * @access private
     * @return void
     */
    public function submit_event($mode, $forum_id, $topic_id, $post_id)
    {
            global $db, $user;

            // Do nothing for a reply/quote
            if ($mode == 'reply' || $mode == 'quote')
            {
                    return;
            }

            // setup defaults
            $cal_isodate = date2iso(request_var('cal_date', $user->lang['NO_DATE']));
            $cal_repeat = 1;

            // get the ending date and interval information
            $cal_interval_date = request_var('cal_interval_date', false);
            $cal_date_end = request_var('cal_date_end', $user->lang['NO_DATE']);
            $cal_repeat_always = request_var('cal_repeat_always', false);

            $cal_interval = request_var('cal_interval', 1);
            $cal_interval_units = request_var('cal_interval_units', 'DAY');
            if ($cal_interval_units != 'DAY' && $cal_interval_units != 'WEEK' && $cal_interval_units != 'MONTH' && $cal_interval_units != 'YEAR')
            {
                    trigger_error('Invalid interval unit!');
            }

            if ($cal_isodate && $cal_interval_date && ($cal_date_end != $user->lang['NO_DATE'] || $cal_repeat_always))
            {
                    // coax the interval to a postive integer
                    $cal_interval = ($tmp_interval = abs($cal_interval)) ? $tmp_interval : 1;
                    if ($cal_repeat_always)
                    {
                            $cal_repeat = 0;
                    } else if ($cal_date_end != $user->lang['NO_DATE'])
                    {
                            $cal_isodate_end = date2iso($cal_date_end);
                            // make sure the end is not before the beginning, if so swap
                            if ($cal_isodate_end < $cal_isodate) {
                                    $tmp = $cal_isodate_end;
                                    $cal_isodate_end = $cal_isodate;
                                    $cal_isodate = $tmp;
                            }

                            // get the number of repeats between the two dates of the interval
                            $sql = 'SELECT ';
                            if ($cal_interval_units == 'DAY')
                            {
                                    $sql .= "FLOOR((TO_DAYS('$cal_isodate_end') - TO_DAYS('$cal_isodate'))/$cal_interval + 1)";
                            } else if ($cal_interval_units == 'WEEK')
                            {
                                    $sql .= "FLOOR((TO_DAYS('$cal_isodate_end') - TO_DAYS('$cal_isodate'))/(7 * $cal_interval) + 1)";
                            } else if ($cal_interval_units == 'MONTH')
                            {
                                    $sql .= "FLOOR(PERIOD_DIFF(DATE_FORMAT('$cal_isodate_end', '%Y%m'), DATE_FORMAT('$cal_isodate', '%Y%m'))/$cal_interval + 1)";
                            } else if ($cal_interval_units == 'YEAR')
                            {
                                    $sql .= "FLOOR((YEAR('$cal_isodate_end') - YEAR('$cal_isodate'))/$cal_interval + 1)";
                            }

                            $sql .= ' as cal_repeat';
                            $result = $db->sql_query($sql);
                            if (!$row = $db->sql_fetchrow($result))
                            {
                                    trigger_error('TopicCalendar_NoRepeatMult');
                            } else if (empty($row['cal_repeat']))
                            {
                                    trigger_error('TopicCalendar_NoRepeatMult');
                            }
                            $db->sql_freeresult($result);
                            $cal_repeat = $row['cal_repeat'];
                    }
            }

            // if this is a new topic and we can post a date to it (do we have to check this) and
            // we have specified a date, then go ahead and enter it
            if ($mode == 'post' && $cal_isodate && forum_check($forum_id))
            {
                    $sql = 'INSERT INTO ' . TOPIC_CALENDAR_TABLE . ' ' . $db->sql_build_array('INSERT', array(
                                       'topic_id'			=> (int)$topic_id,
                                       'cal_date'			=> (string)$cal_isodate,
                                       'cal_interval'		=> (int)$cal_interval,
                                       'cal_interval_units'	=> (string)$cal_interval_units,
                                       'cal_repeat'			=> (int)$cal_repeat,
                                       'forum_id'			=> (int)$forum_id,
                                    ));

                    $db->sql_query($sql);
            } 
            // if we are editing a post, we either update, insert or delete, depending on if date is set
            // and whether or not a date was specified, so we have to check all that stuff
            else if ($mode == 'edit' && forum_check($forum_id))
            {
                    // check if not allowed to edit the calendar event since this is not the first post
                    if (!first_post($topic_id, $post_id))
                    {
                            return;
                    }

                    $sql = 'SELECT topic_id ' .
                                    'FROM ' . TOPIC_CALENDAR_TABLE . ' ' .
                                    'WHERE ' . $db->sql_build_array('SELECT', array(
                                            'topic_id' => (int)$topic_id
                                    ));

                    $result = $db->sql_query($sql);

                    // if we have an event in the calendar for this topic and this is the first post,
                    // then we will affect the entry depending on if a date was provided
                    if ($db->sql_fetchrow($result))
                    {
                            // we took away the calendar date (no start date, no date)
                            if (!$cal_isodate)
                            {
                                    $sql = 'DELETE FROM ' . TOPIC_CALENDAR_TABLE . ' ' .
                                                            'WHERE ' . $db->sql_build_array('SELECT', array(
                                                                    'topic_id' => (int)$topic_id
                                                            ));
                                    $db->sql_query($sql);
                            } else
                            {
                                    $sql = 'UPDATE ' . TOPIC_CALENDAR_TABLE . ' SET ' . $db->sql_build_array('UPDATE', array(
                                                            'cal_date' 				=> (string)$cal_isodate,
                                                            'cal_interval'			=> (int)$cal_interval,
                                                            'cal_interval_units'	=> (string)$cal_interval_units,
                                                            'cal_repeat'			=> (int)$cal_repeat
                                                    )) . ' WHERE ' . $db->sql_build_array('SELECT', array(
                                                            'topic_id'	=> (int)$topic_id
                                                    ));
                                    $db->sql_query($sql);
                            }
                    }
                    // insert the new entry if a date was provided
                    else if ($cal_isodate)
                    {
                            $sql = 'INSERT INTO ' . TOPIC_CALENDAR_TABLE . ' ' . $db->sql_build_array('INSERT', array(
                                       'topic_id'			=> (int)$topic_id,
                                       'cal_date'			=> (string)$cal_isodate,
                                       'cal_interval'		=> (int)$cal_interval,
                                       'cal_interval_units'	=> (string)$cal_interval_units,
                                       'cal_repeat'			=> (int)$cal_repeat,
                                       'forum_id'			=> (int)$forum_id
                                    ));

                            $db->sql_query($sql);
                    }
                    $db->sql_freeresult($result);
            }
    }

    public function move_event($new_forum_id, $topic_id, $leave_shadow = false)
    {
            global $db;

            // if we are not leaving a shadow and the new forum doesn't do events,
            // then delete to event and return
            if (!$leave_shadow && !forum_check($new_forum_id))
            {
                    if (!$leave_shadow)
                    {
    //			delete_event($topic_id, null, false);
                            return;
                    }
            } else
            {
                    $sql = 'UPDATE ' . TOPIC_CALENDAR_TABLE . ' SET ' . $db->sql_build_array('UPDATE', array(
                                            'forum_id' => (int)$new_forum_id
                                    )) . ' WHERE ' . $db->sql_build_array('SELECT', array(
                                            'topic_id' => (int)$topic_id
                                    ));
                    $db->sql_query($sql);
            }
    }

    /**
     * Generate the event date info for topic header view
     *
     * This public function will generate a string for the first post in a topic
     * that declares an event date, but only if the event date has a reference
     * to a forum which allows events to be used.  In the case of a reoccuring/block date,
     * the display will be such that it explains this attribute.
     *
     * @param int  $topic_id identifier of the topic
     * @param int  $post_id identifier of the post, used to determine if this is the leading post (or 0 for forum view)
     *
     * @access public
     * @return string body message
     */
    public function show_event($topic_id, $post_id) 
    {
            global $db, $user;

            $format = $user->lang['DATE_SQL_FORMAT'];
            $info = '';
            $sql_where = array(
                    't.topic_id' => (int)$topic_id,
                    'c.topic_id' => (int)$topic_id
            );
            if ($post_id != 0)
            {
                    $sql_where['t.topic_first_post_id'] = (int)$post_id;
            }
            $sql_array = array(
                    'SELECT'	=> 'c.cal_date, DATE_FORMAT(c.cal_date, "' . $format . '") as cal_date_f, c.cal_interval, c.cal_interval_units, c.cal_repeat, DATE_FORMAT(c.cal_date + INTERVAL (c.cal_repeat - 1) DAY, "%M %D, %Y") as cal_date_end ',
                    'FROM'		=> array(
                            TOPIC_CALENDAR_TABLE	=> 'c',
                            TOPICS_TABLE		=> 't',
                            FORUMS_TABLE		=> 'f'
                    ),
                    'WHERE'		=> $db->sql_build_array('SELECT', $sql_where) . ' 
                            AND c.forum_id = f.forum_id AND f.enable_events > 0',
            );
            $sql = $db->sql_build_query('SELECT', $sql_array);
            $result = $db->sql_query($sql);
            // we found a calendar event, so let's append it
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            if ($row)
            {
                    $cal_date = $row['cal_date'];
                    $cal_date_f = $row['cal_date_f'];
                    $cal_interval = $row['cal_interval'];
                    $cal_repeat = $row['cal_repeat'];
                    $event['message'] = '<i>' . translate_date($cal_date_f) . '</i>';
                    // if this is more than a single date event, then dig deeper
                    if ($row['cal_repeat'] != 1)
                    {
                            // if this is a repeating or block event (repeat > 1), show end date!
                            if ($row['cal_repeat'] > 1)
                            {
                                    // have to pull a little tweak on WEEK since the interval doesn't exist in SQL
                                    $units = $row['cal_interval_units'] == 'WEEK' ? '* 7 DAY' : $row['cal_interval_units'];
                                    // we have to do another query here because SQL does not allow interval units to be a field variable
                                    $sql2 = 'SELECT DATE_FORMAT(\'' . $cal_date . '\' + INTERVAL ' . $cal_interval . ' * (' . $cal_repeat . ' - 1) ' . $units . ', "' . $format . '") as cal_date_end ';
                                    $result2 = $db->sql_query($sql2);
                                    $row2 = $db->sql_fetchrow($result2);
                                    $db->sql_freeresult($result2);
                                    $cal_date_end = $row2['cal_date_end'];
                                    $event['message'] .= ' - <i>' . translate_date($cal_date_end) . '</i>';
                            }

                            // we have a non-continuous interval or a 'forever' repeating event, so we will explain it
                            if (!($row['cal_interval_units'] == 'DAY' && $cal_interval == 1 && $cal_repeat != 0))
                            {
                                    $units = ($row['cal_interval'] == 1) ? $user->lang['INTERVAL'][strtoupper($row['cal_interval_units'])] : $user->lang['INTERVAL'][strtoupper($row['cal_interval_units']) . 'S'];
                                    $repeat = $row['cal_repeat'] ? $row['cal_repeat'] . 'x' : $user->lang['CAL_REPEAT_FOREVER'];
                                    $event['message'] .= '<br /><b>' .  $user->lang['SEL_INTERVAL'] .  '</b> ' .  $row['cal_interval'] .  ' ' .  $units .  '<br /><b>' .  $user->lang['CALENDAR_REPEAT'] .  '</b> ' .  $repeat;
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
     * @param  string $mode
     * @param  int $topic_id
     * @param  int $post_id
     * @param  int $forum_id
     * @param  object $template
     *
     * @access private
     * @return void
     */
    public function generate_entry($mode, $forum_id, $topic_id, $post_id, &$template) 
    {
            global $db, $user;

            // if this is a reply/quote or not an event forum or if we are editing and it is not the first post, just return
            if ($mode == 'reply' || $mode == 'quote' || !$this->forum_check($forum_id) || ($mode == 'edit' && !first_post($topic_id, $post_id)))
            {
                    return;
            }

            // set up defaults first in case we don't find any event information (such as a new post)
            $cal_interval = 1;
            $cal_interval_units = 'DAY';
            $cal_repeat_always = '';

            // we only want to get the date if this is an edit fresh, and not preview
            $cal_date = request_var('cal_date', $user->lang['NO_DATE']);
            $cal_date_end = request_var('cal_date_end', $user->lang['NO_DATE']);
            // okay we are starting an edit on the post, let's get the required info from the tables
            if (($cal_date == $user->lang['NO_DATE'] || $cal_date_end == $user->lang['NO_DATE']) && $mode == 'edit')
            {
                    // setup the format used for the form
                    $format = str_replace(array('m', 'd', 'y'), array('%m', '%d', '%Y'), strtolower($user->lang['DATE_INPUT_FORMAT']));

                    // grab the event info for this topic
                    $sql = 'SELECT DATE_FORMAT(cal_date, "' . $format . '") as cal_date, cal_interval, cal_interval_units, cal_repeat ' .
                                    'FROM ' . TOPIC_CALENDAR_TABLE . ' ' .
                                    'WHERE ' . $db->sql_build_array('SELECT', array(
                                            'topic_id' => (int)$topic_id
                                    ));
                    $result = $db->sql_query($sql);
                    $row = $db->sql_fetchrow($result);
                    $db->sql_freeresult($result);
                    if ($row)
                    {
                            $cal_interval_units = $row['cal_interval_units'];
                            $cal_interval = $row['cal_interval'];
                            $cal_date = $row['cal_date'];
                            // only if the repeat is more than 1 day (meaning it actually repeats) do we get the end date
                            // else it is just a single event
                            if ($row['cal_repeat'] > 1)
                            {
                                    // have to pull a little tweak on WEEK since the interval doesn't exist in SQL
                                    $units = $row['cal_interval_units'] == 'WEEK' ? '* 7 DAY' : $row['cal_interval_units'];

                                    // we have to do another query here because SQL does not allow interval units to be a field variable
                                    $sql2 = "SELECT DATE_FORMAT(cal_date + INTERVAL cal_interval * (cal_repeat - 1) $units, \"$format\") as cal_date_end " .
                                                    'FROM ' . TOPIC_CALENDAR_TABLE . ' ' .
                                                    'WHERE ' . $db->sql_build_array('SELECT', array(
                                                            'topic_id' => (int)$topic_id
                                                    ));
                                    $result2 = $db->sql_query($sql2);
                                    $row2 = $db->sql_fetchrow($result2);
                                    $db->sql_freeresult($result2);
                                    $cal_date_end = $row2['cal_date_end'];
                            } else if ($row['cal_repeat'] == 1)
                            {
                                    $cal_interval = 1;
                                    $cal_interval_units = 'DAY';
                                    $cal_date_end = $user->lang['NO_DATE'];
                            } else
                            {
                                    $cal_repeat_always = 'checked="checked"';
                                    $cal_date_end = $user->lang['NO_DATE'];
                            }
                    }
            }

            $interval_units = array(
                    'DAY'   => pluralize($user->lang['INTERVAL']['DAY'], $user->lang['INTERVAL']['DAYS']),
                    'WEEK'  => pluralize($user->lang['INTERVAL']['WEEK'], $user->lang['INTERVAL']['WEEKS']),
                    'MONTH' => pluralize($user->lang['INTERVAL']['MONTH'], $user->lang['INTERVAL']['MONTHS']),
                    'YEAR'  => pluralize($user->lang['INTERVAL']['YEAR'], $user->lang['INTERVAL']['YEARS']),
            );
            $interval_unit_options = '';
            foreach($interval_units as $unit => $name)
            {
                    $interval_unit_options .= '<option value="' . $unit . '"';
                    if ($cal_interval_units == $unit)
                    {
                            $interval_unit_options .= ' selected="selected"';
                    }
                    $interval_unit_options .= '>' . $name . '</option>';
            }

            $template->assign_vars(array(
                    'S_TOPIC_CALENDAR'				=> true,
                    'CAL_DATE'					=> $cal_date,
                    'CAL_DATE_END'				=> $cal_date_end,
                    'CAL_ADVANCED_FORM'			=> ($cal_date_end != $user->lang['NO_DATE'] || $cal_repeat_always) ? '' : 'none',
                    'CAL_ADVANCED_FORM_ON'		=> ($cal_date_end != $user->lang['NO_DATE'] || $cal_repeat_always) ? 'checked="checked"' : '',
                    'CAL_NO_DATE'				=> $user->lang['NO_DATE'],
                    'CAL_INTERVAL'				=> $cal_interval,
                    'CAL_INTERVAL_UNIT_OPTIONS'	=> $interval_unit_options,
                    'CAL_REPEAT_ALWAYS'			=> $cal_repeat_always,
            ));
            base_calendar();
    }

    public function base_calendar()
    {
            global $user, $template, $phpbb_root_path;

            // generate a list of months for the current language so javascript can pass it up to the calendar
            $monthList = array();
            foreach (array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December') as $month)
            {
                    $monthList[] = '\'' . $user->lang['datetime'][$month] . '\'';
            }
            $monthList = implode(',', $monthList);

            //	Same for week-days
            $weekdays = '';
            $weekdays_long = '';
            for ($i = 0; $i < 7; $i++)
            {
                    if ($weekdays != '')
                    {
                            $weekdays .= ', '; 
                    }
                    $weekdays .= '"' . $user->lang['MINICAL']['DAY']['SHORT'][$i] . '"';
                    if ($weekdays_long != '')
                    {
                            $weekdays_long .= ', '; 
                    }
                    $weekdays_long .= '"' . $user->lang['MINICAL']['DAY']['LONG'][$i] . '"';
            } 

            $template->assign_vars(array(
                    'JS_PATH'					=> $template->root,
                    'CAL_DATE_FORMAT'			=> $user->lang['DATE_INPUT_FORMAT'],
                    'CAL_MONTH_LIST'			=> $monthList,
                    'CAL_WEEKDAY_LIST'			=> $weekdays_long,
                    'CAL_WEEKDAY_SHORT_LIST'	=> $weekdays,
            ));
    }

    /**
     * Do a conversion from 01/01/2000 to the unix timestamp
     *
     * Convert from the date used in the form to a unix timestamp, but
     * do it based on the user preference for date formats
     *
     * @param  string date (all numbers < 10 must be '0' padded at this point)
     *
     * @access public
     * @return int unix timestamp
     */
    public function date2iso($in_stringDate) 
    {
            global $user;

            if ($in_stringDate == '??????' || $in_stringDate == $user->lang['NO_DATE'])
            {
                    return false;
            }

            // find the first punctuation character, which will be our delimiter
            $tmp_format = str_replace(array('y', 'm', 'd'), array('yyyy', 'mm', 'dd'), strtolower($user->lang['DATE_INPUT_FORMAT']));
            $tmp_yOffset = strpos($tmp_format, 'y');
            $tmp_mOffset = strpos($tmp_format, 'm');
            $tmp_dOffset = strpos($tmp_format, 'd');

            // remap the parts to variables, at this point we assume it is coming through the wire 0 padded
            // Enforce user input checking 
            $year  = intval(substr($in_stringDate, $tmp_yOffset, 4));
            $month = intval(substr($in_stringDate, $tmp_mOffset, 2));
            $day   = intval(substr($in_stringDate, $tmp_dOffset, 2));

            if ($year < 2000 or $month < 1 or $month > 12 or $day < 1 or $day > 31)
            {
                    trigger_error('TopicCalendar_CantCheckDate');
            }

            if ($month < 10)
            {
                    $month = '0' . $month;
            }
            if ($day < 10)
            {
                    $day = '0' . $day;
            }
            return $year . '-' . $month . '-' . $day . ' 00:00:00';
    }

    /**
     * Determine if this post is the first post in a topic
     *
     * Simply query the topics table and determine if this post is
     * the first post in the topic...important since calendar events
     * can only be attached to the first post
     *
     * @param  int topic_id
     * @param  int post_id
     *
     * @access public
     * @return boolean is first post
     */
    public function first_post($topic_id, $post_id)
    {
            global $db;

            $sql = 'SELECT ' . $db->sql_build_array('SELECT', array(
                                    'topic_first_post_id' => (int)$post_id
                                    )) . ' as first_post ' .
                            'FROM ' . TOPICS_TABLE . ' ' .
                            'WHERE ' . $db->sql_build_array('SELECT', array(
                               'topic_id' => (int)$topic_id
                            )); 
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            // if this is not the first post, then get out of here
            if (!$row['first_post'])
            {
                    return false;
            }

            return true;
    }

    // if I were to add timezone stuff it would be here
    public function translate_date($in_date)
    {
            global $user, $config;

            return $config['default_lang'] == 'english' ? $in_date : strtr($in_date, $user->lang['datetime']);
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
}
?>