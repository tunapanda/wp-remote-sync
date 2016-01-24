<?php

class EventStream {

	public function __construct() {
		$this->currentStatus=NULL;
	}

	public function start() {
		if (headers_sent())
			throw new Exception("Can't create EventStream, headers sent...");

		ini_set('output_buffering', 'off');
		if (!headers_sent())
			ini_set('zlib.output_compression', false);

		while (@ob_end_flush());
	         
		ini_set('implicit_flush', true);
		ob_implicit_flush(true);

		header('Cache-Control: no-cache');
		header("Content-type: text/event-stream\n\n");

		ob_flush();
		flush();
	}

	public function event($message, $payload=array()) {
		echo "event: $message\n";
		echo "data: ".json_encode($payload)."\n\n";
		ob_flush();
		flush();
	}

	public function log($message) {
		$this->currentStatus=NULL;
		$this->currentStatusPercent=NULL;
		$this->currentStatusTime=NULL;
		$this->event("log",array("message"=>$message));
	}

	public function status($message) {
		$this->event("status",array("message"=>$message));
		$this->currentStatus=$message;
		$this->currentStatusPercent=NULL;
		$this->currentStatusTime=time();
	}

	public function progressStatus($message, $percent) {
		$percent=round($percent);
		$now=time();

		if ($message==$this->currentStatus) {
			if ($percent==$this->currentStatusPercent)
				return;

			if ($now==$this->currentStatusTime)
				return;
		}

		$this->currentStatus=$message;
		$this->currentStatusTime=$now;
		$this->currentStatusPercent=$percent;

		if ($percent)
			$message.=" ".$percent."%";

		$this->event("status",array("message"=>$message));
	}

	public function done() {
	}
}