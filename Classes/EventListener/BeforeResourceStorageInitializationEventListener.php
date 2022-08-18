<?php

declare(strict_types=1);

namespace Jokumer\FalManja\EventListener;

use Jokumer\FalManja\Driver\ManjaDriver;
use TYPO3\CMS\Core\Resource\Event\BeforeResourceStorageInitializationEvent;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;

class BeforeResourceStorageInitializationEventListener {
    
    public function __construct() {
    }

    public function __invoke(BeforeResourceStorageInitializationEvent $event): void {                                
        $record = $event->getRecord();
        if($record['driver'] !== ManjaDriver::DRIVER_TYPE) return;    
        if ($record['processingfolder'] === '') {
            $record['processingfolder'] = ManjaDriver::PROCESSING_FOLDER_DEFAULT;            
            $event->setRecord($record);
        }        
    }
}
