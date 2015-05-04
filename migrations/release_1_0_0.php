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

	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v310\gold');
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
                                                'forum_id'  => array('INT:5')
					),
					'PRIMARY_KEY'	=> 'cal_id',
					'UNIQUE'	=> 'topic_id (topic_id)', 
					'KEY'           => 'cal_date (cal_date)', 
					'KEY'           => 'cal_id (cal_id)', 
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
			array('custom', array(array($this, 'upgrade_from_30'))),
		);
	}
        
	public function revert_data()
	{
		return array(
                );
	}
	
	public function upgrade_from_30()
	{
		if ($this->db_tools->sql_table_exists($this->table_prefix . 'mycalendar'))
		{
			$sql = 'SELECT * FROM ' . $this->table_prefix . 'mycalendar';
			$result = $this->sql_query($sql);
			while ($row = $this->sql_fetchrow($result))
			{
				$date = $row['cal_date']->format('Y-m-d H:i:s');
				if ($date == FALSE)
					$date = '0000-00-00 00:00:00';
				$sql = 'INSERT INTO ' . $this->table_prefix . 'topic_calendar ' . $db->sql_build_array('INSERT', array(
							'topic_id'  => $row('topic_id'),
							'cal_date'  => $date, 
							'forum_id'  => $row('forum_id')
						));
				$this->sql_query($sql);
			}
			if (!$this->db->get_sql_error_triggered())
			{
			//	$this->db_tools->sql_table_drop($this->table_prefix . 'mycalendar');
			}
		}
	}
}
