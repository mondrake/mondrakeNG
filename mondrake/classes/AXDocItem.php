<?php
 
require_once 'MMObj.php';
require_once 'AXAccount.php'; 
require_once 'MMSqlStatement.php';

class AXDocItem extends MMObj {
 
	public function __construct() {	
		parent::__construct();
		self::setColumnProperty(array ('validation_ts'),
			'editable', FALSE);
	}

	public function create($clientPKMap = false)	{
		$res = parent::create($clientPKMap);

		// set watermark date (=date from where to start recalculation of stats)
		$this->setAccountCtl($this->account_id, $this->doc_item_date);
		
		return $res;
	}  
	
	public function update() {
		$prevDbImage = $this->prevDbImage;
		$res = parent::update();
		
		// set watermark date (=date from where to start recalculation of stats). If update changed the account then it is set
		// both for current and pervious account
		if ($res == 1)	{
			$setDate = ($this->doc_item_date <= $prevDbImage['doc_item_date']) ? $this->doc_item_date : $prevDbImage['doc_item_date'];
			if ($this->account_id <> $prevDbImage['account_id'])	{
				$this->setAccountCtl($prevDbImage['account_id'], $setDate);
			}
			$this->setAccountCtl($this->account_id, $setDate);
		}
		
		return $res;
	}
		
	public function delete($clientPKMap = false) {
		$res = parent::delete($clientPKMap);

		// set watermark date (=date from where to start recalculation of stats)
		$this->setAccountCtl($this->account_id, $this->doc_item_date);
		
		return $res;
	}

	public function loadFromArray($arr, $docId, $clientPKReplace = false) {
		parent::loadFromArray($arr, $clientPKReplace);
		$this->doc_id = $docId;
	}					

	public function synch($src, $clientPKMap = false) {
		parent::synch($src, $clientPKMap);
		$this->update();
	}	

	protected function validate() {
		$highErr = parent::validate();
		
		// checks docItemDate inside account validity range
		$acc = new AXAccount;
		$acc->read($this->account_id);
		if ($this->doc_item_date < $acc->valid_from or (!empty($acc->valid_to) and $this->doc_item_date > $acc->valid_to))	{
			$highErr = 4;
			$this->diagLog(4, 1000, array( '#text' => 'Document item date outside account validity period. Doc: %docId, Item date: %docItemDate, Account: %accountShort %accountDesc', 
									'%docId' => $this->doc_id,
									'%docItemDate' => $this->doc_item_date,
									'%accountShort' => $acc->account_short,
									'%accountDesc' => $acc->account_desc, 
									'%fieldName' => 'doc_item_date',
									)); 
		}

		// item validation timestamp
		if ($this->is_doc_item_validated == TRUE)	{
			if (isset($this->prevDbImage))	{
				if ($this->prevDbImage['is_doc_item_validated'] == FALSE)	{
					$this->validation_ts = MMUtils::timestamp();
				}
			}
			else	{
				$this->validation_ts = MMUtils::timestamp();
			}
		}
		else	{
			$this->validation_ts = null;
		}
		
		return $highErr;
	}	

//////// --------- class specific	
	
	public function getDocItems($docId) {
		return $this->readMulti("doc_id = $docId");
	}

	private function setAccountCtl($accountId, $docItemDate)	{
		$acc = new AXAccount;
		$acc->read($accountId);
		if ($acc->accountClass->is_period_balance_req)	{
			$today = MMUtils::date();
			// if watermark is null then set to valid_from
			if (empty($acc->accountCtl->day_watermark))	{
				$acc->accountCtl->day_watermark = $acc->valid_from;
			}
			$acc->accountCtl->is_balance_calc_req = TRUE;
			// if item date is below/equal to latest stat calculated then update watermark
			if ($docItemDate <= $today)	{		
				if ($docItemDate < $acc->accountCtl->day_watermark)	{ 
					$acc->accountCtl->day_watermark = $docItemDate;				
				}
			}
			// if item date is higher than today, but lower than current first future item, set first future item to item date
			else	{ 
				if ($docItemDate < $acc->accountCtl->day_first_future_item || is_null($acc->accountCtl->day_first_future_item))	{		
					$acc->accountCtl->day_first_future_item = $docItemDate;				
				}
				else if	($docItemDate == $acc->accountCtl->day_first_future_item)	{
					$acc->accountCtl->day_first_future_item = $this->getFirstFutureItemDate($acc->account_id, $today);				
				}
			}
			$acc->accountCtl->update();
		}
	}
	
	public function getFirstFutureItemDate($accountId, $dateFrom)	{
		$params = array(
			"#date#" => $dateFrom,
			"#accountId#" => $accountId,
		);
		$sqlq = MMUtils::retrieveSqlStatement("docItemNextFutureDate", $params);
		$updMx = MMObj::query($sqlq);
		if (count($updMx) == 1)	{
			return $updMx[0]['next_date']; 
		}
		else
		{
			return null; 
		}
	}
}