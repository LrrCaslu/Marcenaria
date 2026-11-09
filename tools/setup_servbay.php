<?php
declare(strict_types=1);
/** Assistente local de instalacao para ServBay; nunca expor em um web root. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente CLI'); }
$root = dirname(__DIR__);
$configPath = $root . '/config.local.php';
function ask(string $label, string $default = ''): string {
    echo $label . ($default !== '' ? " [{$default}]" : '') . ': ';
    $line = fgets(STDIN);
    if ($line === false) { throw new RuntimeException('Entrada interrompida'); }
    $value = trim($line);
    return $value === '' ? $default : $value;
}
function announce(string $message): void { echo $message . PHP_EOL; }
announce('=======================================');
announce(' Marcenaria Aurora - Setup ServBay');
announce('=======================================');
announce('PHP: ' . PHP_VERSION);
if (PHP_VERSION_ID < 80300 || !extension_loaded('pdo_mysql')) {
    announce('ERRO: requer PHP 8.3+ com extensao pdo_mysql habilitada.');
    exit(1);
}
if (is_file($configPath)) {
    $reuse = strtolower(ask('Configuracao ja existe. Reutilizar? (S/n)', 'S'));
    if (!in_array($reuse, ['s', 'sim', 'y', 'yes'], true)) {
        announce('Configuracao existente preservada. Edite config.local.php manualmente, se necessario.');
        exit(1);
    }
} else {
    announce('Entre com os dados do MySQL apresentados no ServBay.');
    announce('A senha digitada pode aparecer no terminal. Utilize apenas em ambiente local.');
    $host = ask('Host MySQL', '127.0.0.1');
    $port = ask('Porta MySQL', '3306');
    $name = ask('Banco de dados', 'marcenaria_aurora');
    $user = ask('Usuario MySQL', 'root');
    $pass = ask('Senha MySQL (Enter se vazia)');
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name) || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        announce('ERRO: nome de banco ou porta invalida.'); exit(1);
    }
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->query('SELECT 1');
        announce('Conexao MySQL: OK');
    } catch (PDOException $e) {
        announce('Falha ao conectar ao MySQL: ' . $e->getMessage());
        announce('Nenhuma configuracao foi gravada. Confirme que o servico MySQL esta ativo.');
        exit(1);
    }
    $config = [
        'db_host' => $host, 'db_port' => $port, 'db_name' => $name,
        'db_user' => $user, 'db_pass' => $pass, 'app_name' => 'Marcenaria Aurora',
        'app_env' => 'local', 'app_timezone' => 'America/Sao_Paulo',
    ];
    $content = "<?php\n/** Gerado pelo instalador local ServBay. NAO disponibilizar via web. */\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($configPath, $content, LOCK_EX) === false) {
        announce('ERRO: nao foi possivel criar config.local.php'); exit(1);
    }
    announce('Configuracao criada em config.local.php');
}
announce('Criando/verificando banco e populando demonstracao...');
require $root . '/tools/install.php';
announce('');
announce('Concluido. No ServBay, crie um site apontando o diretorio publico para:');
announce($root . DIRECTORY_SEPARATOR . 'public');
announce('Sugestao de dominio local: aurora.servbay.demo');
announce('Login: admin@aurora.local / Aurora@2026!');
