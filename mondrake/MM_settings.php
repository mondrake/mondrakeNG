<?php
set_include_path('.' . PATH_SEPARATOR . 
				'/home/mondrak1/php' . PATH_SEPARATOR . 
				'/home/mondrak1/public_html/dev03/services' . PATH_SEPARATOR .
				'/home/mondrak1/private/mondrakeNG/dbol' . PATH_SEPARATOR .
				'/home/mondrak1/public_html/qa01/rbppavl/Rbppavl' . PATH_SEPARATOR . 
				'/home/mondrak1/public_html/lab05/vendor/doctrine/dbal/lib/Doctrine/DBAL' . PATH_SEPARATOR . // @todo nooo    
				'/home/mondrak1/private/mondrakeNG/mondrake/core' . PATH_SEPARATOR .
				'/home/mondrak1/private/mondrakeNG/mondrake/classes');

				
define('DB_DBAL', "PDO"); 
define('DB_DRIVER', "pdo_mysql");
define('DB_USERNAME', "mondrak1_mmadmin");
define('DB_PASSWORD',  "pv07R.adk?@)");  
define('DB_SERVER', "localhost");
define('DB_PORT', "3306");
define('DB_DATABASENAME', "mondrak1_mm");
define('DB_CHARSET', "utf8");
define('DB_TABLEPREFIX', "");
define('DB_DECIMALPRECISION', 10);
define('DB_QUERY_PERFORMANCE_LOGGING', true);
define('DB_QUERY_PERFORMANCE_THRESHOLD', 500); 
define('IP_LOCATION_SERVICE_ENABLED', true);
define('IP_LOCATION_SERVICE_KEY', '8628aa053a9cccabd338ff7089b853362c518b5a8182a0a73af01e9879fc4866');
?>