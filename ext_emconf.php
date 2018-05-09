<?php
$EM_CONF[$_EXTKEY] = array (
    'title' => 'FAL Manja',
    'description' => 'Provides a Manja driver for TYPO3 File Abstraction Layer.',
    'category' => 'plugin',
    'author' => 'Joerg Kummer, Falk Roeder',
    'author_email' => 'service@enobe.de, mail@falk-roeder.de',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '1.0.0-dev',
    'constraints' => array (
        'depends' => array (
            'typo3' => '7.6.0-8.99.99',
        ),
        'conflicts' => array (
        ),
        'suggests' => array (
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
