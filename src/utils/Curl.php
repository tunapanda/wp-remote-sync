<?php

/**
 * Object oriented wrapper for curl.
 */
class Curl {

	private static $mock;
	private static $mockResults;
	private static $mockCalls;

	private $resultDecoding;
	private $percentFunc;
	private $lastPercent;
	private $lastPercentTime;

	const JSON="json";

	/**
	 * Constructor.
	 */
	public function __construct($url=NULL) {
		if ($url)
			$this->curl=curl_init($url);

		else
			$this->curl=curl_init();

		$this->resultDecoding=NULL;
		$this->postFields=NULL;
		$this->downloadFileName=NULL;
		$this->percentFunc=NULL;

		$this->lastPercentTime=-1;
		$this->lastPercent=-1;
	}

	/**
	 * Init mock.
	 */
	public static function initMock() {
		Curl::$mock=TRUE;
		Curl::$mockResults=array();
		Curl::$mockCalls=array();
	}

	/**
	 * Set file name to download to.
	 */
	public function setDownloadFileName($fileName) {
		$this->downloadFileName=$fileName;
		return $this;
	}

	/**
	 * Mock a result.
	 */
	public static function mockResult($res) {
		if (!Curl::$mock)
			throw new Exception("Curl is not in mock mode");

		if (is_string($res))
			Curl::$mockResults[]=$res;

		else
			Curl::$mockResults[]=json_encode($res);
	}

	/**
	 * Get mock calls.
	 */
	public static function getMockCalls() {
		return $this->mockCalls;
	}

	/**
	 * Add post field.
	 */
	public function addPostField($field, $value) {
		$this->postFields[$field]=$value;

		return $this;
	}

	/**
	 * Add file upload.
	 */
	public function addFileUpload($field, $fileName) {
		if (class_exists("CurlFile")) {
			$this->postFields[$field]=new CurlFile(
				$fileName,
				mime_content_type($fileName),
				$field
			);
		}

		else {
			$this->addPostField($field,"@".$fileName);
		}

		return $this;
	}

	/**
	 * Set result decoding.
	 */
	public function setResultDecoding($decoding) {
		$this->resultDecoding=$decoding;

		if ($decoding)
			$this->setopt(CURLOPT_RETURNTRANSFER,TRUE);

		return $this;
	}

	/**
	 * Set percent func.
	 */
	public function setPercentFunc($percentFunc) {
		$this->percentFunc=$percentFunc;
	}

	/**
	 * Curl progress.
	 */
	private function onCurlProgress($curl, $totalDown, $down, $totalUp, $up) {
		$percent=NULL;

		if ($totalDown && $down<$totalDown)
			$percent=round(100*$down/$totalDown);

		if ($totalUp && $up<$totalUp)
			$percent=round(100*$up/$totalUp);

		$time=time();

		if ($percent!==$this->lastPercent && $this->lastPercentTime!=$time) {
			$this->lastPercent=$percent;
			$this->lastPercentTime=$time;

			call_user_func($this->percentFunc,$percent);
		}
	}

	/**
	 * Curl progress function compatible with PHP versions before 5.5
	 */
	private function onCurlProgressOld($totalDown, $down, $totalUp, $up) {
		$this->onCurlProgress(NULL, $totalDown, $down, $totalUp, $up);
	}

	/**
	 * Exec.
	 */
	public function exec() {
		if ($this->postFields) {
			$this->setopt(CURLOPT_POST,1);
			$this->setopt(CURLOPT_POSTFIELDS,$this->postFields);
		}

		$outf=NULL;
		if ($this->downloadFileName) {
			$this->setResultDecoding(NULL);
			$this->setopt(CURLOPT_RETURNTRANSFER,FALSE);

			$outf=fopen($this->downloadFileName,"wb");
			if (!$outf)
				throw new Exception("Unable to write file for download: ".$this->downloadFileName);

			$this->setopt(CURLOPT_FILE,$outf);
		}

		if (Curl::$mock) {
			if (sizeof(Curl::$mockCalls)>=sizeof(Curl::$mockResults))
				throw new Exception("Out of mock results");

			$res=Curl::$mockResults[sizeof(Curl::$mockCalls)];
			Curl::$mockCalls[]=$this;

			if ($outf)
				fwrite($outf,$res);

			$returnCode=200;
		}

		else {
			if ($this->percentFunc) {
				curl_setopt($this->curl,CURLOPT_NOPROGRESS,FALSE);

				if (defined("PHP_VERSION_ID") && PHP_VERSION_ID>=50500)
					curl_setopt($this->curl,CURLOPT_PROGRESSFUNCTION,
						array($this,"onCurlProgress"));

				else
					curl_setopt($this->curl,CURLOPT_PROGRESSFUNCTION,
						array($this,"onCurlProgressOld"));
			}

			$res=curl_exec($this->curl);
			$returnCode=$this->getinfo(CURLINFO_HTTP_CODE);
		}

		if ($this->error()) {
			if ($outf)
				fclose($outf);

			if ($this->downloadFileName)
				unlink($this->downloadFileName);

			throw new Exception("Curl error: ".$this->error());
		}

		$this->close();

		if ($outf)
			fclose($outf);

		if ($returnCode!=200)
			throw new Exception("Unexpected return code: ".$returnCode."\n".$res);

		switch ($this->resultDecoding) {
			case Curl::JSON:
				$raw=$res;
				$res=json_decode($res,TRUE);
				if ($res===NULL)
					throw new Exception("Unable to decode json: ".strlen($raw)." bytes: ".$raw);

				if (array_key_exists("Error", $res))
					throw new Exception($res["Error"]);

				break;
		}

		return $res;
	}

	/**
	 * Setopt.
	 */
	public function setopt($option, $value) {
		curl_setopt($this->curl,$option,$value);
		return $this;
	}

	/**
	 * Getinfo.
	 */
	public function getinfo($option) {
		return curl_getinfo($this->curl,$option);
	}

	/**
	 * Close.
	 */
	public function close() {
		curl_close($this->curl);
	}

	/**
	 * Get error.
	 */
	public function error() {
		return curl_error($this->curl);
	}
}