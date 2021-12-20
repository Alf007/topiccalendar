<?php
/**
 *
 * @package phpBB Extension - Topic Calendar
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace alf007\topiccalendar;

use phpbb\extension\base;

class ext extends base
{
	/**
	 * Check whether or not the extension can be enabled.
	 *
	 * Requires phpBB 3.2.0 due to EoL of phpBB 3.1
	 *
	 * @return bool
	 * @access public
	 */
	public function is_enableable()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.2.0', '>=') && phpbb_version_compare(PHP_VERSION, '5.4.7', '>=');
	}
}
