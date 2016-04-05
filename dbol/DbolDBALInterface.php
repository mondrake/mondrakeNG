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
 * DbolDBALInterface
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */
interface DbolDBALInterface
{
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
    public function connect(array $dsn, $connectionParams = null);

    /**
     * Mounts the database connection
     *
     * Mounts the connection to dbol, and instantiates the DBMS-specific interface
     *
     * @param object $dbh the connection object
     *
     * @return void
     */
    public function mount($dbh = null);

    /**
     * Sets DBMS driver interface
     *
     * @param object $i the interface object
     *
     * @return void
     */
    public function setDBMSInterface($i);

    /**
     * Checks if DBMS driver interface is set
     *
     * @return boolean TRUE if driver interface is set; FALSE elsewhere
     */
    public function isSetDBMSInterface();
    
    /**
     * Gets the DBAL version string
     *
     * @return string DBAL version string
     */
    public function getVersion();

    /**
     * Gets the DBAL driver name
     *
     * @return string DBAL driver name
     */
    public function getDriver();

    /**
     * Gets the database server ID
     *
     * @return int database server ID
     */
    public function getDbServer();

    /**
     * Gets the database server version
     *
     * @return string database server version string
     */
    public function getDbServerVersion();

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
    public function listTables($prefix = null);

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
    public function tableInfo($table);

    /**
     * Returns a DBMS specific syntax or setting for dbol abstraction
     *
     * @param string $id the id of the syntax element to retrieve
     *
     * @return mixed response from the dbolD interface
     */
    public function getSyntax($id);

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
    public function quote($value, $type = null, $quote = true, $escapeWildcards = false);

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
    public function setLimit($query, $limit = null, $offset = null);

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
    public function query($query, $types = null);

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
    public function fetchRow($queryHandle, $fetchMode = null, $rowNum = null);

    /**
     * Executes an SQL statement, returning the number of rows affected by the statement
     *
     * @param string $query the SQL statement to execute
     *
     * @return int the number of rows affected
     */
    public function exec($query);

    /**
     * Returns the SQL statement string last executed on the statement handle
     *
     * @param object $statementHandle the statement handle
     *
     * @return string the SQL string
     */
    public function lastQuery($statementHandle);

    /**
     * Prepares an INSERT SQL statement
     *
     * @param string $table   the table
     * @param array  $columns an array of columns to be inserted
     * @param array  $types   an associative array representing column types
     *
     * @return string the SQL string
     */
    public function autoPrepareInsert($table, $columns, $types);

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
    public function autoPrepareUpdate($table, $columns, $whereClause, $types);

    /**
     * Prepares a DELETE SQL statement
     *
     * @param string $table       the table
     * @param string $whereClause .
     *
     * @return string the SQL string
     */
    public function autoPrepareDelete($table, $whereClause);

    /**
     * .
     *
     * @param string $statementHandle .
     * @param array  $values          an associative array representing .
     * @param array  $types           an associative array representing column types
     *
     * @return string the SQL string
     */
    public function executePreparedStatement($statementHandle, $values = null, $types = null);

    /**
     * .
     *
     * @param string $table  the table
     * @param string $column .
     *
     * @return int .
     */
    public function lastInsertID($table = null, $column = null);

    // sequence management methods

    /**
     * Creates a new sequence in the database
     *
     * @param string $seqName the name of the sequence to be created
     * @param int    $start   start value of the sequence; default is 1
     *
     * @return mixed value returned by the DBAL
     */
    public function createSequence($seqName, $start = 1);

    /**
     * Drops a sequence in the database
     *
     * @param string $seqName the name of the sequence to be created
     *
     * @return mixed value returned by the DBAL
     */
    public function dropSequence($seqName);

    /**
     * Gets next value of a sequence
     *
     * @param string $seqName  name of the sequence
     * @param bool   $onDemand when true missing sequences are automatic created
     *
     * @return mixed the next free id of the sequence
     */
    public function nextID($seqName, $onDemand = false);

    /**
     * Gets current value of a sequence
     *
     * @param string $seqName name of the sequence
     *
     * @return mixed the current id of the sequence
     */
    public function currID($seqName);

    // transaction management methods

    /**
     * Determines if a transaction is currently open
     *
     * @param bool $ignoreNested if the nested transaction count should be ignored
     *
     * @return int|bool an integer with the nesting depth is returned if a nested transaction is open
     *                  true is returned for a normal open transaction
     *                  false is returned if no transaction is open
     */
    public function inTransaction($ignoreNested = false);

    /**
     * Start a transaction or set a savepoint
     *
     * @param bool $savepoint name of a savepoint to set
     *
     * @return mixed value returned from DBAL
     */
    public function beginTransaction($savepoint = null);

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
    public function commit($savepoint = null);
}
