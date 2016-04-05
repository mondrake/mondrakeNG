<?php

require_once 'MMObj.php';

class MMSettingValue extends MMObj {
 
	public function getUserSetting($id, $settingId) {
		$this->readSingle("entity_id = 'USR' and id = $id and setting_id = '$settingId'");
		return $this->setting_value;
	}

}