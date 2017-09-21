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
	private $user_tz;

	function __construct($data,$user_tz)
	{
		$this->user_tz = $user_tz;
		$this->data    = $data;
		$this->set_event_data();
	}

	private function set_event_data(){
		$this->event_name = $this->data->name?$this->data->name:'';

		if(isset($this->data->time) && $this->data->time != '') {
			$given_timestamp = date('Y-m-d h:i a', $this->data->time/1000);
			$schedule_date = new DateTime($given_timestamp, new DateTimeZone('UTC'));
			$schedule_date->setTimeZone(new DateTimeZone($this->user_tz));
			$this->event_time = $schedule_date->format('Y-m-d H:i');
		}else {
			$this->event_time = '';
		}

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
		$schedule_date = $schedule_date->setTimeZone(new DateTimeZone($this->user_tz));
		$date1 = $schedule_date->format('h:i a');
		$day = strtolower($schedule_date->format('l'));

		/*If event*/
		if($day == 'sunday' || $day =='saturday'){
			return true;
		}else{

			//check if event is in between given interval
			$date1 = DateTime::createFromFormat('h:i a', $date1);
			$date2 = DateTime::createFromFormat('h:i a', $this->start_time);
			$date3 = DateTime::createFromFormat('h:i a', $this->end_time);
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
				$event = new Event($data,$this->user_tz);
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
?>
<html>
<head>
	<title>Find Meetups Near You : bivek.ca</title>
	<meta name="Description" content="Find Meetups Near You, Bivek Joshi Full Stack Web Developer Toronto Ontario, Experienced in Laravel, Expressjs, Reactjs" />
	<meta name="Keywords" content="Find Meetups Near You, Bivek Joshi, Full Stack,  FullStack, Web Developer, Toronto, Ontario, Laravel, Expressjs, Reactjs" />
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
		.shownote{
			font-size:11px;
		}
	</style>
</head>
<body>
	<div class="row">
		<form name="FindNearbyMeetupup" id="FindNearbyMeetupup" method="get" target="">
			<table class="table-responsive">
				<tr>
					<th colspan="2">Search Nearby meetup</th>
				</tr>
				<tr>
					<td>Radius</td>
					<td><select name="radius">
							<?php
							for ($i=1;$i<=15;$i++){
								?>
								<option value="<?php echo $i;?>" <?php echo (isset($_GET['radius']) && $_GET['radius']==$i)?'selected':'';?>><?php echo $i;?> km(s)</option>
							<?php
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<td colspan="2"><input type="button" onclick="tryGeolocation();/*getLocation();*/" value="Get your latitude and longitude" /> <span id="showmsg" class="shownote"></span></td>
				</tr>
				<tr>
					<td>Your Latitude</td>
					<td><input type="text" name="latitude" id="latitude" value="<?php echo (isset($_GET['latitude']) && $_GET['latitude']!='')?$_GET['latitude']:'';?>" /></td>
				</tr>
				<tr>
					<td>Your Longitude</td>
					<td><input type="text" name="longitude" id="longitude" value="<?php echo (isset($_GET['longitude']) && $_GET['longitude']!='')?$_GET['longitude']:'';?>" /></td>
				</tr>
				<tr>
					<td>Start time: </td>
					<td>
						<select name="start_time">
							<option value="0">Select Start Time</option>
							<?php
							foreach(array('am','pm') as $val){
								for($i=1;$i<=12;$i++){
									for($j=0;$j<60;$j+=15){
										?><option value="<?php echo $i;?>:<?php echo str_pad($j, 2, "0", STR_PAD_LEFT);?> <?php echo $val;?>" <?php echo (isset($_GET['start_time']) && $_GET['start_time'] == $i.':'.str_pad($j, 2, "0", STR_PAD_LEFT).' '.$val)?'selected':'';?>><?php echo $i;?>:<?php echo str_pad($j, 2, "0", STR_PAD_LEFT);?> <?php echo $val;?></option>
										<?php
									}
								}
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<td>End time: </td>
					<td>
						<select name="end_time">
							<option value="0">Select End Time</option>
							<?php
							foreach(array('am','pm') as $val){
								for($i=1;$i<=12;$i++){
									for($j=0;$j<60;$j+=15){
										?><option value="<?php echo $i;?>:<?php echo str_pad($j, 2, "0", STR_PAD_LEFT);?> <?php echo $val;?>" <?php echo (isset($_GET['end_time']) && $_GET['end_time'] == $i.':'.str_pad($j, 2, "0", STR_PAD_LEFT).' '.$val)?'selected':'';?>><?php echo $i;?>:<?php echo str_pad($j, 2, "0", STR_PAD_LEFT);?> <?php echo $val;?></option>
										<?php
									}
								}
							}
							?>
						</select>&nbsp;<span class="shownote">End time will be considered the next day if its less than start time.</span>
					</td>
				</tr>
				<tr>
					<td colspan="2"><input type="submit" name="s" value="Search Meetups" /></td>
				</tr>
		</table>
		</form>
	</div>
	<div>URL params supported: <?php echo $current_page_link;?>?radius=1&latitude=43.668605&longitude=-79.371928&start_time=urlencode(7:30 pm)&end_time=urlencoded(7:30 am)<br />
		example url: <?php echo $current_page_link;?>?radius=1&latitude=43.668605&longitude=-79.371928&start_time=7%3A30%20pm&end_time=7%3A30%20am<br />
		more help: <a href='https://github.com/8ivek/findMeetupsNearYou' target='_blank'>https://github.com/8ivek/findMeetupsNearYou</a>
	</div>
	<?php
	//8iv: JSON URL Format for latitude and longitude is contracted so do not change it.
	$json_url = 'https://api.meetup.com/find/events?photo-host=public&sig_id=70812202&radius='.$radius.'&lat='.$latitude.'&lon='.$longitude.'&sig=4f5c3097f04d92d1cd527864ede0f8f68cdd970f';
	$eventInfo = new EventInfo('America/Toronto',$json_url,$start_time,$end_time);
	?>
	<table border='0' cellpadding='5' cellspacing='5' width='100%'>
		<?php $eventInfo->displayEvents();?>
	</table>

	<script type="text/javascript">
		var x = document.getElementById("showmsg");
		var lat = document.getElementById("latitude");
		var lon = document.getElementById("longitude");

		var apiGeolocationSuccess = function(position) {
			x.innerHTML = "Your Latitude: " + position.coords.latitude + ", Your Longitude: " + position.coords.longitude;
			lat.value = position.coords.latitude;
			lon.value = position.coords.longitude;
			//alert("API geolocation success!\n\nlat = " + position.coords.latitude + "\nlng = " + position.coords.longitude);
		};

		var tryAPIGeolocation = function() {
			jQuery.post( "https://www.googleapis.com/geolocation/v1/geolocate?key=AIzaSyDCa1LUe1vOczX1hO_iGYgyo8p_jYuGOPU", function(success) {
				apiGeolocationSuccess({coords: {latitude: success.location.lat, longitude: success.location.lng}});
			})
				.fail(function(err) {
					alert("API Geolocation error! \n\n"+err);
				});
		};

		var browserGeolocationSuccess = function(position) {
			x.innerHTML = "Your Latitude: " + position.coords.latitude + ", Your Longitude: " + position.coords.longitude;
			lat.value = position.coords.latitude;
			lon.value = position.coords.longitude;
			//alert("Browser geolocation success!\n\nlat = " + position.coords.latitude + "\nlng = " + position.coords.longitude);
		};

		var browserGeolocationFail = function(error) {
			switch (error.code) {
				case error.TIMEOUT:
					alert("Browser geolocation error !\n\nTimeout.");
					break;
				case error.PERMISSION_DENIED:
					if(error.message.indexOf("Only secure origins are allowed") == 0) {
						tryAPIGeolocation();
					}
					break;
				case error.POSITION_UNAVAILABLE:
					alert("Browser geolocation error !\n\nPosition unavailable.");
					break;
			}
		};

		var tryGeolocation = function() {
			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition(
					browserGeolocationSuccess,
					browserGeolocationFail,
					{maximumAge: 50000, timeout: 20000, enableHighAccuracy: true});
			}
		};


		/*function getLocation() {
			if (navigator.geolocation) {
				navigator.geolocation.watchPosition(showPosition);
			} else {
				x.innerHTML = "Geolocation is not supported by this browser.";
			}
		}

		function showPosition(position) {
			x.innerHTML = "Your Latitude: " + position.coords.latitude + ", Your Longitude: " + position.coords.longitude;
			lat.value = position.coords.latitude;
			lon.value = position.coords.longitude;
		}*/
	</script>
</body>
</html>