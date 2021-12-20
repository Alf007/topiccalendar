<?php

/**
 *
 * @package phpBB Extension - Alf007 Topic Calendar
 * @copyright (c) 2021 alf007
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
	'ACP_CAT_TOPICCALENDAR' => 'Topic Calendar',
	'ACP_TOPICCALENDAR_GLOBALSETTINGS' => 'Global settings',
	'TOPICCALENDAR_TITLE' => 'Topic Calendar',
	'TOPICCALENDAR_INDEX' => 'Display Topic Calendar (mini) on the index page',
	'TOPICCALENDAR_LOCATION' => 'Location on the index page',
	'TOPICCALENDAR_BOTTOM' => 'Bottom',
	'TOPICCALENDAR_TOP' => 'Top',
		
	'TOPICCALENDAR_CONFIG_SAVED' => 'Topic Calendar configuration has been updated.',
		
	'ENABLE_EVENTS'	=> 'Enable Events for Topic Calendar',
	'ENABLE_EVENTS_EXPLAIN'	=> 'If set to yes users are able to define event associated with topic (first post) for this forum'
));
