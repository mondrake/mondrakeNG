<?php

require_once 'MMObj.php';

class AXPortfolioItem extends MMObj {

    public function defineChildObjs()    {
		if (!isset(self::$childObjs[$this->className])) {
			self::$childObjs[$this->className] = array(
				'item' => array (
					'className'  		=>	'AXItem',
					'cardinality'  		=>	'one',
					'parameters'		=>  array( 'item_id', ),
					'loading'			=>	'onRead',
				),
			);
		}
    }  
}