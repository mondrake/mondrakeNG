<?php

require_once 'MMObj.php';

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