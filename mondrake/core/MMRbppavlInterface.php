<?php

require_once 'Rbppavl.php';

class MMRbppavlInterface implements RbppavlCbInterface 
{ 
    private $diag = null; 

    public function __construct() 
    {      
        $this->diag = new MMDiag;
    }

    public function compare($a, $b)    
    {
    }

    public function dump($a)    
    {
    }

    public function diagnosticMessage($severity, $id, $text, $params, $qText, $className = null) 
    {
        $params['#text'] = $text;
        if (empty($className))    {
            $className = get_class($this);
        }
        $this->diag->sLog($severity, $className, $id, $params);
    }
    
    public function errorHandler($id, $text, $params, $qText, $className = null) 
    {
    }
}
