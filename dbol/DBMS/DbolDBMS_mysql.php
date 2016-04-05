<?php 

class DbolDBMS_mysql {
    private $dbol = NULL;                        // calling dbol handle

    public function __construct($dbol)  {  
        $this->dbol = $dbol;
    } 
 
    public function getSyntax($id)    {
        static $syntax;
        if (!isset($syntax)) {
            $syntax = array(
                "nullString"                => 'NULL',
                "tableSelect"               => "SELECT {columns} FROM {table} {whereSection} {groupBySection} {orderBySection} {lockMode}",
                "tableInsert"               => "INSERT INTO {table} ({columns}) VALUES ({placeholders})",
                "tableUpdate"               => "UPDATE {table} ({columns}) VALUES ({placeholders}) {whereSection}",
                "tableDelete"               => "DELETE FROM {table} {whereSection}",
                "limitLocation"             => "last",
                "limit"                     => " LIMIT {offset}{limitRows}",
                "limitRows"                 => ", {rows}",
                "lockMode" . DBOL_NO_SELECT_LOCK            => null,
                "lockMode" . DBOL_SELECT_LOCK_SHARED        => "LOCK IN SHARE MODE",
                "lockMode" . DBOL_SELECT_LOCK_FOR_UPDATE    => "FOR UPDATE",
                "nativeSequences"           => false,
            );
        }
        return $syntax[$id];
    }
    
    public function getDriverConnectionDSN($DBAL, array $dsn)
    {
        switch ($DBAL) {
        case 'PDO':
        case 'Drupal':
            $connDSN = $dsn['driver'] . ':host=' . $dsn['hostspec'];
            if (isset($dsn['port'])) {
                $connDSN .= ';port=' . $dsn['port'];
            }
            $connDSN .= ';dbname=' . $dsn['database'];
            return $connDSN;
        default:
            // @todo error  
            return null;
        }
    }

    public function setCharset($charset)
    {
        $this->dbol->executeSQL("SET NAMES $charset");    
    }

    public function listTables($prefix = NULL)    {
        $res = $this->dbol->query('show table status');
        $tables = array();
        foreach ($res as $a => $b) {
            $comment = $b['comment']; 
            $offs = strpos($comment, 'InnoDB free');
            if ($offs !== FALSE)    { 
                $comment = substr($comment, 0, $offs);  
                $comment = substr($comment, 0, strrpos($comment, ';'));
            }
            $entry = array();
            $entry['description'] = $comment;
            $entry['rows'] = $b['rows'];
            $entry['storageMethod'] = $b['engine']; 
            $entry['collation'] = $b['collation'];
            $tables[$b['name']] = $entry;
        }
        return $tables;  
    }

    public function tableInfo($table)  {   
        $res = $this->dbol->query("show full columns from $table");
        $ret = array();
        for ($i = 0; $i < count($res) ; $i++)    {
            $col = $res[$i];
$x = $this->_mapNativeDatatype($col);


            $ent = array(
                'nullable' => ($col['null'] == 'NO') ? false : true,
                'autoincrement' => (strpos($col['extra'], 'auto_increment') !== FALSE) ? true : false,
                'primaryKey' => ($col['key'] == 'PRI') ? true : false,
                'nativetype' => $col['type'],
                'length' => $x[1],
                'fixed' => $x[3],
                'unsigned' => $x[2],
                'default' => $col['default'],
                'type' => null,
                'name' => $col['field'],
                'table' => $table,
                'dboltype' => $x[0][0],
                'comment' => $col['comment'], 
            );
            $ret[] = $ent;
        }
        return $ret;
    }

    /**
     * Maps a native array description of a field to a MDB2 datatype and length
     *
     * @param array  $field native field description
     * @return array containing the various possible types, length, sign, fixed
     * @access public
     */
    public function _mapNativeDatatype($field)
    {
        $db_type = strtolower($field['type']);
        $db_type = strtok($db_type, '(), ');
        if ($db_type == 'national') {
            $db_type = strtok('(), ');
        }
        if (!empty($field['length'])) {
            $length = strtok($field['length'], ', ');
            $decimal = strtok(', ');
        } else {
            $length = strtok('(), ');
            $decimal = strtok('(), ');
        }
        $type = array();
        $unsigned = $fixed = null;
        switch ($db_type) {
            case 'tinyint':
                $type[] = 'integer';
                $type[] = 'boolean';
                if (preg_match('/^(is|has)/', $field['field'])) {
                    $type = array_reverse($type);
                }
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 1;
                break;
            case 'smallint':
                $type[] = 'integer';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 2;
                break;
            case 'mediumint':
                $type[] = 'integer';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 3;
                break;
            case 'int':
            case 'integer':
                $type[] = 'integer';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 4;
                break;
            case 'bigint':
                $type[] = 'integer';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                $length = 8;
                break;
            case 'tinytext':
            case 'mediumtext':
            case 'longtext':
            case 'text':
            case 'varchar':
                $fixed = false;
            case 'string':
            case 'char':
                $type[] = 'text';
                if ($length == '1') {
                    $type[] = 'boolean';
                    if (preg_match('/^(is|has)/', $field['field'])) {
                        $type = array_reverse($type);
                    }
                } elseif (strstr($db_type, 'text')) {
                    $type[] = 'clob';
                    if ($decimal == 'binary') {
                        $type[] = 'blob';
                    }
                }
                if ($fixed !== false) {
                    $fixed = true;
                }
                break;
            case 'enum':
                $type[] = 'text';
                preg_match_all('/\'.+\'/U', $field['type'], $matches);
                $length = 0;
                $fixed = false;
                if (is_array($matches)) {
                    foreach ($matches[0] as $value) {
                        $length = max($length, strlen($value)-2);
                    }
                    if ($length == '1' && count($matches[0]) == 2) {
                        $type[] = 'boolean';
                        if (preg_match('/^(is|has)/', $field['field'])) {
                            $type = array_reverse($type);
                        }
                    }
                }
                $type[] = 'integer';
            case 'set':
                $fixed = false;
                $type[] = 'text';
                $type[] = 'integer';
                break;
            case 'date':
                $type[] = 'date';
                $length = null;
                break;
            case 'datetime':
            case 'timestamp':
                $type[] = 'timestamp';
                $length = null;
                break;
            case 'time':
                $type[] = 'time';
                $length = null;
                break;
            case 'float':
            case 'double':
            case 'real':
                $type[] = 'float';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                if ($decimal !== false) {                // AMA 2011-05-09
                    $length = $length.','.$decimal;        // AMA 2011-05-09
                }                                        // AMA 2011-05-09
                break;
            case 'unknown':
            case 'decimal':
            case 'numeric':
                $type[] = 'decimal';
                $unsigned = preg_match('/ unsigned/i', $field['type']);
                if ($decimal !== false) {
                    $length = $length.','.$decimal;
                }
                break;
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'blob':
                $type[] = 'blob';
                $length = null;
                break;
            case 'binary':
            case 'varbinary':
                $type[] = 'blob';
                break;
            case 'year':
                $type[] = 'integer';
                $type[] = 'date';
                $length = null;
                break;
            default:
                $this->dbol->diag(106, array('%type' => $db_type,));                    // datatype not supported
        }

        if ((int)$length <= 0) {
            $length = null;
        }

        return array($type, $length, $unsigned, $fixed);
    }
    
}
