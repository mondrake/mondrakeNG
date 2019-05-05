<?php

namespace mondrakeNG\mm\core;

/**
 * Database replication
 *
 * Database replication routines for MMDB.
 *
 * PHP version 5
 *
 * @category   CategoryName
 * @package    PackageName
 * @author     mondrake <mondrake@mondrake.org>
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       http://pear.php.net/package/PackageName
 */
class MMDBReplicaSet extends MMRbppavlInterface
{
    public function compare($a, $b)
    {
        if ($a->replicationSeq > $b->replicationSeq) {
            return 1;
        } elseif ($a->replicationSeq < $b->replicationSeq) {
            return -1;
        }

        if ($a->table > $b->table) {
            return 1;
        } elseif ($a->table < $b->table) {
            return -1;
        }

        if ($a->primaryKey > $b->primaryKey) {
            return 1;
        } elseif ($a->primaryKey < $b->primaryKey) {
            return -1;
        } else {
            return 0;
        }
    }

    public function dump($a)
    {
        return "$a->table#$a->primaryKey ($a->operation/$a->updateId)";
    }
}
