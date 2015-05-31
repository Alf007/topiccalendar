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
							'forum_ids'	=> array('TEXT', ''),
							'minical'  => array('BOOL', 0),
							'minical_max_events' => array('USINT', 5),
							'minical_days_ahead' => array('TINT:3', 0),
					),
				),
				$this->table_prefix . 'topic_calendar_events'	=> array(
					'COLUMNS'	=> array(
							'id'	=> array('UINT', null, 'auto_increment'),
							'forum_id'  => array('UINT', null),
							'topic_id'  => array('UINT', null),
							'date' => array('UINT:11', null),
							'cal_interval' => array('TINT:3', 0),
							'cal_repeat' => array('TINT:3', 0),
							'interval_unit' => array('TINT:1', 0),
							'end_date' => array('UINT:11', null),
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
			array('if', array(
				($this->db_tools->sql_table_exists($this->table_prefix . 'mycalendar')),		
				array('custom', array(
						array($this, 'upgrade_from_mycalendar')
					)
				),
			)),
		);
	}
		
	public function revert_data()
	{
		return array(
		);
	}
	
	public function upgrade_from_mycalendar()
	{
		$interval_units = array('DAY', 'WEEK', 'MONTH', 'YEAR');
		//	Convert from MyCalendar (phpbb 3.0) to new table (no mysql dependancy)
		$sql = 'SELECT * FROM ' . $this->table_prefix . 'mycalendar';
		$result = $this->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$date = \DateTime::createFromFormat('Y-m-d H:i:s', $row['cal_date']);
			$end_date = 0;
			if ($date == FALSE)
				$date = new \DateTime();
			elseif ($row['cal_repeat'] > 1)
			{	// Calculate ending date
				$date_end = clone $date;
				$date_end->add(new \DateInterval('P' . sprintf('%d', intval($row['cal_repeat']) * intval($row['cal_interval'])) . substr($row['cal_interval_units'], 0, 1)));
				$end_date = intval($date_end->format('Ymd'));
			}
			$sql = 'INSERT INTO ' . $this->table_prefix . 'topic_calendar_events ' . $this->db->sql_build_array('INSERT', array(
						'forum_id'  => $row['forum_id'],
						'topic_id'  => $row['topic_id'],
						'date' => intval($date->format('Ymd')),
						'cal_interval' => (int)$row['cal_interval'],
						'cal_repeat' => (int)$row['cal_repeat'],
						'interval_unit' => array_search($row['cal_interval_units'], $interval_units),
						'end_date' => $end_date,
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
			$forum_ids = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['enable_events'])
				{
					$forum_ids[] = $row['forum_id'];
				}			
			}
			sort($forum_ids);
			$sql = 'INSERT INTO ' . $this->table_prefix . 'topic_calendar_config ' . $this->db->sql_build_array('INSERT', array(
						'forum_ids'  => implode(',', $forum_ids),
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
