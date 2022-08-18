<?php

defined('TYPO3_MODE') or die();

use Jokumer\FalManja\Driver\ManjaDriver;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$extkey = ManjaDriver::DRIVER_TYPE;
$lang_key = "LLL:EXT:$extkey/Resources/Private/Language/locallang_be.xlf:sys_file_metadata.";

//## extend TCA for file metadata
$tempColumns = [
    'is_manja'    => [
        'exclude' => 1,
        'label'   => $lang_key.'is_manja',
        'config'  => [
            'type' => 'passthrough',
        ],
    ],
    'subject'     => [
        'exclude' => 0,
        'label'   => $lang_key.'subject',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'coverage'    => [
        'exclude' => 0,
        'label'   => $lang_key.'coverage',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'keywords'    => [
        'exclude' => 0,
        'label'   => $lang_key.'keywords',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'contributor' => [
        'exclude' => 0,
        'label'   => $lang_key.'contributor',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'publisher' => [
        'exclude' => 0,
        'label'   => $lang_key.'publisher',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'copyright' => [
        'exclude' => 0,
        'label'   => $lang_key.'copyright',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'creator' => [
        'exclude' => 0,
        'label'   => $lang_key.'creator',
        'config'  => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim'
        ],
    ],
    'created'     => [
        'exclude' => 0,
        'label'   => $lang_key.'created',
        'config'  => [
            'type'       => 'input',
            'renderType' => 'inputDateTime',
            'eval'       => 'datetime,int'
        ],
    ],
    'changed'     => [
        'exclude' => 0,
        'label'   => $lang_key.'changed',
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

/** @var StorageRepository $storageRepository */
$storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
$allStorages = $storageRepository->findAll();

$subtypesConfiguration = [
    'subtype_value_field'  => 'is_manja',
    'subtypes_excludelist' => []
];

foreach ($allStorages as $storage) {
    if ($storage->getDriverType() !== ManjaDriver::DRIVER_TYPE) {
        $subtypesConfiguration['subtypes_excludelist'][$storage->getUid()]
            = 'subject, coverage, contributor, created, changed';
    }
}

foreach ($GLOBALS['TCA']['sys_file_metadata']['types'] as $type => $conf) {
    $GLOBALS['TCA']['sys_file_metadata']['types'][$type] = array_merge($conf, $subtypesConfiguration);
}