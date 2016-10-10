<?php

namespace mondrakeNG\mm\core;

class MMTimer {

	var $startTimeSec;
	var $startTimeuSec;
	var $startTime;
	var $stopTimeSec;
	var $stopTimeuSec;
	var $stopTime;
	var $partialTimeSec;
	var $partialTimeuSec;
	var $partialTime;
	var $tSec;
	var $tUSec;
	var $elapsed;

	public function __construct()  {  }

	public function __clone()  {  }

	public function start()	{
		list($this->startTimeuSec, $this->startTimeSec) = explode(" ", microtime());
		$this->startTimeSec = (float) $this->startTimeSec;
		$this->startTimeuSec = (float) $this->startTimeuSec;
		$this->startTime = (gmdate('Y-m-d H:i:s', $this->startTimeSec) . strstr($this->startTimeuSec, '.'));
	}

	public function stop()	{
		list($this->stopTimeuSec, $this->stopTimeSec) = explode(" ", microtime());
		$this->stopTimeSec = (float) $this->stopTimeSec;
		$this->stopTimeuSec = (float) $this->stopTimeuSec;
		$this->stopTime = (gmdate('Y-m-d H:i:s', $this->stopTimeSec) . strstr($this->stopTimeuSec, '.'));
		$this->elapsed = ($this->stopTimeSec + $this->stopTimeuSec) - ($this->startTimeSec + $this->startTimeuSec);
	}

	public function partial()	{
		list($this->partialTimeuSec, $this->partialTimeSec) = explode(" ", microtime());
		$this->partialTimeSec = (float) $this->partialTimeSec;
		$this->partialTimeuSec = (float) $this->partialTimeuSec;
		$this->partialTime = (gmdate('Y-m-d H:i:s', $this->partialTimeSec) . strstr($this->partialTimeuSec, '.'));
		$this->elapsed = ($this->partialTimeSec + $this->partialTimeuSec) - ($this->startTimeSec + $this->startTimeuSec);
	}

	public function timenow()	{
		list($this->tUSec, $this->tSec) = explode(" ", microtime());
		$this->tSec = gmdate('Y-m-d H:i:s', (float) $this->tSec);
		$this->tUSec = trim(strstr((float) $this->tUSec, '.'), '.');
		return ($this->tSec . '.' . $this->tUSec);
	}
}
