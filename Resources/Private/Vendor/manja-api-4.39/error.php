<?php
declare(strict_types=1);
/**
 * Manja error base type
 *
 * @package   ManjaWeb
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * Exception with additional details
 */
class mjError extends \Exception {

	/**
	 * @var string
	 */
	private $details = '';

	/**
	 * @var bool
	 */
	private $details_are_json_encoded = false;

	/**
	 * constructor
	 *
	 * @param string $message
	 * @param string|array|object|int $details
	 * @param Throwable|null $previous
	 */
	public function __construct( string $message='', $details='', \Throwable $previous=null ) {
		$code = 0;
		if( is_int($details) ) {
			$code = $details;
			$details = '';
		}
		parent::__construct($message,$code,$previous);

		if( !is_array($details) ) {
			$details = [ 'details'=>$details ];
		}
		if( $previous===null || !( $previous instanceof \mjError ) ) {
			// add some common info about the cli/http request (but dont repeat it when $previous already contains it)
			if( PHP_SAPI==='cli' ) {
				$details['_cli_request'] = 	[
					'argv'		=> $_SERVER['argv'] ?? null,
				];
			} else {
				$details['_http_request'] = array_merge(
					\mjHttpUtil::GetRequestStateDetails(),
					[
						'url_root'			=> \mjHttpUtil::GetURLRoot(),
						'server_protocol'	=> mj_arr_val($_SERVER,'SERVER_PROTOCOL'),
						'request_method'	=> mj_arr_val($_SERVER,'REQUEST_METHOD'),
						'request_uri'		=> mj_arr_val($_SERVER,'REQUEST_URI'),
						'http_referer'		=> mj_arr_val($_SERVER,'HTTP_REFERER',null),
						'http_user_agent'	=> mj_arr_val($_SERVER,'HTTP_USER_AGENT'),
					]
				);
			}
			$details['_app_state'] = [
				'cwd'			=> getcwd(),
				// 'base_path'		=> \ManjaAppContext::GetAbsBasePath(),
				// 'public_path'	=> \ManjaAppContext::GetAbsPublicPath(),
			];
			// if( ($app=\ManjaAppContext::GetManjaWebApp())!==null ) $details['_app_state']['base_url'] = $app->GetBaseURL();
		}
		$details = json_encode($details,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);//mj_json_encode($details);
		$this->details_are_json_encoded = true;
		$this->details = (string)$details;
	}

	/**
	 * Return additional details of this exception
	 *
	 * @return string
	 */
	public function getDetails() : string {
		return $this->details;
	}

	/**
	 * Return additional details, decoded to its source type (e.g. string, array, int, null)
	 *
	 * @return mixed
	 */
	public function getDetailsDecoded() {
		return $this->details_are_json_encoded ? json_decode($this->details,true) : $this->details;
	}

	/**
	 * Return additional details specific to actual exception type.
	 *
	 * @return array|null		null or array [ string title, string details, array kvdetails ]
	 */
	public function getTypeDetails() : ?array {
		return null;
	}


	/**
	 * Modify this exception, so that it will include details from a $next exception in chain.
	 *
	 * @param mjError $next		next exception in chain
	 *
	 * @return mjError 			this
	 */
	public function unwrapToNext( \mjError $next ) : \mjError {
		$prev_details = $this->getDetailsDecoded();
		if( !is_array($prev_details) ) $prev_details = [ 'details'=>$prev_details ];

		$stack_frames_seen = [];
		self::getThrowableCustomTraceAsString_0($this,$stack_frames_seen);
		$next_trace = self::getThrowableCustomTraceAsString_0($next,$stack_frames_seen,0,true);

		$new_details = array_merge(
			$prev_details,
			[
				// '_unwrapped_details' => $next_details,
				'_unwrapped_type_details' => $next->getTypeDetails(),
				'_unwrapped_stack' => explode("\n",$next_trace),
			]
		);

		$this->details_are_json_encoded = true;
		$this->details = (string)json_encode($new_details,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		return $this;
	}

	/**
	 * String representation of exception
	 *
	 * @return string
	 */
	public function __toString() : string {
		$stack_frames_seen = [];
		return self::getThrowableToString($this,$stack_frames_seen);
	}

	/**
	 * replacement for getTraceAsString() with some formatting enhancements
	 *
	 * @return string
	 */
	public function getCustomTraceAsString() : string {
		$stack_frames_seen = [];
		return self::getThrowableCustomTraceAsString_0($this,$stack_frames_seen);
	}

	/**
	 * String representation of exception (supports specialties of mjError, but supports any Throwable as well)
	 *
	 * @param Throwable $from
	 * @param array     $stack_frames_seen
	 *
	 * @return string
	 */
	public static function getThrowableToString( Throwable $from, array &$stack_frames_seen ) : string {
		$str_arr = [];
		while( $from ) {
			$indent_str = '  ';
			$str = get_class($from).': '.rtrim($from->getMessage())."\n";
			if( ($code=$from->getCode())!==null && $code!='0' ) $str .= '>Code: '.$code."\n";

			if( $from instanceof \mjError ) {
				if( ($type_details=$from->getTypeDetails())!==null ) $str .= '>'.$type_details[0].':'."\n" . $indent_str.str_replace("\n","\n".$indent_str,rtrim($type_details[1]))."\n";
				if( ($details=$from->getDetails())!=='' && $details!==null ) $str .= '>Details:'."\n" . $indent_str.str_replace("\n","\n".$indent_str,rtrim($details))."\n";
			}

			$str .= '>Stacktrace:'."\n" . $indent_str.str_replace("\n","\n".$indent_str,self::getThrowableCustomTraceAsString_0($from,$stack_frames_seen))."\n";
			$str = rtrim($str,"\r\n\t ")."\n"."\n";

			if( isset($str_arr[0]) ) {
				$nprev = count($str_arr);
				$firstAndOtherLines = explode("\n",$str,2);
				$str = '>Previous('.$nprev.'): '.$firstAndOtherLines[0]."\n"
					 .  $indent_str.str_replace("\n","\n".$indent_str,$firstAndOtherLines[1]);
			}
			$str_arr[] = $str;

			if( ($from=method_exists($from,'getPrevious')?$from->getPrevious():null)===null ) break;
		}
		return implode('',$str_arr)."\n";
	}

	private static function getStackFrameArgString( $arg ) : string {
		if( is_null($arg) ) return 'null';
		else if( is_array($arg) ) return 'Array['.sizeof($arg).']';
		else if( is_object($arg) ) return 'Object('.get_class($arg).')';
		else if( is_bool($arg) ) return $arg ? 'true' : 'false';
		else if( is_resource($arg) ) return 'Ressource:#'.$arg;
		else if( is_string($arg) ) return '"'.str_replace("\r",'',addcslashes(strlen($arg)>256?substr($arg,0,256).'...':$arg,"\\\"'\n\t")).'"';
		$s = var_export($arg,true);
		return strlen($s)>256?substr($s,0,256).'...':$s;
	}

	private static function getStackFrameInfo( int $idx, array $f ) : array {
		$fr['idx'] = $idx;
		$fr['file'] = isset($f['file']) ? $f['file'] : '[internal]';
		$fr['line'] = isset($f['line']) ? $f['line'] : '';

		if( isset($f['codeline']) ) $fr['codeline'] = trim($f['codeline']);

		$r = '';
		if( isset($f['class']) ) $r .= $f['class'];
		if( isset($f['type']) ) $r .= $f['type'];		// '->' or '::' or ''
		if( isset($f['function']) ) $r .= $f['function'];
		$fr['ctf'] = $r;

		$r = '';
		if( isset($f['args']) ) {
			foreach( $f['args'] as $ai=>$arg ) {
				if( $ai!==0 ) $r .= ', ';
				$arg_str = self::getStackFrameArgString($arg);
				$r .= $arg_str;
			}
		}
		$fr['args'] = $r;
		return $fr;
	}



	private static function getLastValidParentTraceFrameIndex( array $stack_frames_seen, string $skey ) {
		$started = false;
		$lastFrIdx = 0;
		foreach( $stack_frames_seen as $iter_seen_skey=>$iter_seen_fr_idx ) {
			if( $iter_seen_skey===$skey ) {
				$started = true;
				$lastFrIdx = $iter_seen_fr_idx;
			} else if( $started ) {
				if( $iter_seen_fr_idx < $lastFrIdx ) {
					// this idx doesnt seem to come from direct parent...
					// but from another exception in chain ...
					// stop
					return $lastFrIdx;
				}
				$lastFrIdx = $iter_seen_fr_idx;
			}
		}
		return $lastFrIdx;
	}

	public static function getThrowableCustomTraceAsString( Throwable $from, array &$stack_frames_seen=null, int $skip_frames=0 ) : string {
		if( $stack_frames_seen===null ) $stack_frames_seen = [];
		return self::getThrowableCustomTraceAsString_0($from,$stack_frames_seen,$skip_frames);
	}

	private static function getThrowableCustomTraceAsString_0( Throwable $from, array &$stack_frames_seen, int $skip_frames=0, bool $stop_at_first_seen=false ) : string {
		$ds = DIRECTORY_SEPARATOR;
		$paths_base = dirname(__DIR__,2);
		$paths_base = rtrim($paths_base,$ds).$ds;

		$compact_screen_width = 'dev';// true;//false;
		$vars = [];
		if( $compact_screen_width ) $vars[] = '$MJW='.str_replace('\\','/',rtrim($paths_base,$ds));

		$trace_arr = $from->getTrace();

		$frame0 = array('file'=>$from->getFile(),'line'=>$from->getLine());
		if( ($abs_fn=$frame0['file'])!=='' && isset($frame0['line']) ) {
			$code_lines = @file($abs_fn);
			$line_idx = (int)$frame0['line'] - 1;
			if( is_array($code_lines) && $line_idx>=0 && $line_idx<count($code_lines) ) $frame0['codeline'] = $code_lines[$line_idx];
		}
		array_unshift($trace_arr,$frame0);

		while( --$skip_frames >= 0 ) array_shift($trace_arr);

		$frames = [];
		$col_widths = [];
		foreach( $trace_arr as $fi=>$frame ) {
			$fr = self::getStackFrameInfo($fi+1,$frame);
			if( $compact_screen_width ) {
				if( $compact_screen_width==='dev' ) $fr['file'] = str_replace($paths_base,'web'.$ds,$fr['file']);
				else $fr['file'] = str_replace($paths_base,'$MJW'.$ds,$fr['file']);
				$fr['file'] = str_replace('\\','/',$fr['file']);
			}
			foreach( $fr as $col_name=>$col_data ) {
				if( !isset($col_widths[$col_name]) ) $col_widths[$col_name] = 0;
				if( ($col_wid=strlen((string)$col_data)) > $col_widths[$col_name] ) $col_widths[$col_name] = $col_wid;
			}
			$frames[] = $fr;
		}

		$str = '';
		foreach( $frames as $fi=>$fr ) {
			$fr_key = $fr['file'].':'.$fr['line'];

			$skey = is_array($stack_frames_seen) && $fr['idx']>1
						? $fr_key
						: null;
			if( $skey!==null && isset($stack_frames_seen[$skey]) ) {
				// $remainingFramesCount = count($frames)+1;
				$remainingFramesCount = count($frames) - $fi;
				// $str .= sprintf(' ... %d more',$remainingFramesCount)."\n";
				$firstParentTraceFrameIndex = $stack_frames_seen[$skey]+1;
				// $lastParentTraceFrameIndex = $firstParentTraceFrameIndex;
				$lastParentTraceFrameIndex = self::getLastValidParentTraceFrameIndex($stack_frames_seen,$skey) + 1;
				$str .= sprintf(' ... %d more: @#%d-%d',$remainingFramesCount,$firstParentTraceFrameIndex,$lastParentTraceFrameIndex);//."\n";
				// if( $prev_trace_item_id_pfx!==null ) {
				// $str .= sprintf(' @# %d', $parentTraceFrameIndex)."\n";
				#$str .= '<a href="#'.$prev_trace_item_id_pfx.($parentTraceFrameIndex).'"># '.$parentTraceFrameIndex.'</a>'."\n";
				// }
//				break;
			}

			$line = [
				sprintf( '#% '.(max(2,$col_widths['idx'])??2).'d', $fr['idx'] ),
				sprintf( '%-'.clip((2+($col_widths['file']??10)+($col_widths['line']??5)),45,130).'s', $fr_key )
			];

			if( isset($fr['codeline']) ) $line[] = $fr['codeline'];
			else $line[] = sprintf( '%s( %s )', $fr['ctf'], $fr['args'] );

			$str .= implode(' ',$line)."\n";

			if( $skey!==null ) $stack_frames_seen[$skey] = $fi;

			if( $stop_at_first_seen ) {
				if( $skey!==null && isset($stack_frames_seen[$skey]) ) {
					return trim($str);
				}
			}

		}

		if( isset($vars[0]) ) $str .= '('.implode(', ',$vars).')';
		return $str;
	}

}
