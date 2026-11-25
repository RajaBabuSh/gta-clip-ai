<?php
// scripts/list_fastapi_routes.php
$openapiJson = file_get_contents('http://127.0.0.1:8000/openapi.json');
$spec = json_decode($openapiJson, true);

echo "FASTAPI REGISTERED ROUTES & METHODS:\n";
foreach ($spec['paths'] as $path => $methods) {
    foreach ($methods as $method => $info) {
        echo strtoupper($method) . " $path\n";
    }
}
