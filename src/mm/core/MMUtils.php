<?php

namespace mondrakeNG\mm\core;
  
use mondrakeNG\mm\classes\MMDbCache;
use mondrakeNG\mm\classes\MMSqlStatement;
 
class MMUtils	{
	public static function date()	{
		return gmdate('Y-m-d');
	}
    
	public static function dateOffset($date, $offset)	{
		return gmdate('Y-m-d', (strtotime($date) + ($offset * 3600 * 24)));
	}
	
	public static function timestamp()	{
		return gmdate('Y-m-d H:i:s');
	}

	public static function generateToken($len) {
		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789[]!@#";
		$charsLen = strlen($chars);
		srand((double)microtime()*1000000);
		$i = 0;
		$pass = '' ;
		while ($i <= ($len - 1)) {
			$num = rand() % ($charsLen - 1);
			$tmp = substr($chars, $num, 1);
			$pass = $pass . $tmp; 
			$i++;
		}
		return md5($pass);
	}	

	public static function retrieveSqlStatement($stmtKey, $params) {
		$stObj = new MMSqlStatement;
		$stObj->read($stmtKey);
		$sqlq = $stObj->sql_text;
//		$sqlr = $this->query("SELECT sql_text FROM #pfx#mm_sql_stmts WHERE sql_id = '$stmtKey'");
//		$sqlq = $sqlr[0]['sql_text'];
		foreach($params as $a => $b) {
			$sqlq = str_replace($a, $b, $sqlq);
		}
		return $sqlq;
	}
	
	public static function getCache($cacheId)	{
		$c = new MMDbCache;
		$r = $c->read($cacheId);
		if ($r) 
			return unserialize($c->data); 
		else
			return null;
	}	

	public static function setCache($cacheId, $data)	{
		$c = new MMDbCache;
		if ($c->read($cacheId))	{
			$c->data = serialize($data);
			$c->update();
		}
		else	{
			$c->cache_id = $cacheId;
			$c->data = serialize($data);
			$c->create();
		}
	}	
	
}



