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
	'ENABLE_EVENTS'	=> 'Autoriser les évènements de Calendrier',
	'ENABLE_EVENTS_EXPLAIN'	=> 'Si positioné à vrai les utilisateurs peuvent définir un évènement associé à un (premier message de) sujet pour ce forum'
));
