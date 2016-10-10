<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class AXPortfolioItem extends MMObj {

    public function defineChildObjs()    {
		if (!isset(self::$childObjs[$this->className])) {
			self::$childObjs[$this->className] = array(
				'item' => array (
					'className'  		=>	MM_CLASS_PATH . 'AXItem',
					'cardinality'  		=>	'one',
					'parameters'		=>  array( 'item_id', ),
					'loading'			=>	'onRead',
				),
			);
		}
    }
}
