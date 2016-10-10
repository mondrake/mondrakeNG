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

  /**
   * Loads the properties of all columns of a table, reversing from the DBMS
   *
   * @param object $dbolE the DbolEntry object representing the table
   *
   * @return void
   */
  static public function fetchAllColumnsProperties($name, $tableName, $dbolE) {
    // stores the lists of table columns & types, and
    // an array with full details by column name
    $tableColumns = static::$connections[$name]['connection']->query(static::$connections[$name]['connection']->getDatabasePlatform()->getListTableColumnsSQL($tableName))->fetchAll();
    $res = static::$connections[$name]['dbms']->tableInfo($tableName, $tableColumns);
    $j = 0;
    foreach ($res as $a => $b) {
      $dbolE->columns[] = $b['name'];
      $dbolE->columnTypes[$b['name']] = $b['dboltype'];

      $colDets = array();
      // set seq property
      $colDets['seq'] = $j;
      // set type property
      $colDets['type'] = $b['dboltype'];
      // set nullable property
      $colDets['nullable'] = $b['nullable'];
      // set length/decimal property
      if (strstr($b['length'], ',')) {
        list($colDets['length'], $colDets['decimal']) = explode(",", $b['length']);
      }
      else {
        $colDets['length'] = $b['length'];
      }
      // set default property
      $colDets['default'] = $b['default'];
      // set Autoincrement properties
      if ($b['autoincrement'] == 1) {
        $dbolE->AIColumns[] = $b['name'];
        $colDets['autoIncrement'] = true;
        $colDets['editable'] = false;
      } else {
        $colDets['autoIncrement'] = false;
        $colDets['editable'] = true;
      }
      // set Primary Key properties
      if ($b['primaryKey']) {
        $dbolE->PKColumns[] = $b['name'];
        $colDets['primaryKey'] = true;
      } else {
        $colDets['primaryKey'] = false;
      }
      // set Comment
      if (isset($b['comment'])) {
        $colDets['comment'] = $b['comment'];
      }
      // set audit property
      if ($dbolE->tableProperties['auditLogLevel'] > Dbol::DBOL_ROW_AUDIT) {
        $colDets['auditLog'] = true;
      }
      $dbolE->columnProperties[$b['name']] = $colDets;
      $j++;
    }
  }

}
