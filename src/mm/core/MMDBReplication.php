<?php

namespace mondrakeNG\mm\core;

use mondrakeNG\rbppavl\RbppavlTraverser;
use mondrakeNG\rbppavl\RbppavlTree;
use mondrakeNG\mm\classes\MMClass;
use mondrakeNG\mm\classes\MMDbReplicaPKMap;
use mondrakeNG\mm\classes\MMDbReplicaInitChunk;

/**
 * MMDB Database Replication class
 *
 * @category   CategoryName
 * @package    PackageName
 * @author     mondrake <mondrake@mondrake.org>
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       http://pear.php.net/package/PackageName
 */
class MMDBReplication
{

    private $dbol = null;

    public function __construct($dbol)
    {
        if (!$dbol) {
            throw new \Exception('missing dbol in replication');
        }
        $this->dbol = $dbol;
    }

    /**
     * Sets a primary key map
     *
     * Creates a map of the primary key of a client-side database table to the
     * corresponding primary key on the server-side table.
     *
     * @param string $dbTable        the database table
     * @param string $masterPK       the server-side primary key
     * @param string $clientPK       the client-side primary key
     * @param bool   $clientAligned  if TRUE determines that the client-side table stores in a
     *                               'master_pk' field the value of the master primary key ($masterPK)
     * @param bool  $serverDeteleted if TRUE indicates that the record was deleted on the server-side
     *                               table. Once $masterPK record delete will have been replicated to client,
     *                               it will be possible to physically remove the map record.
     *
     * @return int 0 if OK, else MM error level
     */
    public function setPKMap($dbTable, $masterPK, $clientPK, $clientAligned = false, $serverDeleted = false)
    {
        $sessionContext = $this->dbol->getVariable();
        $masterPKMap = new MMDbReplicaPKMap;
        $masterPKMap->client_id = $sessionContext['client'];
        $masterPKMap->db_table = $dbTable;
        $masterPKMap->db_master_pk = $masterPK;
        $masterPKMapPK = $masterPKMap->compactPKIntoString();
        $ret = $masterPKMap->read($masterPKMapPK);
        if (is_null($ret)) {            // new map
            $masterPKMap->db_client_pk = $clientPK;
            $masterPKMap->is_client_aligned = $clientAligned ? 1 : 0;
            $masterPKMap->is_deleted_on_server = $serverDeleted ? 1 : 0;
            $masterPKMap->create();
        } else {                        // update map
            if (!empty($masterPKMap->db_client_pk) and ($clientPK <> $masterPKMap->db_client_pk)) {
                throw new \Exception("xReplication error - Table: $dbTable - Attempt to map client PK '$clientPK' to master PK '$masterPK' failed. Already mapped to client PK '$masterPKMap->db_client_pk'.");
            }
            $masterPKMap->db_client_pk = $clientPK;
            $masterPKMap->is_client_aligned = $clientAligned ? 1 : 0;
            $masterPKMap->is_deleted_on_server = $serverDeleted ? 1 : 0;
            $masterPKMap->update();
        }
        return 0;
    }

    /**
     * Marks a primary key map for deletion
     *
     * Called by MMObj routines after deletion of a server-side record requiring primary key mapping,
     * to mark that the map itself can be purged after the record deletion operation has been
     * successfully replicated to the client.
     *
     * @param string $dbTable  the database table
     * @param string $masterPK the server-side primary key
     *
     * @return int 0 if OK, else MM error level
     */
    public function setMasterPKDeleted($dbTable, $masterPK)
    {
        $masterPKMap = new MMDbReplicaPKMap;
        $ret = $masterPKMap->readMulti("db_table = '$dbTable' and db_master_pk = '$masterPK'");
        if (count($ret) > 0) {
            foreach ($ret as $map) {
                $map->is_deleted_on_server = 1;
                $map->update();
            }
        }
        return 0;
    }

    /**
     * Gets the client-side primary key for the given server-side primary key
     *
     * Determines the client from the current MMDB context.
     *
     * @param string $dbTable  the database table
     * @param string $masterPK the server-side primary key
     *
     * @return string the client-side primary key, or null if map not found
     */
    public function getClientPK($dbTable, $masterPK)
    {
        $sessionContext = $this->dbol->getVariable();
        $masterPKMap = new MMDbReplicaPKMap;
        $masterPKMap->client_id = $sessionContext['client'];
        $masterPKMap->db_table = $dbTable;
        $masterPKMap->db_master_pk = $masterPK;
        $masterPKMapPK = $masterPKMap->compactPKIntoString();
        $ret = $masterPKMap->read($masterPKMapPK);
        if (is_null($ret)) {            // no map
            return null;
        } else {                        // client map exists
            return $masterPKMap->db_client_pk;
        }
    }

    /**
     * Gets the master-side primary key for the given client-side primary key
     *
     * Determines the client from the current MMDB context.
     *
     * @param string $dbTable  the database table
     * @param string $clientPK the client-side primary key
     *
     * @return string the server-side primary key, or null if map not found
     */
    public function getMasterPK($dbTable, $clientPK)
    {
        $sessionContext = $this->dbol->getVariable();
        $clientId = $sessionContext['client'];
        $clientPKMap = new MMDbReplicaPKMap;
        $ret = $clientPKMap->readSingle("client_id = $clientId and db_table = '$dbTable' and db_client_pk = '$clientPK'");
        // check if exist
        if (is_null($ret)) {            // no map
            return null;
        } else {                        // client map exists
            return $clientPKMap->db_master_pk;
        }
    }

    /**
     * Gets a database replication chunk
     *
     * @param array $clientType  client type of the client requiring replication
     * @param int   $environment MM environment
     * @param array $chunk       the array to be filled with the replication ops
     * @param int   $lastId      the update_id to start from
     * @param bool  $complete    set to true upon return if no more replication log exists
     * @param int   $limit       the max number of replication commands to be returned
     *
     * @return int 0 if OK, else MM error level
     */
    public function getReplicationChunk($clientType, $environment, &$chunk, &$lastUpdateId, &$complete, $limit)
    {
        // sets highErr
        $highErr = 0;

        // current update id
        $currUpdateId = $this->dbol->currID('seq_update_id');

        // sql statement
        $params = [
            "#idFrom#" => $lastUpdateId,
            "#idTo#" => $currUpdateId,
            "#clientTypeId#" => $clientType,
            "#environmentId#" => $environment,
        ];
        $sqlq = MMUtils::retrieveSqlStatement("getReplicationChunk", $params);
        $qh = $this->dbol->getQueryHandle($sqlq);

        // allocates binary tree
        $tree = new RbppavlTree("\\mondrakeNG\\mm\\core\\MMDBReplicaSet", 3);

        // cycles replication log
        $ctr = 0;
        while ($r = $this->dbol->fetchRow($qh) and $ctr < $limit) {
            // @todo db error management
            //$this->dbol->throwExceptionOnError($r);

            // loads row array into object
            $re = new MMDBReplicationEntry;
            $re->replicationSeq = $r['replication_seq'];
            $re->table = $r['db_table'];
            $re->primaryKey = $r['db_primary_key'];
            $re->operation = $r['db_operation'];
            $re->updateId = $r['db_audit_log_id'];
            $re->environmentId = $r['environment_id'];
            $re->clientId = $r['client_id'];
            $re->isPkSyncReq = $r['is_pk_sync_req'];

            // the log entry was a dummy update for a change of primary key on the server, skip and continue
            if ($re->operation == 'u') {
                continue;
            }

            // loads object in binary tree
            $te = $tree->insert($re);
            // if duplicate found, decides replication op to take priority
            if ($te) {
                if ($re->operation == 'D' && $te->operation == 'I') {         // DELETE after INSERT in same log --> no replica op
                    if ($re->clientId == $te->clientId) {
                        $x = $tree->delete($te);
                        if ($x != null) {
                            unset($x);
                            $ctr--;
                        }
                    } else {
                        $te->operation = 'D';
                        $te->updateId = $re->updateId;
                        $te->clientId = $re->clientId;
                        $te->environmentId = $re->environmentId;
                    }
                } elseif ($re->operation == 'D' && $te->operation == 'U') {    // DELETE after UPDATE in same log --> DELETE prevails
                    $te->operation = 'D';
                    $te->updateId = $re->updateId;
                    $te->clientId = $re->clientId;
                    $te->environmentId = $re->environmentId;
                } elseif ($re->operation == 'I' && $te->operation == 'D') {    // INSERT after DELETE in same log --> INSERT prevails
                    $te->operation = 'I';
                    $te->updateId = $re->updateId;
                    $te->clientId = $re->clientId;
                    $te->environmentId = $re->environmentId;
                } elseif ($re->operation == 'U' && $te->operation == 'U') {    // UPDATE after UPDATE in same log --> update id
                    $te->updateId = $re->updateId;
                    $te->clientId = $re->clientId;
                    $te->environmentId = $re->environmentId;
                } elseif ($re->operation == 'U' && $te->operation == 'D') {    // UPDATE after DELETE in same log --> impossible
                    throw new \Exception("UPDATE after DELETE in same log --> impossible");
                } elseif ($re->operation == 'U' && $te->operation == 'I') {    // UPDATE after INSERT in same log --> INSERT prevails
                    $te->operation = 'I';
                    $te->updateId = $re->updateId;
                    $te->clientId = $re->clientId;
                    $te->environmentId = $re->environmentId;
                } elseif ($re->operation == 'I' && $te->operation == 'U') {    // INSERT after UPDATE in same log --> impossible
                    throw new \Exception("INSERT after UPDATE in same log --> impossible");
                }
            } else {
                $ctr++;
            }
        }
        if ($ctr == $limit) {
            $lastUpdateId = $re->updateId;
            $complete = false;
        } else {
            $lastUpdateId = $currUpdateId;
            $complete = true;
        }

        // debug validation
        if ($tree->getCount()) {
            //$stats = $tree->getVersion($setStatus = true);
            $failingNode = $tree->debugValidate($setStatusOnSuccess = false);
            //$stats = $tree->getStatistics($stat = null, $setStatus = true);
        } else {
            return $highErr;
        }

        // initialises chunk
        $chunk = [
            'r' => [],
            'd' => [],
        ];

        // first traversal: traverses tree left->right for I/U ops
        $trav = new RbppavlTraverser($tree);
        $re = $trav->first();
        $table = $re->table;
        while ($table != null) {
            $cl = new MMClass;
            $cl->getClassFromTableName($table);

            $replTable = [];
            $replRow= [];
            $replOp = [];
            $ctr = 0;
            while ($re != null) {
                if ($re->table != $table) {
                    break;
                }
                if ($re->operation == 'D') {
                    $re = $trav->next();
                    continue;
                }
                $src = new $cl->mm_class_name;
                $res = $src->read($re->primaryKey);
                if ($res) {
                    $replOp['op'] = $re->operation;
                    $replOp['masterPK'] = $re->primaryKey;
                    // if pk mapping required, checks and feeds if it exists otherwise prepares pk map
                    if ($re->isPkSyncReq) {
                        $clientPk = $this->getClientPK($src->getDbObj()->table, $re->primaryKey);
                        if (is_null($clientPk)) {    // creates pk map for client
                            $this->setPKMap($src->getDbObj()->table, $re->primaryKey, null, false);
                        }
                        $replOp['clientPK'] = $clientPk;
                    }
                    // loops the columns
                    $replOp['cols'] = [];
                    foreach ($src->getColumnProperties() as $c => $d) {
                        $replOp['cols'][$c] = $res->$c;
                    }
                    $replRow[$ctr] = $replOp;
                    $replTable['rows'] = $replRow;
                    $ctr++;
                }
                $re = $trav->next();
            }
            if ($ctr) {
                $chunk['r'][$table] = $replTable;
            }
            $table = isset($re->table) ? $re->table : null;
        }

        // second traversal: traverses tree right->left for D
        $re = $trav->last();
        $table = $re->table;
        while ($table != null) {
            $replTable= [];
            $replRow= [];
            $replOp = [];
            $ctr = 0;
            while ($re != null) {
                if ($re->table != $table) {
                    break;
                }
                if ($re->operation == 'I' || $re->operation == 'U') {
                    $re = $trav->prev();
                    continue;
                }
                $replOp['op'] = $re->operation;
                $replOp['masterPK'] = $re->primaryKey;
                $replRow[$ctr] = $replOp;
                $replTable['rows'] = $replRow;
                $re = $trav->prev();
                $ctr++;
            }
            if ($ctr) {
                $chunk['d'][$table] = $replTable;
            }
            $table = isset($re->table) ? $re->table : null;
        }

        return $highErr;
    }

    /**
     * Gets a chunk of data for full table init
     *
     * Prepares full serialised content for the table and stores in temp table on first call.
     *
     * @param array $dbTable     the db table to be initialized
     * @param array $client      the client requiring replication
     * @param int   $environment MM environment
     * @param array $chunk       the array to be filled with the replication ops
     * @param bool  $complete    set to true upon return if no more replication log exists
     * @param int   $limit       the max number of replication commands to be returned
     *
     * @return int 0 if OK, else MM error level
     */
    public function getTableInitChunk($dbTable, $client, $environment, &$chunk, &$nextSeq, &$complete, $limit)
    {
        // sets highErr
        $highErr = 0;

        $sqlq = "select * from #pfx#$dbTable";
        $qh = $this->dbol->getQueryHandle($sqlq);


        $cl = new MMClass;
        $cl->getClassFromTableName($dbTable);

        $complete = false;
        if ($nextSeq == 0) {                            // main entry point - first call
            // this is a first call, the table content are fetched and cached in a temp table (if # records
            // overcomes the $limit); else, no caching is required and the routine returns the current chunk for
            // initialization and $complete set to true
            $seq = 0;
            $inCycle = true;
            while ($inCycle) {
                // initialises chunk
                $chunk = [ 'r' => [] ];
                $replTable = [];
                $replRow= [];
                $ctr = 0;
                while ($r = $this->dbol->fetchRow($qh)) {
                    $src = new $cl->mm_class_name;
                    foreach ($r as $a => $b) {
                        $src->$a = $b;
                    }
                    $src->primaryKeyString = $this->dbol->compactPKIntoString($src, $src->getDbObj());
                    $replOp = [];
                    $replOp['op'] = 'I';
                    $replOp['masterPK'] = $src->primaryKeyString;
                    // loops the columns
                    $replOp['cols'] = [];
                    foreach ($src->getColumnProperties() as $c => $d) {
                        $replOp['cols'][$c] = $src->$c;
                    }
                    $replRow[$ctr] = $replOp;
                    $ctr++;
                    if ($ctr == $limit) {
                        break;
                    }
                }
                $replTable['rows'] = $replRow;
                $chunk['r'][$dbTable] = $replTable;

                if ($r or $seq > 0) {
                    $chunkCache = new MMDbReplicaInitChunk;
                    $chunkCache->client_id = $client;
                    $chunkCache->db_table = $dbTable;
                    $chunkCache->chunk_seq = $seq++;
                    $chunkCache->chunk = serialize($chunk);
                    $chunkCache->create();
                    $isCaching = true;
                }

                if (!$r) {
                    $inCycle = false;
                }
            }
            if ($isCaching) {
                $chunkCache = new MMDbReplicaInitChunk;
                $chunkCache->client_id = $client;
                $chunkCache->db_table = $dbTable;
                $chunkCache->chunk_seq = 0;
                $chunkCache->primaryKeyString = $chunkCache->compactPKIntoString();
                $chunkCache->read($chunkCache->primaryKeyString);
                $chunk = unserialize($chunkCache->chunk);
            } else {
                $complete = true;
            }
            return $highErr;
        } else {                            // secondary entry point - client requires a cached chunk
            // deletes previous chunk
            $chunkCache = new MMDbReplicaInitChunk;
            $chunkCache->client_id = $client;
            $chunkCache->db_table = $dbTable;
            $chunkCache->chunk_seq = $nextSeq - 1;
            $chunkCache->primaryKeyString = $chunkCache->compactPKIntoString();
            $chunkCache->delete();
            // fetches next chunk
            $chunkCache->chunk_seq = $nextSeq;
            $chunkCache->primaryKeyString = $chunkCache->compactPKIntoString();
            $res = $chunkCache->read($chunkCache->primaryKeyString);
            if ($res) {
                $chunk = unserialize($chunkCache->chunk);
            } else {
                $chunk = null;
                $complete = true;
            }
        }
        return $highErr;
    }
}
