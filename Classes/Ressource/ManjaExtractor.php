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
     * @var array
     */
    protected $datTimeFields = [109, 113];

    public function getFileTypeRestrictions(): array {
        return [];
    }

    public function getDriverRestrictions(): array {
        return [
            ManjaDriver::DRIVER_TYPE
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
        $storage = $file->getStorage();
        $storageRecord=$storage->getStorageRecord();
        $storage_uid = $storageRecord['uid']??false;
        $configuration = $storage->getConfiguration();
        if (!isset($configuration["meta_mapping"]) || count($configuration["meta_mapping"]) === 0 || $storage_uid === false) { 
            return $previousExtractedData;
        }
        $meta_mapping = $configuration["meta_mapping"];
        $manjaDriver = ManjaDriver::getInstanceByStorageUID($storage_uid);

        $previousExtractedData['is_manja'] = 1;        
        

        $metaIds = [];
        foreach ($meta_mapping as $metaId) {
            $metaIds[] = $metaId;
        }

        $metaData = $manjaDriver->getDocumentMetaData($file->GetIdentifier(),$metaIds);  
        
        foreach ($meta_mapping as $metaDataField => $meta_id) {
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
