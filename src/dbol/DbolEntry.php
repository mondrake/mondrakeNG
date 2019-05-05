<?php

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
class DbolEntry
{
    var $table;
    var $tableProperties = [];
    var $columns = [];
    var $columnTypes = [];
    var $PKColumns = [];
    var $AIColumns = [];
    var $columnProperties = [];
}
