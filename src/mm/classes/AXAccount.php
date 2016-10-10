<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;
use mondrakeNG\mm\core\MMUtils;
use mondrakeNG\rbppavl\RbppavlTraverser;
use mondrakeNG\rbppavl\RbppavlTree;

class AXAccount extends MMObj
{
    public $env = null;
    public $pts = null;

    public function defineChildObjs()    {
        if (!isset(self::$childObjs[$this->className])) {
            self::$childObjs[$this->className] = array(
                'accountCtl' => array (
                    'className'         =>  MM_CLASS_PATH . 'AXAccountCtl',
                    'cardinality'       =>  'one',
                    'parameters'        =>  array( 'account_id', ),
                    'loading'           =>  'onRead',
                    'onDeleteCascade'   =>  true,
                    'onCreateCallback'  =>  'accountCtlInit',
                ),
                'accountClass' => array (
                    'className'         =>  MM_CLASS_PATH . 'AMAccountClass',
                    'cardinality'       =>  'one',
                    'whereClause'       =>  'account_class_id = #0#',
                    'parameters'        =>  array( 'account_class_id', ),
                    'loading'           =>  'onRead',
                ),
            );
        }
    }

    public function accountCtlInit(){
        $this->accountCtl->environment_id = $this->environment_id;
        $this->accountCtl->account_id = $this->account_id;
        $this->accountCtl->is_balance_calc_req = FALSE;
    }

    private function _getPeriodTypes() {
        $ret = array();
        $pt = new \stdClass;
        $pt->periodTypeId = $this->env->period_type_id;
        $pt->futurePeriods = 2;
        $ret[] = $pt;
        if ($this->account_id == 4 or $this->account_id == 7) {
            $pt = new \stdClass;
            $pt->periodTypeId = 3;
            $pt->futurePeriods = 8;
            $ret[] = $pt;
        }
        return $ret;
    }

    private function _resolveStartEndDates($dtFrom, $dtTo, $pt = null) {
        // the full range of dates for the day summary table starts from the first day of the period
        // where dtFrom is in, to the last day the period resulting by offsetting the period dtTo is
        // in to the number of futurePeriods

        if (!$pt) {
            $pts = $this->pts;
        } else {
            $pts = array($pt);
        }

        $dtStart = null;
        $dtEnd = null;
        foreach ($pts as $pt) {
            // start date
            $dateStart = new AMPeriodDate($pt->periodTypeId, $dtFrom);
            $r = $dateStart->read();
            if (!$r) {
                $this->diagLog(MMObj::MMOBJ_ERROR, 99, array( '#text' => 'Date %fromDate not found in calendar.',
                                '%fromDate' => $dtFrom,));
                return null;
            }
            if (!$dtStart or $dtStart > $dateStart->datePeriod->first_period_date) {
                $dtStart = $dateStart->datePeriod->first_period_date;
            }

            // end date
            $dateEnd = new AMPeriodDate($pt->periodTypeId, $dtTo);
            $r = $dateEnd->read();
            if (!$r) {
                $this->diagLog(MMObj::MMOBJ_ERROR, 99, array( '#text' => 'Date %toDate not found in calendar.',
                                '%toDate' => $dtTo,));
                return null;
            }
            $dtTemp = $dateEnd->datePeriod->getOffsetPeriod($pt->futurePeriods)->last_period_date;
            if (!$dtEnd or $dtEnd < $dtTemp) {
                $dtEnd = $dtTemp;
            }
        }
        //$this->diagLog(MMObj::MMOBJ_DEBUG, 99, array( '#text' => "$dtStart - $dtEnd",));
        return array($dtStart, $dtEnd);
    }

    private function _getDaySummaryTable($dtFrom, &$dtTo, &$isComplete, $debugMode = false) {
        // gets widest date range
        list($dtStart, $dtEnd) = $this->_resolveStartEndDates($dtFrom, $dtTo);

        $docType = new AMDocType;
        $docItemType = new AMDocItemType;

        // gets query handle to the day summary table
        $params = array(
            "#dtFrom#" => $dtStart,
            "#dtTo#" => $dtEnd,
            "#account_id#" => $this->account_id,
        );
        $sqlId = "accountItems";
        $sqlStmt = MMUtils::retrieveSqlStatement($sqlId, $params);
        $qh = self::$dbol->getQueryHandle($sqlStmt, $sqlId);

        // instantiate day summary table
        $tree = new RbppavlTree(MM_CLASS_PATH . "AXDaySummaryTable", 3, false, "5M");

        // by item, allocates amounts to day summary table
        while ($a = self::$dbol->fetchRow($qh)) {
            $item = new AXDaySummaryEntry;
            $item->date = $a['doc_item_date'];
            $tItem = $tree->insert($item);
            if (!$tItem) {
                // not enough memory?
                if ($tree->getStatusCode() == 103) {
                    $isComplete = false;                                    // notify update not complete
                    $dtTo = MMUtils::dateOffset($a['doc_item_date'], -1);   // $dtTo set to last safe date
                    return $tree;                                           // returns tree for partial processing
                }
                // else is OK, and $item is a new insert
                $tItem = $item;
            }
            // balance opening document
            if ($docType->isBalanceOpening($a['doc_type_id'])) {
                $tItem->actualOp += $a['doc_item_account_currency_amount'];
                continue;
            }
            // budget document
            if ($docType->isBudget($a['doc_type_id'])) {
                if ($docItemType->isCredit($a['doc_item_type'])) {
                    $tItem->budgetCt += $a['doc_item_account_currency_amount'];
                } else {
                    $tItem->budgetDt += $a['doc_item_account_currency_amount'];
                }
                $tItem->budgetBal = $tItem->budgetDt + $tItem->budgetCt;
                continue;
            }
            // actual document
            if (!$a['is_doc_item_validated']) {
                if ($docItemType->isCredit($a['doc_item_type'])) {
                    $tItem->unvalCt += $a['doc_item_account_currency_amount'];
                } else {
                    $tItem->unvalDt += $a['doc_item_account_currency_amount'];
                }
            } else {
                if ($docItemType->isCredit($a['doc_item_type'])) {
                    $tItem->actualCt += $a['doc_item_account_currency_amount'];
                } else {
                    $tItem->actualDt += $a['doc_item_account_currency_amount'];
                }
            }
            $tItem->actualBal = $tItem->actualDt + $tItem->actualCt;
            $tItem->unvalBal = $tItem->unvalDt + $tItem->unvalCt;
        }

        if ($tree->getCount() and $debugMode) {
            //$tree->getStatistics(null, true);
            $this->_dumpDaySummaryTable($tree);
        }
        return $tree;
    }

    private function _dumpDaySummaryTable($tree)
    {
        echo '<table class="signup" border="1" cellpadding="2" cellspacing="0" bgcolor="#eeeeee">';
        $trav = new RbppavlTraverser($tree);
        $el = $trav->first();
        while ($el) {
            if(!$thSet) {
                echo "<tr>";
                foreach ($el as $a => $msg)    {
                    echo "<td>$a</td>";
                }
                echo "</tr>";
                $thSet = true;
            }
            echo "<tr>";
            foreach ($el as $msg)    {
                $x = str_replace(".", ",", $msg);
                echo "<td align=right>$x</td>";
            }
            echo "</tr>";
            $el = $trav->next();
        }
        echo '</table>';
    }

    /*
     * -------- NEW
     */
    public function updateBalance($environment, $dtFrom, $dtNeedle, $futurePeriods, $debugMode = false)
    {
        // starts time watching of the balance update
        $this->startWatch(1);
        // checks input parameters
        if (is_null($dtFrom) or $dtFrom < $this->valid_from) {
            $dtFrom = $this->valid_from;
        }
        if ($this->valid_to and $dtNeedle > $this->valid_to) {
            $dtNeedle = $this->valid_to;
        }
        if (!$dtNeedle) {
            $this->diagLog(MMObj::MMOBJ_ERROR, 99, array( '#text' => 'Needle date is null.',));
            return null;
        }
        // initialise class variables
        $this->env = $environment;
        $this->pts = $this->_getPeriodTypes();
        // initialise overall account update flag
        $isAccBalUpdated = false;
        // gets day summary table
        $isComplete = true;
        $tree = $this->_getDaySummaryTable($dtFrom, $dtNeedle, $isComplete, $debugMode);
        // @todo if (!$isComplete) {}
        foreach ($this->pts as $pt) {
            // gets current period type date range
            list($dtStart, $dtEnd) = $this->_resolveStartEndDates($dtFrom, $dtNeedle, $pt);

            // gets date list query handle
            $params = array(
                "#dtFrom#" => $dtStart,
                "#dtTo#" => $dtEnd,
                "#periodTypeId#" => $pt->periodTypeId,
            );
            $sqlId = "listDates";
            $sqlStmt = MMUtils::retrieveSqlStatement($sqlId, $params);
            $qh = self::$dbol->getQueryHandle($sqlStmt, $sqlId);

            // initialise period iteration
            $a = self::$dbol->fetchRow($qh);
            $dtI = $a['period_date'];
            $pxP = new AXPeriodSummaryEntry;
            $pxP->periodTypeId   = $a['period_type_id'];
            $pxP->periodYear     = $a['period_year'];
            $pxP->period         = $a['period'];
            $pxP->periodSequence = $a['period_seq'];
            // initialize running totals
            // gets previous period for Financial accounts to retrieve running totals
            if ($this->accountClass->account_class_type == 'F') {
                $dateStart = new AMPeriodDate($pt->periodTypeId, $dtFrom);
                $r = $dateStart->read();
                if (!$r) {
                    $this->diagLog(MMObj::MMOBJ_ERROR, 99, array( '#text' => 'Date %fromDate not found in AMPeriodDates.',
                                    '%fromDate' => $dtFrom,));
                    return null;
                }
                $prevPer = $dateStart->datePeriod->getOffsetPeriod(-1);
                // fetches data from prev period if existing
                if ($prevPer) {
                    $prevBal = new AXAccountPeriodBalance;
                    $r = $prevBal->read(
                            $this->environment_id, 'ACC', $this->account_id, $pt->periodTypeId,
                            $prevPer->period_year, $prevPer->period
                        );
                    if ($r) {
                        $pxP->actualRunBal = $prevBal->period_closing_balance;
                        $pxP->unvalRunBal = $prevBal->period_uv_closing_balance;
                    }
                }
            }
            $pxI = clone $pxP;

            // period iteration
            while ($a) {
                $px = new AXPeriodSummaryEntry;
                $px->periodTypeId   = $a['period_type_id'];
                $px->periodYear     = $a['period_year'];
                $px->period         = $a['period'];
                $px->periodSequence = $a['period_seq'];
                $px->actualRunBal   = $pxP->actualRunBal;
                $px->unvalRunBal    = $pxP->unvalRunBal;
                // day iteration
                while ($px->periodSequence == $pxI->periodSequence) {
                    $d = new AXDaySummaryEntry;
                    $d->date = $dtI;
                    $tItem = null;
                    // gets day data from say summary table if existing
                    if ($tree->getCount()) {
                        $tItem = $tree->find($d);
                        if ($tItem) {
                            if ($dtI <= $dtNeedle) {
                                $px->actualDt     += $tItem->actualDt;
                                $px->actualCt     += $tItem->actualCt;
                                $px->actualBal    += $tItem->actualBal;
                                $pxP->actualBal   += $tItem->actualOp;
                                $px->actualRunBal += $tItem->actualBal + $tItem->actualOp;
                                if ($this->is_validation_req) {
                                    $px->unvalDt      += $tItem->unvalDt;
                                    $px->unvalCt      += $tItem->unvalCt;
                                    $px->unvalBal     += $tItem->unvalBal;
                                    $px->unvalRunBal  += $tItem->unvalBal;
                                } else {
                                    $px->actualDt     += $tItem->unvalDt;
                                    $px->actualCt     += $tItem->unvalCt;
                                    $px->actualBal    += $tItem->unvalBal;
                                    $px->actualRunBal += $tItem->unvalBal;
                                }
                            } else { // future
                                $px->futureDt     += $tItem->actualDt + $tItem->unvalDt;
                                $px->futureCt     += $tItem->actualCt + $tItem->unvalCt;
                                $px->futureBal    += $tItem->actualBal + $tItem->unvalBal;
                            }
                        }
                    }
                    // else day has no amounts
                    if (!$tItem) {
                        $tItem = $d;
                    }
                    // calculates period aggregates
                    if ($this->accountClass->is_daily_balance_req and $dtI <= $dtNeedle) {
                        if (is_null($px->actualMaxBal) or $px->actualRunBal > $px->actualMaxBal) {
                            $px->actualMaxBal = $px->actualRunBal;
                        }
                        if (is_null($px->actualMinBal) or $px->actualRunBal < $px->actualMinBal) {
                            $px->actualMinBal = $px->actualRunBal;
                        }
                        $px->actualDays++;
                        $px->actualSumBal += $px->actualRunBal;
                        // flushes day to db
                        if ($dtI >= $dtFrom) {
                            $fd = $this->_flushDaySummary($tItem, $px);
                            if ($fd) {
                                $isAccBalUpdated = true;
                            }
                        }
                    }
                    // move to next day
                    $a = self::$dbol->fetchRow($qh);
                    if (!$a) {
                        break;
                    }
                    $dtI = $a['period_date'];
                    $pxI->periodTypeId = $a['period_type_id'];
                    $pxI->periodYear = $a['period_year'];
                    $pxI->period = $a['period'];
                    $pxI->periodSequence = $a['period_seq'];
                }

                // flushes period to db
                $fd = $this->_flushPeriodSummary($px, $pxP);
                if ($fd) {
                    $isAccBalUpdated = true;
                }

                // moves to next period
                if ($this->accountClass->account_class_type == 'F')    {
                    if ($dtI <= $dtNeedle) {
                        $pxI->actualRunBal = $px->actualRunBal;
                        $pxI->unvalRunBal  = $px->unvalRunBal;
                    } else {
                        $pxI->actualRunBal = 0;
                        $pxI->unvalRunBal  = 0;
                    }
                }
                $pxP = clone $pxI;
            }
        }

        if ($isAccBalUpdated) {
            $this->diagLog(MMObj::MMOBJ_NOTICE, 99, array( '#text' => 'Account balance updated - %accountShort (%accountDesc)',
                            '%accountShort' => $this->account_short,
                            '%accountDesc' => $this->account_desc,
                            '#elapsed' => 1));
         }
        return null;
    }

    private function _flushDaySummary($dx, $px) {
        $db = new AXAccountDailyBalance;
        $r = $db->read($this->environment_id, 'ACC', $this->account_id, $px->periodTypeId, $dx->date);
        // apply day progressives
        if ($this->is_validation_req) {
            $db->day_dt_amount = $dx->actualDt;
            $db->day_ct_amount = $dx->actualCt;
            $db->day_balance   = $dx->actualBal;
        } else {
            $db->day_dt_amount = $dx->actualDt  + $dx->unvalDt;
            $db->day_ct_amount = $dx->actualCt  + $dx->unvalCt;
            $db->day_balance   = $dx->actualBal + $dx->unvalBal;
        }
        $db->period_dt_amount    = $px->actualDt;
        $db->period_ct_amount    = $px->actualCt;
        $db->period_balance      = $px->actualBal;
        $db->running_balance     = $px->actualRunBal;
        $db->period_uv_dt_amount = $px->unvalDt;
        $db->period_uv_ct_amount = $px->unvalCt;
        $db->period_uv_balance   = $px->unvalBal;
        $db->running_uv_balance  = $px->unvalRunBal;

/*if ($debugMode) {
echo <<<_END

<table class="signup" border="1" cellpadding="2"
    cellspacing="0" bgcolor="#eeeeee">

_END;
}*/

/*if ($debugMode) {
    if(!$thSet) {
        $colDets = $db->getColumnProperties();
        echo "<tr>";
        foreach ($colDets as $b => $msg)    {
            echo "<td>$b</td>";
        }
        echo "</tr>";
        $thSet = true;
    }
    echo "<tr>";
    foreach ($colDets as $b => $msg)    {
        $x = str_replace(".", ",", $db->$b);
        echo "<td align=right>$x</td>";
    }
    echo "</tr>";
}*/
        // flushes to db
        if ($r) {
            $s = $db->update();
            if ($s) {
                $this->diagLog(MMObj::MMOBJ_DEBUG, 99, array( '#text' => 'Daily Upd %accountShort - %accountDesc; %periodTypeId|%accountId|%day.',
                                    '%accountShort' => $this->account_short,
                                    '%accountDesc' => $this->account_desc,
                                    '%periodTypeId' => $px->periodTypeId,
                                    '%accountId' => $this->account_id,
                                    '%day' => $dx->date,));
                return true;
            }
        } else {
            $s = $db->create();
            if ($s) {
                $this->diagLog(MMObj::MMOBJ_DEBUG, 99, array( '#text' => 'Daily Ins %accountShort - %accountDesc; %periodTypeId|%accountId|%day.',
                                    '%accountShort' => $this->account_short,
                                    '%accountDesc' => $this->account_desc,
                                    '%periodTypeId' => $px->periodTypeId,
                                    '%accountId' => $this->account_id,
                                    '%day' => $dx->date,));
                return true;
            }
        }
        return false;
    }

    private function _flushPeriodSummary($px, $pxP) {
        // period break in day iteration, prepare for period balance update
        $pb = new AXAccountPeriodBalance;
        $r = $pb->read(
                $this->environment_id, 'ACC', $this->account_id, $px->periodTypeId,
                $px->periodYear, $px->period
            );
        // apply period progressives
        if ($this->accountClass->account_class_type == 'F')    {
            $pb->period_opening_balance = $pxP->actualRunBal;
            $pb->period_closing_balance = $px->actualRunBal;
            $pb->period_uv_opening_balance = $pxP->unvalRunBal;
            $pb->period_uv_closing_balance = $px->unvalRunBal;
        }
        else    {
            $pb->period_opening_balance = 0;
            $pb->period_closing_balance = 0;
            $pb->period_uv_opening_balance = 0;
            $pb->period_uv_closing_balance = 0;
        }
        $pb->period_dt_amount = $px->actualDt;
        $pb->period_ct_amount = $px->actualCt;
        $pb->period_balance = $px->actualBal;
        $pb->period_max_balance = $px->actualMaxBal ? $px->actualMaxBal : 0;
        $pb->period_min_balance = $px->actualMinBal ? $px->actualMinBal : 0;
        $pb->period_avg_balance = ($px->actualDays > 0) ? $px->actualSumBal / $px->actualDays : 0;
        $pb->period_uv_dt_amount = $px->unvalDt;
        $pb->period_uv_ct_amount = $px->unvalCt;
        $pb->period_uv_balance = $px->unvalBal;
        $pb->period_future_dt_amount = $px->futureDt;
        $pb->period_future_ct_amount = $px->futureCt;
        $pb->period_future_balance = $px->futureBal;
        // flushes to db
        if ($r) {
            $s = $pb->update();
            if ($s) {
                $this->diagLog(MMObj::MMOBJ_DEBUG, 99, array( '#text' => 'Period Upd %accountShort - %accountDesc; %periodTypeId|%accountId|%year|%period.',
                                    '%accountShort' => $this->account_short,
                                    '%accountDesc' => $this->account_desc,
                                    '%periodTypeId' => $px->periodTypeId,
                                    '%accountId' => $this->account_id,
                                    '%year' => $px->periodYear,
                                    '%period' => $px->period,));
                return true;
            }
        } else {
            $s = $pb->create();
            if ($s) {
                $this->diagLog(MMObj::MMOBJ_DEBUG, 99, array( '#text' => 'Period Ins %accountShort - %accountDesc; %periodTypeId|%accountId|%year|%period.',
                                    '%accountShort' => $this->account_short,
                                    '%accountDesc' => $this->account_desc,
                                    '%periodTypeId' => $px->periodTypeId,
                                    '%accountId' => $this->account_id,
                                    '%year' => $px->periodYear,
                                    '%period' => $px->period,));
                return true;
            }
        }
    }

}