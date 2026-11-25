<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Job.php';
$jobs = Job::getAll(5);
echo json_encode($jobs, JSON_PRETTY_PRINT) . "\n";
