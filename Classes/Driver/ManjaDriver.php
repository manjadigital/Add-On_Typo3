<?php
namespace Jokumer\FalManja\Driver;

use Jokumer\FalManja\Service\ManjaConnector;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class ManjaDriver
 *
 * @package TYPO3
 * @subpackage tx_falmanja
 * @author J. Kummer <typo3@enobe.de>
 * @author Falk Roeder <mail@falk-roeder.de>
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class ManjaDriver extends AbstractHierarchicalFilesystemDriver
{

    /**
     * TYPO3 file system driver type
     *
     * @const string
     */
    const DRIVER_TYPE = 'fal_manja';

    /**
     * Own manja connector instance
     *
     * @var \Jokumer\FalManja\Service\ManjaConnector
     */
    protected $manjaConnector = null;

    /**
     * API manja server instance
     *
     * @var \ManjaServer
     */
    protected $manjaServer = null;

    /**
     * API manja repository instance
     *
     * @var \ManjaServer
     */
    protected $manjaRepository = null;

    /**
     * Cache
     *
     * @var VariableFrontend
     */
    private $cache;

    /**
     * CacheControl
     *
     * @var string
     */
    private $cacheControl = '';

    /**
     * CacheManager
     *
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     */
    protected $cacheManager;

    /**
     * ObjectManager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Define root folder identfifier
     * Default would be '/'
     */
    const ROOT_FOLDER_IDENTIFIER = '/';

    /**
     * Initialize this driver and expose the capabilities for the repository to use
     * Exclude CAPABILITY_WRITABLE which should be set to '0' cause modification of files and folders are not supported
     * 
     * @param array $configuration
     */
    public function __construct(array $configuration = [])
    {
        parent::__construct($configuration);
        $this->capabilities = ResourceStorage::CAPABILITY_BROWSABLE | ResourceStorage::CAPABILITY_PUBLIC; // Exclude ResourceStorage::CAPABILITY_WRITABLE
    }

    /**
     * Processes the configuration for this driver.
     */
    public function processConfiguration()
    {
        if (!empty($this->configuration['cacheControl'])) {
            $this->cacheControl = $this->configuration['cacheControl'];
        }
    }

    /**
     * Initializes this object. This is called by the storage after the driver
     * has been attached.
     *
     * @return void
     */
    public function initialize()
    {
        // Get cache
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->cacheManager = $this->objectManager->get(CacheManager::class);
        $this->cache = $this->cacheManager->getCache('fal_manja');
        // Process connection to manja - instantiates manja server class
        $this->manjaConnector = new ManjaConnector($this->configuration);
        $this->manjaServer = $this->manjaConnector->processConnection();
        if ($this->manjaConnector->getConnectionStatus()) {
            // Instantiate manja repository model class
            $this->manjaRepository = GeneralUtility::makeInstance(\MjCRepository::class, $this->manjaServer);
            /*
             * Require once manja utility php file by instantiate any class from it.
             * Otherwise it needs to be required by path, which will not be future proof
             * require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('fal_manja') . '/Resources/Private/Vendor/manja-api/inc_util.php';
             */
            GeneralUtility::makeInstance('mjException', '');
        }
    }

    /**
     * Merges the capabilities merged by the user at the storage
     * configuration into the actual capabilities of the driver
     * and returns the result.
     *
     * @param int $capabilities
     * @return int
     */
    public function mergeConfigurationCapabilities($capabilities)
    {
        // See constructor method where the capabilities for the repository are exposed
        $this->capabilities &= $capabilities;
        return $this->capabilities;
    }

    /**
     * Returns the identifier of the root level folder of the storage.
     *
     * @return string
     */
    public function getRootLevelFolder()
    {
        return '/';
    }

    /**
     * Returns the identifier of the default folder new files should be put into.
     *
     * @return string
     */
    public function getDefaultFolder()
    {
        return $this->getRootLevelFolder();
    }

    /**
     * Checks if a folder exists.
     *
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExists($folderIdentifier)
    {
        $folderExists = false;
        if ($this->manjaRepository instanceof \MjCRepository) {
            /** @var \MjCFolder $folder */
            $folder = $this->manjaRepository->GetNodeByPath($folderIdentifier);
            if ($folder instanceof \MjCFolder && $folder->IsFolder()) {
                $folderExists = true;
            }
        }
        return $folderExists;
    }

    /**
     * Returns information about a folder
     *
     * @param string $folderIdentifier
     * @return array
     * @throws FolderDoesNotExistException
     */
    public function getFolderInfoByIdentifier($folderIdentifier)
    {
        // @todo: Solve processed folder - should be local an not at external manja storage
        if ($folderIdentifier === '_processed_') {
            throw new \RuntimeException(
                'The processed folder must be local! Configure storage like \'0:/typo3temp/assets/_processed_\' which stores processed files in typo3temp.',
                1519867205
            );
        }
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        return [
            'identifier' => $folderIdentifier,
            'name' => PathUtility::basename($folderIdentifier),
            'storage' => $this->storageUid
        ];
    }

    /**
     * Returns the permissions of a file/folder as an array
     * (keys r, w) of boolean flags
     *
     * @param string $identifier
     * @return array
     */
    public function getPermissions($identifier)
    {
        return [
            'r' => true,
            'w' => true
        ];
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @return int Number of files in folder
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filenameFilterCallbacks = [])
    {
        return count($this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filenameFilterCallbacks));
    }

    /**
     * Returns the number of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @return int Number of folders in folder
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $folderNameFilterCallbacks = [])
    {
        return count($this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $folderNameFilterCallbacks));
    }

    /**
     * Returns the identifier of a file inside the folder
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return string file identifier
     */
    public function getFileInFolder($fileName, $folderIdentifier)
    {
        return $this->canonicalizeAndCheckFileIdentifier($folderIdentifier . '/' . $fileName);
    }

    /**
     * Returns a list of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array of FileIdentifiers
     */
    public function getFilesInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $filenameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        $files = [];
        if ($this->manjaRepository instanceof \MjCRepository) {
            /** @var \MjCFolder $node */
            $node = $this->manjaRepository->GetNodeByPath($folderIdentifier);
            if ($node->GetTotalDocumentCount()) {
                $nodeFiles = $node->GetDocuments();
                if (!empty($nodeFiles)) {
                    /** @var \MjCDocument $nodeFile */
                    foreach ($nodeFiles as $nodeFile) {
                        $currentFileIdentifier = $folderIdentifier . $nodeFile->GetPathSegment(); // Prepend parent folder identifier
                        $files[] = $currentFileIdentifier;
                    }
                }
            }
        }
        return $files;
    }

    /**
     * Returns the identifier of a folder inside the folder
     *
     * @param string $folderName The name of the target folder
     * @param string $folderIdentifier
     * @return string folder identifier
     */
    public function getFolderInFolder($folderName, $folderIdentifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier . '/' . $folderName);
        return $folderIdentifier;
    }

    /**
     * Returns a list of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array of Folder Identifier
     */
    public function getFoldersInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $folderNameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        $folders = [];
        if ($this->manjaRepository instanceof \MjCRepository) {
            /** @var \MjCFolder $folder */
            $folder = $this->manjaRepository->GetNodeByPath($folderIdentifier);
            $subFolders = $folder->GetFolders();
            if (!empty($subFolders)) {
                /** @var \MjCFolder $subFolder */
                foreach ($subFolders as $subFolder) {
                    $currentFolderIdentifier = $folderIdentifier . $subFolder->GetPathSegment(); // Prepend parent folder identifier
                    $folders[] = $this->canonicalizeAndCheckFolderIdentifier($currentFolderIdentifier);
                }
            }
        }
        return $folders;
    }

    /**
     * Checks if a given identifier is within a container, e.g. if
     * a file or folder is within another folder.
     * This can e.g. be used to check for web-mounts.
     *
     * Hint: this also needs to return TRUE if the given identifier
     * matches the container identifier to allow access to the root
     * folder of a filemount.
     *
     * @param string $folderIdentifier
     * @param string $identifier identifier to be checked against $folderIdentifier
     * @return bool TRUE if $content is within or matches $folderIdentifier
     */
    public function isWithin($folderIdentifier, $identifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $entryIdentifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
        if ($folderIdentifier === $entryIdentifier) {
            return true;
        }
        // File identifier canonicalization will not modify a single slash so
        // we must not append another slash in that case.
        if ($folderIdentifier !== '/') {
            $folderIdentifier .= '/';
        }
        return GeneralUtility::isFirstPartOfStr($entryIdentifier, $folderIdentifier);
    }

    /**
     * Checks if a file exists.
     *
     * @param string $fileIdentifier
     * @return bool
     */
    public function fileExists($fileIdentifier)
    {
        $file = $this->getDocumentByIdentifier($fileIdentifier);
        if ($file instanceof \MjCDocument) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns information about a file.
     *
     * @param string $fileIdentifier
     * @param array $propertiesToExtract Array of properties which are be extracted
     *                                   If empty all will be extracted
     * @return array
     * @throws FileDoesNotExistException
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = [])
    {
        $file = $this->getDocumentByIdentifier($fileIdentifier);
        if ($file instanceof \MjCDocument) {
            return array(
                'name' => $file->GetAttribute('filename'),
                'identifier' => $fileIdentifier,
                'identifier_hash' => $this->hashIdentifier($fileIdentifier),
                'folder_hash' => $this->hashIdentifier($this->getParentFolderIdentifierOfIdentifier($fileIdentifier)),
                'atime' => $GLOBALS['EXEC_TIME'],
                'mtime' => strtotime($file->GetAttribute('modified')),
                'ctime' => strtotime($file->GetAttribute('created')),
                'mimetype' => $file->GetAttribute('content_type'),
                'size' => (int)$file->GetAttribute('content_length'),
                'storage' => $this->storageUid
            );
        }
    }

    /**
     * Creates a hash for a file.
     *
     * @param string $fileIdentifier
     * @param string $hashAlgorithm The hash algorithm to use
     * @return string
     */
    public function hash($fileIdentifier, $hashAlgorithm)
    {
        // @todo: Adapt for different hash algorithm
        switch ($hashAlgorithm) {
            case 'sha1':
                return sha1($fileIdentifier);
                break;
        }
    }

    /**
     * Returns a path to a local copy of a file for processing it. When changing the
     * file, you have to take care of replacing the current version yourself!
     *
     * @param string $fileIdentifier
     * @param bool $writable Set this to FALSE if you only need the file for read
     *                       operations. This might speed up things, e.g. by using
     *                       a cached local version. Never modify the file if you
     *                       have set this flag!
     * @return string The path to the file on the local disk
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        return $this->copyFileToTemporaryPath($fileIdentifier);
    }

    /**
     * Directly output the contents of the file to the output
     * buffer. Should not take care of header files or flushing
     * buffer before. Will be taken care of by the Storage.
     *
     * @param string $fileIdentifier
     * @return void
     */
    public function dumpFileContents($fileIdentifier)
    {
        /** @var \MjCDocument $document */
        $document = $this->getDocumentByIdentifier($fileIdentifier);
        if ($document instanceof \MjCDocument) {
            $handle = fopen('php://output', 'w');
            fputs($handle, $document->GetContent());
            fclose($handle);
        }
    }

    /**
     * Creates a folder, within a parent folder.
     * If no parent folder is given, a root level folder will be created
     *
     * @param string $newFolderName
     * @param string $parentFolderIdentifier
     * @param bool $recursive
     * @return string the Identifier of the new folder
     */
    public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = false)
    {
        // Feature not available
    }

    /**
     * Returns the public URL to a file.
     * Either fully qualified URL or relative to PATH_site (rawurlencoded).
     *
     * @param string $identifier
     * @return string
     */
    public function getPublicUrl($identifier)
    {
        // Feature not available
    }

    /**
     * Renames a folder in this storage.
     *
     * @param string $folderIdentifier
     * @param string $newName
     * @return array A map of old to new file identifiers of all affected resources
     */
    public function renameFolder($folderIdentifier, $newName)
    {
        // Feature not available
    }

    /**
     * Removes a folder in filesystem.
     *
     * @param string $folderIdentifier
     * @param bool $deleteRecursively
     * @return bool
     * @throws FileOperationErrorException
     */
    public function deleteFolder($folderIdentifier, $deleteRecursively = false)
    {
        // Feature not available
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @param string $folderIdentifier
     * @return bool TRUE if there are no files and folders within $folder
     */
    public function isFolderEmpty($folderIdentifier)
    {
        // Feature not available
    }

    /**
     * Adds a file from the local server hard disk to a given path in TYPO3s
     * virtual file system. This assumes that the local file exists, so no
     * further check is done here! After a successful the original file must
     * not exist anymore.
     *
     * @param string $localFilePath (within PATH_site)
     * @param string $targetFolderIdentifier
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed
     *                                after successful operation
     * @return string the identifier of the new file
     */
    public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = true)
    {
        // Feature not available
    }

    /**
     * Creates a new (empty) file and returns the identifier.
     *
     * @param string $fileName
     * @param string $parentFolderIdentifier
     * @return string
     * @throws InvalidFileNameException
     */
    public function createFile($fileName, $parentFolderIdentifier)
    {
        // Feature not available
    }

    /**
     * Copies a file *within* the current storage.
     * Note that this is only about an inner storage copy action,
     * where a file is just copied to another folder in the same storage.
     *
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $fileName
     * @return string the Identifier of the new file
     */
    public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName)
    {
        // Feature not available
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $fileIdentifier
     * @param string $newName The target path (including the file name!)
     * @return string The identifier of the file after renaming
     * @throws ExistingTargetFileNameException
     */
    public function renameFile($fileIdentifier, $newName)
    {
        // Feature not available
    }

    /**
     * Replaces a file with file in local file system.
     *
     * @param string $fileIdentifier
     * @param string $localFilePath
     * @return bool TRUE if the operation succeeded
     */
    public function replaceFile($fileIdentifier, $localFilePath)
    {
        // Feature not available
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @param string $fileIdentifier
     * @return bool TRUE if deleting the file succeeded
     */
    public function deleteFile($fileIdentifier)
    {
        // Feature not available
    }

    /**
     * Moves a file *within* the current storage.
     * Note that this is only about an inner-storage move action,
     * where a file is just moved to another folder in the same storage.
     *
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFileName
     * @return string
     */
    public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName)
    {
        // Feature not available
    }

    /**
     * Folder equivalent to moveFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     * @return array All files which are affected, map of old => new file identifiers
     * @throws FolderDoesNotExistException
     */
    public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        // Feature not available
    }

    /**
     * Folder equivalent to copyFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     * @return bool
     */
    public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        // Feature not available
    }

    /**
     * Returns the contents of a file. Beware that this requires to load the
     * complete file into memory and also may require fetching the file from an
     * external location. So this might be an expensive operation (both in terms
     * of processing resources and money) for large files.
     *
     * @param string $fileIdentifier
     * @return string The file contents
     */
    public function getFileContents($fileIdentifier)
    {
        // Feature not available
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @param string $fileIdentifier
     * @param string $contents
     * @return int The number of bytes written to the file
     */
    public function setFileContents($fileIdentifier, $contents)
    {
        // Feature not available
    }

    /**
     * Checks if a file inside a folder exists
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return bool
     */
    public function fileExistsInFolder($fileName, $folderIdentifier)
    {
        // Feature not available
    }

    /**
     * Checks if a folder inside a folder exists.
     *
     * @param string $folderName
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExistsInFolder($folderName, $folderIdentifier)
    {
        // Feature not available
    }

    /**
     * Makes sure the folder identifier given as parameter is valid
     *
     * @param string $folderIdentifier The folder identifier
     * @return string
     */
    protected function canonicalizeAndCheckFolderIdentifier($folderIdentifier)
    {
        if ($folderIdentifier === '/') {
            $canonicalizedIdentifier = $folderIdentifier;
        } else {
            $canonicalizedIdentifier = rtrim($this->canonicalizeAndCheckFileIdentifier($folderIdentifier), '/') . '/';
        }
        return $canonicalizedIdentifier;
    }

    /**
     * Makes sure the file identifier given as parameter is valid
     *
     * @param string $fileIdentifier The file Identifier
     * @return string
     * @throws InvalidPathException
     */
    protected function canonicalizeAndCheckFileIdentifier($fileIdentifier)
    {
        if ($fileIdentifier !== '') {
            $fileIdentifier = $this->canonicalizeAndCheckFilePath($fileIdentifier);
            $fileIdentifier = '/' . ltrim($fileIdentifier, '/');
            if (!$this->isCaseSensitiveFileSystem()) {
                $fileIdentifier = strtolower($fileIdentifier);
            }
        }
        return $fileIdentifier;
    }

    /**
     * Makes sure the file path given as parameter is valid
     *
     * @param string $filePath The file path (including the file name!)
     * @return string
     * @throws InvalidPathException
     * @author Stefan Froemken <froemken@gmail.com>
     */
    protected function canonicalizeAndCheckFilePath($filePath)
    {
        $filePath = PathUtility::getCanonicalPath($filePath);
        // filePath must be valid
        // Special case is required by vfsStream in Unit Test context
        if (!GeneralUtility::validPathStr($filePath)) {
            throw new InvalidPathException('File ' . $filePath . ' is not valid (".." and "//" is not allowed in path).',
                1519677146);
        }
        return $filePath;
    }
    
    /**
     * Returns document object by file identifier (path)
     * Get \MjCDocument from cache or from repository and add to cache
     *
     * @param $fileIdentifier
     * @return mixed $document Instance of \MjCDocument or NULL if not found
     */
    protected function getDocumentByIdentifier($fileIdentifier)
    {
        $document = null;
        $cacheIdentifier = $this->getDocumentCacheByFileIdentifier($fileIdentifier);
        if ($this->cache->has($cacheIdentifier)) {
            // Get from cache
            $documentCache = $this->cache->get($cacheIdentifier);
            if ($documentCache instanceof \MjCDocument) {
                $document = $documentCache;
            }
        } else {
            // Get from reposiotry
            $filePath = $this->getParentFolderIdentifierOfIdentifier($fileIdentifier);
            $fileName = PathUtility::basename($fileIdentifier);
            if ($filePath && $fileName) {
                if ($this->manjaRepository instanceof \MjCRepository) {
                    /** @var \MjCFolder $folder */
                    $folder = $this->manjaRepository->GetNodeByPath($filePath);
                    if ($folder instanceof \MjCFolder) {
                        /** @var \MjCDocument $file */
                        $file = $folder->GetChildByPathSegment($fileName);
                        if ($file instanceof \MjCDocument) {
                            $document = $file;
                            // Add to cache
                            $this->setDocumentCacheByFileIdentifier($document, $fileIdentifier);
                        }
                    }
                }
            }
        }
        return $document;
    }

    /**
     * Returns document id by file identifier (path)
     *
     * @param $fileIdentifier
     * @return int $documentId
     */
    protected function getDocumentIdByIdentifier($fileIdentifier)
    {
        $documentId = null;
        /** @var \MjCDocument $document */
        $document = $this->getDocumentByIdentifier($fileIdentifier);
        if ($document instanceof \MjCDocument) {
            $documentId = $document->GetDocumentId();
        }
        return $documentId;
    }

    /**
     * Cache manja document
     *
     * @param \MjCDocument $document
     * @param string $fileIdentifier
     * @return void
     */
    protected function setDocumentCacheByFileIdentifier(\MjCDocument $document = null, $fileIdentifier)
    {
        if ($document !== null) {
            $cacheIdentifier = $this->getDocumentCacheByFileIdentifier($fileIdentifier);
            if (!$this->cache->has($cacheIdentifier)) {
                $this->cache->set($cacheIdentifier, $document);
            }
        }
    }

    /**
     * Returns the cache identifier for a given file identifier
     *
     * @param string $fileIdentifier
     * @return string
     */
    protected function getDocumentCacheByFileIdentifier($fileIdentifier)
    {
        return sha1($this->storageUid . ':' . trim($fileIdentifier, '/'));
    }

    /**
     * Copies a file to a temporary path and returns that path.
     *
     * @param string $fileIdentifier
     * @return string The temporary path
     * @throws \RuntimeException
     */
    protected function copyFileToTemporaryPath($fileIdentifier)
    {
        $temporaryPath = null;
        /** @var \MjCDocument $file */
        $document = $this->getDocumentByIdentifier($fileIdentifier);
        if ($document instanceof \MjCDocument) {
            $temporaryPath = $this->getTemporaryPathForFile($fileIdentifier);
            file_put_contents($temporaryPath, $document->GetContent());
        } else {
            throw new \RuntimeException(
                'Copying file "' . $fileIdentifier . '" to temporary path "' . $temporaryPath . '" failed.',
                1519867205
            );
        }
        return $temporaryPath;
    }
}
