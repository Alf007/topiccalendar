<?php

/**
 *
 * @package phpBB Extension - Alf007 Topic Calendar
 * @copyright (c) 2013 phpBB Group
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */
namespace alf007\topiccalendar\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'forums', 'enable_events');
	}

	public function update_schema()
	{
		return array(
			'add_tables'		=> array(
				$this->table_prefix . 'topic_calendar'	=> array(
					'COLUMNS'		=> array(
                                                'cal_id'    => array('INT:12', null, 'auto_increment'),
                                                'topic_id'  => array('INT:20', null),
                                                'cal_date'  => array('CHAR:19', '0000-00-00 00:00:00'),
                                                'cal_interval'  => array('TINT:3', 1),
                                                'cal_interval_units'    => array('VCHAR:5', 'DAY'), /* enum('DAY','WEEK','MONTH','YEAR') DEFAULT 'DAY' NOT NULL) */
                                                'cal_repeat'    => array('TINT:3', '1'),
                                                'forum_id'  => array('INT:5')
					),
					'PRIMARY_KEY'	=> 'cal_id',
					'UNIQUE'	=> 'topic_id (topic_id)', 
					'KEY'           => 'cal_date (cal_date)', 
					'KEY'           => 'cal_id (cal_id)', 
					'KEY'           => 'cal_interval (cal_interval)', 
					'KEY'           => 'cal_interval_units (cal_interval_units)', 
					'KEY'           => 'cal_repeat (cal_repeat)', 
					'KEY'           => 'forum_id (forum_id)'
				),
			),
			'add_columns'	=> array(
				$this->table_prefix . 'forums'			=> array(
					'enable_events'				=> array('BOOL', 0),
				),
			),
		);
	}
        
	public function revert_schema()
	{
		return array(
			'drop_columns'	=> array(
				$this->table_prefix . 'forums'			=> array(
					'enable_events',
				),
			),
			'drop_tables'		=> array(
				$this->table_prefix . 'topic_calendar',
			),
		);
	}

        public function update_data()
	{
            return array(
                );
        }
        
        public function revert_data()
	{
		return array(
                );
        }
}
