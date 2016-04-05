<?php

require_once 'MMObj.php';
require_once 'MMClientCtl.php';

class MMClient extends MMObj {

	public function __construct() {	
		parent::__construct();
		self::setColumnProperty(array ('client_token'),
			'editable', FALSE);
	}

    public function defineChildObjs()    {
		if (!isset(self::$childObjs[$this->className])) {
			self::$childObjs[$this->className] = array(
				'clientCtl' => array (
					'className'  		=>	'MMClientCtl',
					'cardinality'  		=>	'one',
					'parameters'		=>  array( 'client_id', ),
					'loading'			=>	'onRead',
					'onDeleteCascade'	=>  true,
					'onCreateCallback'	=>  'clientCtlInit',
				),
			);
		}
    }  

	public function clientCtlInit(){
		$this->clientCtl->client_id = $this->client_id;
	}

	public function create() {
		$this->client_token = MMUtils::generateToken(20);
		$res = parent::create();
	}
	
	public function getClientFromToken($token) {
		$res = $this->readSingle("client_token = '$token'" );
//		if($res) $this->read($this->client_id);
		return $this;
	}
}