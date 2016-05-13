<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class AMDocType extends MMObj 
{
    public function isBalanceOpening($docTypeId) 
    {
        return ($docTypeId == 7) ? true : false;
    }

    public function isBudget($docTypeId) 
    {
        return false;    
    }
}