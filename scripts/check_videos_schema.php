<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=gtaclipai', 'root', '');
$cols = $pdo->query('DESCRIBE videos')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ")\n";
}
