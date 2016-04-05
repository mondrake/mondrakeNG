<?php 
require_once 'MDB2.php';
 
class DbolMDB2Interface implements DbolDBALInterface {
    private $dbol = NULL;                        // calling dbol handle
    private $dbh = NULL;                        // database handle
    /**
     * DBMS interface
     */
    protected $dbmsI = null;

  
    /** 
     * Constructs the DBAL interface instance
     *
     * @param object $dbol the calling dbol instance
     */
    public function __construct($dbol)  {   
        $this->dbol = $dbol;
    }
  
    /** 
     * Voids cloning
     */
    public function __clone()  {  }

    /** 
     * Connects the database
     *
     * Connects via the database abstraction layer to the database specified by 'dsn', passing 'connectionParams'
     *
     * @param array $dsn              the DSN of the database to connect to
     * @param array $connectionParams the parameters to pass to the the connection 
     */
    public function connect(array $dsn, $connectionParams = null)  {  
        // @todo manage connectionParams
        $dsn['phptype'] = $dsn['driver'];
        $this->dbh = &MDB2::connect($dsn);
        $this->checkDbError($this->dbh);
        // load additional MDB2 modules
        $res = $this->dbh->loadModule('Extended'); 
        $this->checkDbError($res);
        // set charset
        $charset = $this->dbol->getVariable('charset');
        if ($charset)    {
            $res = $this->dbh->setCharset($charset); 
            $this->checkDbError($res);
        }
        $this->dbol->mount($this->dbh);
        return $this->dbh;
    }

    /** 
     * Mounts the database connection
     *
     * Mounts the connection to dbol, and instantiates the DBMS-specific interface 
     *
     * @param object $dbh the connection object
     */
    public function mount($dbh = null)    {
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
     * @return string                     DBAL version string 
     *
     * @access public
     */
    public function getVersion()    {
        return MDB2::apiVersion();
    }

    /** 
     * Gets the DBAL driver name
     *
     * @return string                     DBAL driver name 
     *
     * @access public
     */
    public function getDriver()    {
        return PEAR::isError($this->dbh) ? $this->dbol->getVariable('DBALDriver') : $this->dbh->dsn['driver'];
    }

    /** 
     * Gets the database server 
     *
     * @return int                     database server ID 
     *
     * @access public
     */
    public function getDbServer()    {
        switch ($this->getDriver())    {
            case 'mysqli':
            case 'mysql':
                return DBOL_DBMS_MYSQL;
            case 'pgsql':
                return DBOL_DBMS_POSTGRESQL;
            case 'mssql':
            case 'sqlsrv':
                return DBOL_DBMS_MSSQL;
            case 'fbsql':
                return DBOL_DBMS_FBASE;
            case 'ibase':
                return DBOL_DBMS_IBASE;
            case 'oci8':
                return DBOL_DBMS_ORACLE;
            case 'odbc':
                return DBOL_DBMS_ODBC;
            case 'querysim':
                return DBOL_DBMS_QUERYSIM;
            case 'sqlite':
                return DBOL_DBMS_SQLITE;
            default:
                // DBMS not supported
                $this->dbol->diag(105, array('%driver' => $driver,));                    
                return null;
        }
    }

    /** 
     * Gets the database server version
     *
     * @return string                     database server version string 
     *
     * @access public
     */
    public function getDbServerVersion()    {
        return $this->dbh->getServerVersion(true);
    }

    /** 
     * Return a list of tables in the connected database 
     *
     * The associative array returned has the following format:
     *     [{table_name]]        =>    array
     *         'description'        =>    table description taken from table DML comments
     *         'rows'                 =>     the current number of rows
     *      'storageMethod'        =>     the storage engine of the table
     *      'collation'            =>     the character collation
     *
     * @param string $prefix            optional - filters only the tables with name beginning by 'prefix'
     *
     * @return array                     the list of tables 
     *
     * @access public
     */
    public function listTables($prefix = NULL)
    {
        return $this->dbmsI->listTables($prefix);
    }

    /** 
     * Return a list of column details for the specified table
     *
     * The associative array returned has the following format:
     *            'table'             => table name
     *            'name'                 => column name
     *            'nullable'             => are null values accepted
     *            'autoincrement'     => is column set for automatic sequencing
     *            'primaryKey'         => is column part of primary key
     *            'nativetype'         => column type (DBMS specific)
     *             'length'             => column length
     *            'fixed'             => is length fixed
     *            'unsigned'             => is column bearing unsigned number (for numeric columns)
     *             'default'             => column's default value (in case not specified in SQL statements)
     *            'type'                 => column dbol type
     *            'dboltype'             => column dbol type
     *            'comment'             => column comment/description
     *
     * @param string $table                the table for which details are required
     *
     * @return array                     the column details
     *
     * @access public
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

    public function quote($value, $type = NULL, $quote = true, $escapeWildcards = false)  {  
        $res = $this->dbh->quote($value, $type, $quote, $escapeWildcards);
        $this->checkDbError($res);
        return $res;
    }

    public function setLimit($query, $limit = NULL, $offset = NULL)  {  
        $res = $this->dbh->setLimit($limit, $offset);        
        $this->checkDbError($res); 
        return $query;
    }
    
    public function query($query, $types = null)     {
        $queryHandle = $this->dbh->query($query, $types);
        $this->checkDbError($queryHandle);
        return $queryHandle;
    }

    public function fetchRow($queryHandle, $fetchMode = null, $rowNum = null)     {
        $res = $queryHandle->fetchRow(MDB2_FETCHMODE_ASSOC);
        $this->checkDbError($res);
        return $res;
    }

    public function exec($query)     {
        $res = $this->dbh->exec($query);
        $this->checkDbError($res);
        return $res;
    }

    public function lastQuery($statementHandle)     {
        $res = $this->dbh->last_query;
        $this->checkDbError($res);
        return $res;
    }

    public function autoPrepareInsert($table, $columns, $types)     {
        $res = $this->dbh->extended->autoPrepare($table, $columns, MDB2_AUTOQUERY_INSERT, null, $types);
        $this->checkDbError($res);
        return $res;
    }

    public function autoPrepareUpdate($table, $columns, $whereClause, $types)     {
        $res = $this->dbh->extended->autoPrepare($table, $columns, MDB2_AUTOQUERY_UPDATE, $whereClause, $types);
        $this->checkDbError($res);
        return $res;
    }

    public function autoPrepareDelete($table, $whereClause)     {
        $res = $this->dbh->extended->autoPrepare($table, null, MDB2_AUTOQUERY_DELETE, $whereClause, null);
        $this->checkDbError($res);
        return $res;
    }

    public function executePreparedStatement($sth, $values = null, $types = null)     {
        $res =& $sth->execute($values);
        $this->checkDbError($res);
        return $res;
    }

    public function lastInsertID($table = null, $column = null)     {
        $res = $this->dbh->lastInsertID($table, $column);
        $this->checkDbError($res);
        return $res;
    }

    /** 
     * Creates a new sequence in the database 
     *
     * @param string $seqName            the name of the sequence to be created
     * @param int $start                start value of the sequence; default is 1 
     *
     * @return mixed                     value returned by the DBAL
     *
     * @access public
     */
    public function createSequence($seqName, $start = 1)     {
        $res = $this->dbh->createSequence($seqName, $start);
        $this->checkDbError($res);
        return $res;
    }

    /** 
     * Drops a sequence in the database 
     *
     * @param string $seqName            the name of the sequence to be created
     *
     * @return mixed                     value returned by the DBAL
     *
     * @access public
     */
    public function dropSequence($seqName)     {
        $res = $this->dbh->dropSequence($seqName);
        $this->checkDbError($res);
        return $res;
    }

    /** 
     * Gets next value of a sequence 
     *
     * @param string $seqName            name of the sequence
     * @param bool $ondemand            when true missing sequences are automatic created
     *
     * @return mixed                     the next free id of the sequence
     *
     * @access public
     */
    public function nextID($seqName, $ondemand = false)     {
        $res = $this->dbh->nextID($seqName, $ondemand);
        $this->checkDbError($res);
        return $res;
    }

    /** 
     * Gets current value of a sequence 
     *
     * @param string $seqName            name of the sequence
     *
     * @return mixed                     the current id of the sequence
     *
     * @access public
     */
    public function currID($seqName)     {
        $res = $this->dbh->currID($seqName);
        $this->checkDbError($res);
        return $res;
    }

    /** 
     * Determines if a transaction is currently open
     *
     * @param bool $ignore_nested        if the nested transaction count should be ignored 
     *
     * @return int|bool                    an integer with the nesting depth is returned if a nested transaction is open 
     *                                    true is returned for a normal open transaction
     *                                    false is returned if no transaction is open
     *
     * @access public
     */
    public function inTransaction($ignoreNested = false)     {
        $res = $this->dbh->inTransaction($ignoreNested);
        $this->checkDbError($res);
        return $res;
    }

    /** 
     * Start a transaction or set a savepoint
     *
     * @param bool $savepoint            name of a savepoint to set
     *
     * @return mixed                    value returned from DBAL
     *
     * @access public
     */
    public function beginTransaction($savepoint = null)     {
        $res = $this->dbh->beginTransaction($savepoint);
        $this->checkDbError($res);
        return $res;
    }

    /** 
     * Commit the database changes done during a transaction that is in progress or release a savepoint
     *
     * This function may only be called when auto-committing is disabled, otherwise it will fail. 
     * Therefore, a new transaction is implicitly started after committing the pending changes.
     *
     * @param bool $savepoint            name of a savepoint to release
     *
     * @return mixed                    value returned from DBAL
     *
     * @access public
     */
    public function commit($savepoint = null)     {
        $res = $this->dbh->commit($savepoint);
        $this->checkDbError($res);
        return $res;
    }

    /** 
     * Checks status of the last db operation or of the db handle 
     *
     * It will log diagnostic messages from DBAL and from native DB.
     *
     * @param object $link                the result of the last operation from the db
     *
     * @access private
     */
    private function checkDbError($link = null) {
        if($link == null) { 
            $link = $this->dbh;
        }
        if(PEAR::isError($link)) {
            // logs native db error
            if(!PEAR::isError($this->dbh)) {
                list($code, $nativeCode, $nativeMessage) = $this->dbh->errorInfo($link->getCode);
            }
            else    {
                $nativeCode = $this->_retrieveNativeFromUserinfo($link, '[Native code: ');
                if ($nativeCode <> 0)    {
                    $nativeMessage = $this->_retrieveNativeFromUserinfo($link, '[Native message: ');
                }
            }
            if ($nativeCode <> 0)    {
                $this->dbol->diagnosticMessage(DBOL_ERROR, $nativeCode, array('#text' => $nativeMessage,), $this->getDriver(), false);
            }
            // logs MDB2 error
            $this->dbol->diagnosticMessage(DBOL_DEBUG, $link->getCode(), array('#text' => $link->getUserInfo(),), 'MDB2', false);
            $this->dbol->diagnosticMessage(DBOL_ERROR, $link->getCode(), array('#text' => $link->getMessage(),), 'MDB2');
        }        
    }

    /** 
     * MDB2 - retrieves native db info from userInfo
     *
     * Scans userinfo string returned from getUserInfo searching for 'needle' and return the string enclosed within next 
     * square bracket
     *
     * @param object $link   the result of the last operation from the db
     * @param string $needle the string to be searched for
     *
     * @return string the substring enclosed within next square bracket 
     */
    private function _retrieveNativeFromUserinfo($link, $needle) {
        $startOffset = strpos($link->getUserInfo(), $needle) + strlen($needle);
        if (!$startOffset)
            return null;
        $endOffset = strpos($link->getUserInfo(), ']', $startOffset);
        if (!$endOffset)
            return null;
        return substr($link->getUserInfo(), $startOffset, $endOffset - $startOffset);
    }
}
