<?php

namespace mondrakeNG\dbol;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Version as DBALVersion;

class DbConnection
{

    protected static $connections = [];

    public static function setConnection($name, array $dsn, array $connectionParams = null)
    {
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

    public static function getConnection($name)
    {
        return static::$connections[$name];
    }

  /**
   * Gets the DBAL version string
   *
   * @return string DBAL version string
   */
    public static function getDBALVersion()
    {
        return DBALVersion::VERSION;
    }

  /**
   * Gets the DBAL driver name
   *
   * @return string DBAL driver name
   */
    public static function getDBALDriver($name)
    {
        return static::$connections[$name]['connection']->getDriver()->getName();
    }

  /**
   * Gets the database server name
   *
   * @return string database server name
   */
    public static function getDbServerName($name)
    {
        return static::$connections[$name]['connection']->getDriver()->getDatabasePlatform()->getName();
    }

  /**
   * Gets the database server version
   *
   * @return string database server version string
   */
    public static function getDbServerVersion($name)
    {
        return static::$connections[$name]['connection']->getWrappedConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION); // @todo if not PDO??
    }

  /**
   * Return an array with all the tables of the db dbol is connected to
   *
   * @return array array with all the db tables
   */
    public static function fetchAllTables($name)
    {
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
    public static function fetchAllColumnsProperties($name, $tableName, $dbolE)
    {
      // Get the table details from the DBAL Schema Manager.
        $schema_manager = static::$connections[$name]['connection']->getSchemaManager();
        $columns = $schema_manager->listTableColumns($tableName);
        $indexes = $schema_manager->listTableIndexes($tableName);
        foreach ($indexes as $index) {
            if ($index->isPrimary()) {
                $primary_index_columns = [];
                foreach ($index->getColumns() as $seq => $column_name) {
                    $primary_index_columns[$column_name] = $column_name;
                }
                break;
            }
        }

      // Loops through the columns and build the DBOL entry.
        $j = 0;
        foreach ($columns as $column_name => $column_data) {
            $dbolE->columns[] = $column_name;
            $dbolE->columnTypes[$column_name] = $column_data->getType()->getName();

            $colDets = [];
          // set seq property
            $colDets['seq'] = $j;
          // set type property
            $colDets['type'] = $column_data->getType()->getName();
          // set nullable property
            $colDets['nullable'] = !$column_data->getNotNull();
          // set length/decimal property
            if (in_array($column_data->getType()->getName(), ['float', 'decimal'])) {
                $colDets['length'] = $column_data->getPrecision();
                $colDets['decimal'] = $column_data->getScale();
            } else {
                $colDets['length'] = $column_data->getLength();
            }
          // set default property
            $colDets['default'] = $column_data->getDefault();
          // set Autoincrement properties
            if ($column_data->getAutoincrement() == true) {
                $dbolE->AIColumns[] = $column_name;
                $colDets['autoIncrement'] = true;
                $colDets['editable'] = false;
            } else {
                $colDets['autoIncrement'] = false;
                $colDets['editable'] = true;
            }
          // set Primary Key properties
            if (isset($primary_index_columns[$column_name])) {
                $dbolE->PKColumns[] = $column_name;
                $colDets['primaryKey'] = true;
            } else {
                $colDets['primaryKey'] = false;
            }
          // set Comment
            if (($comment = $column_data->getComment()) !== null) {
                $colDets['comment'] = $comment;
            }
          // set audit property
            if ($dbolE->tableProperties['auditLogLevel'] > Dbol::DBOL_ROW_AUDIT) {
                $colDets['auditLog'] = true;
            }
            $dbolE->columnProperties[$column_name] = $colDets;
            $j++;
        }
    }
}
