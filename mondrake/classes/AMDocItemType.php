<?php

class AMDocItemType
{
    public function isCredit($docItemTypeId) 
    {
        return ($docItemTypeId == 'A') ? true : false;
    }
}