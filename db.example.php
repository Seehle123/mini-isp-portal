<?php

$host = 'localhost';
$databaseName = 'your_database_name';
$username = 'your_database_username';
$password = 'your_database_password';

$connectionString = "mysql:host=$host;dbname=$databaseName;charset=utf8mb4";

try {
    $database = new PDO(
        $connectionString,
        $username,
        $password
    );

    $database->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $database->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );
} catch (PDOException $error) {
    exit('Database connection failed.');
}