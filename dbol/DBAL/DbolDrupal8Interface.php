<?php
/**
 * Dbol
 *
 * PHP version 5
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */

use Drupal\Core\Database\Database;

require_once "/home/mondrak1/private/dbol/DbolDBALInterface.php"; 
require_once "/home/mondrak1/private/dbol/DBAL/DbolPDOInterface.php"; 
 
/**
 * DbolDBALInterface - Drupal
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */
class DbolDrupal8Interface extends DbolPDOInterface {

    /**
     * Connects the database
     *
     * Connects via the database abstraction layer to the database specified by 
     * 'dsn', passing 'connectionParams'
     *
     * @param array $dsn              the DSN of the database to connect to
     * @param array $connectionParams the parameters to pass to the the connection
     *
     * @return object .
     */
    public function connect(array $dsn, $connectionParams = null)
    {
      // this is not required in Drupal as the db is already connected
    }

    /**
     * Mounts the database connection
     *
     * Mounts the connection to dbol, and instantiates the DBMS-specific interface
     *
     * @param object $dbh the connection object
     *
     * @return void
     */
    public function mount($dbh = null)
    {
      $options = Database::getConnection()->getConnectionOptions();
      $this->dbh = Database::getConnection()->open($options);
      $this->dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
    }
    
    /**
     * Gets the DBAL version string
     *
     * @return string DBAL version string
     */
    public function getVersion() {
      $info = system_get_info('module', 'system');
      return $info['version'];
    }

    /**
     * Gets the DBAL driver name
     *
     * @return string DBAL driver name
     */
    public function getDriver() {
      //$ret = $this->dbh->databaseType();
      $ret = 'mysqli'; // @todo
      return $ret;
    }
}
