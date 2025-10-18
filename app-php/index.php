<?php
$requiredPort = getenv('APP_REQUIRED_PORT') ?: '8080';
$dbHost = getenv('DB_HOST') ?: 'nube-db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'empresa';
$dbUser = getenv('DB_USER') ?: 'appuser';
$dbPass = getenv('DB_PASSWORD') ?: 'apppass';

$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($hostHeader, ':') !== false) {
    [, $serverPort] = explode(':', $hostHeader, 2);
} else {
    $serverPort = $_SERVER['SERVER_PORT'] ?? '80';
}

$containerName = gethostname();

$checks = [
    'container_name' => [
        'ok' => ($containerName === 'nube-web'),
        'actual' => $containerName,
        'esperado' => 'nube-web'
    ],
    'host_port' => [
        'ok' => ($serverPort === (string)$requiredPort),
        'actual' => $serverPort,
        'esperado' => (string)$requiredPort
    ],
    'env_db_host' => [
        'ok' => ($dbHost === 'nube-db'),
        'actual' => $dbHost,
        'esperado' => 'nube-db'
    ],
];

$dbStatus = [
    'ok' => false,
    'mensaje' => '',
    'total_clientes' => null,
    'tablas_total' => null,
    'tablas_nombres' => [],
];

try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2,
    ]);
    $pdo->query('SELECT 1');

    // Total filas en clientes
    $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM clientes')->fetch()['c'];

    // Cantidad de tablas en el schema
    $stmtCount = $pdo->prepare('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = :db');
    $stmtCount->execute(['db' => $dbName]);
    $tablasTotal = (int)$stmtCount->fetch()['c'];

    // Nombres de todas las tablas
    $stmtNames = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = :db ORDER BY table_name');
    $stmtNames->execute(['db' => $dbName]);
    $tablasNombres = $stmtNames->fetchAll(PDO::FETCH_COLUMN);

    $dbStatus = [
        'ok' => true,
        'mensaje' => 'Conexión OK',
        'total_clientes' => $count,
        'tablas_total' => $tablasTotal,
        'tablas_nombres' => $tablasNombres,
    ];
} catch (Throwable $e) {
    $dbStatus = [
        'ok' => false,
        'mensaje' => 'Error de conexión: ' . $e->getMessage(),
        'total_clientes' => null,
        'tablas_total' => null,
        'tablas_nombres' => [],
    ];
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Monitor Docker – Eval 2</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            margin: 2rem;
        }

        .ok {
            color: #167d17;
            font-weight: 600;
        }

        .fail {
            color: #b00020;
            font-weight: 600;
        }

        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
        }

        table {
            border-collapse: collapse;
            margin-top: 1rem;
        }

        td,
        th {
            border: 1px solid #ddd;
            padding: .5rem .75rem;
            vertical-align: top;
        }

        ul {
            margin: .25rem 0 0 .75rem;
            padding: 0;
        }

        li {
            margin: 0;
        }
    </style>
</head>

<body>
    <h1>Monitor de cumplimiento – Laboratorio 2</h1>

    <table>
        <tr>
            <th>Chequeo</th>
            <th>Estado</th>
            <th>Actual</th>
            <th>Esperado</th>
        </tr>
        <tr>
            <td>Nombre contenedor web</td>
            <td class="<?= $checks['container_name']['ok'] ? 'ok' : 'fail' ?>"><?= $checks['container_name']['ok'] ? 'OK' : 'FAIL' ?></td>
            <td><code><?= htmlspecialchars($checks['container_name']['actual']) ?></code></td>
            <td><code><?= $checks['container_name']['esperado'] ?></code></td>
        </tr>
        <tr>
            <td>Puerto de acceso (host)</td>
            <td class="<?= $checks['host_port']['ok'] ? 'ok' : 'fail' ?>"><?= $checks['host_port']['ok'] ? 'OK' : 'FAIL' ?></td>
            <td><code><?= htmlspecialchars($checks['host_port']['actual']) ?></code></td>
            <td><code><?= $checks['host_port']['esperado'] ?></code></td>
        </tr>
        <tr>
            <td>DB_HOST</td>
            <td class="<?= $checks['env_db_host']['ok'] ? 'ok' : 'fail' ?>"><?= $checks['env_db_host']['ok'] ? 'OK' : 'FAIL' ?></td>
            <td><code><?= htmlspecialchars($checks['env_db_host']['actual']) ?></code></td>
            <td><code><?= $checks['env_db_host']['esperado'] ?></code></td>
        </tr>
        <tr>
            <td>Conexión a BD</td>
            <td class="<?= $dbStatus['ok'] ? 'ok' : 'fail' ?>"><?= $dbStatus['ok'] ? 'OK' : 'FAIL' ?></td>
            <td colspan="2"><?= htmlspecialchars($dbStatus['mensaje']) ?></td>
        </tr>
        <?php if ($dbStatus['ok']): ?>
            <tr>
                <td>Total filas en <code>clientes</code></td>
                <td class="ok">OK</td>
                <td colspan="2"><code><?= (int)$dbStatus['total_clientes'] ?></code></td>
            </tr>
            <tr>
                <td>Cantidad de tablas en <code><?= htmlspecialchars($dbName) ?></code></td>
                <td class="<?= $dbStatus['tablas_total'] >= 1 ? 'ok' : 'fail' ?>"><?= $dbStatus['tablas_total'] >= 1 ? 'OK' : 'FAIL' ?></td>
                <td colspan="2"><code><?= (int)$dbStatus['tablas_total'] ?></code></td>
            </tr>
            <tr>
                <td>Nombres de tablas</td>
                <td class="<?= count($dbStatus['tablas_nombres']) >= 1 ? 'ok' : 'fail' ?>"><?= count($dbStatus['tablas_nombres']) >= 1 ? 'OK' : 'FAIL' ?></td>
                <td colspan="2">
                    <?php if (count($dbStatus['tablas_nombres'])): ?>
                        <ul>
                            <?php foreach ($dbStatus['tablas_nombres'] as $t): ?>
                                <li><code><?= htmlspecialchars($t) ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <code>(sin tablas)</code>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <p>Contenedor actual: <code><?= htmlspecialchars($containerName) ?></code></p>
    <p>Host solicitado: <code><?= htmlspecialchars($hostHeader) ?></code></p>
</body>

</html>