<?php

declare(strict_types=1);

namespace Jokumer\FalManja\Ressource;


use Jokumer\FalManja\Driver\ManjaDriver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\ExtractorInterface;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ManjaExtractor implements ExtractorInterface {

    /**
     * @var \Jokumer\FalManja\Driver\ManjaDriver
     */
    protected $manjaDriver;
    
    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var array
     */
    protected $datTimeFields = [109, 113];

    /**
     * @var string
     */
    protected $mappingKey = 'meta_mapping';

    public function __construct() {
        $this->manjaDriver = null;
        $this->configuration = null;    
    }

    /**
     * initializeManjaDriver
     */
    protected function initializeManjaDriver() : void {
        
    }

    public function getFileTypeRestrictions(): array {
        return [];
    }

    public function getDriverRestrictions(): array {
        return [
            'fal_manja', 'typo3_storage_connector'
        ];
    }

    public function getPriority(): int {
        return 70;
    }

    public function getExecutionPriority(): int {
        return 50;
    }

    public function canProcess(File $file): bool {
        return true;
    }


    public function extractMetaData(File $file, array $previousExtractedData = []): array {
        if( $this->configuration === null) $this->configuration = $file->getStorage()->getConfiguration();
        if( $this->manjaDriver === null) {
            $storage = $file->getStorage();            
            if( $storage && ($storageRecord=$storage->getStorageRecord()) && ($storageRecord['uid']??false) ) {

                // grab & use the driver instance from storage
                // - but Typo3 ResourceStorage interface does not give us access to the driver (how stupid encapsulation is that!?),
                // - so, grab from our own instance list & match by storageUID (to avoid collisions - eg. when multiple manja storages are in use)
                if( ($storageDriver=ManjaDriver::getInstanceByStorageUID($storageRecord['uid']))!==null ) {
                    $this->manjaDriver = $storageDriver;                                        
                }
                
            }
            if($this->manjaDriver === null) {
                // WARN: this will create a second instance of ManjaDriver (with all things separate: connection, repository, caches, etc.)
                // -> very unscalable, very slow -> results in many unnecessary re-connects and so on
                $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
                $this->manjaDriver = $objectManager->get(ManjaDriver::class, $this->configuration);
                if ($this->manjaDriver !== null) {
                    $this->manjaDriver->initialize();
                }
            }            
        }

        $previousExtractedData['is_manja'] = 1;        
        if (!isset($this->configuration[$this->mappingKey]) || count($this->configuration[$this->mappingKey]) === 0) { 
            return $previousExtractedData;
        }

        $metaIds = [];
        foreach ($this->configuration[$this->mappingKey] as $metaId) {
            $metaIds[] = $metaId;
        }

        $metaData = $this->manjaDriver->getDocumentMetaData($file->GetIdentifier(),$metaIds);  
        
        foreach ($this->configuration[$this->mappingKey] as $metaDataField => $meta_id) {
            if (!isset($metaData[$meta_id][0])) {
                continue;
            }

            if (in_array((int)$meta_id, $this->datTimeFields, true)) {
                $dtime = \DateTime::createFromFormat(
                        'Y-m-d H:i:s',
                        $metaData[$meta_id][0],
                        new \DateTimeZone('UTC')
                    );

                $previousExtractedData[$metaDataField] = $dtime ? $dtime->getTimestamp() : 0;

                continue;
            }

            if (is_array($metaData[$meta_id])) {
                $previousExtractedData[$metaDataField] = implode(', ', $metaData[$meta_id]);
            } else {
                $previousExtractedData[$metaDataField] = $metaData[$meta_id];
            }
        }
        
        if($file->getType() === File::FILETYPE_IMAGE) {            
            $fileNameAndPath = $file->getForLocalProcessing(false);

            try {
                /** @var ImageInfo $imageInfo */
                $imageInfo = GeneralUtility::makeInstance(ImageInfo::class, $fileNameAndPath);
                $previousExtractedData['width'] = $imageInfo->getWidth();
                $previousExtractedData['height'] = $imageInfo->getHeight();
            } catch( \Throwable $e )  {
                // ignore these errors
            }
        }

        
        return $previousExtractedData;
    }
}
