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
 * Document in repository model
 *
 */
class MjCDocument extends MjCNode {

	/**
	 * document id = media id
	 *
	 * @var string
	 */
	protected $document_id;

	public function __construct( MjCRepository $repository, array $attributes=[] ) {
		parent::__construct($repository,$attributes);
		$this->document_id = $attributes['document_id'];
	}


	public function GetDocumentId() : string {
		return $this->document_id;
	}

	public function SendToClient() {
		try {
			$result = $this->repository->GetServer()->MediumGet($this->document_id,true,null,['intent'=>'download']);
		} catch( mjServerError $e ) {
			throw new MjCObjectNotFoundException( 'object not found(4): node_id='.$this->node_id );
		}
	}

	public function GetContent() {
		$server = $this->repository->GetServer();
		$result = $server->MediumGet($this->document_id,false,null,['intent'=>'download']);
		if( $result!==false ) return $server->get_payload($result,true);
	}

	public function GetStream() {
		$server = $this->repository->GetServer();
		$result = $server->MediumGet($this->document_id,false,null,['exit'=>1,'intent'=>'download']);
		if( $result!==false ) return $server->get_stream($result,true);
	}

	public function SendRenditionToClient( string $par ) {
		$p = explode('_',$par);
		if( count($p)<2 ) throw new MjCInvalidArgumentError( 'SendRenditionToClient called with incomplete arguments' );
		$server = $this->repository->GetServer();
		$sfx = 'png'; // TODO: use 'jpg' when we know that the thumbnail will not have an alpha channel
		$ctype = $server->GetMediaTypeFromSuffix($sfx);
		$opts = [
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
		];
		//if( false ) {
		//	// send with filename
		//	$filename = $this->GetAttribute('filename');
		//	if( ($x=strrpos($filename,'.'))!==false ) $filename = substr($filename,0,$x) . '_'.$p[0].'x'.$p[1].'.'.$sfx;
		//	return $server->MediumPreview( $opts, true, $filename );
		//} else {
			// .. without filename - doesn't matter anyway
			return $server->MediumPreview( $opts, true );
		//}
	}

	/**
	 * Write document data
	 *
	 * @param resource|string $data
	 * @param int $size if $data is a stream resource, then this should be set to the size of the input, may be -1
	 *
	 * @return bool true on success
	 */
	public function WriteData( $data, int $size=-1 ) : bool {
		if( is_resource($data) ) {
			// $data is a stream, for example it could be php://input
			$tmp = $this->repository->GetServer()->MediumUploadFromStream($data,$size,$this->document_id,1,$this->GetAttribute('filename'));
		} else if( $data==='' && $size===0 ) {
			// special marker for an "empty" input stream -> this will truncate the asset data
			$tmp = $this->repository->GetServer()->MediumUploadFromStream(null,0,$this->document_id,1,$this->GetAttribute('filename'));
		} else {
			// $data is a path to a (temporary) file on this host
			$tmp = $this->repository->GetServer()->MediumUpload($data,$this->document_id,1,$this->GetAttribute('filename'));
		}
		if( $tmp===false ) return false;
		return true;
	}

	/**
	 * Delete this document
	 *
	 * @param bool $rebuild_temp_file_references
	 *
	 * @return bool true on success
	 */
	public function DeleteSelf( bool $rebuild_temp_file_references=true ) : bool {
		throw new MjCNotSupportedException('deleting files is not supported here');
		// $parent_node = $this->GetParentNode();
		// $in_out_media_ids = [$this->document_id];
		// $result = ManjaWebUtil::mjwsu_media_delete(null,$this->repository->GetServer(),$in_out_media_ids,$rebuild_temp_file_references,true);
		// if( $result===false || !isset($result['media_ids']) ) return false;
		// $deleted_ids = $result['media_ids'];
		// if( !in_array($this->document_id,$deleted_ids) ) return false;
		// if( $parent_node!==null ) $parent_node->InvalidateChildCaches();
		// return true;
	}

	/**
	 * Rename document
	 *
	 * @param string $new_name
	 *
	 * @return bool true on success
	 */
	public function Rename( string $new_name ) : bool {
		$meta = ['1'=>[$new_name]];
		if( ($tmp=$this->repository->GetServer()->MediaUpdate([$this->document_id],$meta,[]))===false ) return false;
		$this->path_segment = $this->attributes['filename'] = mj_make_filename($new_name);
		return true;
	}


	/**
	 * Copy/Clone document to specified location in tree
	 *
	 * @param string $trg_path					target parent node
	 * @param string|null $new_name				target name of cloned node
	 * @param string|null $overwrite_mode		TODO
	 *
	 * @return MjCNode							cloned document node
	 */
	public function CopyToPath( string $trg_path, string $new_name=null, string $overwrite_mode=null ) : MjCNode {
		$trg_parent_node = $this->repository->GetNodeByPathString($trg_path);
		if( !($trg_parent_node instanceof MjCFolder) ) throw new MjCNotSupportedException('object at target path must be a folder: trg_path='.$trg_path);
		$trg_parent_folder = $trg_parent_node;

		$trg_segment_name = $new_name===null ? $this->path_segment : mj_make_filename($new_name);
		if( ($trg_existing_node=$trg_parent_folder->GetChildByPathSegment($trg_segment_name))!==null ) {
			// there already exists a node at target
			throw new MjCObjectExistsAtTargetException('object exists at clone target: path='.$trg_path.'; segment='.$trg_segment_name);
		}

		if( ($tmp=$this->repository->GetServer()->MediumClone($this->document_id,null,$trg_parent_folder->GetFolderId(),$trg_segment_name))===false ) {
			throw new MjCError('failed to clone document: path='.$trg_path.'; segment='.$trg_segment_name);
		}
		$new_media_id = $tmp['media_id'];

		$trg_parent_folder->InvalidateChildCaches();

		return $this->repository->GetDocumentNodeByDocumentId($new_media_id);
	}


	/**
	 * Move document to new location and optionally rename the node
	 *
	 * @param string $trg_path
	 * @param string|null $new_name
	 * @param string|null $overwrite_mode
	 *
	 * @return bool true on success
	 */
	public function MoveToPath( string $trg_path, string $new_name=null, string $overwrite_mode=null ) : bool {
		$trg_parent_node = $this->repository->GetNodeByPathString($trg_path);
		if( !($trg_parent_node instanceof MjCFolder) ) throw new MjCNotSupportedException('object at target path must be a folder: trg_path='.$trg_path);
		$trg_parent_folder = $trg_parent_node;

		$trg_segment_name = $new_name===null ? $this->path_segment : mj_make_filename($new_name);
		if( ($trg_existing_node=$trg_parent_folder->GetChildByPathSegment($trg_segment_name))!==null ) {
			// there already exists a node at target
			if( $overwrite_mode==='replace-target' ) {
				// remove target - it will be completely replaced with this document (also keeping the id of THIS document)
				$trg_existing_node->DeleteSelf();
				// continue below ...
			} else if( $overwrite_mode==='integrate-into-target' ) {
				if( $trg_existing_node instanceof MjCFolder ) {
					// remove target folder, so it can be replaced with this file
					$trg_existing_node->DeleteSelf();
					// continue below ...
				} else if( $trg_existing_node instanceof MjCDocument ) {
					// clone contents of this file(data & properties) to target file, then delete this file
					if( ($tmp=$this->repository->GetServer()->MediumClone($this->document_id,$trg_existing_node->GetDocumentId()))===false ) return false;
					return $this->DeleteSelf();
				}
			} else {
				throw new MjCObjectExistsAtTargetException('object exists at target: path='.$trg_path.'; segment='.$trg_segment_name);
			}
		}
		$new_parent_cat_id = $trg_parent_folder->GetFolderId();
		$meta = $new_name===null ? [] : ['1'=>[$new_name]];
		$categories = [ $this->repository->GetRootTreeId() => $new_parent_cat_id ];
		if( ($tmp=$this->repository->GetServer()->MediaUpdate([$this->document_id],$meta,$categories))===false ) return false;
		$this->path_segment = $this->attributes['filename'] = mj_make_filename($new_name);
		return true;
	}

	public function SetTime( string $type, $ts ) : bool {
		if( $ts instanceof \DateTime ) $ts = $ts->format('Y-m-d H:i:s.uP');
		if( $type==='modified' ) {
			if( ($tmp=$this->repository->GetServer()->MediaUpdate([$this->document_id],['-4'=>[$ts]],[],true))===false ) return false;
			return true;
		} else if( $type==='created' ) {
			if( ($tmp=$this->repository->GetServer()->MediaUpdate([$this->document_id],['-6'=>[$ts]],[],true))===false ) return false;
			return true;
		}
		throw new MjCInvalidArgumentError('MjCDocument::SetTime() called with unknown type='.$type);
	}

	public function IsFolder() : bool {
		return false;
	}

}

