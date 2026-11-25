<?php
$res = file_get_contents('http://127.0.0.1:8000/api/v1/health');
echo $res . "\n";
