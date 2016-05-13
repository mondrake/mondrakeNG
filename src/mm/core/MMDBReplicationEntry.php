<?php

namespace mondrakeNG\mm\core;

/**
 * MMDB Replication Entry class
 *
 * @category   CategoryName
 * @package    PackageName
 * @author     mondrake <mondrake@mondrake.org>
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       http://pear.php.net/package/PackageName
 */
class MMDBReplicationEntry {
    public $replicationSeq;
    public $table;
    public $primaryKey;
    public $operation;
    public $updateId;
    public $environmentId;
    public $clientId;
    public $isPkSyncReq;
}
