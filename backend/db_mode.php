<?php
define('DB_MODE_FILE', __DIR__ . '/.db_mode');

function get_db_mode(): string {
    if (file_exists(DB_MODE_FILE)) {
        return trim(file_get_contents(DB_MODE_FILE));
    }
    return 'sql';
}

function set_db_mode(string $mode): void {
    file_put_contents(DB_MODE_FILE, $mode);
}

function is_mongo_mode(): bool {
    return get_db_mode() === 'mongodb';
}
