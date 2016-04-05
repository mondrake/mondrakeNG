<?php

require_once 'MMObj.php';

class MMEnvironmentUser extends MMObj {
 
	public function getUserEnvironments($id) {
		return $this->readMulti("user_id = $id");
	}

}