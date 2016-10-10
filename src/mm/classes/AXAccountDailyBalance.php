<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class AXAccountDailyBalance extends MMObj {

    public function setPKColumns()     {
        $PKColumns = array("environment_id", "balance_type", "account_id", "period_type_id", "period_date");
        return self::$dbol->setPKColumns(self::$dbObj[$this->className], $PKColumns);
	}

}
