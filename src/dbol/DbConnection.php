<?php

namespace mondrakeNG\dbol;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Version as DBALVersion;

class DbConnection {

  static protected $connections = [];

  static public function setConnection($name, array $dsn, array $connectionParams = null) {
    // connects
    // @todo $connectionParams??
    $connection = DriverManager::getConnection($dsn, new Configuration());
    // loads and instantiates DBMS mapper
    $dbms_class = '\\mondrakeNG\\dbol\\DBMS\\' . ucfirst($connection->getDriver()->getDatabasePlatform()->getName());
    static::$connections[$name] = [
      'connection' => $connection,
      'dbms' => new $dbms_class(),
    ];
  }

  static public function getConnection($name) {
    return static::$connections[$name];
  }

  /**
   * Gets the DBAL version string
   *
   * @return string DBAL version string
   */
  static public function getDBALVersion() {
    return DBALVersion::VERSION;
  }

  /**
   * Gets the DBAL driver name
   *
   * @return string DBAL driver name
   */
  static public function getDBALDriver($name) {
    return static::$connections[$name]['connection']->getDriver()->getName();
  }

  /**
   * Gets the database server name
   *
   * @return string database server name
   */
  static public function getDbServerName($name) {
    return static::$connections[$name]['connection']->getDriver()->getDatabasePlatform()->getName();
  }

  /**
   * Gets the database server version
   *
   * @return string database server version string
   */
  static public function getDbServerVersion($name) {
    return static::$connections[$name]['connection']->getWrappedConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION); // @todo if not PDO??
  }

  /**
   * Return an array with all the tables of the db dbol is connected to
   *
   * @return array array with all the db tables
   */
  static public function fetchAllTables($name) {
    $tables = static::$connections[$name]['connection']->query(static::$connections[$name]['dbms']->getListTablesSQL());
    return static::$connections[$name]['dbms']->listTables($tables);
  }

}
