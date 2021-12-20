# phpBB 3.2 Extension - Topic Calendar

## Features:
This Extension allows a phpBB3.1 board owner to mark specified forums as calendar-enabled. Events can then be associated with the topics in these forums.
* Events will appear on a dedicated Calendar page for topics in forums that are calendar-enabled.
* The calendar attribute can be set on a forum by using the forum management tool in the administration center and selecting 'Yes' for 'Are events enabled?'.
* Dates can only be attached to the first post in a topic (hence, events attach to the root of the topic).
* An 'Event' panel is added to the Post editor, to define the date for the topic
* Advanced options allow to define an interval to an ending date, or for recurring dates
* The dedicated Calendar page offers up the first post by mousing over the event to reveal a tooltip. Using this feature, information added to the first post, such as Time and Place, can be viewed directly from the calendar page.
* Convert data from phpBB 3.0 MyCalendar MOD - previously added column to the database FORUMS table is now in a separate table of the Extension _topic_calendar_config which also includes new options for the Extension, _mycalendar table is replaced by _topic_calendar_events table, with MySQL-dependent column type DATETIME replaced by independant common types to store the date
* MyCalendar MOD was extensively using (MySQL) Database dependent functionalities to work with Date calculation, this was replaced in the Extension by PHP (5.3+) equivalent functionalities, so to be cleared from database dependancy.

### Languages supported:
* English
* French

## Requirements
* phpBB 3.2 or higher
* PHP 5.3.3 or higher
* Javascript is required by this extension.

Note: This extension is in development. Installation is only recommended for testing purposes and is not supported on live boards. 

## Installation
1. Clone into phpBB/ext/alf007/topiccalendar:
   git clone https://github.com/Alf007/topiccalendar.git phpBB/ext/alf007/topiccalendar
2. Navigate in the ACP to `Customise -> Manage extensions`.
3. Find Topic Calendar under "Disabled Extensions" and click `Enable`.

## Uninstallation
1. Navigate in the ACP to `Customise -> Manage extensions`.
2. Click the `Disable` link for Topic Calendar.
3. To permanently uninstall, click `Delete Data`, then delete the `topiccalendar` folder from `phpBB/ext/alf007/`.


## License

[GPLv2](license.txt)
