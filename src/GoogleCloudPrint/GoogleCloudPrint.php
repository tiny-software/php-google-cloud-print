<?php
/*
PHP implementation of Google Cloud Print
Author, Yasir Siddiqui

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace GoogleCloudPrint;

class GoogleCloudPrint {
	
	const PRINTERS_SEARCH_URL = "https://www.google.com/cloudprint/search";
	const PRINT_URL = "https://www.google.com/cloudprint/submit";
    const JOBS_URL = "https://www.google.com/cloudprint/jobs";

	private $authToken;
	private $httpRequest;

	/**
	 * Function __construct
	 * Set private members varials to blank
	 */
	public function __construct() {
		
		$this->authToken = "";
		$this->httpRequest = new HttpRequest();
	}
	
	/**
	 * Function setAuthToken
	 *
	 * Set auth tokem
	 * @param string $token token to set
	 * @return $this
	 */
	public function setAuthToken($token) {
		$this->authToken = $token;

		return $this;
	}
	
	/**
	 * Function getAuthToken
	 *
	 * Get auth tokem
	 *
	 * @return auth tokem
	 */
	public function getAuthToken() {
		return $this->authToken;
	}
	
	
	/**
	 * Function getAccessTokenByRefreshToken
	 *
	 * Gets access token by making http request
	 * 
	 * @param $url url to post data to
	 * @param $postFields post fileds array
	 *
	 * @return access tokem
	 */
	
	public function getAccessTokenByRefreshToken($url, $postFields) {
		$responseObj =  $this->getAccessToken($url, $postFields);
		return $responseObj->access_token;
	}
	
	
	/**
	 * Function getAccessToken
	 *
	 * Makes Http request call
	 * @param $url url to post data to
	 * @param $postFields post fileds array
	 *
	 * @return object | boolean http response
	 */
	public function getAccessToken($url, $postFields) {
		
		$this->httpRequest->setUrl($url);
		$this->httpRequest->setPostData($postFields);
		$this->httpRequest->send();
		$response = json_decode($this->httpRequest->getResponse());
		return $response;
	}
	
	/**
	 * Function getPrinters
	 *
	 * Get all the printers added by user on Google Cloud Print. 
	 * Follow this link https://support.google.com/cloudprint/answer/1686197 in order to know how to add printers
	 * to Google Cloud Print service.
	 */
	public function getPrinters() {
		
		// Check if we have auth token
		if(empty($this->authToken)) {
			// We don't have auth token so throw exception
			throw new Exception("Please first login to Google");
		}
		
		// Prepare auth headers with auth token
		$authHeaders = array(
		"Authorization: Bearer " .$this->authToken
		);
		
		$this->httpRequest->setUrl(self::PRINTERS_SEARCH_URL);
		$this->httpRequest->setHeaders($authHeaders);
		$this->httpRequest->send();
		$responseData = $this->httpRequest->getResponse();
		// Make Http call to get printers added by user to Google Cloud Print
		$printers = json_decode($responseData);
		// Check if we have printers?
		if(is_null($printers)) {
			// We dont have printers so return balnk array
			return array();
		}
		else {
			// We have printers so returns printers as array
			return $this->parsePrinters($printers);
		}
		
	}
	
	/**
	 * Function sendPrintToPrinter
	 * 
	 * Sends document to the printer
	 * 
	 * @param $printerId printer id returned by Google Cloud Print service
	 * @param $printJobTitle Print Job Title
	 * @param $filePath Path to the file to be send to Google Cloud Print
	 * @param $contentType File content type e.g. application/pdf, image/png for pdf and images
	 *
	 * @return array
	 */
	public function sendPrintToPrinter($printerId, $printJobTitle, $filePath, $contentType, $ticket = []) {
		
	// Check if we have auth token
		if(empty($this->authToken)) {
			// We don't have auth token so throw exception
			throw new Exception("Please first login to Google by calling loginToGoogle function");
		}
		// Check if prtinter id is passed
		if(empty($printerId)) {
			// Printer id is not there so throw exception
			throw new Exception("Please provide printer ID");	
		}
		// Open the file which needs to be print
		$handle = fopen($filePath, "rb");
		if(!$handle)
		{
			// Can't locate file so throw exception
			throw new Exception("Could not read the file. Please check file path.");
		}
		// Read file content
		$contents = file_get_contents($filePath);
		
		// Prepare post fields for sending print
		$post_fields = array(
				
			'printerid' => $printerId,
			'title' => $printJobTitle,
			'contentTransferEncoding' => 'base64',
			'content' => base64_encode($contents), // encode file content as base64
			'contentType' => $contentType
		);

		if(!empty($ticket)){
			$post_fields['ticket'] = json_encode($ticket);
		}

		// Prepare authorization headers
		$authHeaders = array(
			"Authorization: Bearer " . $this->authToken
		);
		
		// Make http call for sending print Job
		$this->httpRequest->setUrl(self::PRINT_URL);
		$this->httpRequest->setPostData($post_fields);
		$this->httpRequest->setHeaders($authHeaders);
		$this->httpRequest->send();
		$response = json_decode($this->httpRequest->getResponse());
		
		// Has document been successfully sent?
		if($response->success=="1") {
			
			return array('status' =>true,'errorcode' =>'','errormessage'=>"", 'id' => $response->job->id);
		}
		else {
			
			return array('status' =>false,'errorcode' =>$response->errorCode,'errormessage'=>$response->message);
		}
	}

    public function jobStatus($jobId)
    {
        // Prepare auth headers with auth token
        $authHeaders = array(
            "Authorization: Bearer " .$this->authToken
        );

        // Make http call for sending print Job
        $this->httpRequest->setUrl(self::JOBS_URL);
        $this->httpRequest->setHeaders($authHeaders);
        $this->httpRequest->send();
        $responseData = json_decode($this->httpRequest->getResponse());

        foreach ($responseData->jobs as $job)
            if ($job->id == $jobId)
                return $job->status;

        return 'UNKNOWN';
    }


	/**
	 * Function parsePrinters
	 * 
	 * Parse json response and return printers array
	 * 
	 * @param $jsonObj // Json response object
	 *
	 * @return printers list
	 */
	private function parsePrinters($jsonObj) {
		
		$printers = array();
		if (isset($jsonObj->printers)) {
			foreach ($jsonObj->printers as $gcpprinter) {
				$printers[] = array('id' =>$gcpprinter->id,'name' =>$gcpprinter->name,'displayName' =>$gcpprinter->displayName,
						    'ownerName' => @$gcpprinter->ownerName,'connectionStatus' => $gcpprinter->connectionStatus,
						    );
			}
		}
		return $printers;
	}
}
