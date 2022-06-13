<?php
declare(strict_types=1);
/**
 * Diverse Utility functions
 *
 * @package   ManjaWeb
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * wrapper for access to array items, returning a default value, if item not set on array
 *
 * example: mj_arr_val($array,'key','default')
 *
 * for issues, see https://stackoverflow.com/questions/9555758/default-array-values-if-key-doesnt-exist
 *
 * @param array $arr
 * @param string|int $k
 * @param mixed $default
 *
 * @return mixed
 */
function mj_arr_val( array $arr, $k, $default='' ) {
	return isset($arr[$k]) ? $arr[$k] : $default;
}

/**
 * like mj_arr_val(), but for two-dimensional arrays
 *
 * example: mj_arr2_val($array,'section','key','default')
 *
 * @param array $arr
 * @param string|int $k1
 * @param string|int $k2
 * @param mixed $default
 *
 * @return mixed
 */
function mj_arr2_val( array $arr, $k1, $k2, $default='' ) {
	return isset($arr[$k1]) && isset($arr[$k1][$k2]) ? $arr[$k1][$k2] : $default;
}

/**
 * mj_arr_val() for bool
 *
 * example: mj_arr_bval($array,'key',true)
 *
 * @param array $arr
 * @param string|int $k
 * @param bool $default
 *
 * @return bool
 */
function mj_arr_bval( array $arr, $k, bool $default=false ) : bool {
	$v = mj_arr_val($arr,$k,$default?'1':'');
	return $v==='1' || $v==='yes' || $v==='true' || $v===1 || $v===true;
}

/**
 * mj_arr2_val() for bool
 *
 * example: mj_arr2_bval($array,'section','key',true)
 *
 * @param array $arr
 * @param string|int $k1
 * @param string|int $k2
 * @param bool $default
 *
 * @return bool
 */
function mj_arr2_bval( array $arr, $k1, $k2, bool $default=false ) : bool {
	$v = mj_arr2_val($arr,$k1,$k2,$default?'1':'');
	return $v==='1' || $v==='yes' || $v==='true' || $v===1 || $v===true;
}

/**
 * mj_arr_val() for integers
 *
 * @param array $arr
 * @param string|int $k
 * @param int $default
 *
 * @return int
 */
function mj_arr_ival( array $arr, $k, int $default=0 ) : int {
	return isset($arr[$k]) ? intval($arr[$k],10) : $default;
}

/**
 * mj_arr2_val() for integers
 *
 * @param array $arr
 * @param string|int $k1
 * @param string|int $k2
 * @param int $default
 *
 * @return int
 */
function mj_arr2_ival( array $arr, $k1, $k2, int $default=0 ) : int {
	return isset($arr[$k1]) && isset($arr[$k1][$k2]) ? intval($arr[$k1][$k2],10) : $default;
}

/**
 * mj_arr_val() for strings, also adds prefix and suffix if item is set on array
 *
 * @param array $arr
 * @param string|int $k
 * @param string $vpfx
 * @param string $vsfx
 * @param mixed $default
 *
 * @return mixed
 */
function mj_arr_sval( array $arr, $k, string $vpfx='', string $vsfx='', $default='' ) {
	return isset($arr[$k]) && $arr[$k]!=='' ? $vpfx.$arr[$k].$vsfx : $default;
}

/**
 * mj_arr_val() for strings, also adds prefix if item is set on array
 *
 * @param array $arr
 * @param string|int $k
 * @param string $vpfx
 * @param mixed $default
 *
 * @return mixed
 */
function mj_arr_sval1( array $arr, $k, string $vpfx='', $default='' ) {
	return isset($arr[$k]) && $arr[$k]!=='' ? $vpfx.$arr[$k] : $default;
}

/**
 * mj_arr_val() for strings, but also trims value and returns default also if value is empty string
 *
 * @param array $arr
 * @param string|int $k
 * @param mixed $default
 *
 * @return mixed
 */
function mj_arr_sval2( array $arr, $k, $default='' ) {
	return isset($arr[$k])
			? ( ($v=trim($arr[$k]))!=='' ? $v : $default )
			: $default;
}

/**
 * mj_arr2_val() for strings, but also trims value and returns default also if value is empty string
 *
 * @param array $arr
 * @param string|int $k1
 * @param string|int $k2
 * @param mixed $default
 *
 * @return mixed
 */
function mj_arr2_sval2( array $arr, $k1, $k2, $default='' ) {
	return isset($arr[$k1]) && isset($arr[$k1][$k2])
			? ( ($v=trim($arr[$k1][$k2]))!=='' ? $v : $default )
			: $default;
}


if( !function_exists('mb_ucfirst') ) {
	/**
	 * Get string with first character in upper case
	 *
	 * @param string $string
	 * @param string $encoding
	 * @return string
	 */
	function mb_ucfirst( string $string, string $encoding='UTF-8' ) : string {
		$firstChar = mb_substr($string,0,1,$encoding);
		$then = mb_substr($string,1,mb_strlen($string,$encoding)-1,$encoding);
		return mb_strtoupper($firstChar,$encoding).$then;
	}
}




/**
 * Removes comments from JSON string - use this only on trusted source strings!
 * @deprecated This breaks too easily (e.g. an url in string field may result in corrupt result)!
 * @param string $json_str
 *
 * @return string
 */
// function mj_strip_json_comments( string $json_str ) : string {
// 	return preg_replace('![ \t]*//.*[ \t]*[\r\n]!','',$json_str);
// }


/**
 * like json_decode(), bit with common flags used throughout framework,
 * and
 */
function mj_json_decode( string $v, ?bool $assoc=null, int $depth=512, int $options=0 ) {
	$r = json_decode( $v, $assoc, $depth, $options );
	// TODO with php 7.3: use JSON_THROW_ON_ERROR );
	if( ($c=json_last_error())!==JSON_ERROR_NONE ) {
		throw new \mjError('JSON decoding failed: '.$c.' - '.json_last_error_msg(),[$c,$v]);
	}
	return $r;
}


/**
 * like json_encode(), but with common flags used throughout framework
 *
 * @param mixed $v
 * @param bool $force_object
 * @param bool $pretty_print
 *
 * @return string|false
 */
function mj_json_encode( $v, bool $force_object=false, bool $pretty_print=false ) {
	return json_encode( $v, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|($force_object?JSON_FORCE_OBJECT:0)|($pretty_print?JSON_PRETTY_PRINT:0) );
}


if( !function_exists('array_is_list') ) {
	function array_is_list( array $a ) {
		return $a===[] || ( array_keys($a)===range(0,count($a)-1) );
	}
}

// /**
//  * determine whether an array is associative or sequential
//  */
// function mj_array_is_associative( array $arr ) : bool {
// 	return !array_is_list($arr);
// }

/**
 * determine whether an array has numeric keys only (not necessarily sequential)
 */
function mj_array_has_numeric_keys_only( array $arr ) : bool {
	foreach( $arr as $k=>$v ) {
		if( !is_numeric($k) ) return false;
	}
	return true;
}


/**
 * Convert from associative array to an array of items with keys key and value each.
 *
 * @param array $assoc
 * @param string $kn	name of key property
 * @param string $vn	name of value property
 *
 * @return array
 */
function mj_assoc_to_kv_items_list( array $assoc, string $kn='key', string $vn='value' ) : array {
	return array_map(
		function( $k, $v ) use($kn,$vn) {
			return [ $kn=>$k, $vn=>$v ];
		},
		array_keys($assoc),
		array_values($assoc)
	);
}


/**
 * like mj_json_encode(), but will pretty-format the first nesting level
 *
 * @param mixed $v
 * @param string $itemIndent
 * @param int $encode_options   defaults to JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
 *
 * @return string|false
 */
function mj_json_encode_pretty_flat( $v, string $itemIndent=' ', int $encode_options=0 ) {
	$encode_options |= JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
	if( is_object($v) && method_exists($v,'toArray') ) {
		$v = $v->toArray();
		// continue with as-if it'd be plain array (what it is, actually)
	}

	if( is_array($v) ) {
		if( !array_is_list($v) || ($encode_options&JSON_FORCE_OBJECT) ) {
			$r = [];
			foreach( $v as $kn=>$kv ) {
				if( is_object($kv) ) {
					if( $kv instanceof \Closure ) $kv = '(Closure)';
					else $kv = (string)$kv;
				}
				$kv_str = json_encode($kv,$encode_options);
				$r[] = '"'.addcslashes((string)$kn,"\\\"\n\t\r/").'"'.':'.$kv_str;
			}
			return "{".(isset($r[0])?"\n".$itemIndent.implode(",\n".$itemIndent,$r):'')."\n}\n";
		} else {
			$r = [];
			foreach( $v as $kv ) {
				if( is_object($kv) ) {
					if( $kv instanceof \Closure ) $kv = '(Closure)';
					else $kv = (string)$kv;
				}
				$r[] = json_encode($kv,$encode_options);
			}
			return "[".(isset($r[0])?"\n".$itemIndent.implode(",\n".$itemIndent,$r):'')."\n]\n";
		}
	}
	return json_encode($v,$encode_options);
}


/**
 * escape potentially unsafe characters of a string for output to CSV
 *
 * @param string $value
 * @return string
 */
function mj_csv_escape( string $value ) : string {
	$triggers = array( '=', '+', '-', '@', '|' );//, '%' );
	if( in_array( mb_substr(trim($value),0,1), $triggers, true ) ) return "'".$value."'";
	return $value;
}

/**
 * transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
 *
 * @param string $v
 * @param int $multiplier
 * @return int|string
 */
function ini_size_to_num( string $v, int $multiplier=1024 ) {
	$v = trim($v);
	$num = substr($v,0,-1);
	switch( strtoupper(substr($v,-1)) ){
	case 'P': $num *= $multiplier; // intentional fall-through...
	case 'T': $num *= $multiplier; // intentional fall-through...
	case 'G': $num *= $multiplier; // intentional fall-through...
	case 'M': $num *= $multiplier; // intentional fall-through...
	case 'K': $num *= $multiplier;
		break;
	case '0': case '1': case '2': case '3': case '4':
	case '5': case '6': case '7': case '8': case '9':
		return $v; // no suffix
	}
	return $num;
}

/**
 * transform number into a human-readable representation of bytes, e.g. __kB, or __MB
 *
 * @param int|string $size
 *
 * @return string
 */
function mj_formatted_size( $size ) : string {
	$size = intval($size,10);
	$unit = 'B';
	if( $size > 4096 ) {
		$size = intval($size/1024,10);
		$unit = 'kB';
		if( $size > 4096 ) {
			$size = intval($size/1024,10);
			$unit = 'MB';
		}
	}
	return $size.$unit;
}


/**
 * Determine whether the path is absolute or not.
 *
 * - Accepts UNIX paths everywhere.
 * - Accepts windows paths on windows only (detected by backslash as DIRECTORY_SEPARATOR).
 *
 * @param string $path
 * @return bool
 */
function mj_is_absolute_path( string $path ) : bool {
	if( isset($path[0]) && $path[0]==='/' ) return true;
	if( DIRECTORY_SEPARATOR==='\\' ) {
		// e.g. C:\foo\bar; C:/foo/bar
		if( isset($path[1]) && $path[1]===':' ) return true;
	}
	return false;
}


/**
 * get array of segments from path,
 * will normalize separators.
 *
 * @param string $str			path - may or may not be absolute - it doesnt matter here
 *
 * @return array
 */
function mj_pathseg_clean_arr( string $str ) : array {
	if( DIRECTORY_SEPARATOR==='\\' ) $str = str_replace('\\','/',$str);		// on windows: convert \ to /
	return (array)explode('/',trim($str,'/'));
}

/**
 * get array of segments from path,
 * expects posix separators only.
 *
 * @param string $str			path - may or may not be absolute - it doesnt matter here
 *
 * @return array
 */
function mj_pathseg_clean_arr_safe( string $str ) : array {
	return (array)explode('/',trim($str,'/'));
}

/**
 * rewrite a directory path so that it becomes relative to another path
 *
 * @see mj_pathseg_make_relative_to
 *
 * @param array $trg_arr
 * @param array $src_arr
 *
 * @return string
 */
function mj_pathsegarr_make_relative_to( array $trg_arr, array $src_arr ) : string {
	// remove directories that match both src and trg   -> e.g.  trg=/foo/bar/baz, src=/foo/bar/xxx ->  trg=baz, src=xxx
	for( $i=0; isset($trg_arr[$i]) && isset($src_arr[$i]) && $trg_arr[$i]===$src_arr[$i]; ++$i ) ;
	// prepend "../" tokens to get from $trg to $src directory
	return rtrim( str_repeat('../',count($trg_arr)-$i) . implode('/',array_slice($src_arr,$i)), '/' );
}

/**
 * rewrite a directory path so that it becomes relative to another path
 *
 * example:
 *		echo ' 1: '.mj_relative_path('cache','skin/d/f/gfx/img.png')."\n";
 *		echo ' 2: '.mj_relative_path('/cache','/skin/d/f/gfx/img.png')."\n";
 *		echo ' 3: '.mj_relative_path('cache','/skin/d/f/gfx/img.png')."\n";
 *		echo ' 4: '.mj_relative_path('cache','//skin/d/f/gfx/img.png')."\n";
 *		echo ' 5: '.mj_relative_path('/cache','skin/d/f/gfx/img.png')."\n";
 *		echo ' 6: '.mj_relative_path('//cache','skin/d/f/gfx/img.png')."\n";
 *		echo ' 7: '.mj_relative_path('/cache','//skin/d/f/gfx/img.png')."\n";
 *		echo ' 8: '.mj_relative_path('//cache','/skin/d/f/gfx/img.png')."\n";
 *		echo ' 9: '.mj_relative_path('cache','../skin/d/f/gfx/img.png')."\n";
 *		echo '10: '.mj_relative_path('cache','../../skin/d/f/gfx/img.png')."\n";
 *		echo '11: '.mj_relative_path('../cache','skin/d/f/gfx/img.png')."\n";
 *		echo '12: '.mj_relative_path('../../cache','skin/d/f/gfx/img.png')."\n";
 *		echo '13: '.mj_relative_path('../cache','../skin/d/f/gfx/img.png')."\n";
 *		echo '14: '.mj_relative_path('../cache','../../skin/d/f/gfx/img.png')."\n";
 *		echo '15: '.mj_relative_path('../../cache','../../skin/d/f/gfx/img.png')."\n";
 *		echo '16: '.mj_relative_path('../../cache/','../../skin/d/f/gfx/img.png')."\n";
 *		echo '17: '.mj_relative_path('skin/d/f/','cache/gfx/img.png')."\n";
 *
 * output:
 *		 1: ../skin/d/f/gfx/img.png
 *		 2: ../skin/d/f/gfx/img.png
 *		 3: ../skin/d/f/gfx/img.png
 *		 4: ../skin/d/f/gfx/img.png
 *		 5: ../skin/d/f/gfx/img.png
 *		 6: ../skin/d/f/gfx/img.png
 *		 7: ../skin/d/f/gfx/img.png
 *		 8: ../skin/d/f/gfx/img.png
 *		 9: ../../skin/d/f/gfx/img.png
 *		10: ../../../skin/d/f/gfx/img.png
 *		11: ../../skin/d/f/gfx/img.png
 *		12: ../../../skin/d/f/gfx/img.png
 *		13: ../skin/d/f/gfx/img.png
 *		14: ../../skin/d/f/gfx/img.png
 *		15: ../skin/d/f/gfx/img.png
 *		16: ../skin/d/f/gfx/img.png
 *		17: ../../../cache/gfx/img.png
 *
 * @param string $trg_dir_path		target path, separated with slashes, absolute (actually there is a common root implied, same for both src_path and trg_path), points to a directory - not a file
 * @param string $src_path			source path, separated with slashes, absolute (actually there is a common root implied, same for both src_path and trg_path), may point to a directory or a file
 *
 * @return string
 */
function mj_pathseg_make_relative_to( string $trg_dir_path, string $src_path ) : string {
	return mj_pathsegarr_make_relative_to(mj_pathseg_clean_arr($trg_dir_path),mj_pathseg_clean_arr($src_path));
}

/**
 * Concatenate two paths,
 * - canonicalize references like '.' and '..',
 * - does not remove leading '..'.
 *
 * @param array $base_dir_arr		base path segments
 * @param array $append_arr			path segments to append
 *
 * @return array					resulting path segments
 */
function mj_pathsegarr_concat_arr( array $base_dir_arr, array $append_arr ) : array {
	$res_arr = [];
	$res_len = 0;
	foreach( array_merge($base_dir_arr,$append_arr) as $segment ) {
		switch( $segment ) {
		case '..':
			if( $res_len===0 || $res_arr[$res_len-1]==='..' ) {
				// result empty or ends on '../' ? then push '../'
				$res_arr[$res_len++] = '..';
			} else {
				// remove last segment from result
				array_pop($res_arr);
				$res_len--;
			}
			break;
		case '.':
			// skip segment
			break;
		default:
			// push onto result
			$res_arr[$res_len++] = $segment;
		}
	}
	return $res_arr;
}

/**
 * Concatenate two paths,
 * - canonicalize references like '.' and '..',
 * - does not remove leading '..'.
 *
 * @param array $base_dir_arr		base path segments
 * @param array $append_arr			path segments to append
 *
 * @return string
 */
function mj_pathsegarr_concat( array $base_dir_arr, array $append_arr ) : string {
	return implode('/',mj_pathsegarr_concat_arr($base_dir_arr,$append_arr));
}

// /**
//  * Concatenate two paths,
//  * - canonicalize references like '.' and '..',
//  * - does not remove leading '..'.
//  *
//  * @param string $base_dir_path		base path, separated with slashes, relative or absolute doesnt matter here, points to a directory - not a file
//  * @param string $append_path		path to append, separated with slashes, always relative, may point to a directory or a file
//  *
//  * @return string
//  */
// function mj_pathseg_concat( string $base_dir_path, string $append_path ) : string {
// 	$abs_prefix = '';
// 	if( mj_is_absolute_path($base_dir_path) ) {
// 		$abs_prefix = '/';
// 		if( DIRECTORY_SEPARATOR==='\\' && isset($base_dir_path[1]) && $base_dir_path[1]===':' ) {
// 			// e.g. C:\foo\bar; C:/foo/bar
// 			$abs_prefix = substr($base_dir_path,0,2).'/';
// 			$base_dir_path = substr($base_dir_path,2);
// 		}
// 	}
// 	$concat_result = mj_pathsegarr_concat(mj_pathseg_clean_arr($base_dir_path),mj_pathseg_clean_arr($append_path));
// 	return $abs_prefix.$concat_result;
// }


/**
 * Extract common root of two paths. Also returns both paths relative to that common root.
 *
 * @param array $a
 * @param array $b
 *
 * @return array					array( common_root, path a relative to common root, path b relative to common root )
 */
function mj_pathsegarr_extract_root( array $a, array $b ) : array {
	for( $i=0; isset($a[$i]) && isset($b[$i]) && $a[$i]===$b[$i]; ++$i ) ;
	return [ array_slice($a,0,$i), array_slice($a,$i), array_slice($b,$i) ];
}



/**
 * like PHP realpath(), but will always return path with forward-slashes
 *
 * @param string $path
 *
 * @return string|false
 */
function mj_realpath( string $path ) {
	if( ($rpath=realpath($path))===false ) return false;
	return DIRECTORY_SEPARATOR==='\\' ? (string)str_replace('\\','/',$rpath) : $rpath;
}

/**
 * Normalize path - similar to mj_realpath(),
 * but does not require that path or its directories actually exist.
 *
 * @param string $path_components,...
 *
 * @return string
 */
function mj_normalize_path( string ...$path_components ) : string {
	$path = implode('/',$path_components);
	if( DIRECTORY_SEPARATOR==='\\' ) $path = (string)str_replace('\\','/',$path);
	$root = $path[0]==='/' ? '/' : '';
	$segments = explode('/',trim($path,'/'));
	$ret = array();
	foreach( $segments as $segment ) {
		if( $segment==='.' || $segment==='' ) continue;
		if( $segment==='..' ) array_pop($ret);
		else array_push($ret,$segment);
	}
	return $root.implode('/',$ret);
}

/**
 * Like filemtime(string $path), but does not generate E_WARNING when file does not exist.
 * This should be used to test for existence of files when the modification timestamp is
 * required. A combination of file_exists($path) and filemtime($path) is prone to race conditions.
 *
 * @param string $path path to file
 *
 * @return int|false
 */
function mj_file_mtime( string $path ) {
	return @filemtime($path);
}


/**
 * @param int $compare_mtime				timestamp for comparison  - will ignore all files older or equal to that one,
 * @param $dir_path_pfx						directory path; will be prepended to items in $file_paths; may be empty, but if set it must contain a trailing slash.
 * @param array $file_paths					array of paths to check
 *
 * @return string|null						the path of the first of the files that was modified after $compare_mtime, or null if no file modified.
 * 											NOTE: returns the path as given in $file_paths (without $dir_path prepended).
 */
function mj_find_file_of_list_modified_after( int $compare_mtime, string $dir_path_pfx, array $file_paths ) : ?string {
	foreach( $file_paths as $fn_path ) {
		if( ($check_mtime=@filemtime($dir_path_pfx.$fn_path))===false || $check_mtime>=$compare_mtime ) return $fn_path;
	}
	return null;
}

/**
 * Like mj_find_file_of_list_modified_after(), but with $cache.
 *
 * @param int $compare_mtime				timestamp for comparison  - will ignore all files older or equal to that one,
 * @param $dir_path_pfx						directory path; will be prepended to items in $file_paths; may be empty, but if set it must contain a trailing slash.
 * @param array $file_paths					array of paths to check
 * @param array $cache						contains modified times of files previously queried. Key is the relative file path (exactly as contained in $file_paths).
 *
 * @return string|null						the path of the first of the files that was modified after $compare_mtime, or null if no file modified.
 * 											NOTE: returns the path as given in $file_paths (without $dir_path prepended).
 */
function mj_find_file_of_list_modified_after_cached( int $compare_mtime, string $dir_path_pfx, array $file_paths, array &$cache ) : ?string {
	foreach( $file_paths as $fn_path ) {
		if( isset($cache[$fn_path]) ) {
			$check_mtime = $cache[$fn_path];
		} else {
			if( ($check_mtime=@filemtime($dir_path_pfx.$fn_path))===false ) return $fn_path;
			$cache[$fn_path] = $check_mtime;
		}
		if( $check_mtime>=$compare_mtime ) return $fn_path;
	}
	return null;
}

/**
 * Scan in directory for files modified after given mtime.
 *
 * @param int $compare_mtime				timestamp for comparison  - will ignore all files older or equal to that one,
 * @param $dir_path_pfx						directory path to search in.
 * @return string|null						the path of the first of the files that was modified after $compare_mtime, or null if no file modified.
 * 											NOTE: returns the path relative to $dir_path_pfx.
 */
function mj_find_file_in_dir_modified_after( int $compare_mtime, string $dir_path_pfx ) {
	$dir_iterator = new RecursiveDirectoryIterator($dir_path_pfx);//,RecursiveDirectoryIterator::SKIP_DOTS|RecursiveDirectoryIterator::KEY_AS_PATHNAME|RecursiveDirectoryIterator::CURRENT_AS_FILEINFO);
	$iter = new RecursiveIteratorIterator($dir_iterator,/*RecursiveIteratorIterator::SELF_FIRST|*/RecursiveIteratorIterator::LEAVES_ONLY);
	foreach( $iter as $file ) {
		assert( $file instanceof \SplFileInfo );
		if( !$file->isFile() ) continue;
		// @phan-suppress-next-line PhanUndeclaredMethod
		if( ($check_mtime=$file->getMTime())===false || $check_mtime>=$compare_mtime ) return $iter->getSubPathName();
	}
	return null;
}

/**
 * compare given timestamp against modified times of a list of files.
 * - return true only if $compare_mtime is newer than modified-time of any file in list,
 * - return false if some file is newer,
 * - return false if some file does not exist.
 *
 * @param int $compare_mtime		timestamp as returned by time()
 * @param string $dir_path_pfx		path to prefix to items in file list (may be empty string)
 * @param array $file_paths			list of paths (relative or absolute)
 *
 * @return bool
 */
function mj_is_newer_than_file_list_mod_times( int $compare_mtime, string $dir_path_pfx, array $file_paths ) : bool {
	return mj_find_file_of_list_modified_after($compare_mtime,$dir_path_pfx,$file_paths) === null;
}

/**
 * Like mj_is_newer_than_file_list_mod_times(), but with $cache.
 *
 * @param int $compare_mtime		timestamp as returned by time()
 * @param string $dir_path_pfx		path to prefix to items in file list (may be empty string)
 * @param array $file_paths			list of paths (relative or absolute)
 * @param array $cache				contains modified times of files previously queried. Key is the relative file path (exactly as contained in $file_paths).
 *
 * @return bool
 */
function mj_is_newer_than_file_list_mod_times_cached( int $compare_mtime, string $dir_path_pfx, array $file_paths, array &$cache ) : bool {
	return mj_find_file_of_list_modified_after_cached($compare_mtime,$dir_path_pfx,$file_paths,$cache) === null;
}

/**
 * like mj_find_file_of_list_modified_after(), but returns bool
 */
function mj_is_older_than_file_list_mod_times( int $compare_mtime, string $dir_path_pfx, array $file_paths ) : bool {
	return mj_find_file_of_list_modified_after($compare_mtime,$dir_path_pfx,$file_paths) !== null;
}


/**
 * Get max mtime (modified time) of a list of files.
 *
 * @param string $dir_path_pfx				directory path; will be prepended to items in $file_paths; may be empty, but if set it must contain a trailing slash.
 * @param array $file_paths					array of paths to check
 *
 * @return int 								maximum modified time of list of files; 0 if list is empty or at least one of the files does not exist
 */
function mj_file_list_max_mtime( string $dir_path_pfx, array $file_paths ) : int {
	$max_mtime = 0;
	foreach( $file_paths as $fn_path ) {
		if( ($check_mtime=@filemtime($dir_path_pfx.$fn_path))===false ) return 0;
		else if( $check_mtime>$max_mtime ) $max_mtime = $check_mtime;
	}
	return $max_mtime;
}


/**
 * Like mj_file_list_max_mtime(), but with $cache.
 *
 * @param string $dir_path_pfx				directory path; will be prepended to items in $file_paths; may be empty, but if set it must contain a trailing slash.
 * @param array $file_paths					array of paths to check
 * @param array $cache						contains modified times of files previously queried. Key is the relative file path (exactly as contained in $file_paths).
 *
 * @return int 								maximum modified time of list of files; 0 if list is empty or at least one of the files does not exist
 */
function mj_file_list_max_mtime_cached( string $dir_path_pfx, array $file_paths, array &$cache, ?string &$max_mtime_failed_fn=null ) : int {
	$max_mtime = 0;
	foreach( $file_paths as $fn_path ) {
		if( isset($cache[$fn_path]) ) {
			$check_mtime = $cache[$fn_path];
		} else {
			if( ($check_mtime=@filemtime($dir_path_pfx.$fn_path))===false ) {
				if( $max_mtime_failed_fn!==null ) $max_mtime_failed_fn = $fn_path;
				return 0;
			}
			$cache[$fn_path] = $check_mtime;
		}
		if( $check_mtime>$max_mtime ) $max_mtime = $check_mtime;
	}
	return $max_mtime;
}




/**
 * returns clean filename from a request (allows only a-z, 0-9, - and _)
 * - removes all invalid characters
 */
function mj_clean_request_filename( string $name ) : string {
	return (string)preg_replace('/[^a-zA-Z0-9_-]/S','',$name);
}

/**
 * returns clean filename from a request (allows only a-z, 0-9, - and _)
 * - replaces all invalid characters with _
 */
function mj_clean_request_filename2( string $name ) : string {
	return (string)preg_replace('/[^a-zA-Z0-9_-]/S','_',$name);
}


/**
 * convert special characters in filename to underscores
 *
 * @param string $name
 *
 * @return string
 */
function mj_make_filename( string $name ) : string {
	return strtr($name,array('/'=>'_','\\'=>'_',':'=>'_','<'=>'_','>'=>'_','|'=>'_'));
}

/**
 * extract language part of a locale (e.g. locale="de_DE" -> language="de")
 *
 * @param string $locale
 *
 * @return string
 */
function mj_extract_language_from_locale( string $locale ) : string {
	$x = explode('_',$locale,2);
	if( !isset($x[1]) ) throw new \mjError('invalid locale: '.$locale,$locale);
	return $x[0];
}

/**
 * get localized ini value, e.g. if section=foo, key=bar, locale=de_DE, language=de
 * then the value of the first of the following keys that exist will be returned:
 *     [foo] bar_de_DE
 *     [foo] bar_de
 *     [foo] bar
 *     <default>
 *
 * @param array $ini
 * @param string $section
 * @param string $key
 * @param string $locale
 * @param string $language
 * @param mixed $default
 *
 * @return mixed
 */
function mj_get_localized_ini_value( array $ini, string $section, string $key, string $locale, string $language, $default ) {
	if( isset($ini[$section]) ) return mj_get_localized_ini_value2($ini[$section],$key,$locale,$language,$default);
	return $default;
}

/**
 * get localized ini value
 *
 * @param array $ini_section
 * @param string $key
 * @param string $locale
 * @param string $language
 * @param mixed $default
 *
 * @return mixed
 */
function mj_get_localized_ini_value2( array $ini_section, string $key, string $locale, string $language, $default ) {
	if( isset($ini_section[$key.'_'.$locale]) ) return $ini_section[$key.'_'.$locale];
	if( isset($ini_section[$key.'_'.$language]) ) return $ini_section[$key.'_'.$language];
	if( isset($ini_section[$key]) ) return $ini_section[$key];
	return $default;
}

/**
 *
 * @return string
 */
function mj_generate_random_unique_password() : string {
	return 'x'.time().'-'.mt_rand().'.'.substr(md5('A'.mt_rand().'#'.time()),0,16);
}

if( !function_exists('mb_strcasecmp') ) {
	/**
	 *
	 * @param string $str1
	 * @param string $str2
	 * @param string|null $encoding
	 *
	 * @return int
	 */
	function mb_strcasecmp( string $str1, string $str2, ?string $encoding=null ) : int {
		if( $encoding===null ) $encoding = mb_internal_encoding();
		return strcmp( mb_strtolower($str1,$encoding), mb_strtolower($str2,$encoding) );
	}
}

/**
 * validate an email address string
 *
 * @param string $email
 *
 * @return bool
 */
function is_valid_email( string $email ) : bool {
	return preg_match('/^[[:alnum:]][a-z0-9_.+-]*@[a-z0-9.-]+$/i',trim($email))===1;
}


/**
 * Parse an email address which contains a display name.
 * e.g.: "Foo Bar <foo.bar@xyz.com>"
 *
 * @return array		[ display name, email address ]
 */
function mj_parse_email_address( string $address_str ) : array {
	if( preg_match('/[^<]*<([^>]+)>/',$address_str,$matches) ) {
		$p = strpos($address_str,'<');
		return [
			trim(substr($address_str,0,$p)),
			trim($matches[1])
		];
	}
	return ['',$address_str];
}


/**
 * Return array with each value casted to int.
 * - keeps associative array keys
 *
 * @param array $arr
 *
 * @return int[]
 * @phan-return array<int|string,int>
 */
function array_cast_to_int( array $arr ) : array {
	foreach( $arr as &$v ) $v = (int)$v;
	return $arr;
}

/**
 * Return array with each value casted to int.
 * - removes associative array keys, returns indexed array
 *
 * @param array $arr
 *
 * @return int[]
 * @phan-return array<int,int>
 */
function array_cast_to_int2( array $arr ) : array {
	$r = [];
	foreach( $arr as $v ) $r[] = (int)$v;
	return $r;
}

/**
 * Return array with each value casted to int.
 * - only entries with value >=0 are kept,
 * - keeps associative array keys.
 *
 * @param array $arr
 *
 * @return int[]
 * @phan-return array<int|string,int>
 */
function array_cast_to_int_and_filter_g0( array $arr ) : array {
	$r = [];
	foreach( $arr as $k=>$v ) {
		$i = (int)$v;
		if( $i > 0 ) $r[$k] = $i;
	}
	return $r;
}

/**
 * Converts string to camel case.
 *
 * @param string $str
 * @param bool $capitalise_first_char
 * @return string
 */
function to_camel_case( string $str, bool $capitalise_first_char=false ) : string {
	if( !isset($str[0]) ) return $str;
	if( $capitalise_first_char ) $str[0] = strtoupper($str[0]);
	return (string)preg_replace_callback('/_([a-z0-9])/S',function($c){
		return strtoupper($c[1]);
	},$str);
}

/**
 * Converts string to its plural form.
 *
 * @param string $e
 * @return string
 */
function mj_pluralize( string $e ) : string {
	$l = strlen($e);
	if( $l>1 && $e[$l-1]==='y' ) {
		if( $l>2 && ( $e[$l-2]==='a' || $e[$l-2]==='e' || $e[$l-2]==='i' || $e[$l-2]==='o' || $e[$l-2]==='u' ) ) return $e.'s';	// e.g. day->days, key->keys, ?iy->?iys, boy->boys, guy->guys
		return substr($e,0,$l-1).'ies'; // e.g. category->categories, dependency->dependencies, ...
	}
	return $e.'s';	// e.g. article->articles
}

/**
 * Converts string to its singular form.
 *
 * @param string $e
 * @return string
 */
function mj_singularize( string $e ) : string {
	$l = strlen($e);
	if( $l>1 && $e[$l-1]==='s' ) {
		if( $l>2 && $e[$l-2]==='y' ) {								// e.g. days->day, guys->guy, ...
			return (string)substr($e,0,$l-1);
		} else if( $l>3 && $e[$l-3]==='i' && $e[$l-2]==='e' ) {		// e.g. categories->category, dependencies->dependency, ...
			return (string)substr($e,0,$l-3).'y';
		}
		// anything else ...
		return (string)substr($e,0,$l-1);									// e.g. articles->article
	}
	return $e;
}

/**
 * Sort array by key in specified column of each item.
 *
 * @param array $array
 * @param string $column
 */
function array_qsort2( array &$array, string $column ) {
	if( !is_array($array) ) return;
	usort( $array, function($a,$b) use($column) {
		return $a[$column]===$b[$column] ? 0 : ( $a[$column] < $b[$column] ? -1 : +1 );
	} );
	reset($array);
}

/**
 *
 * @param array $assoc_array
 * @param string $id_key
 * @param string $value_key
 * @return array
 */
function array_assoc2indexed( array $assoc_array, string $id_key='id', string $value_key='value' ) : array {
	$r = [];
	foreach( $assoc_array as $k=>$v ) $r[] = array( $id_key=>$k, $value_key=>$v );
	return $r;
}

/**
 * Clip number to min/max range.
 *
 * @param int|float $v
 * @param int|float $min_v
 * @param int|float $max_v
 * @return int|float
 */
function clip( $v, $min_v, $max_v ) {
	return $v < $min_v ? $min_v : ( $v > $max_v ? $max_v : $v );
}

/**
 * Get error message for upload error code.
 *
 * @param int $error_code
 * @return string
 */
function mj_upload_error_string( int $error_code ) {
	switch( $error_code ) {
	case UPLOAD_ERR_OK:			return 'no error';
	case UPLOAD_ERR_INI_SIZE:	return 'uploaded file exceeds upload_max_filesize directive in php.ini';
	case UPLOAD_ERR_FORM_SIZE:	return 'uploaded file exceeds MAX_FILE_SIZE directive that was specified in the HTML form';
	case UPLOAD_ERR_PARTIAL:	return 'uploaded file was only partially uploaded';
	case UPLOAD_ERR_NO_FILE:	return 'no file uploaded';
	case UPLOAD_ERR_NO_TMP_DIR:	return 'no temporary folder';
	case UPLOAD_ERR_CANT_WRITE:	return 'failed to write to disk';
	case UPLOAD_ERR_EXTENSION:	return 'upload stopped by extension';
	}
	return 'unknown error';
}

/**
 *
 * @param array $arr
 * @param string $value1
 * @param string $value2
 * @return array
 */
function array_swap_values( array $arr, string $value1, string $value2 ) : array {
	$index1 = array_search($value1,$arr);
	$index2 = array_search($value2,$arr);
	$arr[$index1] = $value2;
	$arr[$index2] = $value1;
	return $arr;
}

/**
 *
 * @param string $delimiter
 * @param string $str
 *
 * @return array
 */
function mj_split_on_last_occurence( string $delimiter, string $str ) : array {
	if( ($p=strrpos($str,$delimiter))!==false ) return [substr($str,0,$p),substr($str,$p+1)];
	return [$str];
}

/**
 *
 * @param string $delimiter
 * @param string $subject
 *
 * @return string
 */
function mj_str_all_but_last_part( string $delimiter, string $subject ) : string {
	if( ($p=strrpos($subject,$delimiter))!==false ) return substr($subject,0,$p);
	return $subject;
}

/**
 *
 * @param string $delimiter
 * @param string $subject
 *
 * @return string|null
 */
function mj_str_last_part( string $delimiter, string $subject ) : ?string {
	if( ($p=strrpos($subject,$delimiter))!==false ) return substr($subject,$p+1);
	return null;
}



/**
 * Search $subject for first occurence of any character from $char_list.
 *
 * If $start is non-negative, then search will start at that offset.
 * If $start is negative, then $subject will be examined starting at <<-$start'th position from end of $subject>> (i.e. $start = strlen($subject)+$start).
 *
 * The returned offset is counted from beginning of $subject, independent from $start parameter.
 *
 * @param string $subject
 * @param string $char_list
 * @param int $start
 *
 * @return bool|int			Returns the 0-based offset of first occurence of any character from $char_list.
 * 							Returns false if no character from $char_list is contained in $subject
 */
function mj_str_find_first_of( string $subject, string $char_list, int $start=0 ) {
	$hp = false;
	if( $start!==0 ) {
		$max_seg_len = strlen($subject);
		if( $start < 0 ) $start = -$start > $max_seg_len ? 0 : $max_seg_len+$start; // examine starting at <<-$start'th position from end of $subject>>
		else if( $start > $max_seg_len ) $start = $max_seg_len;
	}
	for( $cli=0; isset($char_list[$cli]); ++$cli ) {
		if( ($p=strpos($subject,$char_list[$cli],$start))!==false && ( $hp===false || $hp>$p ) ) {
			if( $p===0 ) return 0; // fast exit if first char matched
			$hp = $p;
		}
	}
	return $hp;
}

/**
 * Search $subject for first occurence of any character from $char_list.
 *
 * If $start is non-negative, then search will start at that offset.
 * If $start is negative, then $subject will be examined starting at <<-$start'th position from end of $subject>> (i.e. $start = strlen($subject)+$start).
 *
 * If $length is set, then search will examine a segment of max $length characters in $subject.
 * If $length is given and is negative, then $subject will be examined from the starting position up to -$length characters from the end of subject.
 *
 * The returned offset is counted from beginning of $subject, independent from $start and $length parameters.
 *
 * @param string $subject
 * @param string $char_list
 * @param int $start
 * @param int|null $length
 *
 * @return bool|int			Returns the 0-based offset of first occurence of any character from $char_list.
 * 							Returns false if no character from $char_list is contained in $subject
 */
function mj_str_find_first_of2( string $subject, string $char_list, int $start=0, $length=null ) {
	$max_seg_len = strlen($subject);
	if( $start!==0 ) {
		if( $start < 0 ) $start = -$start > $max_seg_len ? 0 : $max_seg_len+$start; // examine starting at <<-$start'th position from end of $subject>>
		else if( $start > $max_seg_len ) $start = $max_seg_len;
		$max_seg_len -= $start;
	}
	if( $length===null ) {
		$length = $max_seg_len;
	} else {
		if( $length < 0 ) $length = $max_seg_len+$length;		// examine from <<start>> up to <<-$length'th position from end of subject>>.
		$length = clip($length,0,$max_seg_len);
	}
	$hit_segment_length = strcspn($subject,$char_list,$start,$length);
	return $hit_segment_length < $length ? $start+$hit_segment_length : false;
}

/**
 * Search $subject for occurences of any character from $char_list.
 *
 * If $start is non-negative, then search will start at that offset.
 * If $start is negative, then $subject will be examined starting at <<-$start'th position from end of $subject>> (i.e. $start = strlen($subject)+$start).
 *
 * @param string $subject
 * @param string $char_list
 * @param int $start
 *
 * @return bool			Returns true if any character from $char_list is contained in $subject, false otherwise.
 *
 */
function mj_str_contains_one_of( string $subject, string $char_list, int $start=0 ) {
	if( $start!==0 ) {
		$max_seg_len = strlen($subject);
		if( $start < 0 ) $start = $max_seg_len+$start;		// examine starting at <<-$start'th position from end of $subject>>
		$start = clip($start,0,$max_seg_len);
		//$max_seg_len -= $start;
	}
	for( $cli=0; isset($char_list[$cli]); ++$cli ) {
		if( ($p=strpos($subject,$char_list[$cli],$start))!==false ) return true;
	}
	return false;
}

/**
 * Determine whether $subject starts with string $needle.
 *
 * If $max_len is set, then comparison will examine up to $max_len characters from $subject AND $needle,
 * If $max_len is not set, then comparison will examine all characters from $needle and appropriate number of characters from start of $subject.
 *
 * @param string $subject
 * @param string $needle
 * @param int|null $max_len
 * @param bool $case_insensitive
 *
 * @return bool
 */
function mj_str_starts_with( string $subject, string $needle, int $max_len=null, bool $case_insensitive=false ) : bool {
	if( $max_len===null ) $max_len = strlen($needle);
	if( $max_len===0 ) return true;
	return isset($subject[0]) && isset($needle[0]) && $subject[0]===$needle[0] ? ( $case_insensitive ? strncasecmp($subject,$needle,$max_len) : strncmp($subject,$needle,$max_len) ) === 0 : false;
}


/**
 * Determine whether $subject ends with string $needle.
 *
 * If $max_len is set, then comparison will examine up to $max_len characters of $subject AND $needle,
 * If $max_len is not set, then comparison will examine all characters from $needle and appropriate number of characters from end of $subject.
 *
 * @param string $subject
 * @param string $needle
 * @param int|null $max_len
 * @param bool $case_insensitive
 *
 * @return bool
 */
function mj_str_ends_with( string $subject, string $needle, int $max_len=null, bool $case_insensitive=false ) : bool {
	if( $max_len===null ) $max_len = strlen($needle);
	if( $max_len===0 ) return true;
	return substr_compare($subject,$needle,-$max_len,$max_len,$case_insensitive)===0;
}


/**
 * Returns $subject with $needle chopped off, if $subject ends with $needle.
 *
 * @see mj_str_ends_with()
 *
 * @param string $subject
 * @param string $needle
 * @param int|null $max_len
 * @param bool $case_insensitive
 *
 * @return string
 */
function mj_str_chopped_if_ends_with( string $subject, string $needle, int $max_len=null, bool $case_insensitive=false ) : string {
	if( $max_len===null ) $max_len = strlen($needle);
	return $max_len===0 || substr_compare($subject,$needle,-$max_len,$max_len,$case_insensitive)!==0 ? $subject : (string)substr($subject,0,-$max_len);
}

/**
 *
 * @param string $subject
 * @param string $needle
 * @param int|null $max_len
 * @param bool $case_insensitive
 *
 * @return string
 */
function mj_str_append_if_not_ends_with( string $subject, string $needle, int $max_len=null, bool $case_insensitive=false ) : string {
	if( $max_len===null ) $max_len = strlen($needle);
	return $max_len===0 || substr_compare($subject,$needle,-$max_len,$max_len,$case_insensitive)!==0 ? $subject.$needle : $subject;
}

/**
 *
 * @param string $subject
 * @param string $needle
 * @param int|null $max_len
 * @param bool $case_insensitive
 *
 * @return string
 */
function mj_str_remove_pfx_if_starts_with( string $subject, string $needle, int $max_len=null, bool $case_insensitive=false ) : string {
	if( $max_len===null ) $max_len = strlen($needle);
	return ( $case_insensitive ? strncasecmp($subject,$needle,$max_len) : strncmp($subject,$needle,$max_len) ) === 0 ? (string)substr($subject,$max_len) : $subject;
}


/**
 *
 * @param string $str
 *
 * @return bool
 */
function mj_str_is_signed_int( string $str ) : bool {
	return preg_match('/^-?\\d+$/',$str)===1;
}

/**
 *
 * @param string|array $val
 * @param string $region
 * @param string|null $default_region
 *
 * @return string
 */
function get_localized_string2( $val, string $region, string $default_region=null ) : string {
	if( is_string($val) ) {
		if( isset($val[0]) && $val[0]==='{' ) {
			// is a json string
			$val = json_decode($val,true);
		} else {
			// is a plain string
			return (string)$val;
		}
	}
	if( is_array($val) ) {
		if( isset($val[$region]) ) return (string)$val[$region];
		if( $default_region!==null && isset($val[$default_region]) ) return (string)$val[$default_region];
		if( isset($val['']) ) return (string)$val[''];
	}
	return (string)$val;
}

/**
 * Get string of random bytes.
 *
 * @param int $length
 *
 * @return string|false
 */
function mj_random_bytes( int $length=32 ) {
	$length = max($length,1);
	if( function_exists('random_bytes') ) return random_bytes($length);
	if( function_exists('openssl_random_pseudo_bytes') ) return openssl_random_pseudo_bytes($length);
	throw new \mjError('no cryptographic RNG available');
}

/**
 * Create string with each line indented with given indentation string.
 *
 * @param string $str		full text to indent
 * @param string $istr		indentation string, e.g. '    ' or "\t\t"
 *
 * @return string
 */
function mj_indent_multi_line_str( string $str, string $istr ) : string {
	return $istr.str_replace("\n","\n".$istr,$str);
}


/**
 * Determine line and column number in text.
 *
 * @param string $text
 * @param int $offs							byte-offset into text.
 * @param array|null $add_lc_offs			optional array of (line number offset, column offset)
 *
 * @return array
 */
function mj_GetLineAndColumnNoFromOffset( string $text, int $offs, array $add_lc_offs=null ) : array {
	$column = 1;
	for( $linestartoffs=$offs-1; $linestartoffs>=0; --$linestartoffs ) {
		if( !isset($text[$linestartoffs]) ) throw new mjError('invalid offset into text',['linestartoffs'=>$linestartoffs,'text_len'=>strlen($text),'text'=>$text,'offs'=>$offs,'add_lc_offs'=>$add_lc_offs]);

		$c = $text[$linestartoffs];
		if( $c==="\n" ) break;
		else if( $c==="\t" ) $column += 4;
		else $column ++;
	}
	$line = 1 + ( $offs===0 ? 0 : substr_count($text,"\n",0,$offs) );
	if( $add_lc_offs!==null ) {
		if( $line===1 ) $column += $add_lc_offs[1]-1;
		$line += $add_lc_offs[0]-1;
	}
	return [$line,$column];
}


/**
 * get contents of a file, which could possible exist at different locations (usually either at some custom path or at a default location)
 *
 * - returns the content of the first file (from list of possible locations) which actually exists and is readable.
 *
 * @param array	$paths			array or paths
 *
 * @return string|null
 */
function mj_file_get_contents_from_locations( array $paths ) : ?string {
	foreach( $paths as $f ) {
		if( ($c=@file_get_contents($f))!==false ) return $c;
	}
	return null;
}


/**
 * Determine whether all $keys exist as array keys in $arr.
 *
 * @param array $keys		array of key names
 * @param array $arr		associative array to check for existance of keys
 *
 * @return bool			true if all $keys exist in $arr, false otherwise
 */
function mj_all_array_keys_exist( array $keys, array $arr ) : bool {
	foreach( $keys as $k ) if( !isset($arr[$k]) ) return false;
	return true;
	// // for large arrays, these alternatives may be faster:
	// return !array_diff_key(array_flip($keys),$arr);
	// return !array_diff($keys,array_keys($arr));
}


/**
 * Determine whether at least one of $keys exists as array key in $arr.
 *
 * @param array $keys		array of key names
 * @param array $arr		associative array to check for existance of keys
 *
 * @return bool			true if at least one of $keys exists in $arr, false otherwise
 */
function mj_one_of_array_keys_exists( array $keys, array $arr ) : bool {
	foreach( $keys as $k ) if( isset($arr[$k]) ) return true;
	return false;
}


/**
 * Replace contents in file between $begin_tag and $end_tag.
 *
 * @param string $path
 * @param string $begin_tag
 * @param string $end_tag
 * @param string $replace_content
 */
function mj_generate_replace_in_file( string $path, string $begin_tag, string $end_tag, string $replace_content ) {
	if( ($code=@file_get_contents($path))===false ) throw new \mjError('failed to read file '.$path);
	if( ($s=strpos($code,$begin_tag))===false ) throw new \mjError('no begin_tag in file '.$path.': '.$begin_tag);
	$line_indent = "";
	$xs = $s;
	for( $x=$xs-1; $x>=0; --$x ) if( ($xc=$code[$x])!==' ' && $xc!=="\t" ) break;
	$line_indent = substr($code,$x,$xs-$x);
	$s += strlen($begin_tag);
	if( ($e=strpos($code,$end_tag,$s))===false ) throw new \mjError('no end_tag in file '.$path.': '.$end_tag);
	$result = substr($code,0,$s);
	if( $line_indent!=='' ) {
		$tmpr = [];
		$line_indent = ltrim($line_indent,"\n\r");
		foreach( explode("\n",$replace_content) as $xln ) {
			if( $xln==='' ) $tmpr[] = '';
			else $tmpr[] = $line_indent.$xln;
		}
		$result .= implode("\n",$tmpr).$line_indent;
	} else {
		$result .= $replace_content;
	}
	$result .= substr($code,$e);
	if( file_put_contents($path,$result,LOCK_EX)===false ) throw new \mjError('failed to write file '.$path);
}


/**
 * like explode (without limit), but returns empty array when string is empty (not an array with one element as explode does)
 *
 * @param string $delimiter
 * @param string $string
 *
 * @return array
 */
function x_explode( string $delimiter, string $string ) : array {
	if( $delimiter==='' ) throw new \mjError('invalid delimiter');
	return isset($string[0]) ? (array)explode($delimiter,$string) : [];
}

/**
 * @param string $delimiter
 * @param string $string
 *
 * @return array
 */
function x_explode2floats( string $delimiter, string $string ) : array {
	$r = x_explode($delimiter,$string);
	foreach( $r as &$s ) $s = floatval($s);
	return $r;
}



if( !function_exists('array_key_first') ) {
	/* @phan-suppress-next-line PhanRedefineFunctionInternal */
	function array_key_first( array $arr ) {
		foreach( $arr as $key=>$unused ) return $key;
		return null;
	}
}

if( !function_exists('array_key_last') ) {
	/* @phan-suppress-next-line PhanRedefineFunctionInternal */
	function array_key_last( array $arr ) {
		if( !empty($arr) ) return key(array_slice($arr,-1,1,true));
		return null;
	}
}


function stable_uasort( array &$array, $value_compare_func ) : bool {
	$index = 0;
	foreach( $array as &$item ) $item = [$index++,$item];
	$result = uasort( $array, function( $a, $b ) use($value_compare_func) {
		$result = call_user_func($value_compare_func,$a[1],$b[1]);
		return $result==0 ? $a[0]-$b[0] : $result;
	});
	foreach( $array as &$item ) $item = $item[1];
	return $result;
}


function stable_uksort( array &$array, $value_compare_func ) : bool {
	if( ($count=count($array))===0 ) return true;
	$keys = array_combine(array_keys($array),range(1,$count));
	$result = uksort( $array, function( $a, $b ) use($value_compare_func,$keys) {
		$result = call_user_func($value_compare_func,$a,$b);
		return $result==0 ? $keys[$a]-$keys[$b] : $result;
	});
	return $result;
}


