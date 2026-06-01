<?php
// ============================================
// db_config.php
// Returns the correct database connection
// based on migration status
// ============================================

require_once __DIR__ . '/vendor/autoload.php';

$mongoHost = getenv('MONGO_HOST') ?: 'mongodb';
$mongoPort = getenv('MONGO_PORT') ?: '27017';

$mongo = new MongoDB\Client("mongodb://$mongoHost:$mongoPort");
$mongodb = $mongo->watchfolio_db;

// Check migration flag
$config = null;
try {
    $config = $mongodb->config->findOne(['_id' => 'migration_status']);
} catch (Exception $e) {
    $config = null;
}

$migrated = $config && $config['migrated'] === true;

if (!$migrated) {
    // Use MariaDB
    $dbHost = getenv('DB_HOST') ?: 'mariadb';
    $dbUser = getenv('DB_USER') ?: 'watchfolio_user';
    $dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
    $dbName = getenv('DB_NAME') ?: 'watchfolio';
    
    $sqldb = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
    if ($sqldb->connect_error) {
        die('MariaDB connection failed: ' . $sqldb->connect_error);
    }
    
    return [
        'type' => 'sql',
        'connection' => $sqldb
    ];
} else {
    // Use MongoDB
    return [
        'type' => 'mongodb',
        'connection' => $mongodb
    ];
}
?>
