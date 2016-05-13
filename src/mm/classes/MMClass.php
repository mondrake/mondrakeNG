<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class MMClass extends MMObj {
 
	public function getClassFromTableName($id) {
		return $this->readSingle("db_table_name = '$id'" );
	}

}