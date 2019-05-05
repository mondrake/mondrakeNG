<?php

namespace mondrakeNG\dbol;

/**
 * DbolCallbackInterface
 *
 * @category Database
 * @package  Dbol
 * @author   mondrake <mondrake@mondrake.org>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link     http://github.com/mondrake/Dbol
 */
interface DbolCallbackInterface
{
  /**
   * database objects name resolving methods
   */

  /**
   * .
   *
   * @param string $object .
   *
   * @return array .
   */
    public function getDbObjectName($object);

  /**
   * Return table and column names for emulated sequences
   *
   * @param string $sequence name of the sequence
   *
   * @return array [0] = table name; [1] = column name
   */
    public function getDbEmulatedSequenceQualifiers($sequence);

  /**
   * .
   *
   * @param string $sqlStatement .
   *
   * @return array .
   */
    public function getDbResolvedStatement($sqlStatement);

  /**
   * sequencing methods
   */

  /**
   * .
   *
   * @return null
   */
    public function getNextInsertSequence();

  /**
   * .
   *
   * @return null
   */
    public function getNextUpdateSequence();

  /**
   * .
   *
   * @return null
   */
    public function getNextDeleteSequence();

  /**
   * .
   *
   * @param array $dbImage .
   *
   * @return null
   */
    public function getDbImageInsertSequence($dbImage);

  /**
   * .
   *
   * @param array $dbImage .
   *
   * @return null
   */
    public function getDbImageUpdateSequence($dbImage);

  /**
   * db image manipulation methods
   */

  /**
   * auditing methods
   */

  /**
   * .
   *
   * @return null
   */
    public function getTimestamp();

  /**
   * .
   *
   * @param object &$obj  .
   * @param object $dbolE .
   *
   * @return null
   */
    public function setAuditPreInsert(&$obj, $dbolE);

  /**
   * .
   *
   * @param object  &$obj             .
   * @param object  $dbolE            .
   * @param boolean $primaryKeyChange .
   *
   * @return null
   */
    public function setAuditPreUpdate(&$obj, $dbolE, $primaryKeyChange);

  /**
   * .
   *
   * @param object $obj   .
   * @param object $dbolE .
   * @param char   $dbOp  .
   * @param int    $seq   .
   *
   * @return null
   */
    public function logRowAudit($obj, $dbolE, $dbOp, $seq);

  /**
   * .
   *
   * @param object $obj     .
   * @param object $dbolE   .
   * @param int    $seq     .
   * @param array  $changes .
   *
   * @return null
   */
    public function logFieldAudit($obj, $dbolE, $seq, $changes);

  /**
   * error management methods
   */

  /**
   * Handles a request to log a diagnostic message.
   *
   * @param int    $severity  {DBOL_DEBUG|DBOL_INFO|DBOL_NOTICE|DBOL_WARNING|DBOL_ERROR}
   * @param int    $id        id of the diagnostic message
   * @param string $text      unqualified text of the diagnostic message
   * @param array  $params    parameters to qualify the message
   * @param string $qText     fully qualified text of the diagnostic message
   * @param string $className name of the calling class
   *
   * @return null
   *
   * @api
   */
    public function diagnosticMessage($severity, $id, $text, $params, $qText, $className = null);

  /**
   * Handles an error condition.
   *
   * @param int    $id        id of the diagnostic message
   * @param string $text      unqualified text of the diagnostic message
   * @param array  $params    parameters to qualify the message
   * @param string $qText     fully qualified text of the diagnostic message
   * @param string $className name of the calling class
   *
   * @return null
   *
   * @api
   */
    public function errorHandler($id, $text, $params, $qText, $className = null);

  /**
   * performance tracking methods
   */

  /**
   * .
   *
   * @return null
   */
    public function startPerfTiming();

  /**
   * .
   *
   * @return null
   */
    public function stopPerfTiming();

  /**
   * .
   *
   * @return null
   */
    public function elapsedPerfTiming();

  /**
   * .
   *
   * @param string $sqlId     .
   * @param string $sqlq      .
   * @param string $startTime .
   * @param string $stopTime  .
   * @param float  $elapsed   .
   * @param int    $cnt       .
   *
   * @return null
   */
    public function logSQLPerformance($sqlId, $sqlq, $startTime, $stopTime, $elapsed, $cnt);
}
