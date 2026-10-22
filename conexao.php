<?php
declare(strict_types=1);

function obterConexao(): PDO
{
    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        throw new PDOException('Driver MySQL indisponível.');
    }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'complementa';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD');
    $password = $password === false ? '' : $password;

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'",
    ]);
}
