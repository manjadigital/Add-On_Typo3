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
 * Repository in repository model
 *
 */
class MjCRepository {

	/**
	 * @var ManjaServer
	 */
	private $server;

	/**
	 * @var int
	 */
	private $root_tree_id;

	/**
	 * @var int
	 */
	private $root_folder_id;

	/**
	 * @var bool
	 */
	private $do_sort_categories;

	/**
	 * category cache - created from collected results of FolderGet() and FolderCategoryList()
	 *
	 * @var array
	 */
	private $categories = [];

	/**
	 * array from paths (e.g. "/foo/bar") to already allocated folder nodes
	 *
	 * @var MjCFolder[]
	 */
	private $folders_by_path_cache = [];

	/**
	 * array from category/folder id to already allocated folder nodes
	 *
	 * @var MjCFolder[]
	 */
	private $folders_by_id_cache = [];


	/**
	 * constructor
	 *
	 * @param ManjaServer $server
	 * @param int $root_tree_id
	 * @param int $root_folder_id		folder_id (same as cat_id) of root folder of repository
	 * @param bool $do_sort_categories
	 */
	public function __construct( ManjaServer $server, int $root_tree_id=1, int $root_folder_id=1, bool $do_sort_categories=false ) {
		$this->server = $server;
		$this->root_tree_id = $root_tree_id;
		$this->root_folder_id = $root_folder_id;
		$this->do_sort_categories = $do_sort_categories;
	}

	/**
	 * destructor
	 */
	public function __destruct() {
		$this->folders_by_id_cache = [];
		$this->folders_by_path_cache = [];
		unset($this->server);
	}

	/**
	 * return manja server communication object
	 *
	 * @return \ManjaServer
	 */
	public function GetServer() : ManjaServer {
		return $this->server;
	}

	/**
	 *
	 * @return int
	 */
	public function GetRootTreeId() : int {
		return $this->root_tree_id;
	}

	/**
	 *
	 * @return int
	 */
	public function GetRootFolderId() : int {
		return $this->root_folder_id;
	}

	/**
	 *
	 * @param int $cat_id
	 * @param array $cmeta
	 *
	 * @return array
	 */
	private static function _make_cmeta( int $cat_id, array $cmeta ) : array {
		$cmeta['cat_id'] = $cat_id;
		$cmeta['filename'] = mj_make_filename($cmeta['name']);
		return $cmeta;
	}

	/**
	 *
	 * @param int $cat_id
	 */
	private function _load_category( int $cat_id ) {
		$cmeta = $this->server->FolderGet($cat_id);
		if( $cmeta===false ) throw new MjCObjectNotFoundException( 'object not found(3): node_id='.$cat_id );
		if( $cat_id===$this->root_folder_id ) {
			$cmeta['cat_id'] = $this->root_folder_id;
			$cmeta['filename'] = '';
			$this->categories[$cat_id] = $cmeta;
		} else {
			$this->categories[$cat_id] = self::_make_cmeta($cat_id,$cmeta);
		}
	}

	/**
	 *
	 * @param int $parent_cat_id
	 *
	 * @return array
	 */
	private function _load_category_children( int $parent_cat_id ) : array {
		$candidates = $this->server->FolderCategoryList($parent_cat_id);
		//if( $candidates===false ) throw new MjCObjectNotFoundException( 'object not found(3): node_id='.$parent_cat_id );
		$result = [];
		foreach( $candidates as $cat_id=>$cmeta ) {
			$cat_id = (int)$cat_id;
			$result[$cat_id] = $this->categories[$cat_id] = self::_make_cmeta($cat_id,$cmeta);
		}
		return $result;
	}

	/**
	 *
	 * @param int $parent_cat_id
	 * @param string $path_segment_match
	 *
	 * @return array|null
	 */
	private function _load_category_children_like( int $parent_cat_id, string $path_segment_match ) {
		//if( $path_segment_match!==null ) {
			//$path_segment_match = mj_make_filename($path_segment_match);
			// NOTE: this works, because '_' matches "any single character" in SQL LIKE clause.
			// however, we should replace actual '_' with quoted '\\_' !
			/*$path_segment_match = strtr($path_segment_match,array('%'=>'\\%',
																	'_'=>'\\_',
																	'/'=>'_','\\'=>'_',':'=>'_','<'=>'_','>'=>'_','|'=>'_' ));*/
		//}
		$candidates = $this->server->FolderCategoryList($parent_cat_id,$path_segment_match);
		//if( $candidates===false ) throw new MjCObjectNotFoundException( 'object not found(4): node_id='.$parent_cat_id );
		foreach( $candidates as $cat_id=>$cmeta ) {
			$cat_id = (int)$cat_id;
			$cmeta = $this->categories[$cat_id] = self::_make_cmeta($cat_id,$cmeta);
			if( $cmeta['filename']===$path_segment_match ) return $cmeta;
		}
		return null;
	}

	/**
	 *
	 * @return bool
	 */
	public function GetAutoSortFolderChildren() : bool {
		return $this->do_sort_categories;
	}

	/**
	 *
	 * @param int $folder_id
	 */
	public function EnsureFolderChildrenSorted( int $folder_id ) {
		if( $this->do_sort_categories ) $this->server->CategorySort($folder_id,false);
	}

	/**
	 *
	 */
	public function InvalidateCategoryCaches() {
		$this->categories = [];
		$this->folders_by_path_cache = [];
		$this->folders_by_id_cache = [];
	}

	/**
	 * Get root folder of the repository
	 *
	 * @return MjCFolder
	 */
	public function GetRootFolder() : MjCFolder {
		if( !isset($this->folders_by_path_cache['/']) ) return $this->folders_by_path_cache['/'] = $this->GetFolderNodeByFolderId($this->root_folder_id);
		return $this->folders_by_path_cache['/'];
	}

	/**
	 * Retrieve node by id
	 *
	 * @param string $node_id
	 *
	 * @return MjCNode
	 */
	public function GetNodeById( string $node_id ) : MjCNode {
		if( isset($node_id[0]) && $node_id[0]==='d' ) return $this->GetDocumentNodeById($node_id);
		else if( ctype_digit($node_id) ) return $this->GetFolderNodeByFolderId((int)$node_id);
		throw new MjCInvalidArgumentError( 'invalid node id syntax: node_id=' . $node_id );
	}

	/**
	 * Retrieve document node by id
	 *
	 * @param string $node_id
	 *
	 * @return MjCDocument
	 */
	public function GetDocumentNodeById( string $node_id ) : MjCDocument {
		$media_id = substr($node_id,1);
		return $this->GetDocumentNodeByDocumentId($media_id);
		// $media_info = $this->server->MediaInfo([$media_id],self::GetDocumentNodeRequiredMetaIds());
		// if( empty($media_info['meta']) && empty($media_info['categories']) ) throw new MjCObjectNotFoundException( 'object not found(A): node_id=' . $node_id );
		// $meta = mj_arr2_val($media_info,'meta',$media_id,[]);
		// $cats = mj_arr2_val($media_info,'categories',$media_id,[]);
		// $parent_folder_id = null;
		// if( isset($cats[$this->root_tree_id]) ) {
		// 	foreach( $cats[$this->root_tree_id] as $cat_id ) {
		// 		$parent_folder_id = (int)$cat_id;
		// 		break;
		// 	}
		// }
		// return $this->CreateDocumentNode2($media_id,$parent_folder_id,$meta);
	}

	/**
	 * Retrieve document node by document id (aka media_id)
	 *
	 * @param string $media_id
	 *
	 * @return MjCDocument
	 */
	public function GetDocumentNodeByDocumentId( string $media_id ) : MjCDocument {
		$media_info = $this->server->MediaInfo([$media_id],self::GetDocumentNodeRequiredMetaIds());
		if( empty($media_info['meta']) && empty($media_info['categories']) ) throw new MjCObjectNotFoundException( 'object not found(A): media_id=' . $media_id );
		$meta = mj_arr2_val($media_info,'meta',$media_id,[]);
		$cats = mj_arr2_val($media_info,'categories',$media_id,[]);
		$parent_folder_id = null;
		if( isset($cats[$this->root_tree_id]) ) {
			foreach( $cats[$this->root_tree_id] as $cat_id ) {
				$parent_folder_id = (int)$cat_id;
				break;
			}
		}
		return $this->CreateDocumentNode2($media_id,$parent_folder_id,$meta);
	}

	/**
	 * Retrieve a folder node by id (aka cat_id)
	 *
	 * @param int $folder_id
	 *
	 * @return MjCFolder
	 */
	public function GetFolderNodeByFolderId( int $folder_id ) : MjCFolder {
		if( isset($this->folders_by_id_cache[$folder_id]) ) return $this->folders_by_id_cache[$folder_id];
		if( !isset($this->categories[$folder_id]) ) $this->_load_category($folder_id);
		return $this->CreateFolderNode2($folder_id,$this->categories[$folder_id]);
	}

	/**
	 * Retrieve list of subfolders of given folder id
	 *
	 * @param int $parent_folder_id
	 *
	 * @return MjCFolder[]
	 */
	public function GetSubFolderNodesByFolderId( int $parent_folder_id ) : array {
		$subfolders = [];
		foreach( $this->_load_category_children($parent_folder_id) as $sub_folder_id=>$sub_folder_cmeta ) {
			$subfolders[] = isset($this->folders_by_id_cache[$sub_folder_id]) ? $this->folders_by_id_cache[$sub_folder_id] : $this->CreateFolderNode2($sub_folder_id,$sub_folder_cmeta);
		}
		return $subfolders;
	}

	/**
	 * Retrieve folder node with given $path_segment, which is a sub-folder of given folder id
	 * - optimized for performance in cases where "folders_by_id_cache" should not be altered
	 *
	 * @param int $parent_folder_id
	 * @param string $path_segment
	 *
	 * @return MjCFolder|null
	 */
	public function TryGetSubFolderNodeByPathSegment( int $parent_folder_id, string $path_segment ) {
		if( ($sub_folder_cmeta=$this->_load_category_children_like($parent_folder_id,$path_segment))===null ) return null;
		$sub_folder_id = $sub_folder_cmeta['cat_id'];
		return isset($this->folders_by_id_cache[$sub_folder_id]) ? $this->folders_by_id_cache[$sub_folder_id] : $this->CreateFolderNode2($sub_folder_id,$sub_folder_cmeta);
	}

	/**
	 * Retrieve document node with given $path_segment, which is a child of given parent folder id
	 * - optimized for performance
	 *
	 * @param int $parent_folder_id
	 * @param string $path_segment
	 *
	 * @return MjCDocument|null
	 */
	public function TryGetDocumentNodeByPathSegment( int $parent_folder_id, string $path_segment ) {
		$media_list = $this->server->FolderMediaGet($parent_folder_id,$path_segment,self::GetDocumentNodeRequiredMetaIds());
		foreach( $media_list['media_ids'] as $cmid ) {
			// use first match
			$doc_meta = mj_arr2_val($media_list,'meta',$cmid,[]);
			return $this->CreateDocumentNode2($cmid,$parent_folder_id,$doc_meta,null);
		}
		return null;
	}


	/**
	 * internal node retrieval by path
	 *
	 * @param MjCFolder $node
	 * @param string $node_path_str
	 * @param array $path_segments
	 *
	 * @return MjCNode
	 */
	private function _get_descendant_node_by_path_segments( MjCFolder $node, string $node_path_str, array $path_segments ) : MjCNode {
		/***
		$psc = count($path_segments);
		for( $i=0; $i<$psc-1; ++$i ) {
			// non-last segments may ref' to folders only ..
			$path_segment = $path_segments[$i];
			if( ($sub_folder=$this->TryGetSubFolderNodeByPathSegment($node->GetFolderId(),$path_segment))===null ) throw new MjCObjectNotFoundException( 'object not found(5): path='.$node_path_str.'; segment='.$path_segment );
			$node_path_str = ($node_path_str==='/') ? ('/'.$path_segment) : ($node_path_str.'/'.$path_segment);
			$node = $this->folders_by_path_cache[$node_path_str] = $sub_folder;
		}
		if( $psc>1 ) {
			// last segment may ref' to either folder or document ..
			$path_segment = $path_segments[$psc-1];
			if( ($child_node=$node->GetChildByPathSegment($path_segment,false))===null ) throw new MjCObjectNotFoundException( 'object not found(6): path='.$node_path_str.'; segment='.$path_segment );
			return $child_node;
		}
		return $node;
		***/
		for( $i=0; isset($path_segments[$i]); ++$i ) {
			$segment = $path_segments[$i];
			$populate_sub_folder_cache = $i===0 && $node_path_str==='/';
			if( ($child_node=$node->GetChildByPathSegment($segment,$populate_sub_folder_cache))===null ) {
				throw new MjCObjectNotFoundException( 'object not found(1): path='.$node_path_str.'; segment='.$segment );
			}
			if( !$child_node->IsFolder() ) {
				if( $i!=count($path_segments)-1 ) {
					throw new MjCObjectNotFoundException( 'object not found(2): path='.$node_path_str.'; segment='.$segment );
				}
				return $child_node;
			}
			$node_path_str = ($node_path_str==='/') ? ('/'.$segment) : ($node_path_str.'/'.$segment);
			$node = $this->folders_by_path_cache[$node_path_str] = $child_node;
		}
		return $node;
	}

	/**
	 * Retrieve node by path
	 *
	 * @param MjCPath $path
	 *
	 * @return MjCNode
	 */
	public function GetNodeByPath( MjCPath $path ) : MjCNode {
		// if( !( $path instanceof MjCPath ) ) $path = new MjCPath($path);
		// check path cache, navigate down on path segments from leaf to root
		if( !isset($path->segments[0]) ) return $this->GetRootFolder(); // = the root folder node
		if( !empty($this->folders_by_path_cache) ) {
			$node_path_str = (string)$path;
			if( isset($this->folders_by_path_cache[$node_path_str]) ) {
				// a node matching the path already exists in cache...
				return $this->folders_by_path_cache[$node_path_str];
			}
			// walk path down to root, use any folder node that exists in cache as a starting node for walking up to leaf ...
			for( $x=count($path->segments)-1; $x>=0; --$x ) {
				$parent_path_str = $path->GetSubPathString($x);
				if( isset($this->folders_by_path_cache[$parent_path_str]) ) {
					// found folder node for path of $x'th segment in cache ... -> navigate from cached node up to the actual leaf node ...
					return $this->_get_descendant_node_by_path_segments($this->folders_by_path_cache[$parent_path_str],$parent_path_str,array_slice($path->segments,$x));
				}
			}
		}
		// no cache available: navigate full hierarchy from root up to actual leaf node of path ...
		return $this->_get_descendant_node_by_path_segments($this->GetRootFolder(),'/',$path->segments);
	}

	/**
	 * Retrieve node by path
	 *
	 * @param string $path_str
	 *
	 * @return MjCNode
	 */
	public function GetNodeByPathString( string $path_str ) : MjCNode {
		return $this->GetNodeByPath( new MjCPath($path_str) );
	}


	public static function CreateDocumentNodeId( string $doc_id ) : string				{ return 'd'.$doc_id; }
	public static function CreateFolderNodeId( string $folder_id ) : string				{ return $folder_id; }

	public static function IsDocumentNodeId( string $node_id ) : bool					{ return isset($node_id[0]) && $node_id[0]==='d'; }
	public static function IsFolderNodeId( string $node_id ) : bool						{ return ctype_digit($node_id); }

	public static function CreateDocumentIdFromNodeId( string $node_id ) : string		{ return substr($node_id,1); }
	public static function CreateFolderIdFromNodeId( string $node_id ) : string			{ return $node_id; }


	/**
	 * Get array of meta_id numbers that are required for document nodes
	 * @return array
	 */
	public static function GetDocumentNodeRequiredMetaIds() : array {
		return [-6,-5,-4,-3,-2,-1,1,5,101];
	}

	/**
	 * Utility function for creation of document nodes
	 *
	 * @param string $media_id
	 * @param int|null $parent_folder_id
	 * @param array $meta
	 * @param string|null $filename
	 *
	 * @return MjCDocument
	 */
	public function CreateDocumentNode2( string $media_id, int $parent_folder_id=null, array $meta, string $filename=null ) : MjCDocument {
		if( $filename===null && isset($meta[1]) ) $filename = mj_make_filename($meta[1][0]);
		$attrs = [
			'node_id'			=> 'd'.$media_id,
			'document_id'		=> $media_id,
			'parent_folder_id'	=> $parent_folder_id,
			'created'			=> mj_arr2_val($meta,-6,0,null),//substr(mj_arr2_val($meta,-6,0,null),0,19),
			'created_by'		=> mj_arr2_val($meta,-5,0,null),
			'modified'			=> mj_arr2_val($meta,-4,0,null),//substr(mj_arr2_val($meta,-4,0,null),0,19),
			'modified_by'		=> mj_arr2_val($meta,-3,0,null),
			'content_type'		=> mj_arr2_val($meta,-2,0,null),
			'media_class'		=> mj_arr2_val($meta,-1,0,null),
			'content_length'	=> mj_arr2_val($meta, 5,0,null),
			'name'				=> isset($meta[101]) ? implode(',',$meta[101]) : null,
			'filename'			=> $filename,
		];
		return new MjCDocument($this,$attrs);
	}

	/**
	 * Utility function for creation of folder nodes
	 *
	 * @param int $cat_id
	 * @param array $cmeta
	 *
	 * @return MjCFolder
	 */
	public function CreateFolderNode2( int $cat_id, array $cmeta ) : MjCFolder {
		$parent_folder_id = ($cmeta['parent']===0||$cmeta['parent']==='0') ? null : (int)$cmeta['parent'];
		$attrs = [
			'node_id'			=> (string)$cat_id,
			'folder_id'			=> $cat_id,
			'parent_folder_id'	=> $parent_folder_id,
			'created'			=> substr($cmeta['crd_dt'],0,19),
			'created_by'		=> $cmeta['crd_by'],
			'modified'			=> substr($cmeta['mod_dt'],0,19),
			'modified_by'		=> $cmeta['mod_by'],
			'name'				=> $cmeta['name'],
			'filename'			=> isset($cmeta['filename']) ? $cmeta['filename'] : mj_make_filename($cmeta['name']),
		];
		return $this->folders_by_id_cache[$cat_id] = new MjCFolder($this,$attrs);
	}

}

