<?php
defined('TYPO3_MODE') or die();

// Register driver
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers']['fal_manja'] = [
    'class' => \Jokumer\FalManja\Driver\ManjaDriver::class,
    'shortName' => \Jokumer\FalManja\Driver\ManjaDriver::DRIVER_TYPE,
    'flexFormDS' => 'FILE:EXT:fal_manja/Configuration/FlexForms/ManjaDriver.xml',
    'label' => 'Manja Digital Asset Management'
];
// Cache configuration
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['fal_manja'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['fal_manja'] = [
        'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
        'options' => [
            'defaultLifetime' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::UNLIMITED_LIFETIME
        ],
    ];
}

/* @var $signalSlotDispatcher \TYPO3\CMS\Extbase\SignalSlot\Dispatcher */
$signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\SignalSlot\Dispatcher');
$signalSlotDispatcher->connect(\TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class, 'recordUpdated', \Jokumer\FalManja\Signal\FileIndexRepository::class, 'recordUpdatedOrCreated');
$signalSlotDispatcher->connect(\TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class, 'recordCreated', \Jokumer\FalManja\Signal\FileIndexRepository::class, 'recordUpdatedOrCreated');
