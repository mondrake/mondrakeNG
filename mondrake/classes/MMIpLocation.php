<?php

require_once 'MMObj.php';
require_once('ipinfodb.class.php');

class MMIpLocation extends MMOBj {
 
	public function getAddress($addr)	{
		return $this->readSingle("remote_address = '$addr'");
	}
	
	public function locate($addr)	{

		if(IP_LOCATION_SERVICE_ENABLED == false)
			return; 

		$res = $this->getAddress($addr);

		if(count($res) == 0 or (count($res) == 1 and ((strtotime(gmdate('Y-m-d H:i:s')))-strtotime($this->last_resolution_ts)) > (3600*24*7))) 	{
			$ipinfodb = new ipinfodb;
			$ipinfodb->setKey(IP_LOCATION_SERVICE_KEY);
		 
			//Get errors and locations
			$parms = $ipinfodb->getGeoLocation($addr);
			$errors = $ipinfodb->getError();

			$this->remote_address = $addr;
			$this->last_resolution_ts = gmdate('Y-m-d H:i:s');
			if (!empty($parms) && is_array($parms)) {
				foreach ($parms as $parm => $val) {
					switch ($parm)	{
						case 'Status':
							$this->last_resolution_status =  $val;
							break;
						case 'CountryCode':
							$this->country_id =  $val;
							break;
						case 'RegionCode':
							$this->region_id =  $this->country_id . $val;
							break;
						case 'RegionName':
							$this->region_desc =  $val;
							break;
						case 'City':
							$this->city_desc =  $val;
							break;
						case 'ZipPostalCode':
							$this->zip_desc =  $val;
							break;
						case 'Latitude':
							$this->latitude =  $val;
							break;
						case 'Longitude':
							$this->longitude =  $val;
							break;
						case 'Timezone':
							$this->timezone =  $val;
							break;
						case 'TimezoneName':
							$this->timezone_name =  $val;
							break;
						case 'Gmtoffset':
							$this->gmt_offset =  $val;
							break;
						case 'Dstoffset':
							$this->dst_offset =  $val;
							break;
						case 'Isdst':
							$this->is_dst =  $val;
							break;
						case 'Ip':
							$this->ip_address =  $val;
							break;
					}
				}
			}
			if(count($res) == 0) 	{
				$this->create();
			}
			else	{
				$this->update();
			}
		}
	}

}