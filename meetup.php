<?php
error_reporting(E_ALL & ~E_NOTICE );
date_default_timezone_set('UTC');

class Event{
	public $event_name;
	public $event_time;
	public $event_yes_rsvp_count;
	public $event_venue;
	public $event_latitude;
	public $event_longitude;
	public $event_meetup_group;
	public $event_link;
	public $event_description;
	public $event_how_to_find_us;
	public $event_duration;
	private $data;
	function __construct($data)
	{
		$this->data=$data;
		$this->set_event_data();
	}

	private function set_event_data(){
		$this->event_name = $this->data->name?$this->data->name:'';
		$this->event_time = $this->data->time?date('Y-m-d h:i a',($this->data->time/1000)):'';
		$this->event_yes_rsvp_count = $this->data->yes_rsvp_count?$this->data->yes_rsvp_count:'';
		$this->event_venue = $this->data->venue->name?$this->data->venue->name:'';
		$this->event_venue .= $this->data->venue->city?','.$this->data->venue->city:'';
		$this->event_venue .= $this->data->venue->localized_country_name?','.$this->data->venue->localized_country_name:'';
		$this->event_venue .= $this->data->venue->state?','.$this->data->venue->state:'';

		$this->event_latitude = $this->data->venue->lat?$this->data->venue->lat:'';
		$this->event_longitude = $this->data->venue->lon?$this->data->venue->lon:'';

		$this->event_meetup_group = $this->data->group->name?$this->data->group->name:'';

		$this->event_link = $this->data->link?'<a target="_blank" href="'.$this->data->link.'">'.$this->data->link.'</a>':'';
		$this->event_description = $this->data->description?$this->data->description:'';
		$this->event_how_to_find_us= $this->data->how_to_find_us?$this->data->how_to_find_us:'';
		$this->event_duration= $this->data->duration?($this->data->duration/3600000).' hr(s)':'';
	}

	public function format_event_data(){
		$formatted_data = "
		<tbody>
			<tr>
				<td>Name</td>
				<th>".$this->event_name."</th>
			</tr>
			<tr>
				<td>Time</td>
				<td>".$this->event_time."</td>
			</tr>
			<tr>
				<td>Rsvp</td>
				<td>".$this->event_yes_rsvp_count."</td>
			</tr>
			<tr>
				<td>Venue</td>
				<td>".$this->event_venue."</td>
			</tr>
			<tr>
				<td>Latitude</td>
				<td>".$this->event_latitude."</td>
			</tr>
			<tr>
				<td>Longitude</td>
				<td>".$this->event_longitude."</td>
			</tr>
			<tr>
				<td>Meetup Group</td>
				<td>".$this->event_meetup_group."</td>
			</tr>
			<tr>
				<td>Link</td>
				<td>".$this->event_link."</td>
			</tr>
			<tr>
				<td>Description</td>
				<td>".$this->event_description."</td>
			</tr>
			<tr>
				<td>How to Find Us</td>
				<td>".$this->event_how_to_find_us."</td>
			</tr>
			<tr>
				<td>Duration</td>
				<td>".$this->event_duration."</td>
			</tr>
		</tbody>
		";
		return $formatted_data;
	}

}

class EventInfo{

	private $user_tz;
	private $ignored_events;
	private $events_found;
	private $json_url;
	private $start_time;
	private $end_time;

	function __construct($user_tz,$json_url,$start_time,$end_time){
		$this->user_tz = $user_tz;
		$this->json_url = $json_url;
		$this->events_found = 0;
		$this->ignored_events = 0;
		$this->start_time = $start_time;
		$this->end_time = $end_time;
	}

	/**
	 * @param $given_timestamp => utc time coz it comes from database
	 * @param $starttime => toronto time coz its user set time.
	 * @param $endtime => toronto time coz its user set time.
	 * @return bool
	 */
	private function checkEventTime($given_timestamp){

		$given_timestamp = date('Y-m-d h:i a',$given_timestamp);
		$schedule_date = new DateTime($given_timestamp, new DateTimeZone('UTC') );
		$schedule_date->setTimeZone(new DateTimeZone($this->user_tz));
		$given_timestamp = $schedule_date->getTimestamp();

		/*If event*/
		$day= strtolower(date('l',$given_timestamp));
		if($day == 'sunday' || $day =='saturday'){
			return true;
		}else{

			//check if event is in between given interval
			$given_timestamp = date('H:i a',$given_timestamp);

			$date1 = DateTime::createFromFormat('H:i a', $given_timestamp);
			$date2 = DateTime::createFromFormat('H:i a', $this->start_time);
			$date3 = DateTime::createFromFormat('H:i a', $this->end_time);
			if($date1 > $date3){
				$date3->modify('+1 day');
			}
			if ($date1 > $date2 && $date1 < $date3){
				return true;
			}else{
				return false;
			}

		}
	}

	public function displayEvents(){
		$json = file_get_contents($this->json_url);
		$obj = json_decode($json);
		$this->ignored_events = 0;
		$this->events_found = 0;
		foreach ( $obj as $key=>$data) {
			$given_timestamp = $data->time/1000;
			if($this->checkEventTime($given_timestamp)){
				$this->events_found++;
				$event = new Event($data);
				echo $event->format_event_data();
			}else{
				$this->ignored_events++;
			}
		}
		echo "<tr><th colspan='2'>".$this->events_found." events found and ".$this->ignored_events." events ignored out of ".count($obj)."</th></tr>";
	}
}

if(isset($_GET['radius'])){
	$radius = $_GET['radius'];
}else{
	$radius = 1;
}

if(isset($_GET['latitude'])){
	$latitude = $_GET['latitude'];
}else{
	$latitude = 43.668605;
}

if(isset($_GET['longitude'])){
	$longitude = $_GET['longitude'];
}else{
	$longitude = -79.371928;
}

if(isset($_GET['start_time'])){
	$start_time = urldecode($_GET['start_time']);
}else{
	$start_time = '7:30 pm';
}

if(isset($_GET['end_time'])){
	$end_time = urldecode($_GET['end_time']);
}else{
	$end_time = '7:30 am';
}
$uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
$current_page_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$uri_parts[0]";
echo "<div>URL params supported: ".$current_page_link."?radius=1&latitude=43.668605&longitude=-79.371928&start_time=urlencode(7:30 pm)&end_time=urlencoded(7:30 am)<br />
example url: ".$current_page_link."?radius=1&latitude=43.668605&longitude=-79.371928&start_time=7%3A30%20pm&end_time=7%3A30%20am<br />
more help: <a href='https://github.com/8ivek/findMeetupsNearYou' target='_blank'>https://github.com/8ivek/findMeetupsNearYou</a>
</div>";

//8iv: JSON URL Format for latitude and longitude is contracted so do not change it.
$json_url = 'https://api.meetup.com/find/events?photo-host=public&sig_id=70812202&radius='.$radius.'&lat='.$latitude.'&lon='.$longitude.'&sig=4f5c3097f04d92d1cd527864ede0f8f68cdd970f';
$eventInfo = new EventInfo('America/Toronto',$json_url,$start_time,$end_time);
echo "<table border='0' cellpadding='5' cellspacing='5' width='100%'>";
$eventInfo->displayEvents();
echo "</table>";

?>
<style type="text/css">
	table {
		font-family: arial, sans-serif;
		border-collapse: collapse;
		width: 100%;
	}

	td, th {
		border: 1px solid #dddddd;
		text-align: left;
		padding: 8px;
	}

	tbody:nth-child(even) {
		background-color: #dddddd;
	}
</style>
