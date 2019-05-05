<?php

namespace mondrakeNG\mm\core;

use mondrakeNG\dbol\DbConnection;
use mondrakeNG\dbol\Dbol;
use mondrakeNG\dbol\DbolEntry;

abstract class MMObj
{
    const MMOBJ_DEBUG = 7;
    const MMOBJ_INFO = 6;
    const MMOBJ_NOTICE = 5;
    const MMOBJ_OK = 5;
    const MMOBJ_WARNING = 4;
    const MMOBJ_ERROR = 3;

    protected static $dbol = null;
    protected static $dbObj = array();
    protected static $childObjs = array();
    protected $diag = null;
    protected $className;

    public function __construct()
    {
      // dbol
        if (self::$dbol === null) {
            $dbolParams = array(
            'decimalPrecision'  => DB_DECIMALPRECISION,
            'callbackClass'     => '\\mondrakeNG\\mm\\core\\MMObjDbolCallback',
            'perfLogging'       => DB_QUERY_PERFORMANCE_LOGGING,
            'perfThreshold'     => DB_QUERY_PERFORMANCE_THRESHOLD,
            'user'              => 1,
            );
            self::$dbol = new Dbol('MM', $dbolParams);
        }
      // diag
        $this->diag = new MMDiag;
      // tries to get dbObj from cache
        $this->className = get_class($this);
        if (!isset(self::$dbObj[$this->className])) {
            if ($this->className <> 'mondrakeNG\\mm\\classes\\MMDbCache') {
                $x = MMUtils::getCache("DbolEntry:$this->className");
                if (is_object($x)) {
                    self::$dbObj[$this->className] = clone $x;
                }
            } else {
                $sqlq = "SELECT * FROM #pfx#mm_db_cache where cache_id = 'DbolEntry:MMDbCache'";
                $res = self::$dbol->query($sqlq, $limit = null, $offset = null, $sqlId = null, $skipPerfLog = true);
                if ($res) {
                    self::$dbObj[$this->className] = unserialize($res[0]['data']);
                }
            }
        }
      // if nor cached, builds dbObj
        if (!isset(self::$dbObj[$this->className])) {
            self::$dbObj[$this->className] = new DbolEntry;

          // retrieves table name and audit log level from class<->table_name table in DBMS
            $xxx = str_replace("\\", "\\\\", $this->className);
            $sqlq = "SELECT * FROM #pfx#mm_classes where mm_class_name = '$xxx'";
            $res = self::$dbol->query($sqlq, $limit = null, $offset = null, $sqlId = null, $skipPerfLog = true);
            if (count($res) == 0) {
                $this->diagLog(static::MMOBJ_ERROR, 100, array('#text' => "Class $this->className not found in repository.",), 'MMObj', true);
            }
            self::$dbObj[$this->className]->table = $res[0]['db_table_name'];
            self::$dbObj[$this->className]->tableProperties['auditLogLevel'] = $res[0]['db_table_audit_log_level'];
            self::$dbObj[$this->className]->tableProperties['listOrder'] = $res[0]['list_order'];
            self::$dbObj[$this->className]->tableProperties['environmentDependent'] = $res[0]['is_environment_dependent'];
            self::$dbObj[$this->className]->tableProperties['clientTracking'] = $res[0]['is_client_source_tracked'];
            self::$dbObj[$this->className]->tableProperties['readBackOnChange'] = $res[0]['is_read_back_on_change'];
            self::$dbObj[$this->className]->tableProperties['performanceTracking'] = $res[0]['is_performance_tracked'];
            self::$dbObj[$this->className]->tableProperties['sequencing'] = $res[0]['is_sequenced'];

            DbConnection::fetchAllColumnsProperties('MM', self::$dbObj[$this->className]->table, self::$dbObj[$this->className]);

            self::$dbol->setColumnProperty(
                self::$dbObj[$this->className],
                array (
                'create_by',
                'create_ts',
                'update_by',
                'update_ts',
                'update_id'),
                'editable',
                false
            );

            MMUtils::setCache("DbolEntry:$this->className", self::$dbObj[$this->className]);
        }
        $this->setPKColumns();
        $this->defineChildObjs();
      //print_r(self::$childObjs);
      // sets pk and reads from db if arguments passed
        $args = func_get_args();
        if (count($args) > 0) {
            if (is_array($args[0])) {
                $args = $args[0];
            }
            foreach ($args as $k => $v) {
                $col = self::$dbObj[$this->className]->PKColumns[$k];
                $this->$col = $v;
            }
        }
    }

    public function setPKColumns()
    {
  //        return self::$dbol->setPKColumns(self::$dbObj[$this->className], $cols);
    }
    public function defineChildObjs()
    {
    }

    public function getdbol()
    {
        return self::$dbol;
    }

    public function getDbObj()
    {
        return self::$dbObj[$this->className];
    }

    public function setSessionContext($params)
    {
        self::$dbol->setVariables($params);
    }

    public function getSessionContext($var = null)
    {
        return self::$dbol->getVariable($var);
    }

    public function setColumnProperty($cols, $prop, $value)
    {
        return self::$dbol->setColumnProperty(self::$dbObj[$this->className], $cols, $prop, $value);
    }

    public function getColumnProperties()
    {
        return self::$dbol->getColumnProperties(self::$dbObj[$this->className]);
    }

    public function compactPKIntoString()
    {
        return self::$dbol->compactPKIntoString($this, self::$dbObj[$this->className]);
    }

    public function loadChildObjs($tgt, $objName)
    {
        $obj = self::$childObjs[$this->className][$objName];
        $tgt->$objName = new $obj['className'];
        if (isset($obj['whereClause'])) {
            $whereClause = $obj['whereClause'];
            foreach ($obj['parameters'] as $pno => $p) {
                $whereClause = str_replace("#$pno#", $tgt->$p, $whereClause);
        //print($whereClause);
            }
        }
        if ($obj['cardinality'] == 'one') {
            if (!isset($whereClause)) {
                $parm = $obj['parameters'][0];
                $tgt->$objName->read($tgt->$parm);        // via pk
            } else {
                $tgt->$objName->readSingle($whereClause);        // via whereClause
            }
        } else {
            $tgt->$objName = $tgt->$objName->readMulti($whereClause);
        }
    }

    public function read()
    {
        $args = func_get_args();
        if (count($args) == 1) {
            $pk = $args[0];
        } elseif (count($args) > 1) {
            foreach ($args as $k => $v) {
                $col = $this->getDbObj()->PKColumns[$k];
                $this->$col = $v;
                $pk = $this->compactPKIntoString();
            }
        } else {
            $pk = $this->compactPKIntoString();
        }
        $r = self::$dbol->readSinglePK($this, self::$dbObj[$this->className], $pk);
        if (!$r) {
            return null;
        }
        if (!empty(self::$childObjs[$this->className])) {
            foreach (self::$childObjs[$this->className] as $objName => $obj) {
                if ($obj['loading'] == 'onRead') {
                    self::loadChildObjs($this, $objName);
                }
            }
        }
        return $r;
    }

    public function readSingle($whereClause)
    {
        $r = self::$dbol->readSingle($this, self::$dbObj[$this->className], $whereClause);
        if (!$r) {
            return null;
        }
        if (!empty(self::$childObjs[$this->className])) {
            foreach (self::$childObjs[$this->className] as $objName => $obj) {
                if ($obj['loading'] == 'onRead') {
                    self::loadChildObjs($this, $objName);
                }
            }
        }
        return $r;
    }

    public function readMulti($whereClause = null, $orderClause = null, $limit = null, $offset = null)
    {
        $ret = self::$dbol->readMulti($this, self::$dbObj[$this->className], $whereClause, $orderClause, $limit, $offset);
        if (!empty($ret) and !empty(self::$childObjs[$this->className])) {
            foreach ($ret as $ctr => $rec) {
                foreach (self::$childObjs[$this->className] as $objName => $obj) {
                    if ($obj['loading'] == 'onRead') {
                        self::loadChildObjs($rec, $objName);
                    }
                }
            }
        }
        return $ret;
    }

    public function count($whereClause = null)
    {
        $ret = self::$dbol->count($this, self::$dbObj[$this->className], $whereClause);
        return $ret;
    }

    public function listAll($whereClause = null, $limit = null, $offset = null)
    {
        return $this->readMulti($whereClause, self::$dbObj[$this->className]->tableProperties['listOrder'], $limit, $offset);
    }

    public function create()
    {
        $clientPKMap = func_get_args(0);
        $sessionContext = self::$dbol->getVariable();
        if (empty($sessionContext['user'])) {
            $this->diagLog(static::MMOBJ_ERROR, 999, array('#text' => 'Session context not set. Table: %table',
                                                       '%table' => self::$dbObj[$this->className]->table,));
        }

        // check pk map
        if ($clientPKMap) {
            $DbRepl = new MMDBReplication(self::$dbol);
            $masterPK = $DbRepl->getMasterPK($this->getDbObj()->table, $this->client_pk);
            if (!is_null($masterPK)) {
                $this->read($masterPK);
                return 0;
//                $table = $this->getDbObj()->table;
//                throw new \Exception("Replication error (create) - Table: $table - Attempt to duplicate client PK '$this->client_pk' on master. Already mapped to master PK '$masterPK'.");
            }
        }

        if ($this->validate() < static::MMOBJ_WARNING) {
            $table = $this->getDbObj()->table;
//            throw new \Exception("Validation error on create - Table: $table - PK: $this->primaryKeyString");
            throw new \Exception(var_export($this, true));
        }

        $res = self::$dbol->create($this, self::$dbObj[$this->className]);

        // pk mapping if required
        if ($res == 1 and $clientPKMap) {
            $DbRepl->setPKMap($this->getDbObj()->table, $this->primaryKeyString, $this->client_pk);
        }

        // create children objects
        if (!empty(self::$childObjs[$this->className])) {
            foreach (self::$childObjs[$this->className] as $objName => $obj) {
                if (!empty($obj['onCreateCallback'])) {
                    if ($obj['cardinality'] == 'one') {
                        $this->$objName = new $obj['className'];
                        $this->$obj['onCreateCallback']();
                        $this->$objName->create(); // todo: pk mapping
                    }
                }
            }
        }

        return $res;
    }

    public function createMulti($arr)
    {
//        return self::$dbol->create($arr, self::$dbObj[$this->className]);
        $res = 0;
        foreach ($arr as $c => $obj) {
                $res += $obj->create();
        }
        return $res;
    }

    public function update()
    {
        $sessionContext = self::$dbol->getVariable();
        if (empty($sessionContext['user'])) {
            $this->diagLog(static::MMOBJ_ERROR, 999, array('#text' => 'Session context not set. Table: %table',
                                                       '%table' => self::$dbObj[$this->className]->table,));
        }

//        try    {
        if ($this->validate() < static::MMOBJ_WARNING) {
            $table = $this->getDbObj()->table;
            throw new \Exception("Validation error on update - Table: $table - PK: $this->primaryKeyString");
        }
            return self::$dbol->update($this, self::$dbObj[$this->className]);
//        }
//        catch(Exception $e)    {
//            throw new \Exception($e->getMessage(), $e->getCode());
//        }
    }

    public function delete($clientPKMap = false)
    {
        $sessionContext = self::$dbol->getVariable();
        if (empty($sessionContext['user'])) {
            $this->diagLog(static::MMOBJ_ERROR, 999, array('#text' => 'Session context not set. Table: %table',
                                                       '%table' => self::$dbObj[$this->className]->table,));
        }
        // delete children objects
        if (!empty(self::$childObjs[$this->className])) {
            foreach (self::$childObjs[$this->className] as $objName => $obj) {
                if ($obj['onDeleteCascade']) {
                    if ($obj['cardinality'] == 'one') {
                        $this->$objName->delete();     // todo: pkmapping
                    }
                }
            }
        }
        $res = self::$dbol->delete($this, self::$dbObj[$this->className]);
        // pk mapping update if required
        if ($res == 1 and $clientPKMap) {
            $DbRepl = new MMDBReplication(self::$dbol);
            $DbRepl->setMasterPKDeleted($this->getDbObj()->table, $this->primaryKeyString);
        }
        return $res;
    }

    public function query($sqlq, $limit = null, $offset = null, $sqlId = null)
    {
        return self::$dbol->query($sqlq, $limit, $offset, $sqlId);
    }

    public function beginTransaction()
    {
        return self::$dbol->beginTransaction();
    }

    public function commit()
    {
        return self::$dbol->commit();
    }

    public function executeSql($sqlq)
    {
        return self::$dbol->executeSql($sqlq);
    }

    public function loadFromArray($arr, $clientPKReplace = false)
    {
        $cols = $this->getColumnProperties();
        foreach ($cols as $col => $props) {
            if (is_object($arr[$col]) and $arr[$col]->xmlrpc_type == 'datetime') {
                $this->$col = gmdate('Y-m-d', $arr[$col]->timestamp);
            } else {
                $this->$col = $arr[$col];
            }
        }

        $this->primaryKeyString = $this->compactPKIntoString();

        // pk replacement - NOTE: [master_pk] array element must be morphologically aligned with primary
        // key structure on server BEFORE it reaches here
        if ($clientPKReplace) {
            $this->client_pk = $this->primaryKeyString;
            $attrs = explode('|', $arr[master_pk]);
            foreach ($this->getDbObj()->PKColumns as $ctr => $col) {
                $this->$col = empty($attrs[$ctr]) ? null : $attrs[$ctr];
            }
            $this->primaryKeyString = $arr[master_pk];
        }

        return;
    }

    public function synch($src, $clientPKMap = false)
    {
        // check pk map for case of remapping of client to master
        if ($clientPKMap) {
            $DbRepl = new MMDBReplication(self::$dbol);
            $clientPK = $DbRepl->getClientPK($this->getDbObj()->table, $this->primaryKeyString);
            if (!is_null($clientPK) and ($clientPK <> $src->client_pk)) {
                $table = $this->getDbObj()->table;
                throw new \Exception("Replication error - Table: $table - Attempt to map client PK '$src->client_pk' to master PK '$this->primaryKeyString' failed. Already mapped to client PK '$clientPK'.");
            }
        }

        // synchs column values src-->this
        $cols = $this->getColumnProperties();
        foreach ($cols as $col => $props) {
            if ($props[editable] == true) {
                $this->$col = $src->$col;
            }
        }

        return;
    }

    protected function validate()
    {
        $colDets = $this->getColumnProperties();
        $highErr = static::MMOBJ_DEBUG;
        foreach ($colDets as $a => $b) {
            if (empty($this->$a) && $b['nullable']) {
                continue;
            }
            if ($b['editable']) {
                switch ($b['type']) {
                    case 'integer':
                    case 'decimal':
                    case 'float':
                        if (is_null($this->$a) or $this->$a == ' ') {
                            break;
                        } elseif (!is_numeric($this->$a)) {
                            $highErr = static::MMOBJ_ERROR;
                            $this->diagLog(static::MMOBJ_ERROR, 100, array( '#text' => 'The field %fieldName must be numeric.',
                                                    '%fieldName' => $a));
                            break;
                        }
                        break;
                    case 'boolean':
                        if (is_null($this->$a) || $this->$a == '') {
                            $this->$a = $b['default'] ? $b['default'] : 0;
                            $this->$a = empty($this->$a) ? 0 : $this->$a;
                        }
                        if (!($this->$a >= -1 and $this->$a <= 1)) {
                            $highErr = static::MMOBJ_ERROR;
                            $this->diagLog(static::MMOBJ_ERROR, 101, array( '#text' => 'The field %fieldName must be boolean. Val %val',
                                                    '%val' => $this->$a,
                                                    '%fieldName' => $a));
                        }
                        break;
                    case 'date':
                        // Trasforma in YYYY-MM-DD se il valore è in ISO8601.
                        $d = \DateTime::createFromFormat(\DateTime::ISO8601, $this->$a);
                        if ($d && $d->format(\DateTime::ISO8601) === $this->$a) {
                            $this->$a = $d->format('Y-m-d');
                        }

                        $comp = preg_split("/[\s\-:\.\/]+/", $this->$a);
                        if (count($comp) == 3) {
                            if (is_numeric($comp[0]) and is_numeric($comp[1]) and is_numeric($comp[2])) {
                                if (checkdate($comp[1], $comp[2], $comp[0])) {
                                    $this->$a = $comp[0] . '-' . str_pad($comp[1], 2, "0", STR_PAD_LEFT) . '-' . str_pad($comp[2], 2, "0", STR_PAD_LEFT);
                                    break;
                                }
                            }
                        }
                        $highErr = static::MMOBJ_ERROR;
                        $this->diagLog(static::MMOBJ_ERROR, 102, array( '#text' => 'The field %fieldName must be a date.',
                                                '%fieldName' => $a));
                        break;
                    case 'time':
                        if (!strtotime($this->$a)) {
                            $highErr = static::MMOBJ_ERROR;
                            $this->diagLog(static::MMOBJ_ERROR, 103, array( '#text' => 'The field %fieldName must be a time.',
                                                    '%fieldName' => $a));
                        }
                        break;
                    case 'datetime':
                        if (!strtotime($this->$a)) {
                            $highErr = static::MMOBJ_ERROR;
                            $this->diagLog(static::MMOBJ_ERROR, 104, array( '#text' => 'The field %fieldName must be a timestamp.',
                                                    '%fieldName' => $a));
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        return $highErr;
    }

    protected function diagLog($severity, $id, $params, $className = null, $throwExceptionOnError = true)
    {
        if (empty($className)) {
            $className = get_class($this);
        }
        $this->diag->sLog($severity, $className, $id, $params);
        if ($severity == static::MMOBJ_ERROR) {
            // prepares msg for exception
            $msg = $params['#text'];
            //foreach ($this->diag->get(FALSE) as $a)    {
            //    $msg .= $a->time . ' - ' . $a->severity . ' - ' . $a->className . ' - ' . $a->id . ' - ' . $a->fullText . " \n";
            //}
            // logs backtrace
            //if ($link->backtrace)    {
            //    foreach ($link->backtrace as $a => $b)    {
            //        $this->diag->sLog(static::MMOBJ_ERROR, 'backtrace', 0, array('#text' => $a . ' ' . $b['class'] . '/' . $b['function'] . ' in ' . $b['file'] . ' line ' . $b['line'],));
            //    }
            //}
            if ($throwExceptionOnError) {
                throw new \Exception($msg, $id);
            }
        }
    }

    public function startWatch($id)
    {
        return $this->diag->startWatch($id);
    }

    public function getLog()
    {
        return $this->diag->get();
    }

    public function __call($name, $args)
    {
        throw new \Exception('Undefined method ' . get_class($this) . '.' . $name . ' called');
    }
}
