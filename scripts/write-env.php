<?php

declare(strict_types=1);

function envLine(string $key, string $value): string
{
    return $key.'="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value)."\"\n";
}

$raw = getenv('DATABASE_URL') ?: getenv('DB_URL') ?: '';
$raw = trim($raw, " \t\n\r\0\x0B\"'");
fwrite(STDERR, 'DATABASE_URL set='.($raw !== '' ? 'yes' : 'no').' length='.strlen($raw)."\n");

$host = getenv('DB_HOST') ?: '';
$port = getenv('DB_PORT') ?: '5432';
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';

if ($raw !== '') {
    $parts = parse_url($raw);
    if ($parts === false || empty($parts['host'])) {
        fwrite(STDERR, "ERROR: DATABASE_URL is not a valid URL\n");
        exit(1);
    }
    $host = str_replace('-pooler', '', $parts['host']);
    $port = (string) ($parts['port'] ?? 5432);
    $database = ltrim($parts['path'] ?? '', '/');
    $database = $database !== '' ? explode('?', $database)[0] : 'neondb';
    $username = isset($parts['user']) ? urldecode($parts['user']) : '';
    $password = isset($parts['pass']) ? urldecode($parts['pass']) : '';
}

if ($host === '' || $username === '' || $database === '') {
    fwrite(STDERR, "ERROR: Neon is not connected — DATABASE_URL is missing or incomplete.\n");
    fwrite(STDERR, "Set DATABASE_URL on the service (direct host, no -pooler).\n");
    exit(1);
}

$appUrl = getenv('APP_URL') ?: '';
$appKey = getenv('APP_KEY') ?: '';
$appEnv = getenv('APP_ENV') ?: 'production';
$appDebug = getenv('APP_DEBUG') ?: 'false';

$env = 'APP_NAME="Shatbha"'."\n"
    .envLine('APP_ENV', $appEnv)
    .envLine('APP_KEY', $appKey)
    .envLine('APP_DEBUG', $appDebug)
    .envLine('APP_URL', $appUrl)
    ."LOG_CHANNEL=\"stderr\"\n"
    ."LOG_LEVEL=\"error\"\n"
    ."DB_CONNECTION=\"pgsql\"\n"
    .envLine('DB_HOST', $host)
    .envLine('DB_PORT', $port)
    .envLine('DB_DATABASE', $database)
    .envLine('DB_USERNAME', $username)
    .envLine('DB_PASSWORD', $password)
    ."DB_SSLMODE=\"require\"\n"
    ."SESSION_DRIVER=\"file\"\n"
    ."CACHE_STORE=\"file\"\n"
    ."QUEUE_CONNECTION=\"sync\"\n";

file_put_contents('/var/www/html/.env', $env);
chmod('/var/www/html/.env', 0644);

echo "Neon target host={$host} db={$database} user={$username}\n";

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=require;connect_timeout=30',
    $host,
    $port,
    $database
);

$lastError = 'unknown';
for ($i = 1; $i <= 8; $i++) {
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $name = $pdo->query('select current_database()')->fetchColumn();
        echo "Neon connected ({$name}) on attempt {$i}\n";
        exit(0);
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        fwrite(STDERR, "Neon attempt {$i}/8 failed: {$lastError}\n");
        sleep(3);
    }
}

fwrite(STDERR, "Neon connection failed after retries: {$lastError}\n");
exit(1);
