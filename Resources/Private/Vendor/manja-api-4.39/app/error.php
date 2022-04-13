<?php
declare(strict_types=1);
/**
 * Manja Error Base Types
 *
 * @package   ManjaWeb
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * exception with http response code & a separate details string
 */
class mjHTTPError extends mjError {

	/**
	 * @var int
	 */
	private $http_code;

	/**
	 * @var array
	 */
	private $http_allow_methods;

	/**
	 * constructor
	 *
	 * @param int $http_code
	 * @param string $message
	 * @param string|array|object|int $details
	 * @param Throwable|null $previous
	 * @param array $http_allow_methods
	 */
	public function __construct( int $http_code, string $message='', $details='', Throwable $previous=null, array $http_allow_methods=array('GET','HEAD') ) {
		parent::__construct($message,$details,$previous);
		$this->http_code = $http_code;
		$this->http_allow_methods = $http_allow_methods;
	}

	/**
	 * Returns the HTTP status code of this exception
	 *
	 * @return int
	 */
	public function getHTTPCode() : int {
		return $this->http_code;
	}

	/**
	 * Return the HTTP status message of the HTTP status code of this exception
	 *
	 * @return string
	 */
	public function getHTTPMessage() : string {
		return mjHttpUtil::getHTTPMessageForCode($this->http_code);
	}

	/**
	 * Return additional details specific to actual exception type.
	 *
	 * @return array|null		null or array [ string title, string details, array kvdetails ]
	 */
	public function getTypeDetails() : ?array {
		return [
			'HTTP',
			$this->getHTTPCode().' '.$this->getHTTPMessage(),
			[
				'http_code'=>$this->getHTTPCode(),
				'http_msg'=>$this->getHTTPMessage()
			]
		];
	}

	/**
	 * Return the HTTP methods (alternatively) allowed for request.
	 * - used for code 405 only !
	 *
	 * @return array
	 */
	public function getHTTPAllowMethods() : array {
		return $this->http_allow_methods;
	}

	/**
	 * Sends HTTP response headers to client, depending on current code & extras
	 */
	public function sendHTTPResponseHeaders() {
		mjHttpUtil::SendResponseHeader($this->http_code,$this->getHTTPMessage());
		if( $this->http_code===405 ) header('Allow: '.implode(', ',$this->getHTTPAllowMethods()));
	}

	/**
	 * Sends HTTP response headers for Basic Authentication to client
	 * - will actually work only when HTTP status code is 401
	 */
	public function sendHTTPResponseBasicAuthentication( string $realm ) {
		mjHttpUtil::SendResponseHeader($this->http_code,$this->getHTTPMessage());
		if( $this->http_code===401 ) header( 'WWW-Authenticate: Basic realm="'.$realm.'"' );
	}

}


/**
 * when used in routing script for php built-in web-server:
 * instruct the build-in webserver to use default handler (e.g. deliver static file or execute a php script) error (e.g. CSRF validation failed)
 */
class mjCLIServerUseDefaultHandlerError extends mjError { }


/**
 * Exception signaling that the application config was not loaded yet.
 * - so, some fundamental things are not available yet: like base path, path to logfile, ..
 */
class mjAppConfigNotYetAvailableError extends mjError { }






/**
 * Error about config option which was not set or contains invalid value.
 */
class mjConfigValueError extends mjError {
	public function __construct( string $section=null, string $key, string $value=null, string $hint=null, string $std_fn_or_rel_path=null, Throwable $previous=null ) {
		if( $value===null ) $message = 'config option not set';
		else $message = 'invalid config option value';
		$message .= ': ';
		if( $section!==null ) $message .= '['.$section.'] ';
		$message .= $key;
		$details = [
			'section' => $section,
			'key' => $key,
			'file' => $std_fn_or_rel_path
		];
		if( $value!==null ) $details['value'] = $value;
		if( $hint!==null ) $details['hint'] = $hint;
		parent::__construct($message,$details,$previous);
	}
}


/**
 * Error about config section which does not exist.
 */
class mjConfigSectionMissingError extends mjError {
	public function __construct( string $section, string $hint=null, string $std_fn_or_rel_path=null, Throwable $previous=null ) {
		$message = 'config section not set';
		$message .= ': ';
		$message .= '['.$section.'] ';
		$details = [
			'section' => $section,
			'file' => $std_fn_or_rel_path
		];
		if( $hint!==null ) $details['hint'] = $hint;
		parent::__construct($message,$details,$previous);
	}
}


/**
 * A config file does not exist or is not readable.
 */
class mjConfigFileReadError extends mjError {
	/**
	 * @param string $message
	 * @param string|array|null $std_fn_or_rel_path_or_details_arr
	 * @param Throwable|null $previous
	 */
	public function __construct( string $message, $std_fn_or_rel_path_or_details_arr=null, Throwable $previous=null ) {
		if( is_array($std_fn_or_rel_path_or_details_arr) ) {
			$details = $std_fn_or_rel_path_or_details_arr;
		} else {
			$details = [
				'file' => $std_fn_or_rel_path_or_details_arr
			];
		}
		parent::__construct($message,$details,$previous);
	}
}


/**
 * A config file contains syntax errors.
 */
class mjConfigFileSyntaxError extends mjError {
	/**
	 * @param string $message
	 * @param string|null $std_fn_or_rel_path
	 * @param Throwable|null $previous
	 */
	public function __construct( string $message, string $std_fn_or_rel_path=null, Throwable $previous=null ) {
		$details = [
			'file' => $std_fn_or_rel_path
		];
		parent::__construct($message,$details,$previous);
	}
}



/**
 * Invalid Request or invalid call (e.g. trying to send an URL redirect while in cli mode)
 */
class mjInvalidRequestError extends mjError {
	/**
	 * @param string $message
	 * @param string|array|object|int $details
	 * @param Throwable|null $previous
	 */
	public function __construct( string $message='invalid request', $details='', Throwable $previous=null ) {
		parent::__construct($message,$details,$previous);
	}
}



/**
 * Invalid Request: input parameter validation failed.
 */
class mjRequestInputParameterValidationError extends mjInvalidRequestError {
	/**
	 * @param string $message
	 * @param string|array|object|int $details
	 * @param Throwable|null $previous
	 */
	public function __construct( string $message='invalid request: request parameter validation failed', $details='', Throwable $previous=null ) {
		parent::__construct($message,$details,$previous);
	}
}




/**
 * CSRF Validation error
 */
class mjCSRFValidationError extends mjError { }
