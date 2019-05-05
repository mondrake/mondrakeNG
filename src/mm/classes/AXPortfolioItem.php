<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class AXPortfolioItem extends MMObj
{

    public function defineChildObjs()
    {
        if (!isset(self::$childObjs[$this->className])) {
            self::$childObjs[$this->className] = [
                'item' => [
                    'className'         =>  MM_CLASS_PATH . 'AXItem',
                    'cardinality'       =>  'one',
                    'parameters'        =>  [ 'item_id', ],
                    'loading'           =>  'onRead',
                ],
            ];
        }
    }
}
