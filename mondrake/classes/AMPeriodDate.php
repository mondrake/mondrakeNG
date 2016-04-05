<?php

require_once 'MMObj.php';

class AMPeriodDate extends MMObj 
{
    public function defineChildObjs()    {
        if (!isset(self::$childObjs[$this->className])) {
            self::$childObjs[$this->className] = array(
                'datePeriod' => array (
                    'className'   => 'AMPeriod',
                    'cardinality' => 'one',
                    'whereClause' => 'period_type_id = #0# and period_year = #1# and period = #2#',
                    'parameters'  => array('period_type_id', 'period_year', 'period',),
                    'loading'     => 'onRead',
                ),
            );
        }
    }  
    public function getOffsetDate($offset) {
        $i = new self;
        $dtOffset = MMUtils::dateOffset($this->period_date, $offset);
        $i->read($this->period_type_id, $dtOffset);
        return $i;
    }
}