<?php

defined('TYPO3_MODE') or die();

use Jokumer\FalManja\Driver\FalManja;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

//## extend TCA for file metadata
$tempColumns = [
    'is_manja'    => [
        'exclude' => 1,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.is_manja',
        'config'  => [
            'type' => 'passthrough',
        ],
    ],
    'subject'     => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.subject',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'coverage'    => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.coverage',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'keywords'    => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.keywords',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'contributor' => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.contributor',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'publisher' => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.publisher',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'copyright' => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.copyright',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'creator' => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.creator',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'created'     => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.created',
        'config'  => [
            'type'       => 'input',
            'renderType' => 'inputDateTime',
            'eval'       => 'datetime,int'
        ],
    ],
    'changed'     => [
        'exclude' => 0,
        'label'   => 'LLL:EXT:typo3_storage_connector/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.changed',
        'config'  => [
            'type'       => 'input',
            'renderType' => 'inputDateTime',
            'eval'       => 'datetime,int'
        ],
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    'is_manja, subject, coverage, creator, publisher, contributor, copyright, keywords, created, changed',
    '',
    'after:alternative'
);

if(!isset($GLOBALS['TCA']['sys_file_metadata']['interface'])) $GLOBALS['TCA']['sys_file_metadata']['interface'] = [];
if(!isset($GLOBALS['TCA']['sys_file_metadata']['interface']['showRecordFieldList'])) $GLOBALS['TCA']['sys_file_metadata']['interface']['showRecordFieldList'] = '';
$GLOBALS['TCA']['sys_file_metadata']['interface']['showRecordFieldList'] .= 'subject, coverage, creator, publisher, contributor, copyright, keywords, created, changed';

/**
 *  using subtype_value_field to hide all
 * fields we added for manja documents in all
 * other storages which are not based on manja driver
 */

/*We need a valid BE_USER object for quering storage repository */    
if (!isset($GLOBALS['BE_USER']) || $GLOBALS['BE_USER'] === null) {
    $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(BackendUserAuthentication::class);
    $GLOBALS['BE_USER']->start();
}

/** @var StorageRepository $storageRepository */
$storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
$allStorages = $storageRepository->findAll();

$subtypesConfiguration = [
    'subtype_value_field'  => 'is_manja',
    'subtypes_excludelist' => []
];

foreach ($allStorages as $storage) {
    if ($storage->getDriverType() !== FalManja::DRIVER_TYPE) {
        $subtypesConfiguration['subtypes_excludelist'][$storage->getUid()]
            = 'subject, coverage, contributor, created, changed';
    }
}

foreach ($GLOBALS['TCA']['sys_file_metadata']['types'] as $type => $conf) {
    $GLOBALS['TCA']['sys_file_metadata']['types'][$type] = array_merge($conf, $subtypesConfiguration);
}