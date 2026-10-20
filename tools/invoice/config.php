<?php
/**
 * Invoice tool configuration.
 * Optional overrides go in config.local.php, which is git-ignored and merged over these defaults.
 */
$config = [
    // PDO DSN. Default: SQLite file in storage/. Switch to MySQL later, e.g.
    // 'mysql:host=127.0.0.1;dbname=harnew;charset=utf8mb4'
    'db_dsn'  => 'sqlite:' . __DIR__ . '/storage/invoice.sqlite',
    'db_user' => null,
    'db_pass' => null,

    // 32+ char secret used to encrypt the Jira token and rate. Empty = generated once into storage/app_key.
    'app_key' => '',

    // Jira site and the custom field holding the bill code (values look like "Texas Zip Codes / ZIPC020")
    'jira_base'       => 'https://har.atlassian.net',
    'jira_bill_field' => 'HAR Application (PMO)',

    // Public URL path of the front controller (webroot/tools/invoice/index.php)
    'base_url' => '/tools/invoice/',

    // Defaults for the profile created on first run
    'defaults' => [
        'bill_to'       => "HAR.com\nTaqi Rizvi\ntaqi.rizvi@har.com\nHouston Realtor Associations",
        'note_template' => 'Invoice for Front-end Development of HAR.com for {period}',
    ],
];

$local = is_file(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : null;
if (is_array($local)) {
    $config = array_replace_recursive($config, $local);
}

// Keep the key next to the database it encrypts; never regenerate it once data exists
if ($config['app_key'] === '') {
    $keyFile = __DIR__ . '/storage/app_key';
    if (!is_file($keyFile)) {
        file_put_contents($keyFile, bin2hex(random_bytes(32)));
        chmod($keyFile, 0600);
    }
    $config['app_key'] = trim(file_get_contents($keyFile));
}

return $config;
