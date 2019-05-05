<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class AXPortfolioDailyVal extends MMObj
{

    public function setPKColumns()
    {
        $PKColumns = ["environment_id", "entity_id", "entity_key_01", "period_type_id", "period_date"];
        return self::$dbol->setPKColumns(self::$dbObj[$this->className], $PKColumns);
    }
}
