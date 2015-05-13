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
		return $this->db_tools->sql_table_exists($this->table_prefix . 'topic_calendar_config');
	}

	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v310\gold');
	}
	
	public function update_schema()
	{
		return array(
			'add_tables'		=> array(
				$this->table_prefix . 'topic_calendar_config'	=> array(
					'COLUMNS'	=> array(
							'forum_ids'    => array('TEXT', ''),
							'minical'  => array('BOOL', 0),
					),
				),
				$this->table_prefix . 'topic_calendar_events'	=> array(
					'COLUMNS'	=> array(
							'id'    => array('UINT', null, 'auto_increment'),
							'forum_id'  => array('UINT'),
							'topic_id'  => array('UINT', null),
							'year' => array('USINT', 0),
							'month' => array('TINT:2', 0),
							'day' => array('TINT:2', 0),
							'hour' => array('TINT:2', 0),
							'min' => array('TINT:2', 0),
							'interval' => array('TINT:3', 0),
							'repeat' => array('TINT:3', 0),
							'interval_unit' => array('TINT:1', 0),
					),
					'PRIMARY_KEY'	=> 'id',
					'UNIQUE'	=> 'topic_id (topic_id)', 
					'KEY'		=> 'id (id)', 
					'KEY'		=> 'forum_id (forum_id)'
				),
			),
		);
	}
        
	public function revert_schema()
	{
		return array(
			'drop_tables'		=> array(
				$this->table_prefix . 'topic_calendar_config',
				$this->table_prefix . 'topic_calendar_events',
			),
		);
	}

	public function update_data()
	{
		return array(
			'if', array(
				($this->db_tools->sql_table_exists($this->table_prefix . 'mycalendar')),		
				array('custom', array(
						array(&$this, 'upgrade_from_mycalendar')
				)),
			)
		);
	}
        
	public function revert_data()
	{
		return array(
		);
	}
	
	public function upgrade_from_mycalendar()
	{
		//	Convert from MyCalendar (phpbb 3.0) to new table (no mysql dependant)
		$sql = 'SELECT * FROM ' . $this->table_prefix . 'mycalendar';
		$result = $this->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$date = \DateTime::createFromFormat('Y-m-d H:i:s', strtotime($row['cal_date']));
			if ($date == FALSE)
				$date = '0000-00-00 00:00:00';
			$sql = 'INSERT INTO ' . $this->table_prefix . 'topic_calendar_events ' . $this->db->sql_build_array('INSERT', array(
						'forum_id'  => $row['forum_id'],
						'topic_id'  => $row['topic_id'],
						'year' => (int)$date->format('Y'),
						'month' => (int)$date->format('m'),
						'day' => (int)$date->format('d'),
						'hour' => (int)$date->format('H'),
						'min' => (int)$date->format('i'),
						'interval' => $row['cal_interval'],
						'repeat' => $row['cal_repeat'],
						'interval_unit' => array('DAY', 'WEEK', 'MONTH', 'YEAR')[$row['cal_intercval_units']],
				));
			$this->sql_query($sql);
		}
		if (!$this->db->get_sql_error_triggered() &&
			$this->db_tools->sql_column_exists(FORUMS_TABLE, 'enable_events'))
		{	
			//	Prevent messing with core table anymore, replace flag originally added to forum table,
			//	by list of forum ids in extension config table
			$sql = 'SELECT forum_id, enable_events FROM ' . FORUMS_TABLE;
			$result = $this->sql_query($sql);
			$forum_ids = '';
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['enable_events'])
				{
					if ($forum_ids != '')
						$forum_ids .= ',';
					$forum_ids .= $row['forum_id'];
				}			
			}
			$sql = 'INSERT INTO ' . $this->table_prefix . 'topic_calendar_config ' . $this->db->sql_build_array('INSERT', array(
						'forum_ids'  => $forum_ids,
						'minical'  => true,
				));
			$this->sql_query($sql);
			if (!$this->db->get_sql_error_triggered())
			{
			//	$this->db_tools->sql_column_drop(FORUMS_TABLE, 'enable_events');
			}
		}
		if (!$this->db->get_sql_error_triggered())
		{
		//	$this->db_tools->sql_table_drop($this->table_prefix . 'mycalendar');
		}
	}
}
