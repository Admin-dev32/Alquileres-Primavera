<?php
// Configuración de base de datos
const DB_HOST = 'localhost';
const DB_NAME = 'u172551721_alquileres';
const DB_USER = 'u172551721_alquileres_use';
const DB_PASS = 'Canelo2024.';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die('Error al conectar con la base de datos.');
}

// Asegura que exista el usuario propietario inicial con todos los permisos.
require_once __DIR__ . '/auth_bootstrap.php';
