<?php
// For XAMPP, default root/empty password works locally. Use a restricted DB user on a real server.
return [
    'host' => getenv('BULIG_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('BULIG_DB_PORT') ?: '3306',
    'name' => getenv('BULIG_DB_NAME') ?: 'DEPED_BULIG',
    'user' => getenv('BULIG_DB_USER') ?: 'root',
    'pass' => getenv('BULIG_DB_PASS') ?: '',
    'timezone' => 'Asia/Manila',
];
