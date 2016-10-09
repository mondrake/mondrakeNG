<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class AMPeriod extends MMObj
{
    public function getPeriodFromDate($periodTypeId, $date) {
        return $this->readSingle("period_type_id = $periodTypeId and first_period_date <= '$date' and last_period_date >= '$date'");
    }
    public function getPeriodFromSeq($periodTypeId, $seq) {
        return $this->readSingle("period_type_id = $periodTypeId and period_seq = $seq");
    }
    public function getOffsetPeriod($offset) {
        $i = clone $this;
        if ($offset < 0) {
            while ($offset < 0) {
                $i = $i->getPeriodFromSeq($i->period_type_id, $i->prev_period_seq);
                if (!$i) {
                    return null;
                }
                $offset++;
            }
        } else if ($offset > 0) {
            while ($offset > 0) {
                $i = $i->getPeriodFromSeq($i->period_type_id, $i->next_period_seq);
                if (!$i) {
                    return null;
                }
                $offset--;
            }
        }
        return $i;
    }
}
