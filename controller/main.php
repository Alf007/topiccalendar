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
use alf007\topiccalendar\includes\days_info;

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

	/** @var \phpbb\request\request_interface */
	protected $request;
	
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

	public $functions_topiccal;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth				   $auth
	* @param \phpbb\config\config			   $config
	* @param \phpbb\db\driver\driver_interface	$db
	* @param \phpbb\content_visibility			$content_visibility
	* @param \phpbb\controller\helper		   $helper
	* @param \phpbb\request\request_interface   $request		Request variables
	* @param \phpbb\template\template		   $template
	* @param \phpbb\user						$user
	* @param string							 $root_path	  phpbb root path
	* @param string							 $phpEx		  php file extension
	* @param string 							$table_config  extension config table name
	* @param string 							$table_events  extension events table name
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\content_visibility $content_visibility, \phpbb\controller\helper $helper, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, $root_path, $phpEx, $table_config, $table_events)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->content_visibility = $content_visibility;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->phpEx = $phpEx;
		$this->topic_calendar_table_config = $table_config;
		$this->topic_calendar_table_events = $table_events;
		$this->functions_topiccal = new functions_topic_calendar($config, $db, $request, $template, $user, $table_config, $table_events);
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

		$day_infos = new days_info($this->auth, $this->db, $this->root_path, $this->phpEx, $this->topic_calendar_table_config, $this->topic_calendar_table_events, $this->content_visibility);
		
		$cal_days = $day_infos->get_days_info($this->user, $year, $month);

		$nextyear = $day_infos->monthView['year'];
		$nextmonth = $day_infos->monthView['month'] + 1;
		if ($nextmonth > 12)
		{
			$nextmonth = 1;
			$nextyear++; 
		}

		$previousyear = $day_infos->monthView['year'];
		$previousmonth = $day_infos->monthView['month'] - 1; 
		if ($previousmonth < 1)
		{
			$previousmonth = 12;
			$previousyear--;
		}

		// prepare images and links for month navigation
		$url_prev_month = $this->helper->route('alf007_topiccalendar_controller', array('month' => $previousmonth, 'year' => $previousyear));
		$url_next_month = $this->helper->route('alf007_topiccalendar_controller', array('month' => $nextmonth, 'year' => $nextyear));

		$url_prev_year = $this->helper->route('alf007_topiccalendar_controller', array('month' => $day_infos->monthView['month'], 'year' => $day_infos->monthView['year'] - 1));
		$url_next_year = $this->helper->route('alf007_topiccalendar_controller', array('month' => $day_infos->monthView['month'], 'year' => $day_infos->monthView['year'] + 1));

		$this->template->assign_vars(array(
			'U_PREV_MONTH' => $url_prev_month,
			'U_NEXT_MONTH' => $url_next_month,
			'U_PREV_YEAR'=> $url_prev_year,
			'U_NEXT_YEAR'=> $url_next_year,
			)
		);

		$this->functions_topiccal->apply_weekdays();
		$this->functions_topiccal->apply_links(intval($day_infos->monthView['month']), intval($day_infos->monthView['year']));

		$start_day = date('w', mktime(0, 0, 0, $day_infos->monthView['month'], 1, $day_infos->monthView['year'])) - (int)$this->user->lang['WEEKDAY_START'];
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
						'DAY_BLOCK_BEGIN'	=> $cal_days[$day]['events'][$e]['block_begin'],
						'DAY_BLOCK_END'		=> $cal_days[$day]['events'][$e]['block_end'],
						'DAY_BLOCK'			=> $cal_days[$day]['events'][$e]['in_block'],
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
}
