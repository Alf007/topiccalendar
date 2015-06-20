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
	'PAGE_TITLE'		=> 'Calendrier',
	'EVENTS_FORUM'		=> 'Autoriser les évènements de Calendrier',

	'DATE_SQL_FORMAT'	=> '%W %e %M %Y',	// This should be changed to the default date format for SQL for your language
	'DATE_INPUT_FORMAT'	=> 'd/m/Y',			// Requires 'd', 'm', and 'y' and a punctuation delimiter, order can change

	'INTERVAL'	=> array(
		'0'		=> 'jour',
		'0S'	=> 'jours',
		'1'		=> 'semaine',
		'1S'	=> 'semaines',
		'2'		=> 'mois',
		'2S'	=> 'mois',
		'3'		=> 'année',
		'3S'	=> 'années',
	),

	'WEEKDAY_START'			=> 1,		// Premier jour de la Semaine - 0=Dimanche, 1=Lundi...6=Samedi
	'EVENT_START'			=> 'Date Unique ou Début',
	'EVENT_END'				=> 'Date de fin',
	'EVENT_INTERVAL'		=> 'Intervalle',
	'CALENDAR_ADVANCED'		=> 'avancé',
	'CAL_REPEAT_FOREVER'	=> 'répéter toujours',
	'CLEAR_DATE'			=> 'Supprimer Date',
	'NO_DATE'				=> 'Aucune',
	'SELECT_START_DATE'		=> 'Veuillez sélectionner une Date de Début', // must escape ' as \\\' for javascript
	'CALENDAR_EVENT_TITLE'	=> 'Evènement Calendrier',
	'SEL_INTERVAL'			=> 'Intervalle',
	'CALENDAR_REPEAT'		=> 'Répéter',
	'DATE_SELECTOR_TITLE'	=> 'Sélection de Date',

	//	Mini cal
	'MINI_CAL_EVENTS'		=> 'Evènements à venir',
	'MINI_CAL_NO_EVENTS'	=> 'Aucun évènement à venir',

	'MINI_CAL_DATE_FORMAT'	=> '%a %e %b',
));
