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
   * Gets the db connection
   *
   * @return @todo
   */
  static public function getDbConnection($name) {
    if (isset(static::$connections[$name])) {
      return static::$connections[$name]['connection'];
    }
    else {
      throw new \Exception("Db connection {$name} not found");
    }
  }

  /**
   * Gets the DBAL driver name
   *
   * @return string DBAL driver name
   */
  static public function getDBALDriver($name) {
    return static::getDbConnection($name)->getDriver()->getName();
  }

  /**
   * Gets the database server name
   *
   * @return string database server name
   */
  static public function getDbServerName($name) {
    return static::getDbConnection($name)->getDriver()->getDatabasePlatform()->getName();
  }

  /**
   * Gets the database server version
   *
   * @return string database server version string
   */
  static public function getDbServerVersion($name) {
    return static::getDbConnection($name)->getWrappedConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION); // @todo if not PDO??
  }

  /**
   * Return an array with all the tables of the db dbol is connected to
   *
   * @return array array with all the db tables
   */
  static public function fetchAllTables($name) {
    $db_connection = static::getDbConnection($name);
    $tables = $db_connection->query(static::$connections[$name]['dbms']->getAllTablesSQL());
    return static::$connections[$name]['dbms']->mapTables($tables);
  }

  /**
   * Loads the properties of all columns of a table, reversing from the DBMS
   *
   * @param object $dbolE the DbolEntry object representing the table
   *
   * @return void
   */
  static public function fetchAllColumnsProperties($name, $table_name, $dbolE) {
    $db_connection = static::getDbConnection($name);
    // stores the lists of table columns & types, and
    // an array with full details by column name
    $table_columns = $db_connection->query($db_connection->getDatabasePlatform()->getListTableColumnsSQL($table_name))->fetchAll();
    return static::$connections[$name]['dbms']->mapTableColumns($table_name, $table_columns, $dbolE);
  }

}
