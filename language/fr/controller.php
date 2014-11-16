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
	'DATE_INPUT_FORMAT'	=> 'd/m/y',			// Requires 'd', 'm', and 'y' and a punctuation delimiter, order can change

	'INTERVAL'			=> array(
		'DAY'		=> 'jour',
		'DAYS'		=> 'jours',
		'WEEK'		=> 'semaine',
		'WEEKS'		=> 'semaines',
		'MONTH'		=> 'mois',
		'MONTHS'	=> 'mois',
		'YEAR'		=> 'année',
		'YEARS'		=> 'années',
	),

	'WEEKDAY_START'			=> 1,		// Premier jour de la Semaine - 0=Dimanche, 1=Lundi...6=Samedi
	'EVENT_START'			=> 'Date Unique ou Début',
	'EVENT_END'				=> 'Date Fin ou Intervalle',
	'CALENDAR_ADVANCED'		=> 'avancé',
	'CAL_REPEAT_FOREVER'	=> 'répéter toujours',
	'CLEAR_DATE'			=> 'Supprimer Date',
	'NO_DATE'				=> 'Aucune',
	'SELECT_START_DATE'		=> 'Veuillez sélectionner une Date de Début', // must escape ' as \\\' for javascript
	'CALENDAR_EVENT'		=> 'Evènement Calendrier :',
	'PREVIOUS_MONTH'		=> 'Voir Mois Précédent',
	'NEXT_MONTH'			=> 'Voir Mois Suivant',
	'PREVIOUS_YEAR'			=> 'Voir Année Précédente',
	'NEXT_YEAR'				=> 'Voir Année Suivante',
	'SEL_INTERVAL'			=> 'Intervalle :',
	'CALENDAR_REPEAT'		=> 'Répéter :',
	'DATE_SELECTOR_TITLE'	=> 'Sélection de Date',
	'HAPPY'					=> 'Joyeux Anniversaire(s)&nbsp;: ',
	'EVENT'					=> 'Evènement(s) :',

	//	Error messages
	'TOPIC_CALENDAR_CantQueryDate'				=> 'Erreur lors de la récupération de dates pour le calendrier.',
	'TOPIC_CALENDAR_NoRepeatMult'				=> 'Impossible de déterminer le multiplicateur de répétition pour la date entrée.',
	'TOPIC_CALENDAR_CantCheckDate'				=> 'Erreur en vérifiant la date entrée pour le sujet.',

	//	Mini cal
	'MINI_CAL_CALENDAR'		=> 'Calendrier',
	'MINI_CAL_ADD_EVENT'	=> 'Ajout Evènement',
	'MINI_CAL_EVENTS'		=> 'Evènements à venir',
	'MINI_CAL_NO_EVENTS'	=> 'Aucun évènement à venir',
// uses MySQL DATE_FORMAT - %c  long_month, numeric (1..12) - %e  Day of the long_month, numeric (0..31)
// see http://www.mysql.com/doc/D/a/Date_and_time_functions.html for more details
// currently supports: %a, %b, %c, %d, %e, %m, %y, %Y, %H, %k, %h, %l, %i, %s, %p
	'Mini_Cal_date_format'	=> '%a %e %b',

	'MINICAL'	=> array(
		'DAY'	=> array(
			'SHORT'	=> array(
				'Di',
				'Lu',
				'Ma',
				'Me',
				'Je',
				'Ve',
				'Sa'
			), 
			'LONG'	=> array(
				'Dimanche',
				'Lundi',
				'Mardi',
				'Mercredi',
				'Jeudi',
				'Vendredi',
				'Samedi'
			),
		),
		'MONTH'	=> array(
			'SHORT'	=> array(
				'Jan',
				'Fév',
				'Mar',
				'Avr',
				'Mai',
				'Juin',
				'Juil',
				'Aoû',
				'Sep',
				'Oct',
				'Nov',
				'Déc'
			), 
			'LONG'	=> array(
				'Janvier', 
				'Février',
				'Mars',
				'Avril',
				'Mai',
				'Juin',
				'Juillet',
				'Août',
				'Septembre',
				'Octobre',
				'Novembre',
				'Décembre'
			),
		),
	),
));
