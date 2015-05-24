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
	'DATE_INPUT_FORMAT'	=> 'm/d/Y',			// Requires 'd', 'm', and 'y' and a punctuation delimiter, order can change

	'INTERVAL'			=> array(
		'0'		=> 'day',
		'0S'	=> 'days',
		'1'		=> 'week',
		'1S'	=> 'weeks',
		'2'		=> 'month',
		'2S'	=> 'months',
		'3'		=> 'year',
		'3S'	=> 'years',
	),
		
	'WEEKDAY_START'			=> 1,		// First Day of the Week - 0=Sunday, 1=Monday...6=Saturday
	'EVENT_START'			=> 'Start Date',
	'EVENT_END'				=> 'End Date and Interval',
	'CALENDAR_ADVANCED'		=> 'advanced',
	'CAL_REPEAT_FOREVER'	=> 'repeat forever',
	'CLEAR_DATE'			=> 'Clear Date',
	'NO_DATE'				=> 'None',
	'SELECT_START_DATE'		=> 'Please Select a Start Date', // must escape ' as \\\' for javascript
	'CALENDAR_EVENT_TITLE'	=> 'Calendar Event',
	'SEL_INTERVAL'			=> 'Interval',
	'CALENDAR_REPEAT'		=> 'Repeat',
	'DATE_SELECTOR_TITLE'	=> 'Date Selector',

	//	Mini cal
	'MINI_CAL_EVENTS'		=> 'Upcoming Events',
	'MINI_CAL_NO_EVENTS'	=> 'No Upcoming Event',
// uses MySQL DATE_FORMAT - %c  long_month, numeric (1..12) - %e  Day of the long_month, numeric (0..31)
// see http://www.mysql.com/doc/D/a/Date_and_time_functions.html for more details
// currently supports: %a, %b, %c, %d, %e, %m, %y, %Y, %H, %k, %h, %l, %i, %s, %p
	'MINI_CAL_DATE_FORMAT'	=> '%a %e %b',
));
