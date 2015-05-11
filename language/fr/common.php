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

$lang = array_merge($lang, array(
    'TOPIC_CALENDAR'		=> 'Calendrier',
    'TOPIC_CALENDAR_EXPLAIN'	=> 'Détails du Calendrier',
    'VIEWING_TOPIC_CALENDAR'	=> 'Voir le Calendrier'
));
