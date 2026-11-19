<?php

$root = __DIR__ . DIRECTORY_SEPARATOR . 'dist';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = realpath($root . DIRECTORY_SEPARATOR . ltrim($path, '/'));

if ($path !== '/' && $file && str_starts_with($file, realpath($root)) && is_file($file)) {
    return false;
}

require $root . DIRECTORY_SEPARATOR . 'index.html';
