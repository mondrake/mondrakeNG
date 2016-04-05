<?php

require_once 'MMObj.php';

class AXAccountPeriodBalance extends MMObj {

    public function setPKColumns()     {
        $PKColumns = array("environment_id", "balance_type", "account_id", "period_type_id", "period_year", "period");
        return self::$dbol->setPKColumns(self::$dbObj[$this->className], $PKColumns);
	}
    
 
}