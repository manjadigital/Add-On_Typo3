<?php
declare(strict_types=1);
/**
 * Manja Error Base Types
 *
 * @package   ManjaWeb
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * Exception from manja server with error_code and error_string
 */
class mjServerError extends mjError {

	/**
	 * @var string
	 */
	private $error_code;

	/**
	 * @var string
	 */
	private $error_string;

	/**
	 * constructor
	 *
	 * @param string $error_code
	 * @param string $error_string
	 * @param string|null $formatted_message
	 * @param Throwable|null $previous
	 */
	public function __construct( string $error_code, string $error_string, string $formatted_message=null, Throwable $previous=null ) {
		if( $formatted_message===null ) {
			$format_string = 'Query failed: %2$s (error code=%1$s)';
			// dont localize this error:
			// if( ($app=ManjaAppContext::GetManjaWebApp())!==null ) {
			// 	$app->EnsureTextsLoaded();
			// 	$format_string = $app->GetString('query-failed',false,false,$format_string);
			// }
			$max_error_str_len = 256;
			$error_str_len = strlen($error_string);
			$error_str_4_format = $error_str_len > $max_error_str_len ? substr($error_string,0,$max_error_str_len).' [...]' : $error_string;
			$formatted_message = sprintf($format_string,$error_code,$error_str_4_format);
		}
		$details = $error_code.': '.$error_string;
		$message = $formatted_message ? $formatted_message : $details;
		parent::__construct($message,$details,$previous);
		$this->error_code = $error_code;
		$this->error_string = $error_string;
	}

	/**
	 * Return Manja Server Error Code
	 *
	 * @return string
	 */
	public function getErrorCode() : string {
		return $this->error_code;
	}

	/**
	 * Return Manja Server Error String
	 *
	 * @return string
	 */
	public function getErrorString() : string {
		return $this->error_string;
	}

	/**
	 * Return additional details specific to actual exception type.
	 *
	 * @return array|null		null or array [ string title, string details, array kvdetails ]
	 */
	public function getTypeDetails() : ?array {
		return [
			'Manja Server',
			$this->getErrorCode().' '.$this->getErrorString(),
			[
				'error_code' => $this->getErrorCode(),
				'error_string' => $this->getErrorString(),
			]
		];
	}

}

