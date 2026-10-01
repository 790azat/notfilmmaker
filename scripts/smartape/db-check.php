<?php
// Проверка доступа к MySQL на хостинге. В ISPmanager к имени базы и пользователя
// часто добавляется приставка с логином хостинга (login_name): пробуем варианты
// и, если подходит другой, печатаем его, чтобы release.sh поправил .env.
$env = [];
foreach (file($argv[1], FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], "'\"");
    }
}
$login = getenv('USER') ?: get_current_user();
$host = $env['DB_HOST'] ?? 'localhost';
$user = $env['DB_USERNAME'] ?? '';
$db = $env['DB_DATABASE'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
$users = array_unique([$user, "{$login}_{$user}"]);
$dbs = array_unique([$db, "{$login}_{$db}"]);

foreach ($users as $u) {
    try {
        $pdo = new PDO("mysql:host={$host};port=3306", $u, $pass, [PDO::ATTR_TIMEOUT => 5]);
    } catch (PDOException $e) {
        fwrite(STDERR, "вход как пользователь длиной ".strlen($u).": {$e->getCode()}\n");
        continue;
    }
    $visible = $pdo->query('show databases')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbs as $d) {
        if (in_array($d, $visible, true)) {
            echo "DB_USERNAME={$u}\nDB_DATABASE={$d}\n";
            exit(0);
        }
    }
    fwrite(STDERR, 'вход удался, но базы с нужным именем нет. Доступные базы: '.implode(', ', array_diff($visible, ['information_schema']))."\n");
    exit(2);
}
exit(1);
