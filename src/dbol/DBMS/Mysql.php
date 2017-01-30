<?php

namespace mondrakeNG\dbol\DBMS;

class Mysql {

  public function getListTablesSQL() {
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
  public function listTables($res, $prefix = NULL) {
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

}
