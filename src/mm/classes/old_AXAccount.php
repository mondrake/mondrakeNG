<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;
use mondrakeNG\mm\core\MMUtils;

class AXAccount extends MMObj {
 
	public function __construct() {	
		parent::__construct();
		if (!isset(self::$childObjs[$this->className])) {
			self::$childObjs[$this->className] = array(
				'accountCtl' => array (
					'className'  		=>	MM_CLASS_PATH . 'AXAccountCtl',
					'cardinality'  		=>	'one',
					'parameters'		=>  array( 'account_id', ),
					'loading'			=>	'onRead',
					'onDeleteCascade'	=>  true,
					'onCreateCallback'	=>  'accountCtlInit',
				),
				'accountClass' => array (
					'className'  		=>	MM_CLASS_PATH . 'AMAccountClass',
					'cardinality'  		=>	'one',
					'whereClause'		=>  'account_class_id = #0#',
					'parameters'		=>  array( 'account_class_id', ),
					'loading'			=>	'onRead',
				),
			);
		}
	}

	public function accountCtlInit(){
		$this->accountCtl->environment_id = $this->environment_id;
		$this->accountCtl->account_id = $this->account_id;
		$this->accountCtl->is_balance_calc_req = FALSE;
	}

	//
	// -------- DAILY  
	//
	public function updateDailyBalance($periodTypeId, $dtFrom, $dtTo) {

		if (is_null($dtFrom) or $dtFrom < $this->valid_from) 
			$dtFrom = $this->valid_from;

		$this->startWatch(1);
		$this->diagLog(0, 99, array( '#text' => 'daily ## acc: %accountId, accs: %accountShort, accd: %accountDesc, dtfrom: %dtFrom, dtto: %dtTo ##', 
										'%accountId' => $this->account_id, 
										'%accountShort' => $this->account_short,
										'%accountDesc' => $this->account_desc,
										'%dtFrom' => $dtFrom,
										'%dtTo' => $dtTo));
										
		// finds period corresponding to dtFrom
		$perFrom = new AMPeriod;
		$perFrom->getPeriodFromDate($periodTypeId, $dtFrom);

		// finds date and period of day-1 
		$dtFromPrev = date('Y-m-d', (strtotime($dtFrom)-3600*24));
		$perFromPrev = new AMPeriod;
		$perFromPrev->getPeriodFromDate($periodTypeId, $dtFromPrev);

		// fetches data from day-1 before dtFrom if existing
		$prevBal = new AXAccountDailyBalance;
		$prevBal->environment_id = $this->environment_id;
		$prevBal->balance_type = 'ACC';
		$prevBal->account_id = $this->account_id;
		$prevBal->period_type_id = $periodTypeId;
		$prevBal->period_date = $dtFromPrev;
		$prevBalPK = $prevBal->compactPKIntoString();
		$res = $prevBal->read($prevBalPK);

		// initialises running totals variables
		$dRunBal = 0; $dPerDt =  0;	$dPerCt =  0; $dPerBal = 0;
		$dUVRunBal = 0; $dUVPerDt =  0;	$dUVPerCt =  0; $dUVPerBal = 0;
		if ( count($res) == 1 ) {
			$dRunBal = $prevBal->running_balance;
			$dUVRunBal = $prevBal->running_uv_balance;
			if ($perFrom->period_seq == $perFromPrev->period_seq)	{
				$dPerDt =  $prevBal->period_dt_amount;
				$dPerCt =  $prevBal->period_ct_amount;
				$dPerBal = $prevBal->period_balance;
				$dUVPerDt =  $prevBal->period_uv_dt_amount;
				$dUVPerCt =  $prevBal->period_uv_ct_amount;
				$dUVPerBal = $prevBal->period_uv_balance;
			}
		}

//		$this->diagLog(0, 99, array( '#text' => 'initialise complete'));
		
		// initialises update matrix
		$this->startWatch(2);
		$params = array(
			"#dtFrom#" => $dtFrom,
			"#dtTo#" => $dtTo,
			"#account_id#" => $this->account_id,
			"#period_type_id#" => $periodTypeId,
		);
		if ($this->is_validation_req) {
			$sqlId = "DailyAccBalanceUpdMxVal";
		}
		else {
			$sqlId = "DailyAccBalanceUpdMx";
		} 
		$sqlq = MMUtils::retrieveSqlStatement($sqlId, $params);
		$updMx = MMObj::query($sqlq, NULL, NULL, $sqlId);
//		$this->diagLog(0, 99, array( '#text' => 'fetch update matrix complete (%updMx).', 
//										'#elapsed' => 2,
//										'%updMx' => count($updMx))); 
//print_r($prevBal);print"<br/>";print_r($updMx);
		// main update cycle
		//todo if updmx is null
//		$this->startWatch(2);
		$periodP = $updMx[0][period_seq]; $updCount = 0;
		$insertArray = array();	
		foreach($updMx as $a) {

			$dayBal = new AXAccountDailyBalance;

			// fetches record
			$dayBal->environment_id = $this->environment_id;
			$dayBal->balance_type = 'ACC';
			$dayBal->account_id = $this->account_id;
			$dayBal->period_type_id = $periodTypeId;
			$dayBal->period_date = $a[period_date];
			$dayBalPK = $dayBal->compactPKIntoString();
			$res = $dayBal->read($dayBalPK);
			
			// period check
			$periodC = $a[period_seq];
			if ( $periodC <> $periodP ) {
				$dPerDt =  0; $dPerCt =  0; $dPerBal = 0;
				$dUVPerDt =  0;	$dUVPerCt =  0; $dUVPerBal = 0;
				$periodP = $periodC;
			}
  
			// apply day progressives
			$dayBal->day_dt_amount = ($a[day_dt_sum] == NULL) ? 0 : $a[day_dt_sum];
			$dayBal->day_ct_amount = ($a[day_ct_sum] == NULL) ? 0 : $a[day_ct_sum];
			$dayBal->day_balance = $dayBal->day_dt_amount + $dayBal->day_ct_amount;
			$dPerDt = $dayBal->period_dt_amount = $dPerDt + $dayBal->day_dt_amount;
			$dPerCt = $dayBal->period_ct_amount = $dPerCt + $dayBal->day_ct_amount;
			$dPerBal = $dayBal->period_balance  = $dPerBal + $dayBal->day_balance;
			$dRunBal = $dayBal->running_balance = $dRunBal + $dayBal->day_balance + (($a[day_op_sum] == NULL) ? 0 : $a[day_op_sum]);

			// apply unvalidated day progressives, if accounts requires item validation
			if ($this->is_validation_req) {
				$dUVPerDt += ($a[uv_day_dt_sum] == NULL) ? 0 : $a[uv_day_dt_sum];
				$dUVPerCt += ($a[uv_day_ct_sum] == NULL) ? 0 : $a[uv_day_ct_sum];
				$dUVPerBal = $dUVPerDt + $dUVPerCt;
				$dUVRunBal += (($a[uv_day_dt_sum] == NULL) ? 0 : $a[uv_day_dt_sum]) + (($a[uv_day_ct_sum] == NULL) ? 0 : $a[uv_day_ct_sum]);
				$dayBal->period_uv_dt_amount = $dUVPerDt;
				$dayBal->period_uv_ct_amount = $dUVPerCt;
				$dayBal->period_uv_balance  = $dUVPerBal;
				$dayBal->running_uv_balance = $dUVRunBal;
			}
			else	{
				$dUVPerDt = $dayBal->period_uv_dt_amount = 0;
				$dUVPerCt = $dayBal->period_uv_ct_amount = 0;
				$dUVPerBal = $dayBal->period_uv_balance  = 0;
				$dUVRunBal = $dayBal->running_uv_balance = 0;
			}
			
			if (count($res) == 1)	{		// update existing
				$updCount += $dayBal->update();
			}
			else	{
				$insertArray[] = clone $dayBal;
			}
			
			unset($dayBal);
		}
//		$this->diagLog(0, 99, array( '#text' => 'update existing done (%updCount).', 
//										'#elapsed' => 2,
//										'%updCount' => $updCount)); 


		// inserts
//		$this->startWatch(2);
		$insCount = 0;
		$ins = new AXAccountDailyBalance;
        $insCount = $ins->createMulti($insertArray);
		$this->diagLog(MMOBJ_DEBUG, 99, array( '#text' => 'fetches: %updMx, updates: %updCount, inserts: %insCount', 
										'#elapsed' => 2,
										'%updMx' => count($updMx),
										'%insCount' => $insCount,
										'%updCount' => $updCount)); 

		// final diag log								
		if($insCount or $updCount)
			$this->diagLog(MMOBJ_INFO, 99, array( '#text' => 'Daily balance for account: %accountShort - %accountDesc updated from %dtFrom to %dtTo.', 
										'%accountShort' => $this->account_short,
										'%accountDesc' => $this->account_desc,
										'%dtFrom' => $dtFrom,
										'%dtTo' => $dtTo,
										'#elapsed' => 1));
	}

	//
	// -------- PERIOD
	//
	public function updatePeriodBalance($periodTypeId, $dtFrom, $dtTo) {

		if (is_null($dtFrom) or $dtFrom < $this->valid_from) 
			$dtFrom = $this->valid_from;

		$this->startWatch(1);
		$this->diagLog(0, 99, array( '#text' => 'period ## acc: %accountId, accs: %accountShort, accd: %accountDesc, dtfrom: %dtFrom, dtto: %dtTo ##', 
										'%accountId' => $this->account_id, 
										'%accountShort' => $this->account_short,
										'%accountDesc' => $this->account_desc,
										'%dtFrom' => $dtFrom,
										'%dtTo' => $dtTo));

		// gets period corresponding to dtFrom
		$perFrom = new AMPeriod;
		$res = $perFrom->getPeriodFromDate($periodTypeId, $dtFrom);

		// gets period corresponding to dtTo
		$perTo = new AMPeriod;
		$perTo->getPeriodFromDate($periodTypeId, $dtTo);

		// gets previous period 
		if ($res)	{
			$perFromPrev = new AMPeriod;
			$resp = $perFromPrev->getPeriodFromSeq($periodTypeId, $perFrom->prev_period_seq);
		}

		// fetches data from prev period if existing
		if ($resp)	{
			$prevBal = new AXAccountPeriodBalance;
			$prevBal->environment_id = $this->environment_id;
			$prevBal->balance_type = 'ACC';
			$prevBal->account_id = $this->account_id;
			$prevBal->period_type_id = $periodTypeId;
			$prevBal->period_year = $perFromPrev->period_year;
			$prevBal->period = $perFromPrev->period;
			$prevBalPK = $prevBal->compactPKIntoString();
			$resq = $prevBal->read($prevBalPK);
		}

		// initialises running totals variables
		if ( count($resq) == 1 ) {
			$dRunBal = $prevBal->period_closing_balance;
			$dUVRunBal = ($prevBal->period_uv_closing_balance == NULL) ? 0 : $prevBal->period_uv_closing_balance;
		}
		else	{
			$dRunBal = 0;
			$dUVRunBal = 0;
		}

//		$this->diagLog(0, 99, array( '#text' => 'initialise complete'));
		
		// initialises update matrix
		$this->startWatch(2);
		$params = array(
					"#perFrom#" => $perFrom->period_seq,
					"#perTo#" => $perTo->period_seq,
					"#dtFrom#" => $perFrom->first_period_date,
					"#dtTo#" => $dtTo,
					"#account_id#" => $this->account_id,
					"#period_type_id#" => $periodTypeId
		);
		if ($this->is_validation_req) {
			$sqlId = "PeriodAccBalanceUpdMxVal";
		}
		else	{
			$sqlId = "PeriodAccBalanceUpdMx";
		}
		$sqlq = MMUtils::retrieveSqlStatement($sqlId, $params);
		$updMx = MMObj::query($sqlq, null, null, $sqlId);
//		$this->diagLog(0, 99, array( '#text' => 'fetch update matrix complete (%updMx).', 
//										'#elapsed' => 2,
//										'%updMx' => count($updMx))); 

		// main update cycle
		//todo if updmx is null
//		$this->startWatch(2);
		$updCount = 0;
		$insertArray = array();	
		foreach($updMx as $a) {

			$per = new AMPeriod;
			$perBal = new AXAccountPeriodBalance;

			// gets period 
			$per->period_type_id = $periodTypeId;
			$per->period_year = $a[period_year];
			$per->period = $a[period];
			$per->read($per->compactPKIntoString());

			// fetches record
			$perBal->environment_id = $this->environment_id;
			$perBal->balance_type = 'ACC';
			$perBal->account_id = $this->account_id;
			$perBal->period_type_id = $periodTypeId;
			$perBal->period_year = $a[period_year];
			$perBal->period = $a[period];
			$perBalPK = $perBal->compactPKIntoString();
			$res = $perBal->read($perBalPK);
			
			// apply period progressives
			if ($this->accountClass->account_class_type == 'F')	{
				$perBal->period_opening_balance = $dRunBal = $dRunBal + (($a[per_op_sum] == NULL) ? 0 : $a[per_op_sum]);
			}
			else	{
				$perBal->period_opening_balance = 0;
			}
			$perBal->period_dt_amount = ($a[per_dt_sum] == NULL) ? 0 : $a[per_dt_sum];
			$perBal->period_ct_amount = ($a[per_ct_sum] == NULL) ? 0 : $a[per_ct_sum];
			$perBal->period_balance = $perBal->period_dt_amount + $perBal->period_ct_amount;
			if ($this->accountClass->account_class_type == 'F')	{
				$perBal->period_closing_balance = $dRunBal = $dRunBal + $perBal->period_balance;
			}
			else	{
				$perBal->period_closing_balance = 0;
			}

			// apply unvalidated period progressives, if accounts requires item validation
			if ($this->is_validation_req) {
				if ($this->accountClass->account_class_type == 'F')	{
					$perBal->period_uv_opening_balance = $dUVRunBal;
				}
				else	{
					$perBal->period_uv_opening_balance = 0;
				}
				$perBal->period_uv_dt_amount = ($a[per_uv_dt_sum] == NULL) ? 0 : $a[per_uv_dt_sum];
				$perBal->period_uv_ct_amount = ($a[per_uv_ct_sum] == NULL) ? 0 : $a[per_uv_ct_sum];
				$perBal->period_uv_balance = $perBal->period_uv_dt_amount + $perBal->period_uv_ct_amount;
				if ($this->accountClass->account_class_type == 'F')	{
					$perBal->period_uv_closing_balance = $dUVRunBal = $dUVRunBal + $perBal->period_uv_balance;
				}
				else	{
					$perBal->period_uv_closing_balance = 0;
				}
			}
			else	{
				$perBal->period_uv_opening_balance = 0;
				$perBal->period_uv_dt_amount = 0;
				$perBal->period_uv_ct_amount = 0;
				$perBal->period_uv_balance = 0;
				$perBal->period_uv_closing_balance = 0;
			}
			
			// aggregate values for the period
			if ($this->accountClass->is_daily_balance_req)	{
				$params = array(
							"#dtFrom#" => $per->first_period_date,
							"#dtTo#" => $per->last_period_date,
							"#account_id#" => $this->account_id,
							"#period_type_id#" => $periodTypeId
				);
				$sqlId = "PeriodAccBalanceUpdMxAggreg";
				$sqlq = MMUtils::retrieveSqlStatement($sqlId, $params);
				$resAgg = MMObj::query($sqlq, NULL, NULL, $sqlId);
				$perBal->period_max_balance = $resAgg[0][max_bal];
				$perBal->period_min_balance = $resAgg[0][min_bal];
				$perBal->period_avg_balance = $resAgg[0][avg_bal];
			}

			
			if (count($res) == 1)	{		// update existing
				$updCount += $perBal->update();
			}
			else	{
				$insertArray[] = clone $perBal;
			}
			
			unset($perBal);
		}
//		$this->diagLog(0, 99, array( '#text' => 'update existing done (%updCount).', 
//										'#elapsed' => 2,
//										'%updCount' => $updCount)); 

		// inserts
//		$this->startWatch(2);
		$insCount = 0;
		$ins = new AXAccountPeriodBalance;
        $insCount = $ins->createMulti($insertArray);
		$this->diagLog(MMOBJ_DEBUG, 99, array( '#text' => 'fetches: %updMx, updates: %updCount, inserts: %insCount', 
										'#elapsed' => 2,
										'%updMx' => count($updMx),
										'%insCount' => $insCount,
										'%updCount' => $updCount)); 
//		$this->diagLog(0, 99, array( '#text' => 'insert new done (%insCount).', 
//										'#elapsed' => 2,
//										'%insCount' => $insCount)); 

		// final diag log
		if($insCount or $updCount)
			$this->diagLog(2, 99, array( '#text' => 'Period balance for account: %accountShort - %accountDesc updated from %dtFrom to %dtTo.', 
										'%accountShort' => $this->account_short,
										'%accountDesc' => $this->account_desc,
										'%dtFrom' => $perFrom->period_year . '.' . $perFrom->period,
										'%dtTo' => $perTo->period_year . '.' . $perTo->period,
										'#elapsed' => 1));
	}

	//
	// -------- PERIOD FUTURE
	//
	public function updatePeriodFutureBalance($periodTypeId, $dtFrom) {

		if (is_null($dtFrom) or $dtFrom < $this->valid_from) 
			$dtFrom = $this->valid_from;

		$this->startWatch(1);
		$this->diagLog(0, 99, array( '#text' => 'period future ## acc: %accountId, accs: %accountShort, accd: %accountDesc, dtfrom: %dtFrom ##', 
										'%accountId' => $this->account_id, 
										'%accountShort' => $this->account_short,
										'%accountDesc' => $this->account_desc,
										'%dtFrom' => $dtFrom,
								));

		// gets period corresponding to dtFrom
		$perFrom = new AMPeriod;
		$res = $perFrom->getPeriodFromDate($periodTypeId, $dtFrom);

		// gets previous period 
		if ($res)	{
			$perFromPrev = new AMPeriod;
			$resp = $perFromPrev->getPeriodFromSeq($periodTypeId, $perFrom->prev_period_seq);
		}

		// fetches data from prev period if existing, voids future fields
		if ($resp)	{
			$prevBal = new AXAccountPeriodBalance;
			$prevBal->environment_id = $this->environment_id;
			$prevBal->balance_type = 'ACC';
			$prevBal->account_id = $this->account_id;
			$prevBal->period_type_id = $periodTypeId;
			$prevBal->period_year = $perFromPrev->period_year;
			$prevBal->period = $perFromPrev->period;
			$prevBalPK = $prevBal->compactPKIntoString();
			$resq = $prevBal->read($prevBalPK);
			if ($resq)	{
				$prevBal->period_future_dt_amount = 0;
				$prevBal->period_future_ct_amount = 0;
				$prevBal->period_future_balance = 0;
				$resq->update();
			}
		}

		// initialises update matrix
		$this->startWatch(2);
		$params = array(
					"#perFrom#" => $perFrom->period_seq,
					"#dtFrom#" => $dtFrom,
					"#account_id#" => $this->account_id,
					"#period_type_id#" => $periodTypeId
		);
		$sqlId = "PeriodAccFutureBalanceUpdMx";
		$sqlq = MMUtils::retrieveSqlStatement($sqlId, $params);
		$updMx = MMObj::query($sqlq, 3, NULL, $sqlId);
//		$this->diagLog(0, 99, array( '#text' => 'fetch update matrix complete (%updMx).', 
//										'#elapsed' => 2,
//										'%updMx' => count($updMx))); 

		// main update cycle
		//todo if updmx is null
//		$this->startWatch(2);
		$updCount = 0;
		$insertArray = array();	
		foreach($updMx as $a) {

			$per = new AMPeriod;
			$perBal = new AXAccountPeriodBalance;

			// gets period 
			$per->period_type_id = $periodTypeId;
			$per->period_year = $a[period_year];
			$per->period = $a[period];
			$per->read($per->compactPKIntoString());

			// fetches balance record
			$perBal->environment_id = $this->environment_id;
			$perBal->balance_type = 'ACC';
			$perBal->account_id = $this->account_id; 
			$perBal->period_type_id = $periodTypeId;
			$perBal->period_year = $a[period_year];
			$perBal->period = $a[period];
			$perBalPK = $perBal->compactPKIntoString();
			$res = $perBal->read($perBalPK);
			
			// apply period futures
			$perBal->period_future_dt_amount = ($a[per_dt_sum] == NULL) ? 0 : $a[per_dt_sum];
			$perBal->period_future_ct_amount = ($a[per_ct_sum] == NULL) ? 0 : $a[per_ct_sum];
			$perBal->period_future_balance = $perBal->period_future_dt_amount + $perBal->period_future_ct_amount;
			
			if (count($res) == 1)	{		// update existing
				$updCount += $perBal->update();
			}
			else	{
				$insertArray[] = clone $perBal;
			}
			
			unset($perBal);
		}
//		$this->diagLog(0, 99, array( '#text' => 'update existing done (%updCount).', 
//										'#elapsed' => 2,
//										'%updCount' => $updCount)); 

		// inserts
//		$this->startWatch(2);
		$insCount = 0;
		$ins = new AXAccountPeriodBalance;
        $insCount = $ins->createMulti($insertArray);
//		$this->diagLog(0, 99, array( '#text' => 'insert new done (%insCount).', 
//										'#elapsed' => 2,
//										'%insCount' => $insCount)); 
		$this->diagLog(MMOBJ_DEBUG, 99, array( '#text' => 'fetches: %updMx, updates: %updCount, inserts: %insCount', 
										'#elapsed' => 2,
										'%updMx' => count($updMx),
										'%insCount' => $insCount,
										'%updCount' => $updCount)); 

		// final diag log
		if($insCount or $updCount)
			$this->diagLog(2, 99, array( '#text' => 'Period future balance for account: %accountShort - %accountDesc updated from %dtFrom.', 
										'%accountShort' => $this->account_short,
										'%accountDesc' => $this->account_desc,
										'%dtFrom' => $perFrom->period_year . '.' . $perFrom->period,
										'#elapsed' => 1));
	}
}
