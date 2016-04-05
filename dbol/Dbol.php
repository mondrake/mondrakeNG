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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
require_once 'DriverManager.php'; // @todo nooo

/**
 *
 */
define('DBOL_NO_AUDIT',                 0);
define('DBOL_ROW_AUDIT',                1);
define('DBOL_FIELD_AUDIT',              2);
define('DBOL_NO_SELECT_LOCK',           0);
define('DBOL_SELECT_LOCK_SHARED',       1);
define('DBOL_SELECT_LOCK_FOR_UPDATE',   2);
define('DBOL_DEBUG',                    7);
define('DBOL_INFO',                     6);
define('DBOL_NOTICE',                   5);
define('DBOL_WARNING',                  4);
define('DBOL_ERROR',                    3);
define('DBOL_DEFAULT_PKSEPARATOR',         '|');
define('DBOL_DEFAULT_PKSEPARATORREPLACE',  '#&!vbar!&#');
define('DBOL_DBMS_MYSQL',               1);
define('DBOL_DBMS_MSSQL',               2);
define('DBOL_DBMS_POSTGRESQL',          3);
define('DBOL_DBMS_ORACLE',              4);
define('DBOL_DBMS_QUERYSIM',            5);
define('DBOL_DBMS_SQLITE',              6);
define('DBOL_DBMS_FBASE',               7);
define('DBOL_DBMS_IBASE',               8);
define('DBOL_DBMS_CUBRID',              9);
define('DBOL_DBMS_FIREBIRD',            10);
define('DBOL_DBMS_SYBASE',              11);
define('DBOL_DBMS_DB2',                 12);
define('DBOL_DBMS_INFORMIX',            13);
define('DBOL_DBMS_ODBC',                14);
define('DBOL_DBMS_4D',                  15);


/**
 * DbolEntry
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */
class DbolEntry
{
    var $table;
    var $tableProperties = array();
    var $columns = array();
    var $columnTypes = array();
    var $PKColumns = array();
    var $AIColumns = array();
    var $columnProperties = array();
}

require_once 'DbolCallbackInterface.php';
require_once 'DbolDBALInterface.php';

/**
 * Dbol
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */
class Dbol
{
    private $_variables = array();          // session context variables
    private $_cbc = null;                        // callback interface instance
    private $_dbalI = null;                      // DBAL interface instance

    /**
     * Voids dbol cloning
     *
     * @return void
     */
    public function __clone()
    { 
    }

    /**
     * Constructs dbol instance
     *
     * Sets in the session context variables the values specified via the an array of
     * variables:
     *     'DBAL'               =>    database abstraction layer to be utilised {MDB2|PDO|Drupal}
     *     'DBALDriver'         =>    driver the database abstraction layer will use
     *     'callbackClass'      =>    if passed, name of a callback class to instantiate using 'this'
     *                                as an argument;
     *                                if not passed, instantiated callback object can be passed later via 
     *                                function 'setCallback'
     *     'charset'            =>    the database charset to use
     *     'decimalPrecision'   =>    default precision of decimal fields (can be overridden by single columns)
     *     'PKSeparator'        =>    separator to use to build a primaryKeyString (default = DBOL_DEFAULT_PKSEPARATOR)
     *     'PKSeparatorReplace' =>    string used to replace occurrences of 'PKSeparator' in text of PK columns values
     *                                (default = DBOL_DEFAULT_PKSEPARATORREPLACE)
     *     'perfLogging'        =>    bool to specify if SQL performance should be tracked
     *     'perfThreshold'      =>    threshold in milliseconds above which underperforming SQL statements are tracked
     *
     * @param array $variables the parameters to use to instantiate the dbol class
     */
    public function __construct(array $variables)
    {
        // sets $variables as dbol variables
        $this->setVariables($variables);
        // if callback class name passed, instantiates an object with passing $this as an
        // argument
        if (isset($this->_variables['callbackClass'])) {
            $this->_cbc = new $variables['callbackClass']($this);
        }
        // sets default PK separator
        if (!isset($this->_variables['PKSeparator'])) {
            $this->_variables['PKSeparator'] = DBOL_DEFAULT_PKSEPARATOR;
        }
        // sets default PK separator replace
        if (!isset($this->_variables['PKSeparatorReplace'])) {
            $this->_variables['PKSeparatorReplace'] = DBOL_DEFAULT_PKSEPARATORREPLACE;
        }
/*        // loads and instantiates DBAL Interface
        $dbolDBALInterface = 'Dbol' . $this->_variables['DBAL'] . 'Interface';
        include_once "DBAL/$dbolDBALInterface.php";
        $this->_dbalI = new $dbolDBALInterface($this);*/
    }

    /**
     * Connects dbol to the database
     *
     * Connects via the database abstraction layer to the database specified by '$dsn',
     * passing '$connectionParams'
     *
     * @param array $dsn              the DSN of the database to connect to
     * @param array $connectionParams the parameters to pass to the DBAL connection method
     *
     * @return void
     */
    public function connect(array $dsn, $connectionParams = null)
    {
        // $connectionParams must be array
        if ($connectionParams and !is_array($connectionParams)) {
            $this->diag(100, array('%variable' => '$connectionParams',));
        }
/*        $this->_variables['DBALDriver'] = $dsn['driver'];
        // loads and instantiates DBMS driver interface 
        $dbolDBMSInterface = 'DbolDBMS_' . $this->_variables['DBALDriver'];
        include_once "DBMS/$dbolDBMSInterface.php";
        $this->_dbalI->setDBMSInterface(new $dbolDBMSInterface($this));
        // connects
        $this->_dbalI->connect($dsn, $connectionParams);*/
        $this->connection = DriverManager::getConnection($dsn, new Configuration());
    }

    /**
     * Mounts the database connection
     *
     * Mounts the database connection to dbol, and instantiates 
     * the driver-specific interface
     *
     * @param object $dbh the database handle object
     *
     * @return void
     */
    public function mount($dbh = null)
    {
        // mounts dbol on existing connection
        $this->_dbalI->mount($dbh);
        // loads and instantiates DBMS driver interface if needed
        if (!$this->_dbalI->isSetDBMSInterface()) {
            $driver = $this->_dbalI->getDriver();
            $dbolDBMSInterface = 'DbolDBMS_' . $driver;
            include_once "DBMS/$dbolDBMSInterface.php";
            $this->_dbalI->setDBMSInterface(new $dbolDBMSInterface($this));
        }
    }

    /**
     * Handles calls to nonexisting methods
     *
     * @param string $name the method invoked
     * @param array  $args the array representing the arguments passed to the method
     *
     * @return void
     */
    public function __call($name, $args)
    {
        $this->diag(
            104, array('%method' => $name,                    // undefined method called
                       '%class' =>     get_class($this),)
        );
    }

    /**
     * Sets callback instance
     *
     * @param object $cbc the instantiated callback object
     *
     * @return void
     */
    public function setCallback($cbc)
    {
        if (is_object($cbc)) {
            $this->_cbc = ($cbc);
        }
    }

    /**
     * Gets the DBAL version string
     *
     * @return string DBAL version string
     */
    public function getDBALVersion()
    {
        return $this->_dbalI->getVersion();
    }

    /**
     * Gets the DBAL driver name
     *
     * @return string DBAL driver name
     */
    public function getDBALDriver()
    {
        return $this->_dbalI->getDriver();
    }

    /**
     * Gets the database server ID
     *
     * @return int database server id
     */
    public function getDbServer()
    {
        return $this->_dbalI->getDbServer();
    }

    /**
     * Gets the database server name
     *
     * @return string database server name
     */
    public function getDbServerName()
    {
        $ret = $this->getDBMSInfo($this->_dbalI->getDbServer());
        return $ret[1];
    }

    /**
     * Gets the database server version
     *
     * @return string database server version string
     */
    public function getDbServerVersion()
    {
        return $this->_dbalI->getDbServerVersion();
    }

    /**
     * Sets DBOL variables
     *
     * DBOL variables are used to hold specific instructions for dbol to interact with the
     * database and with the business logic
     *
     * @param array $params the params to be entered in the session context
     *
     * @return void
     */
    public function setVariables($params)
    {
        // arguments validation
        if (!is_array($params)) {
            $this->diag(100, array('%variable' => '$params',));        // variable must be array
        }
        // processes each param rather then overriding them all
        if (!empty($params)) {
            foreach ($params as $parm => $val) {
                $this->_variables[$parm] = $val;
            }
        }
    }

    /**
     * Gets the the session context array or a single variable
     *
     * @param string $var optionally, the single variable to be returned
     *
     * @return mixed session context array or single variable string
     */
    public function getVariable($var = null)
    {
        if ($var) {
            return isset($this->_variables[$var]) ? $this->_variables[$var] : null;
        } else {
            return $this->_variables;
        }
    }

    /**
     * Return an array with all the tables of the db dbol is connected to
     *
     * @return array array with all the db tables
     *
     * @access public
     */
    public function fetchAllTables()
    {
        return $this->_dbalI->listTables();
    }

    /**
     * Loads the properties of all columns of a table, reversing from the DBMS
     *
     * @param object $dbolE the DbolEntry object representing the table
     *
     * @return void
     */
    public function fetchAllColumnsProperties($dbolE)
    {
        // stores the lists of table columns & types, and
        // an array with full details by column name
        $tableName = $this->_cbc ? $this->_cbc->getDbObjectName($dbolE->table) : $dbolE->table;
        $res = $this->_dbalI->tableInfo($tableName);
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
            } else {
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
            if ($dbolE->tableProperties['auditLogLevel'] > DBOL_ROW_AUDIT) {
                $colDets['auditLog'] = true;
            }
            $dbolE->columnProperties[$b['name']] = $colDets;
            $j++;
        }
    }

    /**
     * Sets a property's value for an array of columns
     *
     * @param object $dbolE the DbolEntry object representing the table
     * @param array  $cols  array listing the columns to be affected
     * @param string $prop  the name of the property to be set
     * @param mixed  $value the value to which set the property
     *
     * @return void
     */
    public function setColumnProperty($dbolE, $cols, $prop, $value)
    {
        // arguments validation
        if (!is_array($cols)) {
            $this->diag(100, array('%variable' => '$cols',));        // variable must be array
        }

        if (!empty($cols)) {
            foreach ($cols as $col) {
                if (isset($dbolE->columnProperties[$col])) {
                    $dbolE->columnProperties[$col][$prop] = $value;
                }
            }
        }
    }

    /**
     * Sets the list of Primary Key columns
     *
     * To be used for obejcts mapping to DBMS views, where PK info is not coming from DBMS
     *
     * @param object $dbolE the DbolEntry object representing the table
     * @param array  $cols  array listing the columns representing the Primary Key
     *
     * @return void
     */
    public function setPKColumns($dbolE, $cols)
    {
        // arguments validation
        if (!is_array($cols)) {
            $this->diag(100, array('%variable' => '$cols',));        // variable must be array
        }

        if (!empty($cols)) {
            $this->setColumnProperty($dbolE, $cols, 'primaryKey', true);
            $dbolE->PKColumns = array();
            foreach ($cols as $col) {
                $dbolE->PKColumns[] = $col;
            }
        }
    }

    /**
     * Sets an object's attribute to the value of a DBOL variable
     *
     * Only performs the operation if the object attribute is mapping to an existing db column
     *
     * @param object &$obj    the object to be affected
     * @param object $dbolE   the DbolEntry object representing the table
     * @param string $objAttr the object's attribute to be affected
     * @param string $dbolVar the DBOL variable whose value to copy to the object's attribute
     *
     * @return void
     */
    public function setObjAttrToDbolVariable(&$obj, $dbolE, $objAttr, $dbolVar)
    {
        if (isset($dbolE->columnProperties[$objAttr]['seq'])) {
            $obj->$objAttr = $this->_variables[$dbolVar];
        }
    }

    /**
     * Gets the column properties for the specified dbol entry
     *
     * @param object $dbolE the DbolEntry object representing the table
     *
     * @return array the columnProperties array for the given dbol entry
     */
    public function getColumnProperties($dbolE)
    {
        return $dbolE->columnProperties;
    }

    /**
     * Generates a where clause from the primaryKeyString
     *
     * @param object $dbolE    the DbolEntry object representing the table
     * @param string $PKString the primaryKeyString to process
     *
     * @return string the WHERE clause
     */
    public function explodePKStringIntoWhere($dbolE, $PKString)
    {
        $attrs = explode($this->_variables['PKSeparator'], $PKString);
        $j = 0;
        foreach ($dbolE->PKColumns as $c => $d) {
            $e = str_replace($this->_variables['PKSeparatorReplace'], $this->_variables['PKSeparator'], $attrs[$j]);
            $f = $this->_dbalI->quote($e, $dbolE->columnTypes[$d]);
            $f = ($f == $this->_dbalI->getSyntax('nullString')) ? "''" : $f;
            if (isset($res)) {
                $res .= ' and ' . $d . ' = ' . $f;
            } else {
                $res = $d . ' = ' . $f;
            }
            $j++;
        }
        return $res;
    }

    /**
     * Generates a primaryKeyString from the object's attributes
     *
     * @param object $obj   the object to process
     * @param object $dbolE the DbolEntry object representing the table
     *
     * @return string the primaryKeyString
     */
    public function compactPKIntoString($obj, $dbolE)
    {
        foreach ($dbolE->PKColumns as $c => $d) {
            if (isset($obj->$d)) {
                $tok = str_replace($this->_variables['PKSeparator'], $this->_variables['PKSeparatorReplace'], $obj->$d);
            } else {
                $tok = null;
            }
            if (isset($res)) {
                $res .= $this->_variables['PKSeparator'] . $tok;
            } else {
                $res = $tok;
            }
        }
        return $res;
    }

    /**
     * Reads the record identified by the primaryKeyString into the object
     *
     * @param object $obj   the object to process
     * @param object $dbolE the DbolEntry object representing the table
     * @param string $pkId  the primaryKeyString
     *
     * @return object the object with columns values associated to attributes
     */
    public function readSinglePK($obj, $dbolE, $pkId)
    {
        $whereClause = $this->explodePKStringIntoWhere($dbolE, $pkId);
        return $this->readSingle($obj, $dbolE, $whereClause);
    }

    /**
     * Reads the record identified by the WHERE clause into the object
     *
     * A single record is expected to be returned; if more records returned from the DBMS
     * an error is raised
     *
     * @param object $obj         the object to process
     * @param object $dbolE       the DbolEntry object representing the table
     * @param string $whereClause the WHERE clause
     *
     * @return object                     the object with columns values associated to attributes
     */
    public function readSingle($obj, $dbolE, $whereClause)
    {
        $res = $this->readMulti($obj, $dbolE, $whereClause);
        if (empty($res)) {
            return null;
        }
        if (count($res) > 1) {
            $this->diag(
                101, array( '%table' => $dbolE->table,        // more than 1 record
                            '%class' => get_class($obj),
                            '%whereClause' => $whereClause,)
            );
        }
        return $res[0];
    }

    /**
     * Reads multiple records identified by the WHERE clause into an array of objects
     *
     * @param object $obj         the object to process
     * @param object $dbolE       the DbolEntry object representing the table
     * @param string $whereClause the WHERE clause
     * @param string $orderClause the ORDER BY clause used by DBMS to sort the returnset
     * @param string $limit       passed to _executeRead method to restrict number of rows to be returned
     * @param string $offset      passed to _executeRead method to offset records to start returning from
     *
     * @return array the array of objects with columns values associated to attributes
     */
    public function readMulti($obj, $dbolE, $whereClause = null, $orderClause = null, $limit = null, $offset = null)
    {
        // executes query
        $res = $this->_executeRead($dbolE->table, null, $whereClause, $orderClause, $limit, $offset);
        if (empty($res)) {
            return null;
        }
        // cycles returnset and creates objects in an array
        $rows = array();
        foreach ($res as $row) {
            // associates row columns to object attributes
            foreach ($row as $a => $b) {
                $obj->$a = $b;
            }
            // packs PK attributes into a string
            $obj->primaryKeyString = $this->compactPKIntoString($obj, $dbolE);
            // creates an in-object copy of the db record to enable later image checking
            $obj->prevDbImage = $row;
            $obj->prevDbImage['primaryKeyString'] = $obj->primaryKeyString;

            $arri = clone $obj;
            $rows[] = $arri;
        }
        return $rows;
    }

    /**
     * Counts records identified by the WHERE clause 
     *
     * @param object $obj         the object to process
     * @param object $dbolE       the DbolEntry object representing the table
     * @param string $whereClause the WHERE clause
     *
     * @return integer the number of rows counted
     */
    public function count($obj, $dbolE, $whereClause = null)
    {
        // executes query
        $res = $this->_executeRead($dbolE->table, array('count(*) as rowcount'), $whereClause);
        if (empty($res)) {
            return null;
        }
        return $res[0]['rowcount']; 
    }

    /**
     * Reads a set of records from a table
     *
     * Formats a SELECT SQL statement, executes and returns an array with the recordset returned
     * by the SQL server.
     *
     * @param object $table       the SQL table to SELECT from
     * @param array  $cols        the columns to be selected; if null, all columns in table will be fetched
     * @param string $whereClause the 'WHERE' clause for the SQL statement
     * @param string $orderClause the 'ORDER BY' clause for the SQL statement
     * @param int    $limit       a limit to the number of rows to be returned
     * @param int    $offset      the offset records to start returning the records from
     * @param int    $lockMode    the locking mode of the select statement
     *
     * @return array null if no record found, else an array of rows
     */
    private function _executeRead($table, $cols = null, $whereClause = null, $orderClause = null, $limit = null, $offset = null, $lockMode = DBOL_NO_SELECT_LOCK)
    {
        // composes sql SELECT statement
        $sqlq = $this->_dbalI->getSyntax('tableSelect');
        // columns
        if (empty($cols)) {
            $colClause = "*";
        } else {
            foreach ($cols as $c => $d) {
                if (isset($colClause)) {
                    $colClause .= ', ' . $d;
                } else {
                    $colClause = $d;
                }
            }
        }
        $sqlq = str_replace('{columns}', $colClause, $sqlq);
        // table
        $tableName = $this->_cbc ? $this->_cbc->getDbObjectName($table) : $table;
        $sqlq = str_replace('{table}', $tableName, $sqlq);
        // where
        if (!empty($whereClause)) {
            $sqlq = str_replace('{whereSection}', "WHERE $whereClause", $sqlq);
        } else {
            $sqlq = str_replace('{whereSection}', null, $sqlq);
        }
        // group by
        $sqlq = str_replace('{groupBySection}', null, $sqlq);
        // order by
        if (!empty($orderClause)) {
            $sqlq = str_replace('{orderBySection}', " ORDER BY $orderClause", $sqlq);
        } else {
            $sqlq = str_replace('{orderBySection}', null, $sqlq);
        }
        // lock mode
        $sqlq = str_replace('{lockMode}', $this->_dbalI->getSyntax('lockMode' . $lockMode), $sqlq);
        // executes query - limit clause is handled by the DBAL
        $res = $this->query($sqlq, $limit, $offset);
        if (empty($res)) {
            return null;
        } else {
            return $res;
        }
    }

    /**
     * Returns an array of rows from the database
     *
     * @param string  $sqlq        the SQL statement to be executed
     * @param int     $limit       optional limit to the number of rows to be returned
     * @param int     $offset      optional offset records to start returning the records from
     * @param int     $sqlId       optional identifier to the SQL statement to be used for logging
     * @param boolean $skipPerfLog optional indicator to force avoidance of performance logging
     *
     * @return array null if no record found, else an array of rows
     *
     * @access public
     */
    public function query($sqlq, $limit = null, $offset = null, $sqlId = null, $skipPerfLog = false)
    {
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $startTime = $this->_cbc->startPerfTiming();
        }
        $qh = $this->getQueryHandle($sqlq, $sqlId, $limit, $offset, true);
        $rows = array();
        while ($row = $this->fetchRow($qh)) {
            $rows[] = $row;
        }
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $stopTime = $this->_cbc->stopPerfTiming();
            $elapsed = $this->_cbc->elapsedPerfTiming();
            if ($elapsed > $this->_variables['perfThreshold']) {
                $sqlId = $sqlId ? $sqlId : 'select';
                $this->diag(
                    10, array('%sqlId' => $sqlId,                    // executed SQL over perf threshold
                              '%sqlStmt' => $sqlq,
                              '%rowCount' => count($rows),
                              '%elapsed' => $elapsed,)
                );
                $this->_cbc->logSQLPerformance($sqlId, $sqlq, $startTime, $stopTime, $elapsed, count($rows));
            }
        }
        if (count($rows) > 0) {
            return $rows;
        } else {
            return null;
        }
    }

    /**
     * Returns a query handle ready for row fetching
     *
     * @param string  $sqlq        the SQL statement to be executed
     * @param string  $sqlId       optional identifier to the SQL statement to be used for logging
     * @param int     $limit       optional limit to the number of rows to be returned
     * @param int     $offset      optional offset records to start returning the records from
     * @param boolean $skipPerfLog optional indicator to force avoidance of performance logging
     *
     * @return object the query handle
     *
     * @access public
     */
    public function getQueryHandle($sqlq, $sqlId = null, $limit = null, $offset = null, $skipPerfLog = false)
    {
        if ($this->_cbc) {
            $sqlq = $this->_cbc->getDbResolvedStatement($sqlq);
        }
        $sqlq = $this->_dbalI->setLimit($sqlq, $limit, $offset);
        if ($this->_cbc and $this->_variables['perfLogging'] and !$skipPerfLog) {
            $startTime = $this->_cbc->startPerfTiming();
        }
        $qh = $this->_dbalI->query($sqlq);
        if ($this->_cbc and $this->_variables['perfLogging'] and !$skipPerfLog) {
            $stopTime = $this->_cbc->stopPerfTiming();
            $elapsed = $this->_cbc->elapsedPerfTiming();
            if ($elapsed > $this->_variables['perfThreshold']) {
                $sqlId = $sqlId ? $sqlId : 'select';
                $this->diag(
                    10, array('%sqlId' => $sqlId,                    // executed SQL over perf threshold
                              '%sqlStmt' => $sqlq,
                              '%rowCount' => 'na',
                              '%elapsed' => $elapsed,)
                );
                $this->_cbc->logSQLPerformance($sqlId, $sqlq, $startTime, $stopTime, $elapsed, null);
            }
        }
        return $qh;
    }

    /**
     * Fetches a row from a query handle
     *
     * @param object $qh the query handle to fetch from
     *
     * @return array an associative array with the row fetched or null if EOF
     */
    public function fetchRow($qh)
    {
        return $this->_dbalI->fetchRow($qh);
    }

    /**
     * Executes an SQL statement against the database
     *
     * @param string $sqlq  the SQL statement to be executed
     * @param int    $sqlId an optional identifier to the SQL statement to be used for logging
     *
     * @return int the number of rows affected
     */
    public function executeSql($sqlq, $sqlId = null)
    {
        if ($this->_cbc) {
            $sqlq = $this->_cbc->getDbResolvedStatement($sqlq);
        }
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['startTime'] = $this->_cbc->startPerfTiming();
        }
        $res = $this->_dbalI->exec($sqlq);
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['stopTime'] = $this->_cbc->stopPerfTiming();
            $perfData['elapsed'] = $this->_cbc->elapsedPerfTiming();
            if ($perfData['elapsed'] > $this->_variables['perfThreshold']) {
                $this->diag(
                    10, array(  '%sqlId' => $sqlId ? $sqlId : 'execute',    // executed SQL over perf threshold
                                '%sqlStmt' => $sqlq,
                                '%rowCount' => $res,
                                '%elapsed' => $perfData['elapsed'],)
                );
                $this->_cbc->logSQLPerformance($sqlId, $sqlq, $perfData['startTime'], $perfData['stopTime'], $perfData['elapsed'], $res);
            }
        }
        return $res;
    }

    /**
     * Creates an object's db record in the db table
     *
     * Creates a db record for 'obj' in the database table indicated by its 'dbolE'.
     *
     * @param object $obj   the obj record to be created
     * @param object $dbolE the DbolEntry of the object
     *
     * @return int number of db records created (0 or 1)
     */
    public function create($obj, $dbolE)
    {
        // arguments validation
        if (is_array($obj)) {
            $this->diag(
                102, array('%method' => __FUNCTION__,                    // variable can not be array
                           '%class' =>     get_class($obj[0]),
                           '%table' => $dbolE->table,)
            );
        }

        // prepare insert
        $tableName = $this->_cbc ? $this->_cbc->getDbObjectName($dbolE->table) : $dbolE->table;
        $sth = $this->_dbalI->autoPrepareInsert($tableName, $dbolE->columns, $dbolE->columnTypes);

        // set audit fields
        if (!empty($this->_cbc)) {
            $now  = $this->_variables['timestamp'] = $this->_cbc->getTimestamp();
            if ($dbolE->tableProperties['sequencing']) {
                $iSeq = $this->_variables['insertSequence'] = $this->_cbc->getNextInsertSequence();
            }
            $this->_cbc->setAuditPreInsert($obj, $dbolE);
        }

        // defaults missing properties for PK from db defaults
        foreach ($dbolE->PKColumns as $c => $d) {
            if (!isset($obj->$d) || is_null($obj->$d)) {
                if (isset($dbolE->columnProperties[$d]['default'])) {
                    $obj->$d = $dbolE->columnProperties[$d]['default'];
                }
            }
        }

        // convert obj to array of values according to table column structure
        $values = array();
        foreach ($dbolE->columns as $c => $d) {
            $values[] = isset($obj->$d) ? $obj->$d : null;
        }

        // what's next need to be wrapped in a transaction; if not currently on, opens a local one
        $isTransactionActive = $this->inTransaction();
        if (!$isTransactionActive) {
            $this->beginTransaction();
        }

        // executes insert
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['startTime'] = $this->_cbc->startPerfTiming();
        }
        $res = $this->_dbalI->executePreparedStatement($sth, $values, $dbolE->columnTypes);
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['stopTime'] = $this->_cbc->stopPerfTiming();
            $perfData['elapsed'] = $this->_cbc->elapsedPerfTiming();
            if ($perfData['elapsed'] > $this->_variables['perfThreshold']) {
                $sqlId = 'insert';
                $sqlq = $this->_dbalI->lastQuery($sth);
                $this->diag(
                    10, array(  '%sqlId' => $sqlId,        // executed SQL over perf threshold
                                '%sqlStmt' => $sqlq,
                                '%rowCount' => $res,
                                '%elapsed' => $perfData['elapsed'],)
                );
            }
        }

        // retrieves last autoincrement field values into object, only if these were blank at insert time
        foreach ($dbolE->AIColumns as $c => $d) {
            if (!isset($obj->$d) || is_null($obj->$d)) {
                $lastId = $this->_dbalI->lastInsertID($this->_cbc->getDbObjectName($dbolE->table), $d);
                $obj->$d = $lastId;
            }
        }

        // logs underperforming SQL statements - can't be before to avoid overlaps on fetching lastInsertId
        if ($this->_cbc and $this->_variables['perfLogging']
            and $dbolE->tableProperties['performanceTracking']
            and $perfData['elapsed'] > $this->_variables['perfThreshold']
        ) {
            $this->_cbc->logSQLPerformance($sqlId, $sqlq, $perfData['startTime'], $perfData['stopTime'], $perfData['elapsed'], $res);
        }

        // packs PK attributes into a string
        $obj->primaryKeyString = $this->compactPKIntoString($obj, $dbolE);

        // read back into obj
        if ($dbolE->tableProperties['readBackOnChange']) {
            $this->readSinglePK($obj, $dbolE, $obj->primaryKeyString);
        }

        // generates an audit log if needed
        if ($dbolE->tableProperties['auditLogLevel'] > DBOL_NO_AUDIT) {
            $auditLogId = $this->_cbc->logRowAudit($obj, $dbolE, 'I', $iSeq);
        }

        // commits local transaction if needed
        if (!$isTransactionActive) {
            $this->commit();
        }

        return $res;
    }

    /**
     * Updates current attributes of an object into the db table record
     *
     * Updates 'obj' in the database table indicated by its 'dbolE'.
     *
     * @param object $obj   the obj to be updated
     * @param object $dbolE the DbolEntry of the object
     *
     * @return int 0 if no update was necessary, 1 if update required and successful
     */
    public function update($obj, $dbolE)
    {
        // arguments validation
        if (is_array($obj)) {
            $this->diag(
                102, array('%method' => __FUNCTION__,                    // variable can not be array
                           '%class' =>     get_class($obj[0]),
                           '%table' => $dbolE->table,)
            );
        }

        if (!isset($obj->primaryKeyString)) {
            $this->diag(
                103, array('%method' => __FUNCTION__,                    // missing primaryKeyString
                           '%class' =>     get_class($obj),
                           '%table' => $dbolE->table,)
            );
        }

        // is db update needed?
        // checks against in-object copy of the original record;
        // if no changes, returns 0, otherwise continues
        $values = array();
        $changes = array();
        $dbUpdate = false;
        $primaryKeyChange = false;
        $dbUpdate = $this->_checkRecordChanges($dbolE, $obj, $obj->prevDbImage, $values, $changes, $primaryKeyChange);
        if (!$dbUpdate) {
            return(0);
        }

        // prepares where clause (using ORIGINAL Primary Key)
        $whereClause = $this->explodePKStringIntoWhere($dbolE, $obj->primaryKeyString);

        // what's next need to be wrapped in a transaction; if not currently on, opens a local one
        $isTransactionActive = $this->inTransaction();
        if (!$isTransactionActive) {
            $this->beginTransaction();
        }

        // if db update is required and audit log level is set at DBOL_FIELD_AUDIT, then a preliminary
        // re-read and locking of affected record is required
        if ($dbolE->tableProperties['auditLogLevel'] > DBOL_ROW_AUDIT) {
            // reads the most fresh version of the record from the db (on original PK), locking it for update
            $currentDbImage = $this->_executeRead(
                $dbolE->table, null, $whereClause, null, null, null, DBOL_SELECT_LOCK_FOR_UPDATE
            );

            // if record is not available, then it was deleted by another process so no purpose to update
            if (empty($currentDbImage)) {
                $this->diag(
                    11,  array('%method' => __FUNCTION__,        // Requested update on record no longer existing
                               '%class' =>     get_class($obj),
                               '%table' => $dbolE->table,
                               '%primaryKeyString' => $obj->primaryKeyString,)
                );
                return(0);
            }

            // if update sequence of the fresh version differs from in-object copy, then a different process
            // updated the record in the meantime, so delta should be recalculated based on the fresher version
            if ($this->_cbc->getDbImageUpdateSequence($currentDbImage[0]) != $this->_cbc->getDbImageUpdateSequence($obj->prevDbImage)) {
                $this->diag(
                    12,  array('%method' => __FUNCTION__,        // Requested update on record no longer existing
                               '%class' =>     get_class($obj),
                               '%table' => $dbolE->table,
                               '%primaryKeyString' => $obj->primaryKeyString,
                               '%currDbImageSeq' => $this->_cbc->getDbImageUpdateSequence($currentDbImage[0]),
                               '%prevDbImageSeq' => $this->_cbc->getDbImageUpdateSequence($obj->prevDbImage),)
                );
                $values = array();
                $changes = array();
                $dbUpdate = false;
                $primaryKeyChange = false;
                $dbUpdate = $this->_checkRecordChanges($dbolE, $obj, $currentDbImage[0], $values, $changes, $primaryKeyChange);
                // unlikely to be false
            }
        }

        // set audit fields
        if (!empty($this->_cbc)) {
            $now  = $this->_variables['timestamp'] = $this->_cbc->getTimestamp();
            if ($dbolE->tableProperties['sequencing']) {
                $uSeq = $this->_variables['updateSequence'] = $this->_cbc->getNextUpdateSequence();
                if ($primaryKeyChange) {
                    $iSeq = $this->_variables['insertSequence'] = $this->_cbc->getNextInsertSequence();
                    $dSeq = $this->_variables['deleteSequence'] = $this->_cbc->getNextDeleteSequence();
                }
            }
            $this->_cbc->setAuditPreUpdate($obj, $dbolE, $primaryKeyChange);
            // re-check for changes occurred in callback
            $values = array();
            $changes = array();
            $dbUpdate = false;
            $primaryKeyChange = false;
            $dbUpdate = $this->_checkRecordChanges($dbolE, $obj, $obj->prevDbImage, $values, $changes, $primaryKeyChange);
        }

        // prepares update
        $tableName = $this->_cbc ? $this->_cbc->getDbObjectName($dbolE->table) : $dbolE->table;
        $sth = $this->_dbalI->autoPrepareUpdate($tableName, $dbolE->columns, $whereClause, $dbolE->columnTypes);

        // executes update
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['startTime'] = $this->_cbc->startPerfTiming();
        }
        $res = $this->_dbalI->executePreparedStatement($sth, $values, $dbolE->columnTypes);
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['stopTime'] = $this->_cbc->stopPerfTiming();
            $perfData['elapsed'] = $this->_cbc->elapsedPerfTiming();
            if ($perfData['elapsed'] > $this->_variables['perfThreshold']) {
                $sqlId = 'update';
                $sqlq = $this->_dbalI->lastQuery($sth);
                $this->diag(
                    10, array(  '%sqlId' => $sqlId,        // executed SQL over perf threshold
                                '%sqlStmt' => $sqlq,
                                '%rowCount' => $res,
                                '%elapsed' => $perfData['elapsed'],)
                );
                if ($dbolE->tableProperties['performanceTracking']) {
                    $this->_cbc->logSQLPerformance($sqlId, $sqlq, $perfData['startTime'], $perfData['stopTime'], $perfData['elapsed'], $res);
                }
            }
        }

        // generates an audit log if needed
        if ($dbolE->tableProperties['auditLogLevel'] > DBOL_NO_AUDIT) {
            if (!$primaryKeyChange) {
                $auditLogId = $this->_cbc->logRowAudit($obj, $dbolE, 'U', $uSeq);
                if ($dbolE->tableProperties['auditLogLevel'] > DBOL_ROW_AUDIT) {
                    $this->_cbc->logFieldAudit($obj, $dbolE, $auditLogId, $changes);
                }
            } else {
                $auditLogId = $this->_cbc->logRowAudit($obj, $dbolE, 'u', $uSeq);
                if ($dbolE->tableProperties['auditLogLevel'] > DBOL_ROW_AUDIT) {
                    $this->_cbc->logFieldAudit($obj, $dbolE, $auditLogId, $changes);
                }
                $auditLogId = $this->_cbc->logRowAudit($obj, $dbolE, 'D', $dSeq);
                $obj->primaryKeyString = $this->compactPKIntoString($obj, $dbolE);
            }
        }

        // reads back into obj - needed in case a db level trigger modifies data in the record
        if ($dbolE->tableProperties['readBackOnChange']) {
            $this->readSinglePK($obj, $dbolE, $obj->primaryKeyString);
        }

        // generates an additional audit log if needed and primary key changes (i.e. equals insert)
        if ($dbolE->tableProperties['auditLogLevel'] > DBOL_NO_AUDIT and $primaryKeyChange) {
            $auditLogId = $this->_cbc->logRowAudit($obj, $dbolE, 'I', $iSeq);
        }

        // commits local transaction if needed
        if (!$isTransactionActive) {
            $this->commit();
        }

        return $res;
    }

    /**
     * Compare an object's attributes against an array of fields to determine if db requires update
     *
     * Checks the content of 'obj' against a 'ref' array. Returns TRUE if changes are
     * detected, with the 'values' array bearing the fields content, the  'changes' array
     * bearing the changes at attribute level, and the 'primaryKeyChange' flag to indicate
     * a change to the record's primary key.
     *
     * @param object  $dbolE             the DbolEntry of the object
     * @param object  $obj               the obj to be checked
     * @param array   $ref               the reference array to compare against
     * @param array   &$values           I/O the array of record column values
     * @param array   &$changes          I/O the array of changes detected
     * @param boolean &$primaryKeyChange I/O the indicator of a PK change
     *
     * @return bool TRUE if at least a column changed, FALSE no changes
     */
    private function _checkRecordChanges($dbolE, $obj, $ref, &$values, &$changes, &$primaryKeyChange)
    {
        $dbUpdate = false;
        foreach ($dbolE->columns as $c => $d) {
            $c1 = $obj->$d;
            $c2 = $ref[$d];
            // for float numbers rounds up to avoid minimal differences being tracked
            if ($dbolE->columnTypes[$d] == 'float' or $dbolE->columnTypes[$d] == 'decimal') {
                $roundFactor = $dbolE->columnProperties[$d]['decimal'];
                $roundFactor = empty($roundFactor) ? $this->_variables['decimalPrecision'] : $roundFactor;
                if (!is_null($c1)) {
                    $c1 = (string) round($obj->$d, $roundFactor);
                    $c1 = $c1 == '-0' ? '0' : $c1;
                }
                if (!is_null($c2)) {
                    $c2 = (string) round($ref[$d], $roundFactor);
                    $c2 = $c2 == '-0' ? '0' : $c2;
                }
            }
            // flags record change if field has changed
            if ($c1 <> $c2
                or (is_null($c1) and (!is_null($c2) or $c2 <> ''))
                or (!is_null($c1) and (is_null($c2) or $c2 == ''))
            ) {
                $dbUpdate = true;
                // if change occurs on primary key attribs then special audit logging will be required
                if ($dbolE->tableProperties['auditLogLevel'] > DBOL_NO_AUDIT and $dbolE->columnProperties[$d]['primaryKey']) {
                    $primaryKeyChange = true;
                }
                // if field changes need be tracked then add to the field changes array
                if ($dbolE->tableProperties['auditLogLevel'] > DBOL_ROW_AUDIT and $dbolE->columnProperties[$d]['auditLog']) {
                    if ($dbolE->columnTypes[$d] == 'text' and empty($dbolE->columnProperties[$d]['length'])) {
                      if (extension_loaded('xdiff')) {
                        $diff = xdiff_string_diff($c1, $c2, 0);
                        $changesItem = array ($d, $diff, null, 1);
                      }
                      else {
                        $changesItem = array ($d, $c2, $c1, 0);
                      }
                    } else {
                      $changesItem = array ($d, $c2, $c1, 0);
                    }
                    $changes[] = $changesItem;
                }
            }
            $values[] = $c1;
        }
        return $dbUpdate;
    }

    /**
     * Deletes an object's db record from the db table
     *
     * Deletes the record identifying 'obj' in the database table indicated by its 'dbolE'.
     *
     * @param object $obj   the obj record to be deleted
     * @param object $dbolE the DbolEntry of the object
     *
     * @return int number of db records deleted (0 or 1)
     */
    public function delete($obj, $dbolE)
    {
        // arguments validation
        if (is_array($obj)) {
            $this->diag(
                102, array('%method' => __FUNCTION__,                    // variable can not be array
                           '%class' =>     get_class($obj[0]),
                           '%table' => $dbolE->table,)
            );
        }

        if (!isset($obj->primaryKeyString)) {
            $this->diag(
                103, array('%method' => __FUNCTION__,                    // missing primaryKeyString
                           '%class' =>     get_class($obj),
                           '%table' => $dbolE->table,)
            );
        }

        // prepares for audit log if needed
        if ($dbolE->tableProperties['auditLogLevel'] > DBOL_NO_AUDIT) {
            $now  = $this->_variables['timestamp'] = $this->_cbc->getTimestamp();
            $dSeq = $this->_variables['deleteSequence'] = $this->_cbc->getNextDeleteSequence();
        }

        // prepares where clause
        $whereClause = $this->explodePKStringIntoWhere($dbolE, $obj->primaryKeyString);

        // prepare delete
        $tableName = $this->_cbc ? $this->_cbc->getDbObjectName($dbolE->table) : $dbolE->table;
        $sth = $this->_dbalI->autoPrepareDelete($tableName, $whereClause);

        // what's next need to be wrapped in a transaction; if not currently on, opens a local one
        $isTransactionActive = $this->inTransaction();
        if (!$isTransactionActive) {
            $this->beginTransaction();
        }

        // executes delete
        $res = $this->_dbalI->executePreparedStatement($sth);

        // generates an audit log if needed
        if ($res > 0 and $dbolE->tableProperties['auditLogLevel'] > DBOL_NO_AUDIT) {
            $auditLogId = $this->_cbc->logRowAudit($obj, $dbolE, 'D', $dSeq);
        }

        // commits local transaction if needed
        if (!$isTransactionActive) {
            $this->commit();
        }

        return $res;
    }

    /**
     * Invokes callback to get table and column names for emulated sequences 
     *
     * @param string $seqName name of the sequence
     *
     * @return array returned by the callback - [0] = table name; [1] = column name
     */
    public function getDbEmulatedSequenceQualifiers($seqName)
    {
        return $this->_cbc->getDbEmulatedSequenceQualifiers($seqName);
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
        $sequence = $this->_cbc ? $this->_cbc->getDbObjectName($seqName) : $seqName;
        return $this->_dbalI->createSequence($sequence, $start);
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
        $sequence = $this->_cbc ? $this->_cbc->getDbObjectName($seqName) : $seqName;
        return $this->_dbalI->dropSequence($sequence);
    }

    /**
     * Gets next value of a sequence
     *
     * @param string $seqName  name of the sequence
     * @param bool   $ondemand when true missing sequences are automatic created
     *
     * @return mixed the next free id of the sequence
     */
    public function nextID($seqName, $ondemand = false)
    {
        $sequence = $this->_cbc ? $this->_cbc->getDbObjectName($seqName) : $seqName;
        return $this->_dbalI->nextID($sequence, $ondemand);
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
        $sequence = $this->_cbc ? $this->_cbc->getDbObjectName($seqName) : $seqName;
        return $this->_dbalI->currID($sequence);
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
        return $this->_dbalI->inTransaction($ignoreNested);
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
        return $this->_dbalI->beginTransaction($savepoint);
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
        return $this->_dbalI->commit($savepoint);
    }

    /**
     * Logs a diagnostic message from the id
     *
     * Fetches the severity and textual message via diagnosticMessage and hands over to diagnosticMessage
     *
     * @param int   $id     the id of the diagnostic message
     * @param array $params the parameters to qualify the message
     *
     * @return void
     */
    public function diag($id, $params = null)
    {
        list($severity, $params['#text']) = $this->_message($id);
        $this->diagnosticMessage($severity, $id, $params); 
    }

    /**
     * Logs a diagnostic message and manages error conditions
     *
     * If exitOnError is true and the severity of the message is DBOL_ERROR, calls the errorHandler method
     * in the callback object (or throws exception if callback object is not defined)
     *
     * @param int    $severity    severity of the diagnostic message
     * @param int    $id          id of the diagnostic message
     * @param array  $params      parameters to qualify the message
     * @param string $className   class generating the message
     * @param bool   $exitOnError if true exits execution via callback or via exception
     *
     * @return void
     */
    public function diagnosticMessage($severity, $id, $params, $className = null, $exitOnError = true)
    {
        if (empty($className)) {
            $className = get_class($this);
        }
        $qText = $text = $params['#text'];
        foreach ($params as $a => $b) {
            if ($a <> '#text' and $a <> '#elapsed') {
                $qText = str_replace($a, $b, $qText);
            }
        }
        if (isset($this->_cbc)) {
            $this->_cbc->diagnosticMessage($severity, $id, $text, $params, $qText, $className);
            if ($severity == DBOL_ERROR and $exitOnError) {
                $this->_cbc->errorHandler($id, $text, $params, $qText, $className);
            }
        } else {
            throw new Exception($qText, $id);
        }
    }

    /**
     * Returns an array with error severity and text for an error code
     *
     * @param int|array $id integer error code,
     *                      null to get the current error code-message map,
     *                      or an array with a new error code-message map
     *
     * @return mixed an array with error severity and text,
     *               or DBOL_ERROR if the error code was not recognized
     */
    private function _message($id = null)
    {
        static $diagnosticMessages; 

        if (is_array($id)) {
            $diagnosticMessages = $id;
            return DBOL_NOTICE;
        }
        if (!isset($diagnosticMessages)) {
            $diagnosticMessages = array(
                10       => array(DBOL_DEBUG, '%sqlId exceeded performance threshold (mSec=%elapsed, count=%rowCount)'),
                11       => array(DBOL_INFO,  'Requested update on record no longer existing. Class: %class, Table: %table, PK: %primaryKeyString'),
                12       => array(DBOL_INFO,  'Detected concurrent update. Class: %class, Table: %table, PK: %primaryKeyString, Sequence at read: %prevDbImageSeq, Sequence at update: %currDbImageSeq'),
                100      => array(DBOL_ERROR, 'Variable %variable must be an array'),
                101      => array(DBOL_ERROR, 'readSingle returned more than 1 record. Class: %class, Table: %table, Where: %whereClause'),
                102      => array(DBOL_ERROR, '%method method does not support array of records as input. Class: %class, Table: %table'),
                103      => array(DBOL_ERROR, '%method requested on object missing primaryKeyString attribute. Class: %class, Table: %table'),
                104      => array(DBOL_ERROR, 'Undefined method %class::%method called'),
                105      => array(DBOL_ERROR, "Database system for driver '%driver' not supported"),
                106      => array(DBOL_ERROR, "Native datatype '%type' not supported"),
                107      => array(DBOL_ERROR, "Query result row limitation not supported for %dbms"),
                108      => array(DBOL_ERROR, 'Database handle not initialised.'),
            );
        }
        if (is_null($id)) {
            return $diagnosticMessages;
        }
        return isset($diagnosticMessages[$id]) ? $diagnosticMessages[$id] : DBOL_ERROR;
    }

    /**
     * Returns an array with DBMS details
     *
     * @param int $id DBMS identifier
     *
     * @return array an array with DBMS details
     */
    public function getDBMSInfo($id)
    {
        static $DBMS;
        if (!isset($DBMS)) {
            $DBMS = array(
                DBOL_DBMS_MYSQL         => array('mysql', 'MySql', 'MySql'),
                DBOL_DBMS_MSSQL         => array('mssql', 'MS SQL Server', 'Microsoft SQL Server'),
                DBOL_DBMS_POSTGRESQL    => array('pgsql', 'PostgreSQL', 'PostgreSQL'),
                DBOL_DBMS_ORACLE        => array('oracle', 'Oracle', 'Oracle'),
                DBOL_DBMS_QUERYSIM      => array('querysim', 'Querysim', 'Querysim'),
                DBOL_DBMS_SQLITE        => array('sqlite', 'SQLite', 'SQLite'),
                DBOL_DBMS_FBASE         => array('fbase', 'FrontBase', 'FrontBase'),
                DBOL_DBMS_IBASE         => array('ibase', 'InterBase', 'InterBase'),
                DBOL_DBMS_CUBRID        => array('cubrid', 'Cubrid', 'Cubrid'),
                DBOL_DBMS_FIREBIRD      => array('firebird', 'Firebird', 'Firebird'),
                DBOL_DBMS_SYBASE        => array('sybase', 'Sybase', 'Sybase'),
                DBOL_DBMS_DB2           => array('db2', 'IBM DB2', 'IBM DB2'),
                DBOL_DBMS_INFORMIX      => array('informix', 'Informix', 'Informix'),
                DBOL_DBMS_4D            => array('4d', '4D', '4D'),
                DBOL_DBMS_ODBC          => array('odbc', 'ODBC', 'ODBC'),
            );
        }
        return isset($DBMS[$id]) ? $DBMS[$id] : DBOL_ERROR;
    }
}
