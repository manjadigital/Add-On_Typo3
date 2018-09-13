<?php
$EM_CONF[$_EXTKEY] = array (
    'title' => 'FAL Manja',
    'description' => 'Provides a Manja driver for TYPO3 File Abstraction Layer.',
    'category' => 'plugin',
    'author' => 'Falk Roeder, Joerg Kummer',
    'author_email' => 'mail@falk-roeder.de, service@enobe.de',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '1.0.0-rc3',
    'constraints' => array (
        'depends' => array (
            'typo3' => '7.6.0-9.2.99',
        ),
    ),
    'autoload' => [
        'psr-4' => [
            'Jokumer\\FalManja\\' => 'Classes',
        ],
        'classmap' => [
            'Resources/Private/Vendor/manja-api-4.0',
        ],
    ],
);
