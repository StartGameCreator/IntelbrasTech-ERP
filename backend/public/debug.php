<?php

echo "<pre>";

echo "DOCUMENT_ROOT:\n";
echo $_SERVER['DOCUMENT_ROOT'] . "\n\n";

echo "SCRIPT_FILENAME:\n";
echo $_SERVER['SCRIPT_FILENAME'] . "\n\n";

echo "REQUEST_URI:\n";
echo $_SERVER['REQUEST_URI'] . "\n\n";

echo "FILE EXISTS:\n";
$arquivo = __DIR__ . '/storage/empresas/logos/1/logo.png';
var_dump(file_exists($arquivo));
echo "\n";
echo $arquivo;