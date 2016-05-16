<?php

namespace mondrakeNG\dbol\DBMS;

use mondrakeNG\dbol\Dbol;

class Mysql {

  public function getAllTablesSQL() {
    return "SHOW TABLE STATUS";
  }

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
  public function mapTables($res, $prefix = NULL) {
    $tables = array();
    foreach ($res as $a => $b) {
      $comment = $b['Comment'];
      $offs = strpos($comment, 'InnoDB free');
      if ($offs !== FALSE)    {
        $comment = substr($comment, 0, $offs);
        $comment = substr($comment, 0, strrpos($comment, ';'));
      }
      $entry = array();
      $entry['description'] = $comment;
      $entry['rows'] = $b['Rows'];
      $entry['storageMethod'] = $b['Engine'];
      $entry['collation'] = $b['Collation'];
      $tables[$b['Name']] = $entry;
    }
    return $tables;
  }

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
  public function mapTableColumns($tableName, $tableColumns, $dbolE) {
    $ret = array();
    for ($i = 0; $i < count($tableColumns) ; $i++)    {
      $col = $tableColumns[$i];
      $x = $this->_mapNativeDatatype($col);
      $ent = array(
        'nullable' => ($col['Null'] == 'NO') ? false : true,
        'autoincrement' => (strpos($col['Extra'], 'auto_increment') !== FALSE) ? true : false,
        'primaryKey' => ($col['Key'] == 'PRI') ? true : false,
        'nativetype' => $col['Type'],
        'length' => $x[1],
        'fixed' => $x[3],
        'unsigned' => $x[2],
        'default' => $col['Default'],
        'type' => null,
        'name' => $col['Field'],
        'table' => $tableName,
        'dboltype' => $x[0][0],
        'comment' => $col['Comment'],
      );
      $ret[] = $ent;
/*echo('<br/>');
var_export($ent);
//var_export($obj);
/*echo('<br/>');
var_export($this->$a);
echo('<br/>');
var_export($b);*/
    }

    $j = 0;
    foreach ($ret as $a => $b) {
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
      }
      else {
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
      if ($dbolE->tableProperties['auditLogLevel'] > Dbol::DBOL_ROW_AUDIT) {
        $colDets['auditLog'] = true;
      }
      $dbolE->columnProperties[$b['name']] = $colDets;
      $j++;
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
  public function _mapNativeDatatype($field) {
    $db_type = strtolower($field['Type']);
    $db_type = strtok($db_type, '(), ');
    if ($db_type == 'national') {
      $db_type = strtok('(), ');
    }
    if (!empty($field['length'])) {
      $length = strtok($field['length'], ', ');
      $decimal = strtok(', ');
    }
    else {
      $length = strtok('(), ');
      $decimal = strtok('(), ');
    }
    $type = array();
    $unsigned = $fixed = null;
    switch ($db_type) {
      case 'tinyint':
        $type[] = 'integer';
        $type[] = 'boolean';
        if (preg_match('/^(is|has)/', $field['Field'])) {
          $type = array_reverse($type);
        }
        $unsigned = preg_match('/ unsigned/i', $field['Type']);
        $length = 1;
        break;
      case 'smallint':
        $type[] = 'integer';
        $unsigned = preg_match('/ unsigned/i', $field['Type']);
        $length = 2;
        break;
      case 'mediumint':
        $type[] = 'integer';
        $unsigned = preg_match('/ unsigned/i', $field['Type']);
        $length = 3;
        break;
      case 'int':
      case 'integer':
        $type[] = 'integer';
        $unsigned = preg_match('/ unsigned/i', $field['Type']);
        $length = 4;
        break;
      case 'bigint':
        $type[] = 'integer';
        $unsigned = preg_match('/ unsigned/i', $field['Type']);
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
          if (preg_match('/^(is|has)/', $field['Field'])) {
            $type = array_reverse($type);
          }
        }
        elseif (strstr($db_type, 'text')) {
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
        preg_match_all('/\'.+\'/U', $field['Type'], $matches);
        $length = 0;
        $fixed = false;
        if (is_array($matches)) {
          foreach ($matches[0] as $value) {
            $length = max($length, strlen($value)-2);
          }
          if ($length == '1' && count($matches[0]) == 2) {
            $type[] = 'boolean';
            if (preg_match('/^(is|has)/', $field['Field'])) {
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
        $unsigned = preg_match('/ unsigned/i', $field['Type']);
        if ($decimal !== false) {                // AMA 2011-05-09
          $length = $length.','.$decimal;        // AMA 2011-05-09
        }                                        // AMA 2011-05-09
        break;
      case 'unknown':
      case 'decimal':
      case 'numeric':
        $type[] = 'decimal';
        $unsigned = preg_match('/ unsigned/i', $field['Type']);
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
        throw new \Exception("Native datatype {$db_type} not supported", 106);
    }

    if ((int)$length <= 0) {
      $length = null;
    }

    return array($type, $length, $unsigned, $fixed);
  }

}
