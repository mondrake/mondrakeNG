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

namespace mondrakeNG\dbol;

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
    const DBOL_NO_AUDIT = 0;
    const DBOL_ROW_AUDIT = 1;
    const DBOL_FIELD_AUDIT = 2;
    const DBOL_DEBUG = 7;
    const DBOL_INFO = 6;
    const DBOL_NOTICE = 5;
    const DBOL_WARNING = 4;
    const DBOL_ERROR = 3;
    const DBOL_DEFAULT_PKSEPARATOR = '|';
    const DBOL_DEFAULT_PKSEPARATORREPLACE = '#&!vbar!&#';

    protected $_variables = [];          // session context variables
    protected $_cbc = null;                        // callback interface instance
    protected $dbConnection = null;

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
   *     'callbackClass'      =>    if passed, name of a callback class to instantiate using 'this'
   *                                as an argument;
   *                                if not passed, instantiated callback object can be passed later via
   *                                function 'setCallback'
   *     'decimalPrecision'   =>    default precision of decimal fields (can be overridden by single columns)
   *     'PKSeparator'        =>    separator to use to build a primaryKeyString (default = static::DBOL_DEFAULT_PKSEPARATOR)
   *     'PKSeparatorReplace' =>    string used to replace occurrences of 'PKSeparator' in text of PK columns values
   *                                (default = static::DBOL_DEFAULT_PKSEPARATORREPLACE)
   *     'perfLogging'        =>    bool to specify if SQL performance should be tracked
   *     'perfThreshold'      =>    threshold in milliseconds above which underperforming SQL statements are tracked
   *
   * @param array $variables the parameters to use to instantiate the dbol class
   */
    public function __construct($connection_name, array $variables)
    {
        $this->dbConnection = DbConnection::getConnection($connection_name)['connection'];

      // sets $variables as dbol variables
        $this->setVariables($variables);
      // if callback class name passed, instantiates an object with passing $this as an
      // argument
        if (isset($this->_variables['callbackClass'])) {
            $this->_cbc = new $variables['callbackClass']($this);
        }
      // sets default PK separator
        if (!isset($this->_variables['PKSeparator'])) {
            $this->_variables['PKSeparator'] = static::DBOL_DEFAULT_PKSEPARATOR;
        }
      // sets default PK separator replace
        if (!isset($this->_variables['PKSeparatorReplace'])) {
            $this->_variables['PKSeparatorReplace'] = static::DBOL_DEFAULT_PKSEPARATORREPLACE;
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
            104,
            ['%method' => $name,                    // undefined method called
                 '%class' =>     get_class($this),]
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
            $this->diag(100, ['%variable' => '$params',]);        // variable must be array
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
            $this->diag(100, ['%variable' => '$cols',]);        // variable must be array
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
            $this->diag(100, ['%variable' => '$cols',]);        // variable must be array
        }

        if (!empty($cols)) {
            $this->setColumnProperty($dbolE, $cols, 'primaryKey', true);
            $dbolE->PKColumns = [];
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
    protected function addWhereFromPKString($qb, $dbolE, $PKString, $offset_parm = 0)
    {
        $attrs = explode($this->_variables['PKSeparator'], $PKString);
        $j = 0;
        foreach ($dbolE->PKColumns as $c => $d) {
            $e = str_replace($this->_variables['PKSeparatorReplace'], $this->_variables['PKSeparator'], $attrs[$j]);
            if (!$j) {
                $qb->where($d . ' = ?');
            } else {
                $qb->andWhere($d . ' = ?');
            }
            $qb->setParameter($j + $offset_parm, $e);
            $j++;
        }
        return;
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
        $qb = $this->dbConnection->createQueryBuilder();
        $qb
        ->select('*')
        ->from($dbolE->table);
        $this->addWhereFromPKString($qb, $dbolE, $pkId);
        $ret = $qb->execute();
        $row = $ret->fetch();
        if (empty($row)) {
            return null;
        }
      // associates row columns to object attributes
        foreach ($row as $a => $b) {
            $obj->$a = $b;
        }
      // packs PK attributes into a string
        $obj->primaryKeyString = $this->compactPKIntoString($obj, $dbolE);
      // creates an in-object copy of the db record to enable later image checking
        $obj->prevDbImage = $row;
        $obj->prevDbImage['primaryKeyString'] = $obj->primaryKeyString;

        return clone $obj;
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
                101,
                [ '%table' => $dbolE->table,        // more than 1 record
                      '%class' => get_class($obj),
                      '%whereClause' => $whereClause,]
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
        $rows = [];
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
        $res = $this->_executeRead($dbolE->table, ['count(*) as rowcount'], $whereClause);
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
    protected function _executeRead($table, $cols = null, $whereClause = null, $orderClause = null, $limit = null, $offset = null)
    {
        $qb = $this->dbConnection->createQueryBuilder();
      // columns
        if (empty($cols)) {
            $qb->select('*');
        } else {
            $qb->select($cols);
        }
      // table
        $qb->from($this->resolveDbObjectName($table));
      // where
        if ($whereClause) {
            $qb->where($whereClause);
        }
      // order by
        if ($orderClause) {
            $qb->orderBy($orderClause, ' ');
        }
      // limit
        if ($limit) {
            $qb->setMaxResults($limit);
        }
      // offset
        if ($offset) {
            $qb->setFirstResult($offset);
        }
      // executes query
        $ret = $qb->execute();
        $res = $ret->fetchAll();
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
        $rows = [];
        while ($row = $this->fetchRow($qh)) {
            $rows[] = $row;
        }
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $stopTime = $this->_cbc->stopPerfTiming();
            $elapsed = $this->_cbc->elapsedPerfTiming();
            if ($elapsed > $this->_variables['perfThreshold']) {
                $sqlId = $sqlId ? $sqlId : 'select';
                $this->diag(
                    10,
                    ['%sqlId' => $sqlId,                    // executed SQL over perf threshold
                      '%sqlStmt' => $sqlq,
                      '%rowCount' => count($rows),
                      '%elapsed' => $elapsed,]
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
        $sqlq = $this->dbConnection->getDatabasePlatform()->modifyLimitQuery($sqlq, $limit, $offset);
        if ($this->_cbc and $this->_variables['perfLogging'] and !$skipPerfLog) {
            $startTime = $this->_cbc->startPerfTiming();
        }
        $qh = $this->dbConnection->query($sqlq);
        if ($this->_cbc and $this->_variables['perfLogging'] and !$skipPerfLog) {
            $stopTime = $this->_cbc->stopPerfTiming();
            $elapsed = $this->_cbc->elapsedPerfTiming();
            if ($elapsed > $this->_variables['perfThreshold']) {
                $sqlId = $sqlId ? $sqlId : 'select';
                $this->diag(
                    10,
                    ['%sqlId' => $sqlId,                    // executed SQL over perf threshold
                      '%sqlStmt' => $sqlq,
                      '%rowCount' => 'na',
                      '%elapsed' => $elapsed,]
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
        return $qh->fetch();
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
        $res = $this->dbConnection->query($sqlq);
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['stopTime'] = $this->_cbc->stopPerfTiming();
            $perfData['elapsed'] = $this->_cbc->elapsedPerfTiming();
            if ($perfData['elapsed'] > $this->_variables['perfThreshold']) {
                $this->diag(
                    10,
                    [  '%sqlId' => $sqlId ? $sqlId : 'execute',    // executed SQL over perf threshold
                        '%sqlStmt' => $sqlq,
                        '%rowCount' => $res->rowCount(),
                        '%elapsed' => $perfData['elapsed'],]
                );
                $this->_cbc->logSQLPerformance($sqlId, $sqlq, $perfData['startTime'], $perfData['stopTime'], $perfData['elapsed'], $res->rowCount());
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
                102,
                ['%method' => __FUNCTION__,                    // variable can not be array
                   '%class' =>     get_class($obj[0]),
                   '%table' => $dbolE->table,]
            );
        }

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
        $values = [];
        foreach ($dbolE->columns as $c => $d) {
            $values[] = isset($obj->$d) ? $obj->$d : null;
        }

      // prepare insert
        $tableName = $this->resolveDbObjectName($dbolE->table);
        $qb = $this->dbConnection->createQueryBuilder();
        $qb
        ->insert($tableName);
        foreach ($dbolE->columns as $a => $b) {
            $qb
            ->setValue($b, '?')
            ->setParameter($a, ($values[$a] === '' || $values[$a] === null) ? null : $values[$a]);
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
        $res = $qb->execute();
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['stopTime'] = $this->_cbc->stopPerfTiming();
            $perfData['elapsed'] = $this->_cbc->elapsedPerfTiming();
            if ($perfData['elapsed'] > $this->_variables['perfThreshold']) {
                $sqlId = 'insert';
                $sqlq = $qb->getSQL();
                $this->diag(
                    10,
                    [  '%sqlId' => $sqlId,        // executed SQL over perf threshold
                      '%sqlStmt' => $sqlq,
                      '%rowCount' => $res,
                      '%elapsed' => $perfData['elapsed'],]
                );
            }
        }

      // retrieves last autoincrement field values into object, only if these were blank at insert time
        foreach ($dbolE->AIColumns as $c => $d) {
            if (!isset($obj->$d) || is_null($obj->$d)) {
                $lastId = $this->dbConnection->lastInsertID($this->resolveDbObjectName($dbolE->table)); // @todo AI tracking per column? was in dbol1 but Dcotrine DBAL does not support
                $obj->$d = $lastId;
            }
        }

      // logs underperforming SQL statements - can't be before to avoid overlaps on fetching lastInsertId
        if ($this->_cbc && $this->_variables['perfLogging'] && $dbolE->tableProperties['performanceTracking'] && $perfData['elapsed'] > $this->_variables['perfThreshold']) {
            $this->_cbc->logSQLPerformance($sqlId, $sqlq, $perfData['startTime'], $perfData['stopTime'], $perfData['elapsed'], $res);
        }

      // packs PK attributes into a string
        $obj->primaryKeyString = $this->compactPKIntoString($obj, $dbolE);

      // read back into obj
        if ($dbolE->tableProperties['readBackOnChange']) {
            $this->readSinglePK($obj, $dbolE, $obj->primaryKeyString);
        }

      // generates an audit log if needed
        if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_NO_AUDIT) {
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
                102,
                ['%method' => __FUNCTION__,                    // variable can not be array
                   '%class' =>     get_class($obj[0]),
                   '%table' => $dbolE->table,]
            );
        }

        if (!isset($obj->primaryKeyString)) {
            $this->diag(
                103,
                ['%method' => __FUNCTION__,                    // missing primaryKeyString
                   '%class' =>     get_class($obj),
                   '%table' => $dbolE->table,]
            );
        }

      // is db update needed?
      // checks against in-object copy of the original record;
      // if no changes, returns 0, otherwise continues
        $values = [];
        $changes = [];
        $dbUpdate = false;
        $primaryKeyChange = false;
        $dbUpdate = $this->_checkRecordChanges($dbolE, $obj, $obj->prevDbImage, $values, $changes, $primaryKeyChange);
        if (!$dbUpdate) {
            return(0);
        }

      // gets table name
        $tableName = $this->resolveDbObjectName($dbolE->table);

      // preserves ORIGINAL primary key
        $originalPKstring = $obj->primaryKeyString;

      // what's next need to be wrapped in a transaction; if not currently on, opens a local one
        $isTransactionActive = $this->inTransaction();
        if (!$isTransactionActive) {
            $this->beginTransaction();
        }

      // if db update is required and audit log level is set at static::DBOL_FIELD_AUDIT, then a preliminary
      // re-read and locking of affected record is required
        if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_ROW_AUDIT) {
          // reads the most fresh version of the record from the db (on original PK), locking it for update
            $sql = "SELECT * FROM $tableName";
            $attrs = explode($this->_variables['PKSeparator'], $originalPKstring);
            foreach ($dbolE->PKColumns as $c => $d) {
                if ($c === 0) {
                    $sql .= (' WHERE ' . $d . ' = ?');
                } else {
                    $sql .= (' AND ' . $d . ' = ?');
                }
            }
            $sql .= ' ' . $this->dbConnection->getDatabasePlatform()->getForUpdateSQL();
            $stmt = $this->dbConnection->prepare($sql);
            foreach ($dbolE->PKColumns as $c => $d) {
                $e = str_replace($this->_variables['PKSeparatorReplace'], $this->_variables['PKSeparator'], $attrs[$c]);
                $stmt->bindValue($c + 1, $e);
            }
            $ret = $stmt->execute();
            $currentDbImage = $stmt->fetch();

          // if record is not available, then it was deleted by another process so no purpose to update
            if (empty($currentDbImage)) {
                $this->diag(
                    11,
                    ['%method' => __FUNCTION__,        // Requested update on record no longer existing
                     '%class' =>     get_class($obj),
                     '%table' => $dbolE->table,
                     '%primaryKeyString' => $originalPKstring,]
                );
                return(0);
            }

          // if update sequence of the fresh version differs from in-object copy, then a different process
          // updated the record in the meantime, so delta should be recalculated based on the fresher version
            if ($this->_cbc->getDbImageUpdateSequence($currentDbImage) != $this->_cbc->getDbImageUpdateSequence($obj->prevDbImage)) {
                $this->diag(
                    12,
                    ['%method' => __FUNCTION__,        // Requested update on record no longer existing
                     '%class' =>     get_class($obj),
                     '%table' => $dbolE->table,
                     '%primaryKeyString' => $originalPKstring,
                     '%currDbImageSeq' => $this->_cbc->getDbImageUpdateSequence($currentDbImage),
                     '%prevDbImageSeq' => $this->_cbc->getDbImageUpdateSequence($obj->prevDbImage),]
                );
                $values = [];
                $changes = [];
                $dbUpdate = false;
                $primaryKeyChange = false;
                $dbUpdate = $this->_checkRecordChanges($dbolE, $obj, $currentDbImage, $values, $changes, $primaryKeyChange);
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
            $values = [];
            $changes = [];
            $dbUpdate = false;
            $primaryKeyChange = false;
            $dbUpdate = $this->_checkRecordChanges($dbolE, $obj, $obj->prevDbImage, $values, $changes, $primaryKeyChange);
        }

      // prepares update (using ORIGINAL Primary Key)
        $qb = $this->dbConnection->createQueryBuilder();
        $qb->update($tableName);
        foreach ($dbolE->columns as $a => $b) {
            $qb
            ->set($b, '?')
            ->setParameter($a, ($values[$a] === '' || $values[$a] === null) ? null : $values[$a]);
        }
        $this->addWhereFromPKString($qb, $dbolE, $originalPKstring, ++$a);

      // executes update
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['startTime'] = $this->_cbc->startPerfTiming();
        }
        $res = $qb->execute();
        if ($this->_cbc and $this->_variables['perfLogging']) {
            $perfData['stopTime'] = $this->_cbc->stopPerfTiming();
            $perfData['elapsed'] = $this->_cbc->elapsedPerfTiming();
            if ($perfData['elapsed'] > $this->_variables['perfThreshold']) {
                $sqlId = 'update';
                $sqlq = $qb->getSQL();
                $this->diag(
                    10,
                    [  '%sqlId' => $sqlId,        // executed SQL over perf threshold
                      '%sqlStmt' => $sqlq,
                      '%rowCount' => $res,
                      '%elapsed' => $perfData['elapsed'],]
                );
                if ($dbolE->tableProperties['performanceTracking']) {
                      $this->_cbc->logSQLPerformance($sqlId, $sqlq, $perfData['startTime'], $perfData['stopTime'], $perfData['elapsed'], $res);
                }
            }
        }

      // generates an audit log if needed
        if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_NO_AUDIT) {
            if (!$primaryKeyChange) {
                $auditLogId = $this->_cbc->logRowAudit($obj, $dbolE, 'U', $uSeq);
                if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_ROW_AUDIT) {
                    $this->_cbc->logFieldAudit($obj, $dbolE, $auditLogId, $changes);
                }
            } else {
                $auditLogId = $this->_cbc->logRowAudit($obj, $dbolE, 'u', $uSeq);
                if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_ROW_AUDIT) {
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
        if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_NO_AUDIT and $primaryKeyChange) {
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
    protected function _checkRecordChanges($dbolE, $obj, $ref, &$values, &$changes, &$primaryKeyChange)
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
            if ($c1 <> $c2 || (is_null($c1) and (!is_null($c2) or $c2 <> '')) || (!is_null($c1) and (is_null($c2) or $c2 == ''))) {
                $dbUpdate = true;
              // if change occurs on primary key attribs then special audit logging will be required
                if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_NO_AUDIT and $dbolE->columnProperties[$d]['primaryKey']) {
                    $primaryKeyChange = true;
                }
              // if field changes need be tracked then add to the field changes array
                if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_ROW_AUDIT and $dbolE->columnProperties[$d]['auditLog']) {
                    if ($dbolE->columnTypes[$d] == 'text') {
                        if (extension_loaded('xdiff')) {
                            $diff = xdiff_string_diff($c1, $c2, 0);
                            $changesItem = [$d, $diff, null, 1];
                        } else {
                            $changesItem = [$d, $c2, $c1, 0];
                        }
                    } else {
                        $changesItem = [$d, $c2, $c1, 0];
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
                102,
                ['%method' => __FUNCTION__,                    // variable can not be array
                   '%class' =>     get_class($obj[0]),
                   '%table' => $dbolE->table,]
            );
        }

        if (!isset($obj->primaryKeyString)) {
            $this->diag(
                103,
                ['%method' => __FUNCTION__,                    // missing primaryKeyString
                   '%class' =>     get_class($obj),
                   '%table' => $dbolE->table,]
            );
        }

      // prepares for audit log if needed
        if ($dbolE->tableProperties['auditLogLevel'] > static::DBOL_NO_AUDIT) {
            $now  = $this->_variables['timestamp'] = $this->_cbc->getTimestamp();
            $dSeq = $this->_variables['deleteSequence'] = $this->_cbc->getNextDeleteSequence();
        }

      // prepare delete
        $tableName = $this->resolveDbObjectName($dbolE->table);
        $qb = $this->dbConnection->createQueryBuilder();
        $qb->delete($tableName);
        $this->addWhereFromPKString($qb, $dbolE, $obj->primaryKeyString);

      // what's next need to be wrapped in a transaction; if not currently on, opens a local one
        $isTransactionActive = $this->inTransaction();
        if (!$isTransactionActive) {
            $this->beginTransaction();
        }

  // @todo delete is not traced for performance

      // executes delete
        $res = $qb->execute();

      // generates an audit log if needed
        if ($res > 0 and $dbolE->tableProperties['auditLogLevel'] > static::DBOL_NO_AUDIT) {
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
        $sequence = $this->resolveDbObjectName($seqName);
        return false; // @todo not yet implemented
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
        $sequence = $this->resolveDbObjectName($seqName);
        return false; // @todo not yet implemented
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
        $sequence = $this->resolveDbObjectName($seqName);
      // @todo improve, implement onDemand
        if ($this->dbConnection->getDatabasePlatform()->supportsSequences()) {
          // @todo
            $value = null;
        } else {
            list($seqTable, $seqCol) = $this->getDbEmulatedSequenceQualifiers($sequence);
            $qb = $this->dbConnection->createQueryBuilder();
            $qb
            ->insert($seqTable)
            ->setValue($seqCol, '?')
            ->setParameter(0, null);
            $result = $qb->execute();
            $value = $this->dbConnection->lastInsertID($seqTable);
            if (is_numeric($value)) {
                $qb = $this->dbConnection->createQueryBuilder();
                $qb
                ->delete($seqTable)
                ->where("$seqCol < ?")
                ->setParameter(0, $value);
                $result = $qb->execute();
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
        $sequence = $this->resolveDbObjectName($seqName);
      // @todo improve
        if ($this->dbConnection->getDatabasePlatform()->supportsSequences()) {
          // @todo
            return null;
        } else {
            list($seqTable, $seqCol) = $this->getDbEmulatedSequenceQualifiers($sequence);
            $qb = $this->dbConnection->createQueryBuilder();
            $qb
            ->select("MAX($seqCol) AS a")
            ->from($seqTable);
            $ret = $qb->execute();
            return $ret->fetch()['a'];
        }
    }

  /**
   * Determines if a transaction is currently open
   *
   * @return bool     true is returned for a normal open transaction
   *                  false is returned if no transaction is open
   */
    public function inTransaction()
    {
        return $this->dbConnection->isTransactionActive();
    }

  /**
   * Start a transaction
   */
    public function beginTransaction()
    {
        $res = $this->dbConnection->beginTransaction();
        return $res;
    }

  /**
   * Commit the database changes done during a transaction that is in progress or release a savepoint
   *
   * This function may only be called when auto-committing is disabled, otherwise it will fail.
   * Therefore, a new transaction is implicitly started after committing the pending changes.
   */
    public function commit()
    {
        $res = $this->dbConnection->commit();
        return $res;
    }

  /**
   * Returns fully qualified db object name.
   *
   * @param string $db_object   the unqualified db oject name
   *
   * @return string
   */
    protected function resolveDbObjectName($db_object)
    {
        return $this->_cbc ? $this->_cbc->getDbObjectName($db_object) : $db_object;
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
   * If exitOnError is true and the severity of the message is static::DBOL_ERROR, calls the errorHandler method
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
            if ($severity == static::DBOL_ERROR and $exitOnError) {
                $this->_cbc->errorHandler($id, $text, $params, $qText, $className);
            }
        } else {
            throw new \Exception($qText, $id);
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
   *               or static::DBOL_ERROR if the error code was not recognized
   */
    protected function _message($id = null)
    {
        static $diagnosticMessages;

        if (is_array($id)) {
            $diagnosticMessages = $id;
            return static::DBOL_NOTICE;
        }
        if (!isset($diagnosticMessages)) {
            $diagnosticMessages = [
            10       => [static::DBOL_DEBUG, '%sqlId exceeded performance threshold (mSec=%elapsed, count=%rowCount)'],
            11       => [static::DBOL_INFO,  'Requested update on record no longer existing. Class: %class, Table: %table, PK: %primaryKeyString'],
            12       => [static::DBOL_INFO,  'Detected concurrent update. Class: %class, Table: %table, PK: %primaryKeyString, Sequence at read: %prevDbImageSeq, Sequence at update: %currDbImageSeq'],
            100      => [static::DBOL_ERROR, 'Variable %variable must be an array'],
            101      => [static::DBOL_ERROR, 'readSingle returned more than 1 record. Class: %class, Table: %table, Where: %whereClause'],
            102      => [static::DBOL_ERROR, '%method method does not support array of records as input. Class: %class, Table: %table'],
            103      => [static::DBOL_ERROR, '%method requested on object missing primaryKeyString attribute. Class: %class, Table: %table'],
            104      => [static::DBOL_ERROR, 'Undefined method %class::%method called'],
            105      => [static::DBOL_ERROR, "Database system for driver '%driver' not supported"],
            106      => [static::DBOL_ERROR, "Native datatype '%type' not supported"],
            107      => [static::DBOL_ERROR, "Query result row limitation not supported for %dbms"],
            108      => [static::DBOL_ERROR, 'Database handle not initialised.'],
            ];
        }
        if (is_null($id)) {
            return $diagnosticMessages;
        }
        return isset($diagnosticMessages[$id]) ? $diagnosticMessages[$id] : static::DBOL_ERROR;
    }
}
