<?php

use mondrakeNG\dbol\DbConnection;

define('DB_TABLEPREFIX', "");
define('DB_DECIMALPRECISION', 10);
define('DB_QUERY_PERFORMANCE_LOGGING', true);
define('DB_QUERY_PERFORMANCE_THRESHOLD', 100);
define('IP_LOCATION_SERVICE_ENABLED', true);
define('IP_LOCATION_SERVICE_KEY', '8628aa053a9cccabd338ff7089b853362c518b5a8182a0a73af01e9879fc4866');
define('MM_CLASS_PATH', '\\mondrakeNG\\mm\\classes\\');

$dsn = [
  'driver' => 'pdo_mysql',
  'dbname' => 'mondrak1_mm',
  'user' => 'mondrak1_mmadmin',
  'password' => 'pv07R.adk?@)',
  'host' => 'localhost',
  'port' => '3306',
  'charset' => 'utf8',
];

DbConnection::setConnection('MM', $dsn);
