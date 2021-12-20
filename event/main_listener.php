<?php
/**
*
* @package phpBB Extension - Alf007 Topic Calendar
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace alf007\topiccalendar\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use alf007\topiccalendar\includes\functions_topic_calendar;
use alf007\topiccalendar\includes\days_info;

/**
* Event listener
*/
class main_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'							=> 'load_language_on_setup',
			'core.page_header_after'					=> 'add_page_header_after',
			'core.acp_manage_forums_initialise_data'	=> 'initialize_forums_data',
			'core.acp_manage_forums_request_data'		=> 'request_forums_data',
			'core.acp_manage_forums_display_form'		=> 'display_forums_form',
			'core.acp_manage_forums_move_content'		=> 'move_forums_content',
			'core.delete_forum_content_before_query'	=> 'delete_forum_content',
			'core.delete_posts_after'					=> 'delete_posts',
			'core.move_topics_before_query'				=> 'move_topics',
			'core.delete_topics_before_query'			=> 'delete_topics',
			'core.posting_modify_template_vars'			=> 'posting_modify_template',
			'core.posting_modify_submission_errors'		=> 'posting_modify_submission',
			'core.posting_modify_submit_post_after'		=> 'submit_post_after',
			'core.viewforum_modify_topicrow'			=> 'modify_topicrow',
			'core.viewtopic_modify_post_row'			=> 'modify_post_row',
			'core.index_modify_page_title'				=> 'modify_page_title',
			'core.search_modify_param_before'			=> 'modify_search_param',
		);
	}

	/** @var \phpbb\auth\auth */
	protected $auth;
	
	/* @var \phpbb\config\config */
	protected $config;
	
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

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
	* @param \phpbb\auth\auth					$auth
	* @param \phpbb\config\config				$config
	* @param \phpbb\db\driver\driver_interface  $db
	* @param \phpbb\controller\helper	$helper	Controller helper object
	* @param \phpbb\request\request_interface   $request		Request variables
	* @param \phpbb\template\template			$template	Template object
	* @param \phpbb\user						$user
	* @param string							 	$root_path	  phpbb root path
	* @param string								$phpEx	  php file extension
	* @param string								$table_config  extension config table name
	* @param string								$table_events  extension events table name
	* @access public
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, $root_path, $phpEx, $table_config, $table_events)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
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
	* Event to load language files and modify user data on every page
	*
	* @event core.user_setup
	* @var	array	user_data	Array with user's data row
	* @var	string	user_lang_name	Basename of the user's langauge
	* @var	string	user_date_format	User's date/time format
	* @var	string	user_timezone	User's timezone, should be one of
	*						http://www.php.net/manual/en/timezones.php
	* @var	mixed	lang_set	String or array of language files
	* @var	array	lang_set_ext	Array containing entries of format
	* 					array(
	* 						'ext_name' => (string) [extension name],
	* 						'lang_set' => (string|array) [language files],
	* 					)
	* 					For performance reasons, only load translations
	* 					that are absolutely needed globally using this
	* 					event. Use local events otherwise.
	* @var	mixed	style_id	Style we are going to display
	* @since 3.1.0-a1
	*/
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'alf007/topiccalendar',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	* Execute code and/or overwrite _common_ template variables after they have been assigned.
	*
	* @event core.page_header_after
	* @var	string	page_title			Page title
	* @var	bool	display_online_list		Do we display online users list
	* @var	string	item				Restrict online users to a certain
	*									session item, e.g. forum for
	*									session_forum_id
	* @var	int		item_id				Restrict online users to item id
	* @var	array		http_headers			HTTP headers that should be set by phpbb
	*
	* @since 3.1.0-b3
	*/
	public function add_page_header_after($event)
	{
		$this->user->add_lang_ext('alf007/topiccalendar', 'controller');
		
		$this->template->assign_vars(array(
			'U_TOPIC_CALENDAR'	=> $this->helper->route('alf007_topiccalendar_controller'),
		));
	}
	
	/**
	* Initialise data before we display the add/edit form
	*
	* @event core.acp_manage_forums_initialise_data
	* @var	string	action		Type of the action: add|edit
	* @var	bool	update		Do we display the form only
	*							or did the user press submit
	* @var	int		forum_id	When editing: the forum id,
	*							when creating: the parent forum id
	* @var	array	row			Array with current forum data
	*							empty when creating new forum
	* @var	array	forum_data	Array with new forum data
	* @var	string	parents_list	List of parent options
	* @since 3.1.0-a1
	*/
	public function initialize_forums_data($event)
	{
		if (!$event['update'] && $event['action'] == 'add')
		{
			$forum_data_ext = $event['forum_data'];
			$forum_data_ext['enable_events'] = false;
			$event['forum_data'] = $forum_data_ext;
		}
	}

	/**
	* Request forum data and operate on it (parse texts, etc.)
	*
	* @event core.acp_manage_forums_request_data
	* @var	string	action		Type of the action: add|edit
	* @var	array	forum_data	Array with new forum data
	* @since 3.1.0-a1
	*/
	public function request_forums_data($event)
	{
		$forum_data_ext = $event['forum_data'];
		$forum_data_ext['enable_events'] = $event['action'] == 'edit' ? $this->request->variable('enable_events', false) : false;
		$event['forum_data'] = $forum_data_ext;
	}
	
	/**
	* Modify forum template data before we display the form
	*
	* @event core.acp_manage_forums_display_form
	* @var	string	action		Type of the action: add|edit
	* @var	bool	update		Do we display the form only
	*							or did the user press submit
	* @var	int		forum_id	When editing: the forum id,
	*							when creating: the parent forum id
	* @var	array	row			Array with current forum data
	*							empty when creating new forum
	* @var	array	forum_data	Array with new forum data
	* @var	string	parents_list	List of parent options
	* @var	array	errors		Array of errors, if you add errors
	*					ensure to update the template variables
	*					S_ERROR and ERROR_MSG to display it
	* @var	array	template_data	Array with new forum data
	* @since 3.1.0-a1
	*/
	public function display_forums_form($event)
	{
		$template_data_ext = $event['template_data'];
		$template_data_ext['TC_EVENTS'] = $event['forum_data']['enable_events'] ? true : false;
		$event['template_data'] = $template_data_ext;
	}
	
	/**
	* Event when we move content from one forum to another
	*
	* @event core.acp_manage_forums_move_content
	* @var	int		from_id		If of the current parent forum
	* @var	int		to_id		If of the new parent forum
	* @var	bool	sync		Shall we sync the "to"-forum's data
	* @var	array	errors		Array of errors, should be strings and not
	*							language key. If this array is not empty,
	*							The content will not be moved.
	* @since 3.1.0-a1
	*/
	public function move_forums_content($event)
	{
		$from_id = $event['from_id'];
		$to_id = $event['to_id'];
		$sql = "UPDATE $this->topic_calendar_table_events
			SET forum_id = $to_id
			WHERE forum_id = $from_id";
		$this->db->sql_query($sql);
	}
	
	/**
	 * Perform additional actions before forum content deletion
	 *
	 * @event core.delete_forum_content_before_query
	 * @var	array	table_ary	Array of tables from which all rows will be deleted that hold the forum_id
	 * @var	int		forum_id	the forum id
	 * @var	array	topic_ids	Array of the topic ids from the forum to be deleted 	
	 * @var	array	post_counts	Array of counts of posts in the forum, by poster_id
	 * @since 3.1.5-RC1
	 */
	public function delete_forum_content($event)
	{
		//	Add our table to delete row for forum with content deleted
		$event['table_ary'][] = $this->topic_calendar_table_events;
	}
	
	/**
	 * Perform additional actions before topics move
	 *
	 * @event core.move_topics_before_query
	 * @var	array	table_ary	Array of tables from which forum_id will be updated for all rows that hold the moved topics
	 * @var	array	topic_ids	Array of the moved topic ids 
	 * @var	string	forum_id	The forum id from where the topics are moved
	 * @var	array	forum_ids	Array of the forums where the topics are moving (includes also forum_id)
	 * @var bool	auto_sync	Whether or not to perform auto sync
	 * @since 3.1.5-RC1
	 */
	public function move_topics($event)
	{
		//	Add our table to update row for topics moved
		$event['table_ary'][] = $this->topic_calendar_table_events;
	}
	
	/**
	* Perform additional actions after post(s) deletion
	*
	* @event core.delete_posts_after
	* @var	array	post_ids					Array with deleted posts' ids
	* @var	array	poster_ids					Array with deleted posts' author ids
	* @var	array	topic_ids					Array with deleted posts' topic ids
	* @var	array	forum_ids					Array with deleted posts' forum ids
	* @var	string	where_type					Variable containing posts deletion mode
	* @var	mixed	where_ids					Array or comma separated list of posts ids to delete
	* @var	array	delete_notifications_types	Array with notifications types to delete
	* @since 3.1.0-a4
	*/
	public function delete_posts($event)
	{
		$sql = "DELETE FROM $this->topic_calendar_table_events
				WHERE " . $this->db->sql_in_set('topic_id', $event['post_ids']);
		$this->db->sql_query($sql);
	}
	
	/**
	 * Perform additional actions before topic(s) deletion
	 *
	 * @event core.delete_topics_before_query
	 * @var	array	table_ary	Array of tables from which all rows will be deleted that hold a topic_id occuring in topic_ids
	 * @var	array	topic_ids	Array of topic ids to delete
	 * @since 3.1.4-RC1
	 */
	public function delete_topics($event)
	{
		//	Add our table to delete row for deleted topic
		$event['table_ary'][] = $this->topic_calendar_table_events;
	}

	/**
	* This event allows you to modify template variables for the posting screen
	*
	* @event core.posting_modify_template_vars
	* @var	array	post_data	Array with post data
	* @var	array	moderators	Array with forum moderators
	* @var	string	mode		What action to take if the form is submitted
	*				post|reply|quote|edit|delete|bump|smilies|popup
	* @var	string	page_title	Title of the mode page
	* @var	bool	s_topic_icons	Whether or not to show the topic icons
	* @var	string	form_enctype	If attachments are allowed for this form
	*				"multipart/form-data" or empty string
	* @var	string	s_action	The URL to submit the POST data to
	* @var	string	s_hidden_fields	Concatenated hidden input tags of posting form
	* @var	int	post_id		ID of the post
	* @var	int	topic_id	ID of the topic
	* @var	int	forum_id	ID of the forum
	* @var	bool	submit		Whether or not the form has been submitted
	* @var	bool	preview		Whether or not the post is being previewed
	* @var	bool	save		Whether or not a draft is being saved
	* @var	bool	load		Whether or not a draft is being loaded
	* @var	bool	cancel		Whether or not to cancel the form (returns to
	*				viewtopic or viewforum depending on if the user
	*				is posting a new topic or editing a post)
	* @var	array	error		Any error strings; a non-empty array aborts
	*				form submission.
	*				NOTE: Should be actual language strings, NOT
	*				language keys.
	* @var	bool	refresh		Whether or not to retain previously submitted data
	* @var	array	page_data	Posting page data that should be passed to the
	*				posting page via $template->assign_vars()
	* @var	object	message_parser	The message parser object
	* @since 3.1.0-a1
	* @change 3.1.0-b3 Added vars post_data, moderators, mode, page_title,
	*		s_topic_icons, form_enctype, s_action, s_hidden_fields,
	*		post_id, topic_id, forum_id, submit, preview, save, load,
	*		delete, cancel, refresh, error, page_data, message_parser
	* @change 3.1.2-RC1 Removed 'delete' var as it does not exist
	*/
	public function posting_modify_template($event)
	{
		$this->user->add_lang_ext('alf007/topiccalendar', 'controller');
		$calendar_data = $this->functions_topiccal->generate_entry($event['post_data'], $event['mode'], $event['forum_id'], $event['topic_id'], $event['post_id']);
		if (is_array($calendar_data))
		{
			$template_data = $event['page_data'];
			array_merge($template_data, $calendar_data);
			$event['page_data'] = $template_data;
		}
	}

	/**
	 * This event allows you to define errors before the post action is performed
	 *
	 * @event core.posting_modify_submission_errors
	 * @var	array	post_data	Array with post data
	 * @var	string	mode		What action to take if the form is submitted
	 *				post|reply|quote|edit|delete|bump|smilies|popup
	 * @var	string	page_title	Title of the mode page
	 * @var	int	post_id		ID of the post
	 * @var	int	topic_id	ID of the topic
	 * @var	int	forum_id	ID of the forum
	 * @var	bool	submit		Whether or not the form has been submitted
	 * @var	array	error		Any error strings; a non-empty array aborts form submission.
	 *				NOTE: Should be actual language strings, NOT language keys.
	 * @since 3.1.0-RC5
	 */
	public function posting_modify_submission($event)
	{
		$this->user->add_lang_ext('alf007/topiccalendar', 'controller');
		//	Insert Calendar input data to submit
		$data = $event['post_data'];
		$data['cal_date'] = $this->request->variable('cal_date', $this->user->lang['NO_DATE']);
		$data['interval_date'] = $this->request->variable('cal_interval_date', false);
		$data['date_end'] = $this->request->variable('cal_date_end', $this->user->lang['NO_DATE']);
		$data['repeat_always'] = $this->request->variable('cal_repeat_always', false);
		$data['cal_interval'] = $this->request->variable('cal_interval', 1);
		$data['interval_unit'] = $this->request->variable('cal_interval_units', 0);
		$event['post_data'] = $data;
	}
	
	/**
	* This event allows you to define errors after the post action is performed
	*
	* @event core.posting_modify_submit_post_after
	* @var	array	post_data	Array with post data
	* @var	array	poll		Array with poll data
	* @var	array	data		Array with post data going to be stored in the database
	* @var	string	mode		What action to take if the form is submitted
	*				post|reply|quote|edit|delete
	* @var	string	page_title	Title of the mode page
	* @var	int	post_id		ID of the post
	* @var	int	topic_id	ID of the topic
	* @var	int	forum_id	ID of the forum
	* @var	string	post_author_name	Author name for guest posts
	* @var	bool	update_message		Boolean if the post message was changed
	* @var	bool	update_subject		Boolean if the post subject was changed
	* @var	string	redirect_url		URL the user is going to be redirected to
	* @var	bool	submit		Whether or not the form has been submitted
	* @var	array	error		Any error strings; a non-empty array aborts form submission.
	*				NOTE: Should be actual language strings, NOT language keys.
	* @since 3.1.0-RC5
	*/
	public function submit_post_after($event)
	{
		$this->user->add_lang_ext('alf007/topiccalendar', 'controller');
		// retrieve calendar data from post data
		$data = $event['post_data'];
		$this->functions_topiccal->submit_event(
				$event['mode'],
				$event['forum_id'],
				$event['data']['topic_id'],
				$event['post_id'],
				$data
		);
	}
	
	/**
	* Modify the topic data before it is assigned to the template
	*
	* @event core.viewforum_modify_topicrow
	* @var	array	row			Array with topic data
	* @var	array	topic_row	Template array with topic data
	* @since 3.1.0-a1
	*/
	public function modify_topicrow($event)
	{
		$this->user->add_lang_ext('alf007/topiccalendar', 'controller');
		$topic_row = $event['topic_row'];
		$topic_row['EVENT_DATE'] = $this->functions_topiccal->show_event($topic_row['TOPIC_ID'], 0);
		$event['topic_row'] = $topic_row;
	}
	
	/**
	* Modify the posts template block
	*
	* @event core.viewtopic_modify_post_row
	* @var	int		start				Start item of this page
	* @var	int		current_row_number	Number of the post on this page
	* @var	int		end					Number of posts on this page
	* @var	int		total_posts			Total posts count
	* @var	int		poster_id			Post author id
	* @var	array	row					Array with original post and user data
	* @var	array	cp_row				Custom profile field data of the poster
	* @var	array	attachments			List of attachments
	* @var	array	user_poster_data	Poster's data from user cache
	* @var	array	post_row			Template block array of the post
	* @var	array	topic_data			Array with topic data
	* @since 3.1.0-a1
	* @change 3.1.0-a3 Added vars start, current_row_number, end, attachments
	* @change 3.1.0-b3 Added topic_data array, total_posts
	* @change 3.1.0-RC3 Added poster_id
	*/
	public function modify_post_row($event)
	{
		$this->user->add_lang_ext('alf007/topiccalendar', 'controller');
		$post_row = $event['post_row'];
		$post_row['EVENT_DATE'] = $this->functions_topiccal->show_event($event['topic_data']['topic_id'], $event['row']['post_id']);
		$event['post_row'] = $post_row;
	}

	/**
	* Modify the page title and load data for the index
	*
	* @event core.index_modify_page_title
	* @var	string	page_title		Title of the index page
	* @since 3.1.0-a1
	*/
	public function modify_page_title($event)
	{
		$month = $this->request->variable('month', 0);
		$year = $this->request->variable('year', 0);
		$this->display_mini_calendar($month, $year);	
	}
	
	/**
	* Event to modify the SQL parameters before pre-made searches
	*
	* @event core.search_modify_param_before
	* @var	string	keywords		String of the specified keywords
	* @var	array	sort_by_sql		Array of SQL sorting instructions
	* @var	array	ex_fid_ary		Array of excluded forum ids
	* @var	array	author_id_ary	Array of exclusive author ids
	* @var	string	search_id		The id of the search request
	* @since 3.1.3-RC1
	*/
	public function modify_search_param($event)
	{
		global $phpbb_container;

		if ($event['search_id'] != 'cal_events')
			return;
		$date = $this->user->create_datetime($this->request->variable('d', ''));
		$ex_fid_ary = $event['ex_fid_ary'];
		$id_ary = $this->functions_topiccal->search_events($this->auth, $ex_fid_ary, $date);
		//	Hack, inserting our own result array
		$event['id_ary'] = $id_ary;
		$event['show_results'] = 'topics';
		$sort_by_sql = $event['sort_by_sql'];
		$sort_by_sql['t'] = 't.topic_last_post_time';
		$sort_by_sql['s'] = 't.topic_title';
		$event['sort_by_sql'] = $sort_by_sql; 
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

		$day_infos = new days_info($this->auth, $this->db, $this->root_path, $this->phpEx, $this->topic_calendar_table_config, $this->topic_calendar_table_events);
		
		// setup template
		$this->template->set_filenames(array(
				'mini_cal_body' => 'mini_cal_body.html')
		);
		$this->template->assign_var('S_IN_MINI_CAL', true);
		
		$cal_days = $day_infos->get_days_info($this->user, $year, $month, true);

		$this->template->assign_var('S_MONTH', $day_infos->monthView['monthName']);
		$this->template->assign_var('S_YEAR', $day_infos->monthView['year']);

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

		$result = $day_infos->get_days_ahead();
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
				$eventdate = \DateTime::createFromFormat(DATE_FORMAT, sprintf('%d-%02d-%02d 12:00:00', substr($row['date'], 0, 4), substr($row['date'], 4, 2), substr($row['date'], 6, 2)));
				$cal_date_replace = array( 
					$this->user->lang['datetime'][date('D', strtotime('Monday +' . ($eventdate->format('w') - 1) . ' days'))], 
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
		$nextyear = $year;
		$nextmonth = $month + 1;
		if ($nextmonth > 12)
		{
			$nextmonth = 1;
			$nextyear++; 
		}
		$previousyear = $year;
		$previousmonth = $month - 1; 
		if ($previousmonth < 1)
		{
			$previousmonth = 12;
			$previousyear--;
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
