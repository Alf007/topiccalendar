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

/**
* Event listener
*/
class main_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'						   => 'load_language_on_setup',
			'core.page_header_after'					=> 'add_page_header_after',
			'core.acp_manage_forums_initialise_data'	=> 'initialize_forums_data',
			'core.acp_manage_forums_request_data'	   => 'request_forums_data',
			'core.acp_manage_forums_display_form'	   => 'display_forums_form',
			'core.acp_manage_forums_move_content'	   => 'move_forums_content',
			'core.acp_manage_forums_delete_content'	 => 'delete_forums_content',
			'core.move_topics'						  => 'move_topics',
			'core.delete_posts_after'				   => 'delete_posts',
			'core.delete_topics'						=> 'delete_topics',
			'core.posting_modify_submit_post_after'	 => 'submit_post_after',
			'core.viewforum_modify_topicrow'			=> 'modify_topicrow',
			'core.viewtopic_modify_post_row'			=> 'modify_post_row',
			'core.index_modify_page_title'				=> 'display_mini_calendar',
		);
	}

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

	/** @var string PHP extension */
	protected $phpEx;

	protected $topic_calendar_table_config;
	protected $topic_calendar_table_events;
	
	/* @var \alf007\topiccalendar\controller\main */
	protected $tc_functions;
	
	public $functions_topiccal;
	
	/**
	* Constructor
	*
	* @param \phpbb\config\config			   $config
	* @param \phpbb\db\driver\driver_interface  $db
	* @param \phpbb\controller\helper	$helper		Controller helper object
	* @param \phpbb\request\request_interface   $request		Request variables
	* @param \phpbb\template\template			$template	Template object
	* @param \phpbb\user						$user
	* @param string $phpEx	  php file extension
	* @param string $table_config  extension config table name
	* @param string $table_events  extension events table name
	* @access public
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, $phpEx, $table_config, $table_events, \alf007\topiccalendar\controller\main $tc_functions)
	{
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->phpEx = $phpEx;
		$this->topic_calendar_table_config = $table_config;
		$this->topic_calendar_table_events = $table_events;
		$this->tc_functions = $tc_functions;
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
		if ($this->user->data['is_registered'])
		{
			$mode = $this->request->variable('mode', '');
			$forum_id = $this->request->variable('f', 0);
			$topic_id = $this->request->variable('t', 0);
			$post_id = $this->request->variable('p', 0);
			$date = $this->request->variable('date', $this->user->lang['NO_DATE']);
			$this->functions_topiccal->generate_entry($mode, $forum_id, $topic_id, $post_id, $date);
		}

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
	* Event when we delete forum content
	*
	* @event core.acp_manage_forums_delete_content
	* @var	int		forum_id	Id of the forum
	* @var	array	errors		Array of errors, should be strings and not
	*							language key. If this array is not empty,
	*							The content will not be deleted.
	* @since 3.1.1
	*/
	public function delete_forums_content($event)
	{
		$forum_id = $event['forum_id'];
		$this->db->sql_query("DELETE FROM $this->topic_calendar_table_events WHERE forum_id = $forum_id");
	}
	
	/**
	* Event after topics are moved to another forum
	*
	* @event core.move_topics
	* @var	array	topic_ids	Topics moving
	* @var	int	forum_id	Id of the new forum parent
	* @since 3.1.2
	*/
	public function move_topics($event)
	{
		$this->functions_topiccal->move_event($event['forum_id'], $event['topic_ids']);
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
	* Event when we delete topics
	*
	* @event core.delete_topics
	* @var	array	topic_ids	Array of topic Id to be deleted
	* @since 3.1.2
	*/
	public function delete_topics($event)
	{
		$this->db->sql_query("DELETE FROM $this->topic_calendar_table_events WHERE " . $this->db->sql_in_set('topic_id', $event['topic_ids']));
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
		$data = $event['data'];
		$date = $this->request->variable('date', $user->lang['NO_DATE']);
		$repeat = 1;
		// get the ending date and interval information
		$interval_date = $this->request->variable('interval_date', false);
		$date_end = $this->request->variable('date_end', $user->lang['NO_DATE']);
		$repeat_always = $this->request->variable('repeat_always', false);
		$interval = $this->request->variable('interval', 1);
		$interval_units = $this->request->variable('interval_units', 0);
		$this->functions_topiccal->submit_event(
				$event['mode'],
				$event['forum_id'],
				$data['topic_id'],
				$event['post_id'],
				$date,
				$repeat,
				$interval_date,
				$date_end,
				$repeat_always,
				$interval,
				$interval_units
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
		$topic_row = $event['topic_row'];
		$topic_row[] = array (
			'EVENT' => $this->functions_topiccal->show_event($topic_row['topic_id'], 0)
		);
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
		$post_row = $event['post_row'];
		$post_row[] = array (
			'EVENT'		 => $this->functions_topiccal->show_event($event['topic_data']['topic_id'], $event['row']['post_id']),
		);
		$event['post_row'] = $post_row;
	}

	/**
	 * Display a mini calendar
	 * 
	 * @param unknown $event
	 */	
	public function display_mini_calendar($event)
	{
		$month = $this->request->variable('month', 0);
		$year = $this->request->variable('year', 0);
		$this->tc_functions->display_mini_calendar($month, $year);	
	}
}
