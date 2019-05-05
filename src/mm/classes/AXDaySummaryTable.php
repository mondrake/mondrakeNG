<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMRbppavlInterface;

class AXDaySummaryTable extends MMRbppavlInterface
{
    public function compare($a, $b)
    {
        return ($a->date == $b->date) ? 0 : (($a->date > $b->date) ? 1 : -1);
    }

    public function dump($a)
    {
        return $a->date;
    }
}
