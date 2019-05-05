<?php

namespace mondrakeNG\mm\core;

use mondrakeNG\dbol\DbolCallbackInterface;

use mondrakeNG\mm\classes\MMDbAuditLog;
use mondrakeNG\mm\classes\MMDbFieldAuditLog;
use mondrakeNG\mm\classes\MMDbQueryPerfLog;

class MMObjDbolCallback implements DbolCallbackInterface
{
    private $dbol = null;
    private $diag = null;

    public function __construct($dbol)
    {
        $this->dbol = $dbol;
        $this->diag = new MMDiag;
    }

    public function getDbObjectName($object)
    {
        return $object;
    }

    public function getDbEmulatedSequenceQualifiers($sequence)
    {
        return array($sequence . '_seq', 'sequence');
    }

    public function getDbResolvedStatement($sqlStatement)
    {
        return str_replace('#pfx#', DB_TABLEPREFIX, $sqlStatement);
    }

    public function getNextInsertSequence()
    {
        return $this->dbol->nextID('seq_update_id');
    }

    public function getNextUpdateSequence()
    {
        return $this->dbol->nextID('seq_update_id');
    }

    public function getNextDeleteSequence()
    {
        return $this->dbol->nextID('seq_update_id');
    }

    public function getDbImageInsertSequence($dbImage)
    {
    }

    public function getDbImageUpdateSequence($dbImage)
    {
        return $dbImage['update_id'];
    }

    public function getTimestamp()
    {
        return MMUtils::timestamp();
    }

    public function setAuditPreInsert(&$obj, $dbObj)
    {
        $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'create_ts', 'timestamp');
        $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'create_by', 'user');
        $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'update_ts', 'timestamp');
        $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'update_by', 'user');
        $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'update_id', 'insertSequence');
    }

    public function setAuditPreUpdate(&$obj, $dbObj, $primaryKeyChange)
    {
        $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'update_ts', 'timestamp');
        $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'update_by', 'user');
        if (!$primaryKeyChange) {
            $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'update_id', 'updateSequence');
        } else {
            $this->dbol->setObjAttrToDbolVariable($obj, $dbObj, 'update_id', 'insertSequence');
        }
    }

    // audit log inserts/updates/deletes at table/PK level
    public function logRowAudit($obj, $dbObj, $dbOp, $seq)
    {
        $sessionContext = $this->dbol->getVariable();
        $log = new MMDbAuditLog;
        $log->environment_id = $dbObj->tableProperties['environmentDependent'] ? $sessionContext['environment'] : null;
        $log->client_id = $dbObj->tableProperties['clientTracking'] ? $sessionContext['client'] : null;
        $log->db_audit_log_id = $seq;
        $log->db_table = $dbObj->table;
        $log->db_operation = $dbOp;
        $log->db_primary_key = $obj->primaryKeyString;
        $log->db_operation_by = $sessionContext['user'];
        $log->db_ts = $sessionContext['timestamp'];
        $log->create();
        return $obj->update_id;
    }

    // audit log field changes on update at table/PK/column level
    public function logFieldAudit($obj, $dbObj, $seq, $changes)
    {
        $sessionContext = $this->dbol->getVariable();
        if (!empty($changes)) {
            foreach ($changes as $chgItem) {
                $log = new MMDbFieldAuditLog;
                $log->environment_id = $dbObj->tableProperties['environmentDependent'] ? $sessionContext['environment'] : null;
                $log->client_id = $dbObj->tableProperties['clientTracking'] ? $sessionContext['client'] : null;
                $log->db_audit_log_id = $seq;
                $log->db_field_audit_log_id = null;
                $log->db_table = $dbObj->table;
                $log->db_primary_key = $obj->primaryKeyString;
                $log->db_column = $chgItem[0];
                $log->db_old_value = $chgItem[1];
                $log->db_new_value = $chgItem[2];
                $log->db_delta_algorithm = $chgItem[3];
                $log->db_operation_by = $sessionContext['user'];
                $log->db_ts = $sessionContext['timestamp'];
                $log->create();
            }
        }
    }

    public function diagnosticMessage($severity, $id, $text, $params, $qText, $className = null)
    {
        if (empty($className)) {
            $className = get_class($this);
        }
        $this->diag->sLog($severity, $className, $id, $params);
    }

    public function errorHandler($id, $text, $params, $qText, $className = null)
    {
/*          if ($link->backtrace)   {
                foreach ($link->backtrace as $a => $b)  {
                    self::$diag->sLog(4, 'backtrace', 0, array('#text' => $a . ' ' . $b['class'] . '/' . $b['function'] . ' in ' . $b['file'] . ' line ' . $b['line'],));
                }
            }*/
        $fullText = $params['#text'];
        foreach ($params as $a => $b) {
            if ($a <> '#text' and $a <> '#elapsed') {
                $fullText = str_replace($a, $b, $fullText);
            }
        }
        throw new \Exception($fullText, $id);
    }

    public function startPerfTiming()
    {
        return $this->diag->startWatch(1);
    }

    public function stopPerfTiming()
    {
        return $this->diag->stopWatch(1);
    }

    public function elapsedPerfTiming()
    {
        return ($this->diag->elapsed(1) * 1000);
    }

    public function logSQLPerformance($sqlId, $sqlq, $startTime, $stopTime, $elapsed, $cnt)
    {
        $log = new MMDbQueryPerfLog;
//      $log->db_query_perf_log_id = null;
        $log->sql_id = $sqlId;
        $log->sql_statement = $sqlq;
        $log->start_ts = $startTime;
        $log->end_ts = $stopTime;
        $log->duration = $elapsed;
        $log->cnt = $cnt;
        $log->create();
    }
}
