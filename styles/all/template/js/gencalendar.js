//
//Calendar generation
//Alf007 - 2008
//License http://opensource.org/licenses/gpl-license.php GNU Public License
//
//Partly inspired from Scalable EM-based Calendar by Mike Purvis (http://sandbox.mikepurvis.com/css/calendar/em.php#)
//
//Variables to be defined to use these functions
//
//var calendarFormat			Calendar Date format string
//var calendarMonths			List of Months names
//var calendarWeekdaysShort		List of Week-days short names
//var calendarWeekdays			List of Week-days names
//var weekday_start				Index of weekday start (from sunday=0)
//var insert_id					Id of object where to insert generated Calendar
//var currentDay				Current Day
//var currentMonth				Current Month
//var currentYear				Current Year
//var days_info					Array of content for day elements

//feburary will be corrected for later
var calendarDays  = new Array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
//For CSS positionning
var startDays = new Array('sundaystart', 'mondaystart', 'tuesdaystart', 'wednesdaystart', 'thursdaystart', 'fridaystart', 'saturdaystart'); 
//To identify nth 'day' element for later fill
var class_day = 'day_';


function y2k(number)
{
	return (number < 1000) ? number + 1900 : number; 
}

function formatDate(month, day, year) 
{
	// pad numbers under 10 with '0' to conform to iso date standards and to make life easier
	month = month < 10 ? '0' + month : month;
	day   = day   < 10 ? '0' + day   : day;
	selectedDate = calendarFormat;
	selectedDate = selectedDate.replace(/m/, month);
	selectedDate = selectedDate.replace(/d/, day);
	selectedDate = selectedDate.replace(/Y/, year);
	return selectedDate;
}

function fill_div(dest_id, content)
{
	var dest_div = document.getElementById(dest_id);
	if (dest_div)
	{
		try
		{
			dest_div.innerHTML = content;
		} catch(e)
		{	//	Special for dumb IE - current innerHTML is r/o 
			if (dest_div.hasChildNodes())
			{	//	Clear previous content
				dest_div.removeChild(dest_div.firstChild);
			}
			//	Create new div to put content
			var inter_div = document.createElement('div');
			inter_div.innerHTML = content;
			//	Add created div to target element
			dest_div.appendChild(inter_div);
		}
	}
}

function build_calendar(month, day, year)
{
	// Determined whether this is a leap year or not
	if (((year % 4 == 0) && (year % 100 != 0)) || (year % 400 == 0))
	{
		calendarDays[1] = 29; 
	} else
	{
		calendarDays[1] = 28;
	}
	// Filling the calendar content
	var output = '<form><div class="calendar"><ol class="monthyear">';
	output += '<li class="previous small-icon prev_arrow"><a href="#' + insert_id + '" onclick="update_calendar(' + (month - 1) + ', ' + day + ', ' + year + ');"></a></li>';
	output += '<li>' + calendarMonths[month] + '&nbsp;' + year + '</li>';
	output += '<li class="next small-icon next_arrow"><a href="#' + insert_id + '" onclick="update_calendar(' + (month + 1) + ', ' + day + ', ' + year + ');"> </a></li>';
	output += '</ol></div>';
	// print out the days of the week
	weekday = weekday_start;
	firstDay = new Date(year, month, 1);
	startDay = firstDay.getDay();
	startDay -= weekday;
	output += '<ol class="dayheaders">';
	for (i = 0; i < 7; i++)
	{
		output += '<li><abbr title="' + calendarWeekdays[weekday] + '">' + calendarWeekdaysShort[weekday] + '</abbr></li>';
		weekday++;
		if (weekday > 6)
		{
			weekday = 0; 
		}
	}
	output += '</ol>';
	var add_prev = false;
	if (startDay > 0)
	{
		startDay--;
		add_prev = true;
	} else if (startDay < 0)
	{
		startDay += 7;
	}
	output += '<ol class="calendar navbar ' + startDays[startDay] + '">';
	var li_first = 'firstday ';
	var action = '';
	if (add_prev)
	{
		output += '<li class="' + li_first + ' small-icon prev_arrow">';
		output += '<a href="#' + insert_id + '" onclick="update_calendar(' + (month - 1) + ', ' + day + ', ' + year + ');"></a> ';
		output += '</li>';
		li_first = '';
	}
	for (i = 1; i <= calendarDays[month]; i++)
	{
		output += '<li class="' + li_first + (i == currentDay && month == currentMonth && year == currentYear ? 'bg1' : 'bg2') + '" id="' + class_day + i + '"></li>';
		if (li_first != '')
		{
			li_first = '';
		}
	}
	if ((i + startDay) % 7 != 0)
	{
		output += '<li class="small-icon next_arrow">';
		output += '<a href="#' + insert_id + '" onclick="update_calendar(' + (month + 1) + ', ' + day + ', ' + year + ');"></a> ';
		output += '</li>';
	}
	output += '</ol></form>';
	return output;
}

function update_calendar(new_month, day, year)
{
	if (new_month < 0)
	{
		new_month = 11;
		year--;
	} else if (new_month > 11)
	{
		new_month = 0;
		year++;
	}
	fill_div(insert_id, build_calendar(new_month, day, year));
	fill_calendar(new_month, year);
}

function fill_day(day, content)
{
	fill_div(class_day + day, content);
}

function select_date(formField)
{
	// get the reference to the target element and setup the date
	targetDateField = formField;
	var dateString = targetDateField.value;

	if (dateString != '')
	{
		// convert the user format of the date into something we can parse to make a javascript Date object
		// we need to pad with placeholders to get the rigth offset
		tmp_format = calendarFormat.replace(/m/i, 'mm').replace(/d/i, 'dd').replace(/y/i, 'yyyy');
		tmp_yOffset = tmp_format.indexOf('y');
		tmp_mOffset = tmp_format.indexOf('m');
		tmp_dOffset = tmp_format.indexOf('d');
		var today = new Date(dateString.substring(tmp_yOffset, tmp_yOffset + 4), dateString.substring(tmp_mOffset, tmp_mOffset + 2) - 1, dateString.substring(tmp_dOffset, tmp_dOffset + 2));
		if ((today == "Invalid Date") || (isNaN(today)))
		{
   			var today = new Date();
		}
	} else
	{
		var today = new Date();
	}
	day = today.getDate();
	month = today.getMonth();
	year  = y2k(today.getYear());
	fill_div(insert_id, build_calendar(month, day, year));
	fill_calendar(month, year);
	document.getElementById(insert_id).style.display = 'block';
}

function fill_calendar(month, year)
{
	for (i = 1; i <= calendarDays[month]; i++)
	{
		fill_day(i, '<a href="javascript: void(0);" onclick="sendDate(' + (month + 1) + ', ' + i + ', ' + year + ');" title="' + formatDate(month + 1, i, year) + '">' + i + '</a>');
	}	
}

function sendDate(month, day, year) 
{
	targetDateField.value = formatDate(month, day, year);
	var dest = document.getElementById(insert_id);
	dest.style.display = 'none';
}
