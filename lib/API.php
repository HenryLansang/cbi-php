<?php
	//use UTC for all time calculations
	date_default_timezone_set("UTC");

	class API {
		function __construct($api_key, $secret_key, $sandbox, $accept_header='json') {
			$this->api_key = $api_key;
			$this->sec_key = $secret_key;
			$this->credits_left = false;
			$this->last_call_cost = 0;
			$this->sandbox = $sandbox;
			$this->accept_header = $accept_header;
		}//__construct

		public function Call($url, $method=null, $params=[]) {
			$ch = $sha = $ts = $url_params = null;
			$data = array();
			$method = strtolower($method);

			if((empty($method) || $method == 'get') && !empty($params)) {
				//encode each parameter
				foreach($params as $key => $val) {
					$url_params .= urlencode($key).'='.urlencode($val).'&';
				}//foreach

				//remove trailing '&'
				$url_params = substr($url_params, 0, -1);
 			
	 			//attach parameters to base URL
	 			$url .= "?$url_params";
 			}//if

			//current timestamp for hash of Api-Data header and Api-Ts header
			$ts = time();

			//create hash for Api-Data header
			$sha = utf8_encode(strtolower($this->api_key.$url.$this->sec_key.$ts));
			$data = hash("sha256", $sha, false);

			//make call to API and get resulting JSON
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_VERBOSE, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			
			//certain parameters for different HTTP methods
			switch($method){
				case 'post':
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
					break;
				case 'delete':
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
					curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
					break;
				case 'put':
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
					curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
					break;
				case 'head':
					// HEAD request, do nothing
					break;
				default:
					// GET request, do nothing
					break;
			}//switch

			//finish setting up and execute CURL request
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Api-Data: $data", "Api-Key: ".$this->api_key, "Api-Ts: $ts", "Api-Sandbox: ".$this->sandbox, "Api-Accept: ".strtolower($this->accept_header)));
			$resp = curl_exec($ch);

			//separate header/body
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$header = substr($resp, 0, $header_size);
			$body = substr($resp, $header_size);

			//parse out response headers
			$header = $this->ParseHeaders($header);
			
			//store amt of API credits account has left
			if(!empty($header["Cbi-Credits-Left"])) {
				$this->credits_left = $header["Cbi-Credits-Left"];
			}//if

			//store cost of last API call
			if(!empty($header['Cbi-Call-Cost'])) {
				$this->last_call_cost = $header['Cbi-Call-Cost'];
			}//if

			//return just the body (JSON) as the data
		    return($body);
		}//Call

		public function CreditsLeft() {
			//make dummy call to 404 page to get number of credits left
			//this solves for the case when a person hasn't made an API call yet
			if($this->credits_left === false) {
				$this->Call('http://localhost/api/1/404/', 'get', array());
			}//if

			return($this->credits_left);
		}//CreditsLeft

		public function LastCallCost() {
			return($this->last_call_cost);
		}//LastApiCallCost

		private function ParseHeaders($raw_headers) {
			$headers = array();
	   
			foreach(explode("\n", $raw_headers) as $i => $h) {
				$h = explode(':', $h, 2);
				
				if(isset($h[1])) {
					if(!isset($headers[$h[0]])) {
						$headers[$h[0]] = trim($h[1]);
					}//if 
					elseif(is_array($headers[$h[0]])) {
						$tmp = array_merge($headers[$h[0]], array(trim($h[1])));
						$headers[$h[0]] = $tmp;
					}//elseif
					else {
						$tmp = array_merge(array($headers[$h[0]]), array(trim($h[1])));
						$headers[$h[0]] = $tmp;
					}//else
				}//if
			}//foreach
			
			return $headers;
		}//parseHeaders

		private $accept_header, $api_key, $credits_left, $last_call_cost, $sandbox, $sec_key;
	}//API
?>