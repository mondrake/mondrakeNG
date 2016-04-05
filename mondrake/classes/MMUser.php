<?php

require_once 'MMObj.php';
require_once 'MMSettingValue.php';
require_once 'MMEnvironment.php';

class MMUser extends MMObj	{
 
	public function __construct() {	
		parent::__construct();
		self::setColumnProperty(array ('user_token'),
			'editable', FALSE);
	}

	public function read() {
    $id = func_get_arg(0);
    parent::read($id);
	
		$tmp = new MMSettingValue;
		$this->currentEnvironmentId = $tmp->getUserSetting($this->user_id, "DEFAULT_ENVIRONMENT"); 

		if ($this->currentEnvironmentId)	{
			$this->currentEnvironment = new MMEnvironment;
			$this->currentEnvironment->read($this->currentEnvironmentId);
		}
			
		return $this;
	}

	public function create() {
		$this->user_token = MMUtils::generateToken(20);
		return parent::create();
	}

	public function getUserLogin($id) {
//		$res = $this->readSingle("login_id = '$id'" );
//		if($res) $this->read($this->user_id);
		return $this->readSingle("login_id = '$id'" );
	}

	public function getUserFromToken($token) {
//		$res = $this->readSingle("user_token = '$token'" );
//		if($res) $this->read($this->user_id);
		return $this->readSingle("user_token = '$token'" );
	}
}

?>