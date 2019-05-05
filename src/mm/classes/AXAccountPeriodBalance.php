<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class AXAccountPeriodBalance extends MMObj
{

    public function setPKColumns()
    {
        $PKColumns = ["environment_id", "balance_type", "account_id", "period_type_id", "period_year", "period"];
        return self::$dbol->setPKColumns(self::$dbObj[$this->className], $PKColumns);
    }
}
