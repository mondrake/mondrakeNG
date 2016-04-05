<?php

require_once 'MMObj.php';

class MMClass extends MMObj {
 
	public function getClassFromTableName($id) {
		return $this->readSingle("db_table_name = '$id'" );
	}

}