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

	protected $authToken;
	protected $httpRequest;

	/**
	 * Function __construct
	 * Set protected members varials to blank
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
		$responseObj = $this->getAccessToken($url, $postFields);
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
		if (empty($this->authToken)) {
			throw new \Exception("Please first login to Google");
		}

		$authHeaders = array("Authorization: Bearer " . $this->authToken);

		// Make Http call to get printers added by user to Google Cloud Print
		$this->httpRequest->setUrl(self::PRINTERS_SEARCH_URL);
		$this->httpRequest->setHeaders($authHeaders);
		$this->httpRequest->send();
		$responseData = $this->httpRequest->getResponse();
		$printers = json_decode($responseData);

		if (is_null($printers)) {
			// We dont have printers so return blank array
			return array();
		} else {
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
	public function sendPrintToPrinter(
		$printerId,
		$printJobTitle,
		$contents,
		$contentType,
		$ticket = []
	) {
		if (empty($this->authToken)) {
			throw new \Exception(
				"Please first login to Google by calling loginToGoogle function"
			);
		}

		if (empty($printerId)) {
			throw new \Exception("Please provide printer ID");
		}

		$post_fields = array(
			'printerid' => $printerId,
			'title' => $printJobTitle,
			'contentType' => $contentType
		);

		if ($contentType == "url") {
			$post_fields["content"] = $contents;
		} else {
			$post_fields['contentTransferEncoding'] = 'base64';
			$post_fields['content'] = base64_encode($contents);
		}

		if (!empty($ticket)) {
			$post_fields['ticket'] = json_encode($ticket);
		}

		$authHeaders = array("Authorization: Bearer " . $this->authToken);

		// Make http call for sending print Job
		$this->httpRequest->setUrl(self::PRINT_URL);
		$this->httpRequest->setPostData($post_fields);
		$this->httpRequest->setHeaders($authHeaders);
		$this->httpRequest->send();
		$response = json_decode($this->httpRequest->getResponse());

		if ($response->success == "1") {
			return $response->job->id;
		} else {
			throw new \Exception($response->message, $response->errorCode);
		}
	}

	public function jobStatus($jobId) {
		$authHeaders = array("Authorization: Bearer " . $this->authToken);

		// Make http call for sending print Job
		$this->httpRequest->setUrl(self::JOBS_URL);
		$this->httpRequest->setHeaders($authHeaders);
		$this->httpRequest->send();
		$responseData = json_decode($this->httpRequest->getResponse());

		foreach ($responseData->jobs as $job) {
			if ($job->id == $jobId) {
				return $job->status;
			}
		}

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
	protected function parsePrinters($jsonObj) {
		$printers = array();
		if (isset($jsonObj->printers)) {
			foreach ($jsonObj->printers as $gcpprinter) {
				$printers[] = array(
					'id' => $gcpprinter->id,
					'name' => $gcpprinter->name,
					'displayName' => $gcpprinter->displayName,
					'ownerName' => @$gcpprinter->ownerName,
					'connectionStatus' => $gcpprinter->connectionStatus
				);
			}
		}
		return $printers;
	}

}
