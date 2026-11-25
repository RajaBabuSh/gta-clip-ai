<?php
echo json_encode(["status" => "ok", "php_version" => phpversion(), "time" => date("Y-m-d H:i:s")]);
