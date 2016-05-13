<?php
/**
 * DbolEntry
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
 * DbolEntry
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */
class DbolEntry {
  var $table;
  var $tableProperties = array();
  var $columns = array();
  var $columnTypes = array();
  var $PKColumns = array();
  var $AIColumns = array();
  var $columnProperties = array();
}
