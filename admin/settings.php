<?php
// admin/settings.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/AdminMiddleware.php';

AdminMiddleware::requireAdmin();
redirect('admin/system-settings.php');
