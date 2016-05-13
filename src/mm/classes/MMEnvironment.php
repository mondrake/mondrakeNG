<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;
use mondrakeNG\mm\core\MMUtils;

class MMEnvironment extends MMObj {

    public function defineChildObjs()    {
        if (!isset(self::$childObjs[$this->className])) {
            self::$childObjs[$this->className] = array(
                'currPeriod' => array (
                    'className'          =>    MM_CLASS_PATH . 'AMPeriod',
                    'cardinality'          =>    'one',
                    'whereClause'        =>  "period_type_id = #1# and first_period_date <= '#0#' and last_period_date >= '#0#'",
                    'parameters'        =>  array( 'day_watermark', 'period_type_id', ),
                    'loading'            =>    'onRead',
                ),
                'accounts' => array (
                    'className'          =>    MM_CLASS_PATH . 'AXAccount',
                    'cardinality'          =>    'zeroMany',
                    'whereClause'        =>  'environment_id = #0#',
                    'parameters'        =>  array( 'environment_id', ),
                    'loading'            =>    'onDemand',
                ),
            );
        }
    }  

    public function loadAccounts() {
        $this->loadChildObjs($this, 'accounts');
        // sort accounts
        usort($this->accounts, array($this, "cmpAcc"));
    } 

    public function loadAccountStats() {
        if (!empty($this->accounts))    {
            // retrieve account stats
            $accStat = new AXAccountPeriodBalance;
            foreach($this->accounts as $a => $acc)    {
                $cp = $this->currPeriod;
                $acc->accountStats = $accStat->readSingle("environment_id = $this->environment_id and balance_type = 'ACC' and account_id = $acc->account_id and period_type_id = $cp->period_type_id and period_year = $cp->period_year and period = $cp->period");
            } 
        }
        return $this;
    } 

    static function cmpAcc($a, $b) {
        if ($a->accountClass->seq == $b->accountClass->seq) {
            if ($a->account_short == $b->account_short) {
                return 0;
            }
            return ($a->account_short < $b->account_short) ? -1 : 1;
        }
        return ($a->accountClass->seq < $b->accountClass->seq) ? -1 : 1;
    }

    public function updateBalances($debugMode = false) {
        
        $this->startWatch(0);
        //$this->diagLog(MMOBJ_DEBUG, 99, array( '#text' => 'Update balances start.'));
        
        // define current date
        $newDate =     MMUtils::date();

        // finds period corresponding to $newDate, sets $hasPeriodChanged when there is a period change
        $hasPeriodChanged = false;
        $newPer = new AMPeriod;
        $newPer->getPeriodFromDate($this->period_type_id, $newDate);
        if ($this->currPeriod->period_seq != $newPer->period_seq)    {
            $hasPeriodChanged = true;
        }
        //$hasPeriodChanged = true;

        // loads accounts to be refreshed
        $this->startWatch(1);
        $params = array(
            "#newDate#" => $newDate,
            "#environmentId#" => $this->environment_id,
        );
        if ($hasPeriodChanged)    {
            $sqlId = "envAccountListForRefreshPerCh";
        }
        else    {
            $sqlId = "envAccountListForRefresh";
        }
        $sqlq = MMUtils::retrieveSqlStatement($sqlId, $params);
        $updMx = MMObj::query($sqlq, null, null, $sqlId);
        if (!empty($updMx))    {
            $this->accounts = array();
            foreach ($updMx as $row)    {
                $acc = new AXAccount;
                $acc->read($row['account_id']);
                $this->accounts[] = $acc;
            }
            $this->diagLog(MMOBJ_DEBUG, 99, array( '#text' => 'Accounts loaded.', 
                                            '#elapsed' => 1));
        }
        else    {
            $this->diagLog(MMOBJ_DEBUG, 99, array( '#text' => 'No accounts to process.', 
                                            '#elapsed' => 1));
            return null;
        }

        // loops through accounts to be refreshed
        if (!empty($this->accounts))    {
            foreach($this->accounts as $a => $acc)    {
                $this->beginTransaction();
                 
                // from the previous high limit
                $oldDate =     empty($acc->accountCtl->day_watermark) ? $acc->valid_from : $acc->accountCtl->day_watermark;

                // skips if new date before low limit
                if ($newDate < $acc->valid_from) continue; // todo: set old date
                
                $this->startWatch(2);
                $acc->updateBalance($this, $oldDate, $newDate, 2, $debugMode);
                //print("<table><tr><td>heartbeat $acc->account_id ($acc->account_short - $acc->account_desc) " . MMUtils::timestamp() . "</td></tr></table>");
                set_time_limit(30);        
                $this->diagLog(MMOBJ_DEBUG, 99, array( '#text' => 'Account %accountId (%accountShort - %accountDesc) processed.', 
                                    '%accountId' => $acc->account_id,
                                    '%accountShort' => $acc->account_short,
                                    '%accountDesc' => $acc->account_desc,
                                    '#elapsed' => 2));
                
                // sets new watermark date 
                $acc->accountCtl->day_watermark = $newDate;

                // sets date of new first future item, if existing
                if ($newDate >= $acc->accountCtl->day_first_future_item)    {
                    $docItem = new AXDocItem;
                    $acc->accountCtl->day_first_future_item = $docItem->getFirstFutureItemDate($acc->account_id, $newDate);                
                }    
                
                // sets balance recalc flag to false
                $acc->accountCtl->is_balance_calc_req = false;
                
                $acc->accountCtl->update();

                $this->commit();
            } 
            $this->day_watermark = $newDate;
            $this->update();
        }
        
        // final diag log                                
        $this->diagLog(MMOBJ_DEBUG, 99, array( '#text' => 'Update balances complete.', 
                                        '#elapsed' => 0));
    } 
}
