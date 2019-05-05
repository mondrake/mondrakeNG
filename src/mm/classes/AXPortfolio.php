<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;
use mondrakeNG\mm\core\MMTimer;
use mondrakeNG\mm\core\MMUtils;

class AXPortfolio extends MMObj
{

    public function defineChildObjs()
    {
        if (!isset(self::$childObjs[$this->className])) {
            self::$childObjs[$this->className] = [
                'portfolioCtl' => [
                    'className'         =>  MM_CLASS_PATH . 'AXPortfolioCtl',
                    'cardinality'       =>  'one',
                    'parameters'        =>  [ 'portfolio_id', ],
                    'loading'           =>  'onRead',
                    'onDeleteCascade'   =>  true,
                    'onCreateCallback'  =>  'portfolioCtlInit',
                ],
                'portfolioItems' => [
                    'className'         =>  MM_CLASS_PATH . 'AXPortfolioItem',
                    'cardinality'       =>  'zeroMany',
                    'whereClause'       =>  'portfolio_id = #0#',
                    'parameters'        =>  [ 'portfolio_id', ],
                    'loading'           =>  'onDemand',
                    'onDeleteCascade'   =>  true,
                ],
            ];
        }
    }

    public function portfolioCtlInit()
    {
        $this->portfolioCtl->environment_id = $this->environment_id;
        $this->portfolioCtl->portfolio_id = $this->portfolio_id;
        $this->portfolioCtl->day_watermark = null;
        $this->portfolioCtl->is_balance_calc_req = false;
    }

    //
    // -------- DAILY
    //
    public function dayValuation($periodTypeId, $docId)
    {
        $docType = new AMDocType;
        $docItemType = new AMDocItemType;

        $timer = new MMTimer;
        $this->msgs = [];

        $timer->start();

        $doc = new AXDoc;
        $doc->read($docId);

        $this->msgs[] = $timer->timenow() . " daily ## env:$this->environment_id, pof:$this->portfolio_id, pofd:$this->portfolio_desc, dt:$doc->doc_date ##";

        // main update cycle
        //todo if updmx is null
        $updCount = $insCount= 0;
        foreach ($doc->docItems as $a) {
            $dayBal = new AXPortfolioDailyVal;
//print_r($a);
            // fetches record
            $dayBal->environment_id = $doc->environment_id;
            $dayBal->period_type_id = $periodTypeId;
            $dayBal->period_date = $a->doc_item_date;
            if ($docItemType->isCredit($a->doc_item_type)) {
                $dayBal->entity_id = 'PFI';
                $dayBal->entity_key_01 = $a->portfolio_item_id;
                $dayBalPK = $dayBal->compactPKIntoString();
                $res = $dayBal->read($dayBalPK);
                $dayBal->day_value = ($a->doc_item_account_currency_amount == null) ? 0 : -$a->doc_item_account_currency_amount;
            } else {
                $dayBal->entity_id = 'PFT';
                $dayBal->entity_key_01 = $a->portfolio_id;
                $dayBalPK = $dayBal->compactPKIntoString();
                $res = $dayBal->read($dayBalPK);
                $dayBal->day_value = ($a->doc_item_account_currency_amount == null) ? 0 : $a->doc_item_account_currency_amount;
            }
//throw new \Exception(print_r($a, true));

            // initialises update matrix
            $params = [
                "#dtTo#" => $a->doc_item_date,
                "#item_id#" => $a->portfolio_item_id,
                "#portfolio_id#" => $a->portfolio_id
            ];
            if ($docItemType->isCredit($a->doc_item_type)) {
                $sqlId = "PFIDayVal";
            } else {
                $sqlId = "PFTDayVal";
            }
            $sqlq = MMUtils::retrieveSqlStatement($sqlId, $params);
            $updMx = MMObj::query($sqlq, null, null, $sqlId);

            $dPortaf = 0;
            $eVals = [];
            foreach ($updMx as $mov) {
                $dPortaf += $mov[val];
                $valEntry = [];
                $valEntry[date] = $mov[doc_item_date01];
                $valEntry[value] = $mov[val];
                $eVals[] = $valEntry;
            }
            $valEntry = [];
            $valEntry[date] = $a->doc_item_date;
            if ($docItemType->isCredit($a->doc_item_type)) {
                $valEntry[value] = -$a->doc_item_account_currency_amount;
            } else {
                $valEntry[value] = $a->doc_item_account_currency_amount;
            }
            $eVals[] = $valEntry;
//print_r($eVals);print"<br/>";
            $dayBal->loading_value = -$dPortaf;
            $dayBal->day_delta = ($dayBal->day_value / $dayBal->loading_value) - 1;
            $dayBal->day_xirr = self::xirrCalculate($eVals, $iterations);

            if (count($res) == 1) {       // update existing
                $updCount += $dayBal->update();
                $this->msgs[] = $timer->timenow() . " update $dayBal->entity_id $dayBal->entity_key_01 $dayBal->day_xirr iterations:$iterations";
            } else {
                $insCount += $dayBal->create();
                $this->msgs[] = $timer->timenow() . " insert $dayBal->entity_id $dayBal->entity_key_01 $dayBal->day_xirr iterations:$iterations";
            }

            unset($dayBal);
        }
        $timer->stop();
        $this->msgs[] = $timer->timenow() . " daily ## complete - elapsed: " . $timer->elapsed;
    }

    private function xirrCalculate($arr, $iterations)
    {
        $iterations = 0;

        if (count($arr) == 2 and $arr[0][date] == $arr[1][date]) {
            return 0;
        }

        $value1 = 0;
        for ($X1 = 1; $X1 <= 10000; $X1++) {
            $xrate = $X1;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
//print($iterations . " " . "X1" . " " . $X1 . " " . $sumA . " " . $xrate . "<br/>");
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA < $value1) {
                break;
            }
        }
        for ($X2 = $X1; $X2 >= -1; $X2 -= 0.1) {
            $xrate = $X2;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA > $value1) {
                break;
            }
        }
        for ($X3 = $X2; $X3 <= $X1; $X3 += 0.01) {
            $xrate = $X3;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA < $value1) {
                break;
            }
        }
        for ($X4 = $X3; $X4 >= $X2; $X4 -= 0.001) {
            $xrate = $X4;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA > $value1) {
                break;
            }
        }
        for ($X5 = $X4; $X5 <= $X3; $X5 += 0.0001) {
            $xrate = $X5;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA < $value1) {
                break;
            }
        }
        for ($X6 = $X5; $X6 >= $X4; $X6 -= 0.00001) {
            $xrate = $X6;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA > $value1) {
                break;
            }
        }
        for ($X7 = $X6; $X7 <= $X5; $X7 += 0.000001) {
            $xrate = $X7;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA < $value1) {
                break;
            }
        }
        for ($X8 = $X7; $X8 >= $X6; $X8 -= 0.0000001) {
            $xrate = $X8;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA > $value1) {
                break;
            }
        }
        for ($X9 = $X8; $X9 <= $X7; $X9 += 0.00000001) {
            $xrate = $X9;
            $sumA = self::xirrCycle($iterations, $arr, $xrate);
            if ($sumA == 0) {
                return $xrate;
            }
            if ($sumA < $value1) {
                break;
            }
        }
        return $xrate;
    }

    private function xirrCycle($iterations, $arr, $xrate)
    {
        $iterations++;
        $date1 = $arr[0][date];
        $sumA = $arr[0][value];
        for ($x = 1; $x <= count($arr) - 1; $x++) {
            if ($arr[$x][value] == 0) {
                break;
            }
            $date2 = $arr[$x][date];
            $yrs = (strtotime($date2) - strtotime($date1)) / 3600 / 24 / 365;
            $sumB = $arr[$x][value];
            $vpn = $sumB / (pow((1 + $xrate), $yrs));
            $sumA += $vpn;
        }
        return $sumA;
    }
}
