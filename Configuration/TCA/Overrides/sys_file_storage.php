<?php

defined('TYPO3_MODE') or die();

use Jokumer\FalManja\Driver\FalManja;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

//## extend TCA for file metadata
$tempColumns = [
    'processingfolder' => [
        'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_storage.processingfolder',
        'config' => [
            'type' => 'input',
            'placeholder' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_storage.processingfolder.placeholder',
            'size' => 20,
            'default' => '0:/typo3temp/assets/_processed_manja'
        ],
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_storage', $tempColumns);
