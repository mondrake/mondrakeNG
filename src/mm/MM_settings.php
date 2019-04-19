<?php

use mondrakeNG\dbol\DbConnection;

const DB_TABLEPREFIX = "";
const DB_DECIMALPRECISION = 10;
const DB_QUERY_PERFORMANCE_LOGGING = true;
const DB_QUERY_PERFORMANCE_THRESHOLD = 100;
const MM_CLASS_PATH = '\\mondrakeNG\\mm\\classes\\';

$dsn = [
  'driver' => 'pdo_mysql',
  'dbname' => 'mm',
  'user' => 'admin',
  'password' => 'admin',
  'host' => 'localhost',
  'port' => '3306',
  'charset' => 'utf8',
];

DbConnection::setConnection('MM', $dsn);

