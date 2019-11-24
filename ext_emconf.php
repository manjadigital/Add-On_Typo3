<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'FAL Manja',
    'description' => 'Provides a Manja driver for TYPO3 File Abstraction Layer.',
    'category' => 'plugin',
    'author' => 'Falk Roeder, Joerg Kummer',
    'author_email' => 'mail@falk-roeder.de, service@enobe.de',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'alpha',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '2.0.0-rc1',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.5.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Jokumer\\FalManja\\' => 'Classes',
        ],
        'classmap' => [
            'Resources/Private/Vendor/manja-api-4.0',
        ],
    ],
];
