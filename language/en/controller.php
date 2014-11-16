<?php
/**
*
* @package phpBB Extension - Alf007 Topic Calendar
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'PAGE_TITLE'		=> 'Calendar',
	'EVENTS_FORUM'		=> 'Allow calendar events',

	'DATE_SQL_FORMAT'	=> '%M %e, %Y',		// This should be changed to the default date format for SQL for your language
	'DATE_INPUT_FORMAT'	=> 'm/d/y',			// Requires 'd', 'm', and 'y' and a punctuation delimiter, order can change

	'INTERVAL'			=> array(
		'DAY'		=> 'day',
		'DAYS'		=> 'days',
		'WEEK'		=> 'week',
		'WEEKS'		=> 'weeks',
		'MONTH'		=> 'month',
		'MONTHS'	=> 'months',
		'YEAR'		=> 'year',
		'YEARS'		=> 'years',
	),

	'WEEKDAY_START'			=> 1,		// First Day of the Week - 0=Sunday, 1=Monday...6=Saturday
	'EVENT_START'			=> 'Single or Start Date',
	'EVENT_END'				=> 'End Date and Interval',
	'CALENDAR_ADVANCED'		=> 'advanced',
	'CAL_REPEAT_FOREVER'	=> 'repeat forever',
	'CLEAR_DATE'			=> 'Clear Date',
	'NO_DATE'				=> 'None',
	'SELECT_START_DATE'		=> 'Please Select a Start Date', // must escape ' as \\\' for javascript
	'CALENDAR_EVENT'		=> 'Calendar Event:',
	'PREVIOUS_MONTH'		=> 'View Previous Month',
	'NEXT_MONTH'			=> 'View Next Month',
	'PREVIOUS_YEAR'			=> 'View Previous Year',
	'NEXT_YEAR'				=> 'View Next Year',
	'SEL_INTERVAL'			=> 'Interval:',
	'CALENDAR_REPEAT'		=> 'Repeat:',
	'DATE_SELECTOR_TITLE'	=> 'Date Selector',
	'HAPPY'					=> 'Happy Birthday(s): ',
	'EVENT'					=> 'Event(s): ',

	//	Error messages
	'TOPIC_CALENDAR_CantQueryDate'				=> 'Error querying dates for calendar.',
	'TOPIC_CALENDAR_NoRepeatMult'				=> 'Could not determine repeat multiplier for date entry.',
	'TOPIC_CALENDAR_CantCheckDate'				=> 'Failure when looking up date entry for topic.',

	//	Mini cal
	'MINI_CAL_CALENDAR'		=> 'Calendar',
	'MINI_CAL_ADD_EVENT'	=> 'Add Event',
	'MINI_CAL_EVENTS'		=> 'Upcoming Events',
	'MINI_CAL_NO_EVENTS'	=> 'No Upcoming Event',
// uses MySQL DATE_FORMAT - %c  long_month, numeric (1..12) - %e  Day of the long_month, numeric (0..31)
// see http://www.mysql.com/doc/D/a/Date_and_time_functions.html for more details
// currently supports: %a, %b, %c, %d, %e, %m, %y, %Y, %H, %k, %h, %l, %i, %s, %p
	'Mini_Cal_date_format'	=> '%a %e %b',
// if you change the first day of the week in constants.php, you should change values for the short day names accordingly
// e.g. FDOW = Sunday -> 	'mini_cal']['day'][1	=> 'Su', ... 	'mini_cal']['day'][7	=> 'Sa', 
//      FDOW = Monday -> 	'mini_cal']['day'][1	=> 'Mo', ... 	'mini_cal']['day'][7	=> 'Su', 
	'MINICAL'	=> array(
		'DAY'	=> array(
			'SHORT'	=> array(
				'Su',
				'Mo',
				'Tu',
				'We',
				'Th',
				'Fr',
				'Sa'
			), 
			'LONG'	=> array(
				'Sunday',
				'Monday',
				'Tuesday',
				'Wednesday',
				'Thursday',
				'Friday',
				'Saturday'
			),
		),
		'MONTH'	=> array(
			'SHORT'	=> array(
				'Jan',
				'Feb',
				'Mar',
				'Apr',
				'May',
				'Jun',
				'Jul',
				'Aug',
				'Sep',
				'Oct',
				'Nov',
				'Dec'
			), 
			'LONG'	=> array(
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
			),
		),
	),
));
