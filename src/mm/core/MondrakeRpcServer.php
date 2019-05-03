<?php

namespace mondrakeNG\mm\core;

use mondrakeNG\mm\core\MMObj;
use mondrakeNG\mm\core\MMDBReplication;
use mondrakeNG\mm\core\MMDiag;
use mondrakeNG\mm\core\MMTimer;
use mondrakeNG\mm\classes\MMClientCtl;
use mondrakeNG\mm\classes\MMClient;
use mondrakeNG\mm\classes\AMAccountClass;
use mondrakeNG\mm\classes\AXAccount;
use mondrakeNG\mm\classes\AXAccountDailyBalance;
use mondrakeNG\mm\classes\AXAccountPeriodBalance;
use mondrakeNG\mm\classes\MMCurrency;
use mondrakeNG\mm\classes\MMCountry;
use mondrakeNG\mm\classes\AXCurrency;
use mondrakeNG\mm\classes\AXCurrencyExchangeRate;
use mondrakeNG\mm\classes\AXDoc;
use mondrakeNG\mm\classes\AXDocItem;
use mondrakeNG\mm\classes\MMSqlStatement;
use mondrakeNG\mm\classes\MMUser;
use mondrakeNG\mm\classes\MMClass;
use mondrakeNG\mm\classes\MMEnvironment;
use mondrakeNG\mm\classes\MMUserLogin;
use mondrakeNG\mm\classes\MMDbReplicaTable;
use mondrakeNG\mm\classes\AXPortfolioDailyVal;
use PhpXmlRpc\Encoder;
use PhpXmlRpc\Response;

class MondrakeRpcServer {

  private static $gObj = NULL;

  /**
   * returns pong to a ping request
   *
   * @return string         pong
   */
  public static function setLink() {
    try {
      $srvRunTime = new MMTimer;
      $srvRunTime->start();
      return new Response(self::formatResponse('setLink', MMObj::MMOBJ_OK, '', '', $srvRunTime));
    }
    catch(\Exception $e){
      $trace = $e->getTrace();
      $backtrace = '';
      foreach ($trace as $n => $msg)  {
        $backtrace .= "\n{$msg['class']} {$msg['type']} {$msg['function']} {$msg['file']}:{$msg['line']}\n";
      }
      throw new \XML_RPC2_FaultException($e->getMessage() . ' ' . $backtrace, $e->getCode());
    }
  }

  /**
   * returns the AXDoc required
   *
   * @param integer doc_id
   * @return object the object
   */
  public static function getAXDoc($id) {
    self::sessionValidate();
      $doc = new AXDoc;
    $doc->getDoc($id);
    return $doc;
  }

  /**
   * returns an auth object
   *
   * @param array authParms
   * @return object the object
   */
  public static function authenticate($xmlrpcmsg) {
    $encoder = new Encoder();
    $n = $encoder->decode($xmlrpcmsg);    
    $authParms = $n[0];
    $srvRunTime = new MMTimer;
    $srvRunTime->start();
    $authParms['mmTokenSecsToExpiration'] = 8*24*3600;
    $mmToken = isset($authParms['mmToken']) ? $authParms['mmToken'] : null;
    $mmUserLogin = new MMUserLogin;
    $mmUserLogin->beginTransaction();
    $res = $mmUserLogin->userAuthenticate($authParms);
    if ($res == TRUE) {
      if ($mmToken) $mmToken = $authParms['mmToken'];
      $_SESSION['mmToken'] = $mmToken;
    }
    $mmUserLogin->commit();
    switch($authParms['authResult'])  {
      case 0:       // OK
        $stat = MMObj::MMOBJ_OK;
        break;
      case 10:      // Token Expired
      case 11:      // Token Invalid
        $stat = MMObj::MMOBJ_WARNING;
        break;
      default:      // Invalid
        $stat = MMObj::MMOBJ_ERROR;
    }
    return new Response(self::formatResponse('authenticate', $stat, isset($authParms['authMsg']) ? $authParms['authMsg'] : null, $authParms, $srvRunTime));
  }

    /**
     * client acknowledgement of last update processed
     *
     * @updateId int last update
     * @return array ---
     */
    public static function ackLastUpdate($xmlrpcmsg) {
      $encoder = new Encoder();
      $n = $encoder->decode($xmlrpcmsg);    
      $updateId = $n[0];
      $srvRunTime = new MMTimer;
      $srvRunTime->start();
      self::sessionValidate();
      try{
        // sets last confirmed update id
        self::$gObj->beginTransaction();
        $clientCtl = new MMClientCtl;
        $sessionContext = self::$gObj->getSessionContext();
        $clientCtl->read($sessionContext['client']);
        $clientCtl->last_update_id = $updateId;
        $clientCtl->update();
        self::$gObj->commit();
        return new Response(self::formatResponse('ackLastUpdate', MMObj::MMOBJ_OK, null, null, $srvRunTime));
      }
      catch(\Exception $e){
        throw new \XML_RPC2_FaultException($e->getMessage(), $e->getCode());
      }
    }

    /**
     * updates stats on the server
     *
     * @return array ---
     */
    public static function flush() {
      $srvRunTime = new MMTimer;
      $srvRunTime->start();
      self::sessionValidate();
      try{
        // updates balances
        $env = new MMEnvironment;
        $sessionContext = self::$gObj->getSessionContext();
        $env->read($sessionContext['environment']);
        $env->updateBalances();
        return new Response(self::formatResponse('flush', MMObj::MMOBJ_OK, null, null, $srvRunTime));
      }
      catch(\Exception $e){
        throw new \XML_RPC2_FaultException($e->getMessage(), $e->getCode());
      }
    }

    /**
     * downloads to client
     *
     * @return array            the object
     */
    public static function Ydownload() {
    $srvRunTime = new MMTimer;
    $srvRunTime->start();
    self::sessionValidate();
    try{
      $payloadResponse = array();
      $downloadResponse = array();

      // last update id confirmed by client
      $client = new MMClient;
      $sessionContext = self::$gObj->getSessionContext();
      $client->read($sessionContext['client']);
      $lastUpdateId = $client->clientCtl->last_update_id;

      // current update id
      $currUpdateId = self::$gObj->currID('seq_update_id');

      // tables subject to download
      $dbReplTab = new MMDbReplicaTable;
      $dbTables = $dbReplTab->readMulti("client_type_id = $client->client_type_id and is_downloadable <> 0 and download_method = 'download'", "replication_seq, db_table");

      // processes each table
      foreach ($dbTables as $ctr => $repTab)  {
        $cl = new MMClass;
        $cl->getClassFromTableName($repTab->db_table);
        $res = self::prepareDownload($cl, $sessionContext['environment'], $lastUpdateId, $repTab->is_pk_sync_req);
        if (!is_null($res)) $downloadResponse[$repTab->db_table] = $res;
      }

      $payloadResponse['download'] = $downloadResponse;
      $payloadResponse['lastUpdateId'] = $currUpdateId;
      return self::formatResponse('download', MMObj::MMOBJ_OK, null, $payloadResponse, $srvRunTime);
    }
    catch(\Exception $e){
      throw new \XML_RPC2_FaultException($e->getMessage(), $e->getCode());
    }
    }

    /**
     * downloads to client
     *
     * @return array            the object
     */
    public static function download($xmlrpcmsg) {
      $encoder = new Encoder();
      $n = $encoder->decode($xmlrpcmsg);    
      $environment = $n[0];
      $cliLastUpdateId = $n[1];
      $limit = $n[2];
      $srvRunTime = new MMTimer;
      $srvRunTime->start();
      $diag = new MMDiag;
      self::sessionValidate();

      // todo: environment validation

      try{
        // updates balances
        $env = new MMEnvironment;
        $sessionContext = self::$gObj->getSessionContext();
        $env->read($sessionContext['environment']);
        $env->updateBalances();

        $dbRepl = new MMDBReplication(self::$gObj->getdbol());
        $payloadResponse = array();
        $replChunk = array();
        $isComplete = false;

        // last update id confirmed by client
        $client = new MMClient;
        $client->read($sessionContext['client']);
        $client->clientCtl->last_update_id = $cliLastUpdateId;
        $client->clientCtl->update();
        $ret = $dbRepl->getReplicationChunk($client->client_type_id, $environment, $replChunk, $cliLastUpdateId, $isComplete, $limit);
        $payloadResponse['download'] = $replChunk;
        $payloadResponse['lastUpdateId'] = $cliLastUpdateId;
        $payloadResponse['isComplete'] = $isComplete;
        return new Response(self::formatResponse('download', MMObj::MMOBJ_OK, $diag->get(), $payloadResponse, $srvRunTime));
      }
      catch(\Exception $e){
        $trace = $e->getTrace();
        $backtrace = '';
        foreach ($trace as $n => $msg)  {
          $backtrace .= "\n{$msg['class']} {$msg['type']} {$msg['function']} {$msg['file']}:{$msg['line']}\n";
        }
        throw new \XML_RPC2_FaultException($e->getMessage() . ' ' . $backtrace, $e->getCode());
      }
    }

    /**
     * downloads to client
     *
     * @return array            the object
     */
    public static function initDownload($dbTable, $nextSeq, $limit) {
    $srvRunTime = new MMTimer;
    $srvRunTime->start();
    self::sessionValidate();

    // todo: environment validation
    $environment = 1;

    try{
      $dbRepl = new MMDBReplication(self::$gObj->getdbol());
      $payloadResponse = array();
      $replChunk = array();
      $isComplete = false;

      // last update id confirmed by client
      $client = new MMClient;
      $sessionContext = self::$gObj->getSessionContext();
      $client->read($sessionContext['client']);
      $client->clientCtl->last_update_id = $cliLastUpdateId;
      $client->clientCtl->update();
      $ret = $dbRepl->getTableInitChunk($dbTable, $client->client_id, $environment, $replChunk, $nextSeq, $isComplete, $limit);
      $payloadResponse['download'] = $replChunk;
      $payloadResponse['isComplete'] = $isComplete;
/*$sqlq = new MMSqlStatement;
$sqlq->read('xxxx');
$sqlq->sql_text = print_r($payloadResponse, true);
//$sqlq->sql_text = "$environment $cliLastUpdateId $limit";
$sqlq->update();
throw new \exception('test');*/
      return self::formatResponse('initDownload', MMObj::MMOBJ_OK, null, $payloadResponse, $srvRunTime);
    }
    catch(\Exception $e){
      throw new \XML_RPC2_FaultException($e->getMessage(), $e->getCode());
    }
    }

    /**
     * upload Docs
     *
     * @arr    array          doc_idccccccccccccccc
     * @return array          the object
     */
    public static function uploadDocs($xmlrpcmsg) {
      $encoder = new Encoder();
      $n = $encoder->decode($xmlrpcmsg);    
      $arr = $n[0];
error_log(var_export($arr, true));
      $srvRunTime = new MMTimer;
      $srvRunTime->start();
      $diag = new MMDiag;
      self::sessionValidate();

      try{
        // uploads docs
        $stat = MMObj::MMOBJ_DEBUG;
        $uploadResponse = array();
        foreach ($arr as $cmd)  {
          $validationReturn = self::validateDocArrayFromClient($cmd['doc']);
          $cmdResponse = array();
          $cmdResponse['syncCommand'] = $cmd['syncCommand'];
          $cmdResponse['syncResponse'] = MMObj::MMOBJ_ERROR;
          if ($validationReturn)  {
            $src = new AXDoc;
            self::$gObj->beginTransaction();
            switch ($cmd['syncCommand'])  {
              case 'delete':
                $src->read($cmd['doc']['master_pk']);
                if (!is_null($src->doc_id)) {
                  $cmdResponse['masterPK'] = $src->primaryKeyString;
                  $cmdResponse['clientPK'] = $cmd['doc']['client_pk'];
                  $res = $src->delete(true);
                  if ($res == 1) $cmdResponse['syncResponse'] = MMObj::MMOBJ_OK;
                }
                break;
              case 'replace':
                $src->loadFromArray($cmd['doc'], true);
                if(is_null($src->doc_id)) { // brand new insert
                  $res = $src->create(true);
                  $cmdResponse['masterPK'] = $src->primaryKeyString;
                  $cmdResponse['clientPK'] = $src->client_pk;
                  $cmdResponse['updateId'] = $src->update_id;
                  if ($res)
                    $cmdResponse['syncResponse'] = MMObj::MMOBJ_OK;
                  else
                    $cmdResponse['syncResponse'] = MMObj::MMOBJ_WARNING;
                }
                else  {           // update
                  $tgt = new AXDoc;
                  $res = $tgt->read($src->doc_id);
  /*$sqlq = new MMSqlStatement;
  $sqlq->read('xxxx');
  $sqlq->sql_text = print_r($src, true);
  //$sqlq->sql_text = "$environment $cliLastUpdateId $limit";
  $sqlq->update();
  //throw new \exception('test');*/
                  if(is_null($res)) throw new \Exception("Missing record for master_pk");
                  $res = $tgt->synch($src, true);
                  if ($res == 1) {
                    $cmdResponse['syncResponse'] = MMObj::MMOBJ_OK;
                    $cmdResponse['masterPK'] = $tgt->primaryKeyString;
                    $cmdResponse['clientPK'] = $src->client_pk;
                    $cmdResponse['updateId'] = $tgt->update_id;
                  }
                }
                break;
            }
            self::$gObj->commit();
          }
          else {
            $cmdResponse['masterPK'] = $cmd['doc']['master_pk'];
            $cmdResponse['clientPK'] = $cmd['doc']['doc_id'];
          }
          if ($cmdResponse['syncResponse'] <= MMObj::MMOBJ_WARNING) {
            $stat = MMObj::MMOBJ_WARNING;
          }
          $uploadResponse[] = $cmdResponse;
        }
        return new Response(self::formatResponse('uploadDocs', $stat, null, $uploadResponse, $srvRunTime));
      }
      catch(\Exception $e){
        $trace = $e->getTrace();
        $backtrace = '';
        foreach ($trace as $n => $msg)  {
          $backtrace .= "\n{$msg['class']} {$msg['type']} {$msg['function']} {$msg['file']}:{$msg['line']}\n";
        }
        throw new \XML_RPC2_FaultException($e->getMessage() . ' ' . $backtrace, $e->getCode());
  /*      throw new \XML_RPC2_FaultException($e->getMessage(), $e->getCode());
        $trace = $e->getTrace();
        foreach ($trace as $n => $msg)  {
          $diag->sLog(4, 'backtrace', $n, array('#text'=>"$msg['class']$msg['type']$msg['function'] $msg['file']:$msg['line']"));
        }
        return self::formatResponse('uploadDocs', MMObj::MMOBJ_ERROR, $diag->get(), null, $srvRunTime);*/
  //      throw new \XML_RPC2_FaultException($e->getMessage(), $e->getCode());
      }
    }

    /**
     * synchronizes primary keys
     *
     * @arr    array         doc_idccccccccccccccc
     * @return array         --
     */
    public static function pkSync($xmlrpcmsg) {
      $encoder = new Encoder();
      $n = $encoder->decode($xmlrpcmsg);    
      $arr = $n[0];
      $srvRunTime = new MMTimer;
      $srvRunTime->start();
      self::sessionValidate();
      try{
        // process pk sync
        self::$gObj->beginTransaction();
        $pkSyncResponse = array();
        foreach ($arr as $tab => $det)  {
          $cl = new MMClass;
          $cl->getClassFromTableName($tab);
          require_once $cl->mm_class_name . '.php';
          $tabResponse = array();
          foreach ($det as $cmd)  {
            // process input
            $DbRepl = new MMDBReplication(self::$gObj->getdbol());
            $DbRepl->setPKMap($tab, $cmd['master_pk'], $cmd['client_pk'], true);
            // output
            $syncResponse = array();
            $syncResponse['master_pk'] = $cmd['master_pk'];
            $syncResponse['client_pk'] = $cmd['client_pk'];
            $obj = new $cl->mm_class_name;
            $obj->read($cmd['master_pk']);
            $syncResponse['update_id'] = $obj->update_id;
            $tabResponse[] = $syncResponse;
          }
          $pkSyncResponse[$tab] = $tabResponse;
        }
        self::$gObj->commit();
        return new Response(self::formatResponse('pkSync', MMObj::MMOBJ_DEBUG, null, $pkSyncResponse, $srvRunTime));
      }
      catch(\Exception $e){
        throw new \XML_RPC2_FaultException($e->getMessage(), $e->getCode());
      }
    }

    /**
     * upload
     *
     * @param  object         doc_idccccccccccccccc
     * @return int            the object
     */
    public static function upload($xmlrpcmsg) {
      $encoder = new Encoder();
      $n = $encoder->decode($xmlrpcmsg);    
      $arr = $n[0];
      $srvRunTime = new MMTimer;
      $srvRunTime->start();
      self::sessionValidate();
      try{
        $uploadResponse = array();
        foreach($arr as $upTable) {
          $cl = new MMClass;
          $cl->getClassFromTableName($upTable['tableName']);
  //throw new \XML_RPC2_FaultException($cl->mm_class_name . '.php', 0);
    //      require_once $cl->mm_class_name . '.php';
          $tableRowUploadResponse= array();
          foreach ($upTable['rows'] as $ctr => $row)  {
            $tableRowCmd = $row['syncCommand'];
            $tableRowCols = $row['cols'];
            $cmdResponse = array();

            $src = new $cl->mm_class_name;
            $src->loadFromArray($tableRowCols);

            $tgt = new $cl->mm_class_name;
            $tgt->read($src->primaryKeyString);

            $cmdResponse['primaryKey'] = $src->primaryKeyString;
            $cmdResponse['syncCommand'] = $row['syncCommand'];
            $cmdResponse['syncResponse'] = 0;
            switch ($row['syncCommand'])  {
              case 'delete':
                if (!is_null($tgt->primaryKeyString)) {
                  $res = $tgt->delete();
                  if ($res == 1) $cmdResponse['syncResponse'] = 1;
                  // else?
                }
                break;
              case 'replace':
                if (is_null($tgt->primaryKeyString)) {
                  // new row to be created
                  $res = $src->create();
                  if ($res == 1) $cmdResponse['syncResponse'] = 1;
                  // else?
                  $cmdResponse['updateId'] = $src->update_id;
                }
                else  {
                  // existing row to be synched
                  $tgt->synch($src);
                  $res = $tgt->update();
                  if ($res == 1) $cmdResponse['syncResponse'] = 1;
                  // else?
                  $cmdResponse['updateId'] = $tgt->update_id;
                }
                break;
            }
            $tableRowUploadResponse[$ctr] = $cmdResponse;
          }
          $uploadResponse[$upTable['tableName']] = $tableRowUploadResponse;
        }

        //self::$gObj->commit();
        return new Response(self::formatResponse('upload', MMObj::MMOBJ_DEBUG, null, $uploadResponse, $srvRunTime));
      }
      catch(\Exception $e){
        throw new \XML_RPC2_FaultException($e->getMessage(), $e->getCode());
      }
    }


    // XXX
    private static function validateDocArrayFromClient($doc) {
    // manage transforms at doc level
    if ($doc['reco_group_id'] == 0)
      $doc['reco_group_id'] = null;
    if ($doc['reco_yr'] == 0)
      $doc['reco_yr'] = null;
    if ($doc['reco_nbr'] == 0)
      $doc['reco_nbr'] = null;
    if ($doc['is_reco_closed'])
      $doc['is_reco_closed'] = 1;
    else
      $doc['is_reco_closed'] = 0;
    if ($doc['portfolio_id'] == 0)
      $doc['portfolio_id'] = null;

    // transforms at docItem level
    foreach ($doc['docItems'] as $itm)  {
      if ($itm['is_doc_item_validated'])
        $itm['is_doc_item_validated'] = 1;
      else
        $itm['is_doc_item_validated'] = 0;
      if ($itm['reco_yr'] == 0)
        $itm['reco_yr'] = null;
      if ($itm['reco_nbr'] == 0)
        $itm['reco_nbr'] = null;
      if ($itm['reco_doc_id'] == 0)
        $itm['reco_doc_id'] = null;
      if ($itm['portfolio_id'] == 0)
        $itm['portfolio_id'] = null;
      if ($itm['portfolio_item_id'] == 0)
        $itm['portfolio_item_id'] = null;
      if (!empty($itm['p_doc_item_id']))  {
        if (!$itm['_map_p_doc_item_id'])  {
          $DbRepl = new MMDBReplication(self::$gObj->getdbol());
          $masterPk = $DbRepl->getMasterPK('ax_doc_items', $itm['p_doc_item_id']);  // TODO: obj related
          if(is_null($masterPk))  { // map for master not existing
            return FALSE;
          }
          else  {
            $itm['p_doc_item_id'] = $masterPk;
            $itm['_map_p_doc_item_id'] = TRUE;
          }
        }
      }
    }

    return TRUE;
    }


    // public methods call this private method to check session is authenticated
    private static function sessionValidate() {
    if (isset($_SESSION['mmToken']))  {
      if (self::$gObj === NULL) {
        self::$gObj = new MMUserLogin;
      }
//      $ulo = new MMUserLogin;
      $res = self::$gObj->readToken($_SESSION['mmToken']);
      if(is_null($res)) {
        throw new \Exception("Validation token invalid");
      }
      $user = new MMUSer;
      $user->read(self::$gObj->user_id);
      $user->setSessionContext(array(   'user' => self::$gObj->user_id,
                        'environment' => self::$gObj->environment_id,
                        'client' => self::$gObj->client_id,));
//      self::$gObj->setSessionContext(self::$gObj->user_id, self::$gObj->environment_id, self::$gObj->client_id);
    }
    else {
      throw new \Exception("Session non authenticated");
    }
    }

    // download a table's records
/*    private function prepareDownload($cl, $environmentId, $lastUpdateId, $isPKMappingReq) {
    require_once $cl->mm_class_name . '.php';
    $tableDownloadResponse= array();
    $tableRowDownloadResponse= array();

    $src = new $cl->mm_class_name;

    $filter = "";
    if ($cl->is_environment_dependent)  {
      $filter .= "environment_id = $environmentId and ";
    }
    $filter .= "update_id > $lastUpdateId";

    $res = $src->readMulti($filter);
    if (is_null($res)) return null;

    $cmdResponse = array();
    // loops the rows
    foreach ($res as $ctr => $row)  {
      // determines primary key
      $cmdResponse['masterPK'] = $row->primaryKeyString;
      // if pk mapping required, checks and feeds if it exists otherwise prepares pk map
      if ($isPKMappingReq)  {
        $DbRepl = new MMDBReplication(self::$gObj->dbol);
        $clientPk = $DbRepl->getClientPK($src->getDbObj()->dbTable, $row->primaryKeyString);
        if(is_null($clientPk))  { // creates pk map for client
          $DbRepl->setPKMap($src->getDbObj()->dbTable, $row->primaryKeyString, null, false);
        }
        $cmdResponse['clientPK'] = $clientPk;
      }
      $cmdResponse['syncRequest'] = 'replace';
      $cmdResponse['columns'] = array();
      // loops the columns
      foreach($src->getDbColumnProperties() as $c => $d)  {
        $cmdResponse['columns'][$c] = $row->$c;
      }
      $tableRowDownloadResponse[$ctr] = $cmdResponse;
    }
    $tableDownloadResponse['rows'] = $tableRowDownloadResponse;
    return $tableDownloadResponse;
    }
*/

    // format response
    private static function formatResponse($methodInvoked, $methoodResponseStatus, $methodResponseMsgs, $methodResponsePayload, $runTime, $repeatTot = null, $repeatCurr = null) {
    $resp = array();

    $r = array();
    $r['method'] = $methodInvoked;
    $r['status'] = $methoodResponseStatus;
    $r['messages'] = $methodResponseMsgs;
    if (!is_null($repeatTot)) {
      $r['segments'] = $repeatTot;
    }
    if (!is_null($repeatCurr))  {
      $r['currentSegment'] = $repeatCurr;
    }
    $r['payload'] = $methodResponsePayload;

    $runTime->stop();
    $r['runTime'] = $runTime->elapsed;

    $resp[] = $r;
/*$sqlq = new MMSqlStatement;
$sqlq->read('xxxx');
$sqlq->sql_text = print_r($resp, true);
//$sqlq->sql_text = $res;
$sqlq->update();
throw new \exception('test');*/

    //return $resp;  
    return (new Encoder())->encode($resp);
    }
}
