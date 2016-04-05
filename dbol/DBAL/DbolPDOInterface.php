<?php
/**
 * Dbol
 *
 * PHP version 5
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */

/**
 * DbolDBALInterface - PDO
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */
class DbolPDOInterface implements DbolDBALInterface
{
    /**
     * Caller dbol instance
     */
    protected $dbol = null;

    /**
     * Database handle
     */
    protected $dbh = null;

    /**
     * DBMS interface
     */
    protected $dbmsI = null;

    /**
     * In transaction indicator
     */
    private $_inTransaction;
    
    /**
     * Last SQL statement passed to PDO exec method
     */
    protected $execString;
    
    /**
     * Constructs the DBAL interface instance
     *
     * @param Dbol $dbol the calling dbol instance
     *
     * @return void
     */
    public function __construct(Dbol $dbol)
    {
        $this->dbol = $dbol;
    }

    /**
     * Voids cloning
     *
     * @return void
     */
    public function __clone()
    {
    }

    /**
     * Connects the database
     *
     * Connects via the database abstraction layer to the database specified by 'dsn',
     * passing 'connectionParams'
     *
     * @param array $dsn              the DSN of the database to connect to
     * @param array $connectionParams the parameters to pass to the the connection
     *
     * @return object .
     */
    public function connect(array $dsn, $connectionParams = null)
    {
        // @todo manage connectionParams
        $driverConnectionDSN = $this->dbmsI->getDriverConnectionDSN('PDO', $dsn);
        $this->dbh = new PDO($driverConnectionDSN, $dsn['username'], $dsn['password']);
        $this->checkDb($this->dbh);
        // @todo cycle connectionParams
        $this->dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $this->checkDb($this->dbh);
        $this->dbol->mount($this->dbh);
        // set charset
        $charset = $this->dbol->getVariable('charset');
        if ($charset) {
            $this->dbmsI->setCharset($charset);
        }
        return $this->dbh;
    }

    /**
     * Mounts the database connection
     *
     * Mounts the connection to dbol, and instantiates the DBMS-specific interface
     *
     * @param object $dbh the connection object
     *
     * @return void
     */
    public function mount($dbh = null)
    {
        $this->dbh = $dbh;
    }

    /**
     * Sets DBMS driver interface
     *
     * @param object $i the interface object
     *
     * @return void
     */
    public function setDBMSInterface($i)
    {
        $this->dbmsI = $i;
    }

    /**
     * Checks if DBMS driver interface is set
     *
     * @return boolean TRUE if driver interface is set; FALSE elsewhere
     */
    public function isSetDBMSInterface()
    {
        return isset($this->dbmsI);
    }

    /**
     * Gets the DBAL version string
     *
     * @return string DBAL version string
     */
    public function getVersion()
    {
        return phpversion();
    }

    /**
     * Gets the DBAL driver name
     *
     * @return string DBAL driver name
     */
    public function getDriver()
    {
        $ret = $this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->checkDb($this->dbh);
        return $ret;
    }

    /**
     * Gets the database server ID
     *
     * @return int database server ID
     */
    public function getDbServer()
    {
        $driver = $this->getDriver();
        switch ($driver)    {
        case 'mysql':
            return DBOL_DBMS_MYSQL;
        case 'pgsql':
            return DBOL_DBMS_POSTGRESQL;
        case 'mssql':
        case 'sqlsrv':
        case 'dblib':
            return DBOL_DBMS_MSSQL;
        case 'firebird':
            return DBOL_DBMS_FIREBIRD;
        case 'oci':
            return DBOL_DBMS_ORACLE;
        case 'odbc':
            return DBOL_DBMS_ODBC;
        case 'sqlite':
            return DBOL_DBMS_SQLITE;
        case 'cubrid':
            return DBOL_DBMS_CUBRID;
        case 'sybase':
            return DBOL_DBMS_SYBASE;
        case 'ibm':
            return DBOL_DBMS_DB2;
        case 'informix':
            return DDBOL_DBMS_INFORMIX;
        case '4D':
            return DDBOL_DBMS_4D;
        default:
            // DBMS not supported
            $this->dbol->diag(105, array('%driver' => $driver,));
            return null;
        }
    }

    /**
     * Gets the database server version
     *
     * @return string database server version string
     */
    public function getDbServerVersion()
    {
        $ret = $this->dbh->getAttribute(PDO::ATTR_SERVER_VERSION);
        $this->checkDb($this->dbh);
        return $ret;
    }

    /**
     * Returns a list of tables in the connected database
     *
     * The associative array returned has the following format:
     *   [{table_name]]        =>    array
     *    'description'        =>    table description taken from table DML comments
     *    'rows'               =>     the current number of rows
     *    'storageMethod'      =>     the storage engine of the table
     *    'collation'          =>     the character collation
     *
     * @param string $prefix optional - filters only the tables with name beginning by 'prefix'
     *
     * @return array the list of tables
     */
    public function listTables($prefix = null)
    {
        return $this->dbmsI->listTables($prefix);
    }

    /**
     * Returns a list of column details for the specified table
     *
     * The associative array returned has the following format:
     *    'table'             => table name
     *    'name'              => column name
     *    'dboltype'          => column dbol type
     *    'nullable'          => are null values accepted
     *    'autoincrement'     => is column set for automatic sequencing
     *    'primaryKey'        => is column part of primary key
     *    'nativetype'        => full column type (DBMS specific)
     *    'type'              => column type (DBMS specific)
     *    'length'            => column length
     *    'fixed'             => is length fixed
     *    'unsigned'          => is column bearing unsigned number (for numeric columns)
     *    'default'           => column's default value (in case not specified in SQL statements)
     *    'comment'           => column comment/description
     *
     * @param string $table the table for which details are required
     *
     * @return array the column details
     */
    public function tableInfo($table)
    {
        return $this->dbmsI->tableInfo($table);
    }

    /**
     * Returns a DBMS specific syntax or setting for dbol abstraction
     *
     * @param string $id the id of the syntax element to retrieve
     *
     * @return mixed response from the dbolD interface
     */
    public function getSyntax($id)
    {
        return $this->dbmsI->getSyntax($id);
    }

    /**
     * Places quotes around the input string
     *
     * Escapes special characters within the input string, using a quoting style appropriate
     * to the underlying driver
     *
     * @param mixed   $value           the element to be quoted
     * @param string  $type            the dbol type of the element to be quoted
     * @param boolean $quote           quote
     * @param boolean $escapeWildcards escape wildcards
     *
     * @return mixed quoted value or value itself if quoting not needed
     */
    public function quote($value, $type = null, $quote = true, $escapeWildcards = false)
    {
        if (is_null($value)) {
            $ret = $this->getSyntax('nullString');
        } else {
            switch ($type) {
            case 'text':
            case 'clob':
            case 'blob':
            case 'date':
            case 'time':
            case 'timestamp':
                $ret = $this->dbh->quote($value, PDO::PARAM_STR);
                $this->checkDb($this->dbh);
                break;
            case 'integer':
            case 'boolean':
            case 'decimal':
            case 'float':
            default:
                $ret = $value;
            }
        }
        return $ret;
    }

    /**
     * Sets the range of the next query
     *
     * Raises error if setLimit is not supported by underlying DBMS
     *
     * @param string  $query  the query to be limited
     * @param string  $limit  number of rows to select
     * @param boolean $offset first row to select
     *
     * @return string the query updated with limit clause (if needed)
     */
    public function setLimit($query, $limit = null, $offset = null)
    {
        $limitClause = null;
        if ($limit or $offset) {
            $limitLocation = $this->getSyntax('limitLocation');
            if (!$limitLocation) {
                // setLimit not supported
                $this->dbol->diag(
                    107, array('%dbms' => $this->dbol->getdbServerName($this->getDriver()),)
                );                    
            }
            $limitClause = $this->getSyntax('limit');
            $limitClause = str_replace('{offset}', ($offset ? $offset : 0), $limitClause);
            $limitClause = str_replace('{limitRows}', ($limit ? str_replace('{rows}', $limit, $this->getSyntax('limitRows')) : null), $limitClause);
            switch ($limitLocation) {
            case "last":
            default:
                $query .= $limitClause;
            }
        }
        return $query;
    }

    /**
     * Executes an SQL statement for fetching
     *
     * Returns the handle to the result set (if any) for fetching rows
     *
     * @param string $query the query to be executed
     * @param array  $types optional - an associative array to the dbol types expected in the resultset
     *
     * @return object the result set handle
     */
    public function query($query, $types = null)
    {
        if (!$this->dbh) {
            $this->dbol->diag(108);
        }
        $queryHandle = $this->dbh->prepare($query);
        $this->checkDb($queryHandle);
        $queryHandle->execute();
        $this->checkDb($queryHandle);
        return $queryHandle;
    }

    /**
     * Fetches a row from a result set
     *
     * Returns an associative array [column] => value
     *
     * @param object $queryHandle the result set handle
     * @param int    $fetchMode   optional - the fetchMode (by default dbol uses associative fetch)
     * @param int    $rowNum      optional - the absolute number of the row in the result set that shall be fetched (offset)
     *
     * @return array the returned row
     */
    public function fetchRow($queryHandle, $fetchMode = null, $rowNum = null)
    {
        $res = $queryHandle->fetch(PDO::FETCH_ASSOC);
        $this->checkDb($queryHandle);
        return $res;
    }

    /**
     * Executes an SQL statement, returning the number of rows affected by the statement
     *
     * @param string $query the SQL statement to execute
     *
     * @return int the number of rows affected
     */
    public function exec($query)
    {
        $this->execString = $query;
        $res = $this->dbh->exec($query);
        $this->checkDb($this->dbh);
        $this->execString = null;
        return $res;
    }

    /**
     * Returns the SQL statement string last executed on the statement handle
     *
     * @param object $statementHandle the statement handle
     *
     * @return string the SQL string
     */
    public function lastQuery($statementHandle)
    {
        $res = $statementHandle->queryString;
        //$this->checkDb($queryHandle);
        return $res;
    }

    /**
     * Prepares an INSERT SQL statement
     *
     * @param string $table   the table
     * @param array  $columns an array of columns to be inserted
     * @param array  $types   an associative array representing column types
     *
     * @return string the SQL string
     */
    public function autoPrepareInsert($table, $columns, $types)
    {
        $query = $this->getSyntax('tableInsert');
        // table
        $query = str_replace('{table}', $table, $query);
        // columns
        $isFirst = true;
        foreach ($columns as $a => $b) {
            if ($isFirst) {
                $c = "$b";
                $p = "?";
                $isFirst = false;
            } else {
                $c .= ", $b";
                $p .= ", ?";
            }
        }
        $query = str_replace('{columns}', $c, $query);
        $query = str_replace('{placeholders}', $p, $query);
        $res = $this->dbh->prepare($query);
        $this->checkDb($res);
        return $res;
    }

    /**
     * Prepares an UPDATE SQL statement
     *
     * @param string $table       the table
     * @param array  $columns     an array of columns to be updated
     * @param string $whereClause .
     * @param array  $types       an associative array representing column types
     *
     * @return string the SQL string
     */
    public function autoPrepareUpdate($table, $columns, $whereClause, $types)
    {
        $query = "UPDATE $table SET ";
        $isFirst = true;
        foreach ($columns as $a => $b) {
            if ($isFirst) {
                $query .= "$b = ?";
                $isFirst = false;
            } else {
                $query .= ", $b = ?";
            }
        }
        $query .= " WHERE $whereClause ";
        $res = $this->dbh->prepare($query);
        $this->checkDb($res);
        return $res;
    }

    /**
     * Prepares a DELETE SQL statement
     *
     * @param string $table       the table
     * @param string $whereClause .
     *
     * @return string the SQL string
     */
    public function autoPrepareDelete($table, $whereClause)
    {
        $query = $this->getSyntax('tableDelete');
        // table
        $query = str_replace('{table}', $table, $query);
        // where
        if (!empty($whereClause)) {
            $query = str_replace('{whereSection}', " WHERE $whereClause", $query);
        } else {
            $query = str_replace('{whereSection}', null, $query);
        }
        $res = $this->dbh->prepare($query);
        $this->checkDb($res);
        return $res;
    }

    /**
     * .
     *
     * @param string $statementHandle .
     * @param array  $values          an associative array representing .
     * @param array  $types           an associative array representing column types
     *
     * @return string the SQL string
     */
    public function executePreparedStatement($statementHandle, $values = null, $types = null)
    {
        // @todo migliorare
        if ($values) {
            $ak=array_keys($types);
            foreach ($values as $a => $b) {
                if (is_null($values[$a])) {
                    $statementHandle->bindValue($a + 1, null, PDO::PARAM_NULL);
                } else {
                    switch ($types[$ak[$a]])    {
                    case 'integer':
                        if ((string) $values[$a] == '0') {
                            $statementHandle->bindValue($a + 1, 0, PDO::PARAM_INT);
                        } else if ($values[$a] == '') {
                            $statementHandle->bindValue($a + 1, null, PDO::PARAM_NULL);
                        } else {
                            $statementHandle->bindValue($a + 1, $values[$a], PDO::PARAM_INT);
                        }
                        break;
                    case 'boolean':
                        $statementHandle->bindValue($a + 1, $values[$a], PDO::PARAM_INT);
                        break;
                    case 'date':
                    case 'time':
                    case 'timestamp':
                        if ($values[$a] == '') {
                            $statementHandle->bindValue($a + 1, null, PDO::PARAM_NULL);
                        } else {
                            $statementHandle->bindValue($a + 1, $values[$a], PDO::PARAM_STR);
                        }
                        break;
                    case 'text':
                    case 'clob':
                    case 'blob':
                    case 'decimal':
                    case 'float':
                    default:
                        $statementHandle->bindValue($a + 1, $values[$a], PDO::PARAM_STR);
                    }
                }
                $this->checkDb($statementHandle);
            }
        }
        $res = $statementHandle->execute();
        $this->checkDb($statementHandle);
        return $res ? 1 : 0;
    }

    /**
     * .
     *
     * @param string $table  the table
     * @param string $column .
     *
     * @return int .
     */
    public function lastInsertID($table = null, $column = null)
    {
        // @todo verificare
        $res = $this->dbh->lastInsertID($table);
        return $res;
    }

    /**
     * Creates a new sequence in the database
     *
     * @param string $seqName the name of the sequence to be created
     * @param int    $start   start value of the sequence; default is 1
     *
     * @return mixed value returned by the DBAL
     */
    public function createSequence($seqName, $start = 1)
    {
        // @todo implement
        $res = $this->dbh->createSequence($seqName, $start);
        $this->checkDb($res);
        return $res;
    }

    /**
     * Drops a sequence in the database
     *
     * @param string $seqName the name of the sequence to be created
     *
     * @return mixed value returned by the DBAL
     */
    public function dropSequence($seqName)
    {
        // @todo implement
        $res = $this->dbh->dropSequence($seqName);
        $this->checkDb($res);
        return $res;
    }

    /**
     * Gets next value of a sequence
     *
     * @param string $seqName  name of the sequence
     * @param bool   $onDemand when true missing sequences are automatic created
     *
     * @return mixed the next free id of the sequence
     */
    public function nextID($seqName, $onDemand = false)
    {
        // @todo improve, implement onDemand
        if ($this->dbmsI->getSyntax('nativeSequences')) {
            // @todo
            $value = null;
        } else {
            list($seqTable, $seqCol) = $this->dbol->getDbEmulatedSequenceQualifiers($seqName);
            $query = "INSERT INTO $seqTable ($seqCol) VALUES (NULL)";
            $result = $this->exec($query);
            $value = $this->dbh->lastInsertID($seqTable);
            if (is_numeric($value)) {
                $query = "DELETE FROM $seqTable WHERE $seqCol < $value";
                $result = $this->exec($query);
            }
        }
        return $value;
    }

    /**
     * Gets current value of a sequence
     *
     * @param string $seqName name of the sequence
     *
     * @return mixed the current id of the sequence
     */
    public function currID($seqName)
    {
        // @todo improve
        if ($this->dbmsI->getSyntax('nativeSequences')) {
            // @todo
            return null;
        } else {
            list($seqTable, $seqCol) = $this->dbol->getDbEmulatedSequenceQualifiers($seqName);
            $query = "SELECT MAX($seqCol) AS a FROM $seqTable";
            $ret = $this->dbol->query($query);
            return $ret[0]['a'];
        }
    }

    /**
     * Determines if a transaction is currently open
     *
     * @param bool $ignoreNested if the nested transaction count should be ignored
     *
     * @return int|bool an integer with the nesting depth is returned if a nested transaction is open
     *                  true is returned for a normal open transaction
     *                  false is returned if no transaction is open
     */
    public function inTransaction($ignoreNested = false)
    {
        // @todo check Php version to pass PDO internal
        $res = $this->_inTransaction;
        return $res;
    }

    /**
     * Start a transaction or set a savepoint
     *
     * @param bool $savepoint name of a savepoint to set
     *
     * @return mixed value returned from DBAL
     */
    public function beginTransaction($savepoint = null)
    {
        $res = $this->dbh->beginTransaction();
        $this->checkDb($this->dbh);
        $this->_inTransaction = true;
        return $res;
    }

    /**
     * Commit the database changes done during a transaction that is in progress or release a savepoint
     *
     * This function may only be called when auto-committing is disabled, otherwise it will fail.
     * Therefore, a new transaction is implicitly started after committing the pending changes.
     *
     * @param bool $savepoint name of a savepoint to release
     *
     * @return mixed value returned from DBAL
     */
    public function commit($savepoint = null)
    {
        $res = $this->dbh->commit();
        $this->checkDb($this->dbh);
        $this->_inTransaction = false;
        return $res;
    }

    /**
     * Checks status of the last db operation or of the db handle
     *
     * It will log diagnostic messages from DBAL and from native DB.
     *
     * @param object $link the result of the last operation from the db
     *
     * @return void
     */
    protected function checkDb($link = null)
    {
        if ($link == null) {
            $link = $this->dbh;
        }
        if (get_class($link) == 'PDOStatement') {
            $lastQuery = $this->lastQuery($link);
        } else {
            $lastQuery = $this->execString;
        }
        $error = $link->errorInfo(); 
        if ($error[0] <> 0) {
            $this->dbol->diagnosticMessage(DBOL_DEBUG, 100, array('#text' => $lastQuery,), 'PDO', false);
            $this->dbol->diagnosticMessage(DBOL_ERROR, $error[0], array('#text' => "SQLSTATE: " . $error[0],), 'PDO', false);
            $this->dbol->diagnosticMessage(DBOL_ERROR, $error[1], array('#text' => $error[2],), $this->getDriver());
        }
    }
}
