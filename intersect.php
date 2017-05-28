<?php

$ACCEPTED_METHODS = array('GET', 'POST', 'OPTIONS'); // GET, POST, PUT, DELETE, OPTIONS, PATCH
$SIMPLE_DEFAULT_HOME = 'Api bridge by aimingoo.'; // Option
$DOMAIN_ACCEPT = 'aimingoo.github.io';	// Option - Your Github pages site
$SOURCE_ACCEPT = $DOMAIN_ACCEPT;

$PROXY_PROTOCOL = "https://";
$PROXY_DOMAIN = "github.com";
$ROOT_CERT = __DIR__ . "/cacert.pem"; // Option - if you have these

// !!!PLEASE!!! Create a OAuth applicaton in your Github settings, and put "Client Secret" at here.
$PRIVATE_CLIENT_SECRET = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // Option - !MUST UPDATE!


/*------------------------------------------------------------
** accept for frontend
**----------------------------------------------------------*/
// CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	header("Access-Control-Allow-Methods: " . implode(', ', $ACCEPTED_METHODS));
	// header('Access-Control-Max-Age: 86400');
}

// Simple home notice
$REQUEST_URI = $_SERVER['REQUEST_URI'];
if (empty($REQUEST_URI) || $REQUEST_URI === '/') {
	die($SIMPLE_DEFAULT_HOME);
}

// Simple method guard
$method = strtoupper($_SERVER['REQUEST_METHOD']);
if (array_search($method, $ACCEPTED_METHODS) === false) {
	die('Error: Method no accept.');
}

// Simple accept guard
if (isset($_SERVER['HTTP_ORIGIN']) &&
	!preg_match('{^https?://'.$SOURCE_ACCEPT.'}i', $_SERVER['HTTP_ORIGIN']))
{
	die('Error: Domain no accept.');
}

// simple accept guard
if (isset($_SERVER['HTTP_REFERER']) &&
	!preg_match('{^https?://'.$DOMAIN_ACCEPT.'}i', $_SERVER['HTTP_REFERER']))
{
	die('Error: Source no accept.');
}

// simple request guard
if (!(
	// preg_match('{^/login/oauth/authorize(\?|$)}', $REQUEST_URI) ||
	preg_match('{^/login/oauth/access_token(\?|$)}', $REQUEST_URI)
)) {
	die('Error: Api no accept.');
}

// CORS's OPTIONS header checker
if ($method == 'OPTIONS') {
	// $_SERVER['HTTP_ORIGIN'] and $_SERVER['REQUEST_URI'] are checked, will
	// skip Access-Control-Request-Method, Access-Control-Request-Headers ...
	// and direct response.
	if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
		header("Access-Control-Allow-Headers: " . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
	}

	// header("Connection: Close");  // normal setting by gateway, 'Keep-Alive' etc.
	header("Content-Length: 0");
	die();
}

// for Github auth secret
//	- get method, or post method with form-urlencoded
$CLIENT_SECRET = '';
if (preg_match('{^/login/oauth/access_token(\?|$)}', $REQUEST_URI)) {
	$CLIENT_SECRET = $PRIVATE_CLIENT_SECRET;
}

// URL of the target (this should be changed to be modular)
//	- proxy to backend
$url = $PROXY_PROTOCOL.$PROXY_DOMAIN;
$script_rel_path = preg_replace('/.*public_html/','', __FILE__); //not all servers have public_html
$url_part = str_replace($script_rel_path, '', $_SERVER['REQUEST_URI']);
$url .= $url_part;
$curl = curl_init();
$append_request_length = 0;

/*------------------------------------------------------------
** proxy methods
**----------------------------------------------------------*/

/** 
 * Post with https
 *  - http://blog.csdn.net/linvo/article/details/8816079
 */
function curlPost($url, $timeout = 30, $CA = true){
	$SSL = substr($url, 0, 8) == "https://" ? true : false;

	// $ch = curl_init();
	// curl_setopt($ch, CURLOPT_URL, $url);
	global $curl, $ROOT_CERT;
	$ch = $curl;
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout-2);
	if ($SSL && $CA) {
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $CA);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $CA?2:1);
		if ($CA) {
			curl_setopt($ch, CURLOPT_CAINFO, $ROOT_CERT);
		}
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
/* remove by aimingoo
	curl_setopt($ch, CURLOPT_POST, true);
	// curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); //data with URLEncode

	$ret = curl_exec($ch);
	//var_dump(curl_error($ch));

	curl_close($ch);
	return $ret;  
*/
}

/** 
 * Comment by aimingoo:
 *   - http://php.net/manual/en/function.curl-setopt.php
 *  Passing an array to CURLOPT_POSTFIELDS will encode the data as multipart/form-data,
 *  while passing a URL-encoded string will encode the data as application/x-www-form-urlencoded.
 */
function mixopts($method) {
	global $CLIENT_SECRET, $curl, $append_request_length;

	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
	if (empty($CLIENT_SECRET)) {
		curl_setopt($curl, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
	}
	else {
        $PARAM = '&client_secret=' . $CLIENT_SECRET;
		curl_setopt($curl, CURLOPT_POSTFIELDS, file_get_contents('php://input') . $PARAM);
		$append_request_length += strlen($PARAM);
	}
}


function get() {
	// curl default method
	// 	- curl_setopt($curl, CURLOPT_HTTPGET, true);
	global $CLIENT_SECRET, $url, $curl;
	if (! empty($CLIENT_SECRET)) {  // !! rewrite
		curl_setopt($curl, CURLOPT_URL, $url .
			(empty($_SERVER['QUERY_STRING']) ? '?' : '&') . 'client_secret=' . $CLIENT_SECRET);
	}
}

function delete() {
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
}

function post() {
	global $url;
	mixopts("POST");
	curlPost($url);
}

function put() {
	mixopts("PUT");
}

function patch() {
	mixopts("PATCH");
}

function applyRequestHeaders() {
	global $append_request_length;

	$headers = array();
    $all_headers = array(); 
	$forbidden_headers = array('Host'); // array('Origin', 'Host', 'Referer', 'X-Forwarded-For', 'X-Real-Ip');

	// build headers from $_SERVER variants
	foreach($_SERVER as $key => $value) {
		if (substr($key, 0, 5) <> 'HTTP_' && $key !== 'CONTENT_TYPE' && $key !== 'CONTENT_LENGTH') {
			continue;
		}
		$all_headers[$key] = $value;

		// skip for raw_header
		if ($key === 'HTTP_CONTENT_TYPE' || $key === 'HTTP_CONTENT_LENGTH' ||
			$key === 'HTTP_CONNECTION') {
			continue;
		}

		// adjust size
		if (!empty($append_request_length) && ($key === 'CONTENT_LENGTH')) {
			$value += $append_request_length;
		}

		// php server variants as http-request-header
		if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
			$key = 'HTTP_'.$key;
		}

		$header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));

		if (array_search($header, $forbidden_headers) !== false) {
			continue;
		}

		array_push($headers, $header.': '.$value);
	}
	// var_dump($headers);
    // var_dump($all_headers);
	return $headers;
}

function applyResponseHeaders($header_text) {
	$content_length = 0;
	foreach (explode("\r\n", $header_text) as $i => $line) {
		list ($key, $value) = explode(': ', $line);
		if (empty($value)) {
			continue;
		}

		$ukey = strtoupper($key);

		// Transfer-Encoding: chunked
		if (($ukey == 'TRANSFER-ENCODING') &&
			(stripos($value, 'chunked') !== false))  {
			continue;
		}

   		// Access-Control...
		// Connection: ...
		if ((stripos($key, 'ACCESS-CONTROL') !== false) ||
			($ukey == 'CONNECTION')) {
			continue;
		}

   		// Content-Length: ...
		if ($ukey == 'CONTENT-LENGTH') {
			$content_length = $value;
		}

		header($line);
	}
	return $content_length;
}

error_reporting(E_ALL ^E_WARNING ^E_NOTICE);

/*------------------------------------------------------------
** proxy now and check result
**----------------------------------------------------------*/
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_VERBOSE, 1);

// applay/set all options
$method_name = strtolower($method);
if (function_exists($method_name)) {
	call_user_func($method_name, $curl);
}

// adjust headers
$headers = applyRequestHeaders();
$headers[] = 'Host: '.$PROXY_DOMAIN;
// $headers[] = 'Origin: '.$PROXY_PROTOCOL.$PROXY_DOMAIN;
// ...
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
curl_setopt($curl, CURLOPT_HEADER, 1);
//curl_setopt($curl, CURLOPT_ENCODING, 'identity');

$result = curl_exec($curl);

// certificate validation results
//  - https://github.com/jublonet/codebird-cors-proxy
$validation_result = curl_errno($curl);
if ($validation_result && in_array( $validation_result, [
        58, // CURLE_SSL_CERTPROBLEM,
        60, // CURLE_SSL_CACERT,
        77, // CURLE_SSL_CACERT_BADFILE,
        82, // CURLE_SSL_CRL_BADFILE,
        83  // CURLE_SSL_ISSUER_ERROR
])) {
    die("Error " . $validation_result . ' while validating the API certificate.');
}

/*------------------------------------------------------------
** build response and send
**----------------------------------------------------------*/
// $httpstatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
$header = substr($result, 0, $header_size);
$content_length = applyResponseHeaders($header);
$body = substr($result, $header_size);
if (! $content_length) {
	header('Content-Length: ' . strlen($body));
}

// close backend connection always.
curl_close($curl);
// close frontend connection, recommend for minor api access.
header("Connection: Close");
die($body);

?>