<?php
/*------------------------------------------------------------------------------
 (c)(r) 2008-2018 IT-Service Robert Frunzke
--------------------------------------------------------------------------------
Manja Repository Concepts
- prefixed MjC (=Manja Concept)
------------------------------------------------------------------------------*/

/*
 * notes:
 * node ID syntax:
 * 		d_<int> 		Document in Manja Repository
 * 		f_<int>			Folder in Manja Respoitory
 * 		D_<string>		Document in Temporary File Storage
 * 		F_<string>		Folger in Temporary File Storage
 *
 */

class MjCException extends Exception {}
class MjCInvalidArgumentException extends MjCException {}
class MjCObjectNotFoundException extends MjCException {}
class MjCObjectExistsAtTargetException extends MjCException {}
class MjCNotSupportedException extends MjCException {}
class MjCConstraintException extends MjCException {}

/**
 * A path is just a list of path segments,
 * - can be converted to a string representation any time
 */
class MjCPath {
	/**
	 * Array of path segments
	 * - each segment
	 * @var array
	 */
	public $segments;

	public function __construct( $path_string=null ) {
		if( is_array($path_string) ) {
			$this->segments = $path_string;
		} else {
			if( $path_string===null ) {
				$this->segments = array();
			} else {
				$path_string = trim($path_string,'/');
				$this->segments = $path_string==='' ? array() : explode('/',$path_string);
			}
		}
	}

	public function Append( $segment ) {
		$this->segments[] = $segment;
	}

	public function GetSubPath( $nsegments ) {
		return new MjCPath( array_slice($this->segments,0,$nsegments) );
	}

	public function GetSegments() {
		return $this->segments;
	}

	public function __toString() {
		return '/'.implode('/',$this->segments);
	}

	/*
	public function GetDebugString() {
		$r = 'MjCPath:'."\n";
		$this_str = (string)$this;
		$r .= "\tthis_str: ".$this_str."\n";
		$r .= "\tthis_str: ".bin2hex($this_str)."\n";
		$norm_str = mj_normalize_unicode_input($this_str);
		$r .= "\tnorm_str: ".$norm_str."\n";
		$r .= "\tnorm_str: ".bin2hex($norm_str)."\n";
		$r .= "\n";
		return $r;
	}
	*/

}


/**
 * A node has a parent and a path
 */
abstract class MjCNode {

	/**
	 * @var MjCRepository
	 */
	protected $repository = null;
	/**
	 * @var array
	 */
	protected $attributes = null;
	/**
	 * @var string
	 */
	protected $node_id = null;
	/**
	 * @var string
	 */
	private $parent_node_id = null;
	/**
	 * @var string
	 */
	protected $path_segment = null;

	public function __construct( MjCRepository $repository, $attributes=array() ) {
		$this->repository = $repository;
		$this->attributes = $attributes;
		$this->node_id = $attributes['node_id'];
		$this->parent_node_id = $attributes['parent_node_id'];
		$this->path_segment = $attributes['filename'];
	}

	/**
	 * @return MjCRepository
	 */
	public function GetRepository() {
		return $this->repository;
	}

	public function GetNodeId() {
		return $this->node_id;
	}

	public function GetParentNodeId() {
		return $this->parent_node_id;
	}

	/**
	 * @return MjCNode
	 */
	public function GetParentNode() {
		return $this->parent_node_id===null ? null : $this->repository->GetFolderNodeById($this->parent_node_id);
	}

	/**
	 * @return MjCPath
	 */
	public function GetPath() {
		if( ($parent_node=$this->GetParentNode())===null ) {
			// path of root node
			return new MjCPath();
		} else {
			// compound path
			$new_path = clone $parent_node->GetPath();
			$new_path->Append($this->path_segment);
			return $new_path;
		}
	}

	public function GetAttribute( $key ) {
		return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
	}

	public function GetAttributes() {
		return $this->attributes;
	}

	public function GetPathSegment() {
		return $this->path_segment;
	}

	abstract public function DeleteSelf();
	abstract public function Rename( $new_name );
	abstract public function MoveToPath( $trg_path, $new_name=null, $overwrite_mode=null );
	abstract public function IsFolder();
}

class MjCDocument extends MjCNode {

	/**
	 * document id = media id
	 * @var string
	 */
	protected $document_id = null;

	public function __construct( MjCRepository $repository, $attributes=array() ) {
		MjCNode::__construct($repository,$attributes);
		$this->document_id = $attributes['document_id'];
	}

	public function GetDocumentId() {
		return $this->document_id;
	}

	public function SendToClient() {
		$result = $this->repository->GetServer()->MediumGet($this->document_id,true,null,array('intent'=>'download'));
	}

	public function GetContent() {
		$server = $this->repository->GetServer();
		$result = $server->MediumGet($this->document_id,false,null,array('intent'=>'download'));
		if( $result!==false ) return $server->get_payload($result,true);
	}

	public function GetStream() {
		$server = $this->repository->GetServer();
		$result = $server->MediumGet($this->document_id,false,null,array('exit'=>1,'intent'=>'download'));
		if( $result!==false ) return $server->get_stream($result,true);
	}

	public function SendRenditionToClient($par) {
		$p = explode('_',$par);
		if( count($p)<2 ) throw new MjCInvalidArgumentException( 'SendRenditionToClient called with incomplete arguments' );
		$server = $this->repository->GetServer();
		$sfx = 'png'; // TODO: use 'jpg' when we know that the thumbnail will not have an alpha channel
		$ctype = $server->GetMediaTypeFromSuffix($sfx);
		$opts = array(
			'media_id'		=> $this->document_id,
			'ctype'			=> $ctype,
			'max_width'		=> $p[0],
			'max_height'	=> $p[1],
			'pixel_format'	=> 'RGB',
			'color_profile'	=> 'RGBdefault',
			'page'			=> 1,
			'no_cache'		=> 0,
			'up_scale'		=> 0,
			'progressive'	=> 0,
		);
		if( false ) {
			// send with filename
			$filename = $this->GetAttribute('filename');
			if( ($x=strrpos($filename,'.'))!==false ) $filename = substr($filename,0,$x) . '_'.$p[0].'x'.$p[1].'.'.$sfx;
			return $server->MediumPreview( $opts, true, $filename );
		} else {
			// .. without filename - doesn't matter anyway
			return $server->MediumPreview( $opts, true );
		}
	}

	/**
	 * Write document data
	 * @param $data resource|string
	 * @param $size integer if $data is a stream resource, then this should be set to the size of the input, may be -1
	 * @return boolean true on success
	 */
	public function WriteData( $data, $size=-1 ) {
		if( is_resource($data) ) {
			// $data is a stream, for example it could be php://input
			$tmp = $this->repository->GetServer()->MediumUploadFromStream($data,$size,$this->document_id,1,$this->GetAttribute('filename'));
		} else if( $data==='' && $size===0 ) {
			// special marker for an "empty" input stream -> this will truncate the asset data
			$tmp = $this->repository->GetServer()->MediumUploadFromStream(null,0,$this->document_id,1,$this->GetAttribute('filename'));
		} else {
			// $data is a path to a (temporar) file on this host
			$tmp = $this->repository->GetServer()->MediumUpload($data,$this->document_id,1,$this->GetAttribute('filename'));
		}
		if( $tmp===false ) return false;
		return true;
	}

	/**
	 * Delete this document
	 * @return boolean true on success
	 */
	public function DeleteSelf( $rebuild_temp_file_references=true ) {
		$parent_node = $this->GetParentNode();
		$in_out_media_ids = array($this->document_id);
		if( !function_exists('mjwsu_media_delete') ) require dirname(__FILE__).'/inc_manja_web_server_util.php';
		$result = mjwsu_media_delete(null,$this->repository->GetServer(),$in_out_media_ids,$rebuild_temp_file_references,true);
		if( $result===false || !isset($result['media_ids']) ) return false;
		$deleted_ids = $result['media_ids'];
		if( !in_array($this->document_id,$deleted_ids) ) return false;
		if( $parent_node!==null ) $parent_node->InvalidateChildCaches();
		return true;
	}

	/**
	 * Rename document
	 * @return boolean true on success
	 */
	public function Rename( $new_name ) {
		$meta = array( '1'=>array($new_name) );
		$tmp = $this->repository->GetServer()->MediaUpdate($this->document_id,$meta,array());
		if( $tmp===false ) return false;
		$this->path_segment = $this->attributes['filename'] = mj_make_filename($new_name);
		return true;
	}

	/**
	 * Move document to new location and optionally rename the node
	 * @param string $trg_path
	 * @param string|null $new_name
	 */
	public function MoveToPath( $trg_path, $new_name=null, $overwrite_mode=null ) {
		$new_parent_node = $this->repository->GetNodeByPath($trg_path);

		$trg_segment_name = $new_name===null ? $this->path_segment : mj_make_filename($new_name);
		if( ($target_existing_node=$new_parent_node->GetChildByPathSegment($trg_segment_name))!==null ) {
			// there already exists a node at target
			if( $overwrite_mode==='replace-target' ) {
				// remove target - it will be completely replaced with this document (also keeping the id of THIS document)
				$target_existing_node->DeleteSelf();
				// continue below ...
			} else if( $overwrite_mode==='integrate-into-target' ) {
				if( $target_existing_node->IsFolder() ) {
					// remove target folder, so it can be replaced with this file
					$target_existing_node->DeleteSelf();
					// continue below ...
				} else {
					// clone contents of this file(data & properties) to target file, then delete this file
					$tmp = $this->repository->GetServer()->MediumClone( $this->document_id, $target_existing_node->GetDocumentId() );//, true );
					if( $tmp===false ) return false;
					return $this->DeleteSelf();
				}
			} else {
				throw new MjCObjectExistsAtTargetException('object exists at target: path='.$trg_path.'; segment='.$trg_segment_name );
			}
		}

		$new_parent_cat_id = $new_parent_node->GetFolderId();
		$meta = $new_name===null ? array() : array( '1'=>array($new_name) );
		$categories = array( $this->repository->GetRootTreeId() => $new_parent_cat_id );
		$tmp = $this->repository->GetServer()->MediaUpdate($this->document_id,$meta,$categories);
		if( $tmp===false ) return false;
		$this->path_segment = $this->attributes['filename'] = mj_make_filename($new_name);
		return true;
	}

	public function IsFolder() {
		return false;
	}

}

class MjCFolder extends MjCNode {

	/**
	 * folder id = category id
	 * @var string
	 */
	protected $folder_id = null;

	/**
	 * children folder nodes cache
	 * @var MjCFolder[]
	 */
	private $folders = null;

	/**
	 * number of child documents
	 * @var integer
	 */
	private $document_count = null;


	public function __construct( MjCRepository $repository, $attributes=array() ) {
		MjCNode::__construct($repository,$attributes);
		$this->folder_id = $attributes['folder_id'];
	}

	public function GetFolderId() {
		return $this->folder_id;
	}

	public function GetFolders( $offs=0, $count=2147483637 ) {
		if( $this->folders===null ) $this->folders = $this->repository->GetSubFolderNodesById($this->node_id,true);
		return $offs==0 && $count==2147483637 ? $this->folders : array_slice($this->folders,$offs,$count);
	}

	public function GetTotalFolderCount() {
		if( $this->folders===null ) $this->folders = $this->repository->GetSubFolderNodesById($this->node_id,true);
		return count($this->folders);
	}

	public function GetDocuments( $offs=0, $count=2147483637 ) {
		$result = array();
		$media_list = $this->repository->GetServer()->FolderMediaList($this->folder_id,$offs,$count,$this->repository->GetDocumentNodeRequiredMetaIds());
		$mlmeta = isset($media_list['meta']) ? $media_list['meta'] : array();
		foreach( $media_list['media_ids'] as $cmid ) {
			$doc_meta = isset($mlmeta[$cmid]) ? $mlmeta[$cmid] : array();
			$doc_fn = isset($doc_meta[1]) ? mj_make_filename($doc_meta[1][0]) : null;
			if( $doc_fn===null || $doc_fn==='' ) {
				// item has no actual filename -> silently ignore this item (does this situation really occur????)
			} else {
				$result[] = $this->repository->CreateDocumentNode2($cmid,$this->node_id,$doc_meta,$doc_fn);
			}
		}
		$this->document_count = intval($media_list['result_count'],10);
		return $result;
	}

	public function GetTotalDocumentCount() {
		if( $this->document_count===null ) $this->GetDocuments(0,1); // fetch one document and determine the count
		return $this->document_count;
	}

	/**
	 * @return MjCNode[]
	 */
	public function GetChildren( $offs=0, $count=2147483637 ) {
		$result_folders = $this->GetFolders($offs,$count);
		$count -= count($result_folders);
		$offs  -= count($this->folders);
		if( $offs < 0 ) $offs = 0;
		if( $count <= 0 ) return $result_folders;
		return array_merge( $result_folders, $this->GetDocuments($offs,$count) );
	}

	/**
	 * @return integer
	 */
	public function GetTotalChildrenCount() {
		return $this->GetTotalFolderCount() + $this->GetTotalDocumentCount();
	}

	/**
	 * @param string $path_segment
	 * @return MjCNode
	 */
	public function GetChildByPathSegment( $path_segment, $populate_cache=true ) {
		// match against subfolder names
		if( $this->folders===null ) {
			if( $populate_cache ) {
				$this->folders = $this->repository->GetSubFolderNodesById($this->node_id,true);
				foreach( $this->folders as $child_folder ) {
					if( $path_segment===$child_folder->path_segment ) return $child_folder;
				}
			} else {
				// do not instantiate all sub-folder node objects, but just search through sub-category names and return single result node
				// - this improves performance for simple single "MjCRepository::GetNodeByPath(path)" requests,
				//   which are called really often in WebDAV interface!
				if( ($child_folder=$this->repository->TryGetSubFolderNodeByPathSegment($this->node_id,$path_segment,true))!==null ) return $child_folder;
			}
		} else {
			// use $this->folders
			foreach( $this->folders as $child_folder ) {
				if( $path_segment===$child_folder->path_segment ) return $child_folder;
			}
		}
		// return document node, if the path matches to a document in folder...
		return $this->repository->TryGetDocumentNodeByPathSegment($this->node_id,$path_segment); // MjCDocument or null
	}

	public function InvalidateChildCaches() {
		$this->folders = null;
		$this->document_count = null;
	}

	/**
	 * Create child folder node
	 * @return string new node id or null on error
	 */
	public function CreateFolder( $name ) {
		if( $this->repository->GetAutoSortFolderChildren() ) {
			$after_id = 0;
			$sort_alphabetically_into_parent = false;
		} else {
			$after_id = 0;
			$sort_alphabetically_into_parent = true;
		}
		$tmp = $this->repository->GetServer()->CategoryAdd($name,$this->repository->GetRootTreeId(),$this->folder_id,$after_id,$sort_alphabetically_into_parent);
		if( $tmp===false ) return null;
		$this->InvalidateChildCaches();
		$this->repository->EnsureFolderChildrenSorted($this->folder_id);
		$this->repository->InvalidateCategoryCaches();
		return $this->repository->CreateFolderNodeId($tmp['cat_id']);
	}

	/**
	 * Create child document node
	 * @param $name string
	 * @param $data resource|string
	 * @param $size integer if $data is a stream, then this should be set to the size of the input, may be -1
	 * @return string new node id or null on error
	 */
	public function CreateDocument( $name, $data, $size=-1 ) {
		//die('CreateDocument('.$name.','.gettype($data).','.$size.')');
		$cat_id_tree_1 = $this->repository->GetRootTreeId()==1 ? $this->folder_id : 1;
		if( is_resource($data) ) {
			// $data is a stream, for example it could be php://input
			$tmp = $this->repository->GetServer()->MediumUploadFromStream($data,$size,null,$cat_id_tree_1,$name);
		} else if( $data==='' && $size===0 ) {
			// special marker for an "empty" input stream -> this will create a zero-sized asset
			$tmp = $this->repository->GetServer()->MediumUploadFromStream(null,0,null,$cat_id_tree_1,$name);
		} else {
			// $data is a path to a (temporary) file on this host
			$tmp = $this->repository->GetServer()->MediumUpload($data,null,$cat_id_tree_1,$name);
		}
		if( $tmp===false ) return null;
		$new_media_id = $tmp['media_id'];
		if( $this->repository->GetRootTreeId()!=1 ) {
			$categories = array( $this->repository->GetRootTreeId() => $this->folder_id );
			$tmp = $this->repository->GetServer()->MediaUpdate($new_media_id,array(),$categories);
			if( $tmp===false ) return false;
		}
		$this->InvalidateChildCaches();
		$tmp_result = $this->repository->CreateDocumentNodeId($new_media_id);
		if( $tmp_result!==null ) $this->document_count ++;
		return $tmp_result;
	}

	/**
	 * Delete this folder
	 * @return boolean true on success
	 */
	public function DeleteSelf( $rebuild_temp_file_references=true ) {
		$parent_node = $this->GetParentNode();
		$tmp = $this->repository->GetServer()->CategoryDelete($this->folder_id);
		if( $tmp===false ) return false;
		if( $parent_node!==null ) $parent_node->InvalidateChildCaches();
		$this->repository->InvalidateCategoryCaches();
		return true;
	}

	/**
	 * Rename folder
	 * @return boolean true on success
	 */
	public function Rename( $new_name ) {
		if( $this->repository->GetAutoSortFolderChildren() ) {
			$sort_alphabetically_into_parent = false;
		} else {
			$sort_alphabetically_into_parent = true;
		}
		$tmp = $this->repository->GetServer()->CategoryUpdate($this->folder_id,$new_name,$sort_alphabetically_into_parent);
		if( $tmp===false ) return false;
		$this->path_segment = $this->attributes['filename'] = mj_make_filename($new_name);
		if( ($parent_node=$this->GetParentNode())!==null ) {
			$parent_node->InvalidateChildCaches();
			$this->repository->EnsureFolderChildrenSorted($parent_node->GetFolderId());
		}
		$this->repository->InvalidateCategoryCaches();
		return true;
	}

	/**
	 * Move folder to new location and optionally rename the node
	 * @param string $trg_path
	 * @param string|null $new_name
	 */
	public function MoveToPath( $trg_path, $new_name=null, $overwrite_mode=null ) {
		$new_parent_node = $this->repository->GetNodeByPath($trg_path);
		$trg_segment_name = $new_name===null ? $this->path_segment : mj_make_filename($new_name);

		if( ($target_existing_node=$new_parent_node->GetChildByPathSegment($trg_segment_name))!==null ) {
			// there already exists a node at target
			if( $overwrite_mode==='replace-target' ) {
				// remove target - it will be completely replaced with this folder and all its contents
				$target_existing_node->DeleteSelf();
				// continue below ...
			} else if( $overwrite_mode==='integrate-into-target' ) {
				if( $target_existing_node->IsFolder() ) {
					// integrate into target folder (instead of replacing it)
					$childs_trg_path = $target_existing_node->GetPath();
					foreach( $this->GetChildren() as $tc ) {
						$tc->MoveToPath($childs_trg_path,$tc->GetPathSegment(),$overwrite_mode);
					}
					// and delete self
					$this->DeleteSelf();
					return true;
				} else {
					// remove target - it will be completely replaced with this folder and all its contents
					$target_existing_node->DeleteSelf();
					// continue below ...
				}
			} else {
				throw new MjCObjectExistsAtTargetException('object exists at target: path='.$trg_path.'; segment='.$trg_segment_name );
			}
		}

		$new_parent_cat_id = $new_parent_node->GetFolderId();
		if( ($old_parent_node=$this->GetParentNode())!==null ) $old_parent_node->InvalidateChildCaches();
		$new_parent_node->InvalidateChildCaches();
		if( $this->repository->GetAutoSortFolderChildren() ) {
			$after_id = 0;
			$sort_alphabetically_into_parent = false;
		} else {
			$after_id = 0;
			$sort_alphabetically_into_parent = true;
		}
		$tmp = $this->repository->GetServer()->CategoryMove($this->folder_id,$new_parent_cat_id,$after_id,$sort_alphabetically_into_parent);
		if( $tmp===false ) return false;

		$this->path_segment = $this->attributes['filename'] = mj_make_filename($tmp['name']);
		if( $new_name!==null ) {
			$tmp = $this->repository->GetServer()->CategoryUpdate($this->folder_id,$new_name);
			if( $tmp===false ) return false;
			$this->path_segment = $this->attributes['filename'] = mj_make_filename($new_name);
		}
		$this->repository->EnsureFolderChildrenSorted($new_parent_cat_id);
		$this->repository->InvalidateCategoryCaches();
		return true;
	}

	public function IsFolder() {
		return true;
	}
}


class MjCRepository {

	/**
	 * @var ManjaServer
	 */
	private $server = null;

	/**
	 * @var integer
	 */
	private $root_tree_id = null;

	/**
	 * @var string
	 */
	private $root_category_id = null;

	/**
	 * @var string
	 */
	private $root_node_id = null;

	/**
	 * @var bool
	 */
	private $do_sort_categories = false;

	/**
	 * result of CategoryList()
	 * @var array
	 */
	private $categories = array();

	/**
	 * array from paths (e.g. "/foo/bar") to already allocated folder nodes
	 * @var MjCFolder[]
	 */
	private $folders_by_path_cache = array();

	/**
	 * array from category/folder id to already allocated folder nodes
	 * @var MjCFolder[]
	 */
	private $folders_by_id_cache = array();


	/**
	 * repository constructor
	 * - a repository is defined by a tree_id & a 
	 * Enter description here ...
	 * @param unknown_type $server
	 */
	public function __construct( ManjaServer $server, $root_tree_id=1, $root_category_id=1, $do_sort_categories=false ) {
		$this->server = $server;
		$this->root_tree_id = $root_tree_id;
		$this->root_category_id = $root_category_id;
		$this->do_sort_categories = $do_sort_categories;
		$this->root_node_id = $this->CreateFolderNodeId($root_category_id);
	}

	public function __destruct() {
		if( $this->server!==null ) $this->server->Disconnect();
	}

	/**
	 * return manja server communication object
	 * @return ManjaServer
	 */
	public function GetServer() {
		return $this->server;
	}

	public function GetRootTreeId() {
		return $this->root_tree_id;
	}

	public function GetRootFolderId() {
		return $this->root_category_id;
	}

	private function _normalize_folder_name( $fn ) {
		return mj_make_filename(mj_normalize_unicode_input($fn));
	}

	private function _load_category( $cat_id ) {
		$cmeta = $this->server->FolderGet($cat_id);
		$cmeta['filename'] = $cat_id==$this->root_category_id ? '' : $this->_normalize_folder_name($cmeta['name']);
		$this->categories[$cat_id] = $cmeta;
		return $cat_id;
	}

	private function _load_category_children( $parent_cat_id ) {
		$children = $this->server->FolderCategoryList($parent_cat_id);
		foreach( $children as $cat_id => $cmeta ) {
			$cmeta['filename'] = $this->_normalize_folder_name($cmeta['name']);
			$this->categories[$cat_id] = $cmeta;
		}
		return array_keys($children);
	}

	public function GetAutoSortFolderChildren() {
		return $this->do_sort_categories;
	}

	public function EnsureFolderChildrenSorted( $folder_id ) {
		if( $this->do_sort_categories ) {
			$this->server->CategorySort($folder_id,false);
		}
	}

	public function InvalidateCategoryCaches() {
		$this->categories = array();
		$this->folders_by_path_cache = array();
		$this->folders_by_id_cache = array();
	}

	/**
	 * returns root folder of the repository
	 * @return MjCFolder
	 */
	public function GetRootFolder() {
		if( isset($this->folders_by_path_cache['/']) ) return $this->folders_by_path_cache['/'];
		return $this->folders_by_path_cache['/'] = $this->GetFolderNodeById($this->root_node_id);
	}

	/**
	 * retrieve node by id
	 * @param string $node_id
	 * @return MjCNode
	 */
	public function GetNodeById( $node_id ) {
		if( strncmp($node_id,'d_',2)===0 /*$this->IsDocumentNodeId($node_id)*/ ) return $this->GetDocumentNodeById($node_id);
		else if( strncmp($node_id,'f_',2)===0 /*$this->IsFolderNodeId($node_id)*/ ) return $this->GetFolderNodeById($node_id);
		throw new MjCInvalidArgumentException( 'invalid node id syntax: node_id=' . $node_id );
	}

	/**
	 * retrieve a document node by id
	 * @param string $node_id
	 * @return MjCDocument
	 */
	public function GetDocumentNodeById( $node_id ) {
		$media_id = $this->CreateDocumentIdFromNodeId($node_id);
		$media_info = $this->server->MediaInfo(array($media_id),$this->GetDocumentNodeRequiredMetaIds());
		if( count($media_info['meta'])===0 && count($media_info['categories'])===0 ) throw new MjCObjectNotFoundException( 'object not found(A): node_id=' . $node_id );
		$meta = isset($media_info['meta'][$media_id]) ? $media_info['meta'][$media_id] : array();
		$cats = isset($media_info['categories'][$media_id]) ? $media_info['categories'][$media_id] : array();
		$parent_node_id = null;
		if( isset($cats[$this->root_tree_id]) ) {
			foreach( $cats[$this->root_tree_id] as $cat_id ) {
				if( !isset($this->categories[$cat_id]) ) $this->_load_category($cat_id);
				if( isset($this->categories[$cat_id]) ) {
					$parent_node_id = $this->CreateFolderNodeId($cat_id);
					break;
				}
			}
		}
		return $this->CreateDocumentNode2($media_id,$parent_node_id,$meta);
	}

	/**
	 * retrieve a folder node by id
	 * @param string $node_id
	 * @return MjCFolder
	 */
	public function GetFolderNodeById( $node_id ) {
		$folder_id = $this->CreateFolderIdFromNodeId($node_id);
		if( isset($this->folders_by_id_cache[$folder_id]) ) return $this->folders_by_id_cache[$folder_id];
		if( !isset($this->categories[$folder_id]) ) {
			$this->_load_category($folder_id);
			if( !isset($this->categories[$folder_id]) ) throw new MjCObjectNotFoundException( 'object not found(B): node_id=' . $node_id );
		}
		return $this->CreateFolderNode2($folder_id,$this->categories[$folder_id]);
	}

	/**
	 * retrieve list of subfolders of given node id
	 * @param string $node_id
	 * @param boolean $skip_parent_validation
	 * @return MjCFolder[]
	 */
	public function GetSubFolderNodesById( $node_id, $skip_parent_validation=false ) {
		$folder_id = $this->CreateFolderIdFromNodeId($node_id);
		if( $skip_parent_validation===false ) {
			if( !isset($this->categories[$folder_id]) ) {
				$this->_load_category($folder_id);
				if( !isset($this->categories[$folder_id]) ) throw new MjCObjectNotFoundException( 'object not found(C): node_id=' . $node_id );
			}
		}
		$subfolders = array();
		foreach( $this->_load_category_children($folder_id) as $cand_cat_id ) {
			if( isset($this->folders_by_id_cache[$cand_cat_id]) ) $subfolders[] = $this->folders_by_id_cache[$cand_cat_id];
			else $subfolders[] = $this->CreateFolderNode2($cand_cat_id,$this->categories[$cand_cat_id]);
		}
		return $subfolders;
	}

	/**
	 * retrieve folder node with given $path_segment, which is a sub-folder of given node id
	 * - optimized for performance in cases where "folders_by_id_cache" should not be altered 
	 * @param string $node_id
	 * @param string $path_segment
	 * @return MjCFolder
	 */
	public function TryGetSubFolderNodeByPathSegment( $node_id, $path_segment, $skip_parent_validation=false ) {
		$folder_id = $this->CreateFolderIdFromNodeId($node_id);
		if( $skip_parent_validation===false ) {
			if( !isset($this->categories[$folder_id]) ) {
				$this->_load_category($folder_id);
				if( !isset($this->categories[$folder_id]) ) throw new MjCObjectNotFoundException( 'object not found(D): node_id=' . $node_id );
			}
		}
		$subfolders = array();
		foreach( $this->_load_category_children($folder_id) as $cand_cat_id ) {
			$cand_cmeta = $this->categories[$cand_cat_id];
			if( $path_segment===$cand_cmeta['filename'] ) {
				if( isset($this->folders_by_id_cache[$cand_cat_id]) ) return $this->folders_by_id_cache[$cand_cat_id];
				else return $this->CreateFolderNode2($cand_cat_id,$cand_cmeta);
			}
		}
		return null;
	}

	/**
	 * retrieve document node with given $path_segment, which is a child of given parent node id
	 * - optimized for performance
	 * @param string $parent_node_id
	 * @param string $path_segment
	 * @return MjCDocument
	 */
	public function TryGetDocumentNodeByPathSegment( $parent_node_id, $path_segment ) {
		// search for a document
		$folder_id = $this->CreateFolderIdFromNodeId($parent_node_id);
		$media_list = $this->server->FolderMediaGet($folder_id,$path_segment,$this->GetDocumentNodeRequiredMetaIds());
		$mlmeta = isset($media_list['meta']) ? $media_list['meta'] : array();
		foreach( $media_list['media_ids'] as $cmid ) {
			// use first match
			$doc_meta = isset($mlmeta[$cmid]) ? $mlmeta[$cmid] : array();
			return $this->CreateDocumentNode2($cmid,$parent_node_id,$doc_meta,null);
		}
		return null;
	}


	/**
	 * internal node retrieval by path
	 * @param MjCNode $node
	 * @param string $node_path_str
	 * @param array $path_segments
	 * @return MjCNode
	 */
	private function _get_descendant_node_by_path_segments( MjCNode $node, $node_path_str, array $path_segments ) {
		//$node_path_str = $node->GetPath();
		$psc = count($path_segments);
		for( $i=0; $i<$psc; ++$i ) {
			$segment = $path_segments[$i];
			if( ($child_node=$node->GetChildByPathSegment($segment,false))===null ) throw new MjCObjectNotFoundException( 'object not found(1): path='.$node_path_str.'; segment='.$segment );
			$node = $child_node;
			if( $node->IsFolder() ) {
				$node_path_str = ($node_path_str==='/') ? ('/'.$segment) : ($node_path_str.'/'.$segment);
				$this->folders_by_path_cache[$node_path_str] = $node;
			} else {
				if( $i!=$psc-1 ) throw new MjCObjectNotFoundException( 'object not found(2): path='.$node_path_str.'; segment='.$segment );
				break;
			}
		}
		return $node;
	}

	/**
	 * retrieve node by path
	 * @param string|MjCPath $path
	 * @return MjCNode
	 */
	public function GetNodeByPath( $path ) {
		if( !( $path instanceof MjCPath ) ) $path = new MjCPath($path);
		// check path cache, navigate down on path segments from leaf to root
		$cnt_ps = count($path->segments);
		if( $cnt_ps===0 ) {
			// = the root folder node
			return $this->GetRootFolder();
		}
		if( count($this->folders_by_path_cache) ) {
			$str_path = (string)$path;
			if( isset($this->folders_by_path_cache[$str_path]) ) {
				// a node matching "full path" exists in cache...
				return $this->folders_by_path_cache[$str_path];
			}
			// walk path down to root, use any folder node that exists in cache as a starting node for walking up to leaf ...
			for( $x=$cnt_ps-1; $x>=0; --$x ) {
				$test_path = $path->GetSubPath($x);
				$str_path = (string)$test_path;
				if( isset($this->folders_by_path_cache[$str_path]) ) {
					// found folder node for path of $x'th segment in cache ...
					$cached_folder_node = $this->folders_by_path_cache[$str_path];
					// navigate from cached node up to the actual leaf node ...
					return $this->_get_descendant_node_by_path_segments($cached_folder_node,$str_path,array_slice($path->segments,$x));
				}
			}
		}
		// no cache available: navigate full hierarchy from root up to actual leaf node of path ...
		return $this->_get_descendant_node_by_path_segments($this->GetRootFolder(),'/',$path->segments);
	}


	public function CreateDocumentNodeId( $doc_id )			{ return 'd_'.$doc_id; }
	public function CreateFolderNodeId( $folder_id )		{ return 'f_'.$folder_id; }
	public function IsDocumentNodeId( $node_id )			{ return strncmp($node_id,'d_',2)===0; }
	public function IsFolderNodeId( $node_id )				{ return strncmp($node_id,'f_',2)===0; }
	public function CreateDocumentIdFromNodeId( $node_id )	{ return substr($node_id,2); }
	public function CreateFolderIdFromNodeId( $node_id )	{ return substr($node_id,2); }


	/**
	 * @returns array of meta_id numbers that are required for document nodes
	 */
	public function GetDocumentNodeRequiredMetaIds() {
		return array(-6,-5,-4,-3,-2,-1,1,5,101);
	}

	/**
	 * Utility function for creation of document nodes
	 * @param string $media_id
	 * @param string $parent_node_id
	 * @param array $meta
	 * @param string $filename
	 * @return MjCDocument
	 */
	public function CreateDocumentNode2( $media_id, $parent_node_id, $meta, $filename=null ) {
		if( $filename===null ) $filename = isset($meta[1]) ? mj_make_filename($meta[1][0]) : null;
		$attrs = array(	'node_id'			=> 'd_'.$media_id,  							// $this->CreateDocumentNodeId($media_id)
						'document_id'		=> $media_id,
						'parent_node_id'	=> $parent_node_id,
						'created'			=> isset($meta[ -6]) ? $meta[ -6][0] : null,
						'created_by'		=> isset($meta[ -5]) ? $meta[ -5][0] : null,
						'modified'			=> isset($meta[ -4]) ? $meta[ -4][0] : null,
						'modified_by'		=> isset($meta[ -3]) ? $meta[ -3][0] : null,
						'content_type'		=> isset($meta[ -2]) ? $meta[ -2][0] : null,
						'media_class'		=> isset($meta[ -1]) ? $meta[ -1][0] : null,
						'content_length'	=> isset($meta[  5]) ? $meta[  5][0] : null,
						'name'				=> isset($meta[101]) ? implode(',',$meta[101]) : null,
						'filename'			=> $filename,
			);
		return $this->CreateDocumentNode($attrs);											// <-- note: we have to use document node factory
	}

	/**
	 * Utility function for creation of folder nodes
	 * @param string $cat_id
	 * @param string $parent_node_id
	 * @param array $cmeta
	 * @return MjCFolder
	 */
	public function CreateFolderNode2( $cat_id, $cmeta ) {
		$parent_node_id = ($cmeta['parent']===0||$cmeta['parent']==='0') ? null : 'f_'.$cmeta['parent'];
		$attrs = array(	'node_id'			=> 'f_'.$cat_id,								// $this->CreateFolderNodeId($cat_id)
						'folder_id'			=> $cat_id,
						'parent_node_id'	=> $parent_node_id,
						'created'			=> substr($cmeta['crd_dt'],0,19),
						'created_by'		=> $cmeta['crd_by'],
						'modified'			=> substr($cmeta['mod_dt'],0,19),
						'modified_by'		=> $cmeta['mod_by'],
						'name'				=> $cmeta['name'],
						'filename'			=> isset($cmeta['filename']) ? $cmeta['filename'] : mj_make_filename($cmeta['name']),
			);
		$node = $this->CreateFolderNode($attrs);											// <-- note: we have to use folder node factory
		$this->folders_by_id_cache[$cat_id] = $node;
		return $node;
	}



	/**
	 * factory method for folder nodes
	 * @return MjCFolder
	 */
	public function CreateFolderNode( $attributes ) {
		return new MjCFolder($this,$attributes);
	}

	/**
	 * factory method for document nodes
	 * @return MjCDocument
	 */
	public function CreateDocumentNode( $attributes ) {
		return new MjCDocument($this,$attributes);
	}

}


