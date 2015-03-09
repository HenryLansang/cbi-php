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
			$this->context_timestamp = microtime();
		}//__construct

		public function Call($url, $method=null, $params=array(), $reqContentType="") {
			$ch = $sha = $ts = null;
			$url_params = array();
			$data = array();
			$method = strtolower($method);

			if((empty($method) || $method == 'get') && !empty($params)) {
				//encode each parameter
				foreach($params as $key => $val) {
					if(is_array($val)) {
						foreach($val as $value){
							//Add [] to the parameter
							$url_params []= urlencode($key) . '[]=' . urlencode($value);
						} //foreach
					} else {
						$url_params []= urlencode($key).'='.urlencode($val);
					} //else
				}//foreach

	 			//attach parameters to base URL
	 			$url .= "?".implode('&', $url_params);
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
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify_peer);

			if(!empty($contentType)){
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Content-Type:'.$contentType
				]);
			}

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

			// get unique request context to track API call in logs.
			$req_context = $this->GetContext();
			$referer = $_SERVER["HTTP_REFERER"];
			// send a reference to context in response headers.
			header('Request-Context: '.$req_context);

			//finish setting up and execute CURL request
			$httpHeaderHolder = [
				"Request-Context: ".$req_context,
				"Http-Referer: ".$referer,
				"Api-Data: ".$data,
				"Api-Key: ".$this->api_key,
				"Api-Ts: ".$ts,
				"Api-Sandbox: ".$this->sandbox,
				"Api-Accept: ".strtolower($this->accept_header),
			];

			if(!empty($reqContentType)){
				$httpHeaderHolder[] = "Content-Type: ".$reqContentType;
			}

			curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaderHolder);
			$resp = curl_exec($ch);

			if(curl_error($ch) != '') {
				return(json_encode(array('curlError' => curl_error($ch))));
			}//if

			//separate header/body
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$header = substr($resp, 0, $header_size);
			$body = substr($resp, $header_size);
			$decoded_body = json_decode($body);
			$remote_ip = $this->GetIp();


			if ($status_code >= 400) {
				error_log("[API CALL ERROR] received a ".$status_code." error in response header. url: ".$url.", method: ".$method.", request-data: ".json_encode($params).", referer: ".$_SERVER["HTTP_REFERER"].", remote: ".$remote_ip.", context: ".$req_context);
			} else if (is_object($decoded_body) && !empty($decoded_body->{'httpCode'}) && $decoded_body->{'httpCode'} >= 400) {
				error_log("[API CALL WARNING] received a " . $decoded_body->{'httpCode'} . " error in response body. url: " . $url . ", method: ".$method.", request-data: ".json_encode($params).", referer: ".$_SERVER["HTTP_REFERER"].", remote: ".$remote_ip.", context: ".$req_context);
			} elseif (count($decoded_body) == 0 && $method == "get") {
				error_log('[API CALL WARNING] GET request returned no result. url: ' . $url . ', request-data: '.json_encode($params).', referer: '.$referer.", remote: ".$remote_ip.", context: ".$req_context);
			}

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

		public function SetSSLVerifyPeer($ssl_vp) {
			$this->ssl_verify_peer = $ssl_vp;
		}//SetSSLVerifyPeer

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

		private function GetIp() {

			//Just get the headers if we can or else use the SERVER global
			if ( function_exists( 'apache_request_headers' ) ) {

				$headers = apache_request_headers();

			} else {
				$headers = $_SERVER;
			}

			//Get the forwarded IP if it exists
			if ( array_key_exists( 'X-Forwarded-For', $headers ) && filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {

				$the_ip = $headers['X-Forwarded-For'];

			} elseif ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ) && filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
			) {

				$the_ip = $headers['HTTP_X_FORWARDED_FOR'];

			} else {

				$the_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );

			}
			return $the_ip;
		}

		private function GetContext() {

			if (isset($_SESSION['user']) && isset($_SESSION['user']['id_user'])) {
				$unique_id = $_SESSION['user']['id_user'];
			} else {
				$unique_id = $this->GetIp();
			}

			if(empty($_SERVER["HTTP_REFERER"])) {
				$_SERVER["HTTP_REFERER"] = "";
			}//if

			return $unique_id."-".hash("md5", $_SERVER["HTTP_REFERER"].$unique_id, false)."-".hash("md5", $unique_id.$this->context_timestamp);
		}

		private $accept_header, $api_key, $credits_left, $last_call_cost, $sandbox, $sec_key;
		private $ssl_verify_peer = true;
	}//API


?>