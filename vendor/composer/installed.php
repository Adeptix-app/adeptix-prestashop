<?php return array(
    'root' => array(
        'name' => 'adeptix/adeptix-prestashop',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'prestashop-module',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'adeptix/adeptix-php' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '325bbc4356d30d65927c719c395351b430f7e17c',
            'type' => 'library',
            'install_path' => __DIR__ . '/../adeptix/adeptix-php',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'adeptix/adeptix-prestashop' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'prestashop-module',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
