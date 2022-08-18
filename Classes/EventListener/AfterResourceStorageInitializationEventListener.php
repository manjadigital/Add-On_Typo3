<?php

declare(strict_types=1);

namespace Jokumer\FalManja\EventListener;

use Jokumer\FalManja\Driver\ManjaDriver;
use TYPO3\CMS\Core\Resource\Event\AfterResourceStorageInitializationEvent;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;

class AfterResourceStorageInitializationEventListener {
    
    public function __construct() {
    }

    public function __invoke(AfterResourceStorageInitializationEvent $event): void
    {                
        $storage = $event->getStorage();
        if ($storage->getDriverType() !== ManjaDriver::DRIVER_TYPE) {
            return;
        }
        $record = $storage->getStorageRecord();
        $matches = [];
        preg_match('/^(\d+):\//', $record['processingfolder'], $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) === 0 || ($record['is_writable'] === 0 && (int)$matches[1][0] === $record['uid'])) {
            throw new InvalidConfigurationException("Processinfolder is not valid");
        }        
        
    }
}
