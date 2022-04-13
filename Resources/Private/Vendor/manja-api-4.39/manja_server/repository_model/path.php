<?php
declare(strict_types=1);
/**
 * Manja Repository Model - an abstract model of data in typical repository scheme on manja server
 *
 * Notes:
 *    node ID syntax:
 *      d<int> 			Document in Manja Repository
 *      <int>			Folder in Manja Repository
 *
 * @package ManjaRepositoryModel
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * Path to a node in repository model.
 *
 * - is a list of path segments,
 * - can be converted to string representation any time
 *
 */
class MjCPath {

	/**
	 * Array of path segments
	 *
	 * @var array
	 */
	public $segments;

	/**
	 * Construct a path
	 *
	 * @param array|string|null $path_string
	 */
	public function __construct( $path_string=null ) {
		if( is_array($path_string) ) {
			$this->segments = $path_string;
		} else if( $path_string===null ) {
			$this->segments = [];
		} else {
			$path_string = trim($path_string,'/');
			$this->segments = $path_string==='' ? [] : explode('/',$path_string);
		}
	}

	// public function __destruct() {
	// 	$this->segments = [];
	// }

	/**
	 * Append segment to path
	 *
	 * @param string $segment
	 */
	public function Append( string $segment ) {
		$this->segments[] = $segment;
	}

	/**
	 * Get new path with a segment appended
	 *
	 * @param string $segment
	 *
	 * @return MjCPath
	 */
	public function GetAppended( string $segment ) : MjCPath {
		$result = clone $this;
		$result->Append($segment);
		return $result;
	}

	/**
	 * Get sub path
	 *
	 * @param int $nsegments
	 *
	 * @return MjCPath
	 */
	public function GetSubPath( int $nsegments ) : MjCPath {
		return new MjCPath( array_slice($this->segments,0,$nsegments) );
	}

	/**
	 * Get sub path
	 *
	 * @param int $nsegments
	 *
	 * @return string
	 */
	public function GetSubPathString( int $nsegments ) : string {
		return '/'.implode('/',array_slice($this->segments,0,$nsegments));
	}

	/**
	 * Get array of path segments
	 *
	 * @return array
	 */
	public function GetSegments() : array {
		return $this->segments;
	}

	/**
	 * Return basename of path = the name of its last segment.
	 * 
	 * @return string
	 */
	public function GetBasename() : string {
		$nsegments = count($this->segments);
		return $this->segments[$nsegments-1] ?? '';
	}

	/**
	 * Return dirname of path.
	 * 
	 * @return MjCPath
	 */
	public function GetDirname( int $levels=1 ) : MjCPath {
		return $this->GetSubPath(-$levels);
	}

	/**
	 * Get as string, with extra slash appended at end (except for "/" - which stays "/")
	 * 
	 * @return string
	 */
	public function GetFolderPathString() : string {
		return rtrim( '/'.implode('/',$this->segments), '/' ) . '/';
	}

	/**
	 * Get string representation
	 *
	 * @return string
	 */
	public function __toString() : string {
		return '/'.implode('/',$this->segments);
	}

}

