<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.local.php';
$config = is_file($configFile) ? require $configFile : require dirname(__DIR__) . '/config.example.php';
date_default_timezone_set($config['app_timezone'] ?? 'America/Sao_Paulo');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('aurora_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function config(string $key): mixed {
    global $config;
    return $config[$key] ?? null;
}
function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Extensão pdo_mysql não encontrada no PHP. Ative-a no servidor.');
    }
    $host = config('db_host'); $port = config('db_port'); $name = config('db_name');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, (string)config('db_user'), (string)config('db_pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function rows(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
function one(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $r = $stmt->fetch();
    return $r ?: null;
}
function execSql(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $route = 'dashboard', array $params = []): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    return $script . '?' . http_build_query(array_merge(['route' => $route], $params));
}
function asset(string $filename): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    return rtrim(str_replace('\\', '/', dirname($script)), '/') . '/assets/' . ltrim($filename, '/');
}
function redirect(string $route, array $params = []): never {
    header('Location: ' . url($route, $params), true, 303);
    exit;
}
function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function verify_csrf(): void {
    if (!isset($_POST['_csrf']) || !hash_equals(csrf_token(), (string)$_POST['_csrf'])) {
        http_response_code(419);
        exit('Sessão expirada ou formulário inválido. Atualize a página e tente novamente.');
    }
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function logged_in(): bool { return isset($_SESSION['user']['id']); }
function allow(array $roles): bool {
    $role = current_user()['role'] ?? '';
    return $role === 'admin' || in_array($role, $roles, true);
}
function require_auth(): void {
    if (!logged_in()) redirect('login');
    $u = one('SELECT id,name,email,role,active FROM users WHERE id=?', [(int)current_user()['id']]);
    if (!$u || !(bool)$u['active']) {
        unset($_SESSION['user']);
        flash('Conta desativada ou sessão inválida.', 'danger');
        redirect('login');
    }
    $_SESSION['user'] = array_intersect_key($u, array_flip(['id','name','email','role']));
}
function require_role(array $roles): void {
    require_auth();
    if (!allow($roles)) {
        http_response_code(403);
        render('forbidden', ['title' => 'Acesso restrito', 'active' => '']);
        exit;
    }
}
function money(float|int|string|null $value): string {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
function qty(float|int|string|null $value): string {
    return rtrim(rtrim(number_format((float)$value, 3, ',', '.'), '0'), ',');
}
function date_br(?string $date): string {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '—';
}
function date_time_br(?string $date): string {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}
function label_status(string $value): string {
    return [
        'planning'=>'Planejamento','approved'=>'Aprovado','in_production'=>'Produção',
        'installation'=>'Instalação','completed'=>'Concluído','cancelled'=>'Cancelado',
        'draft'=>'Rascunho','sent'=>'Enviado','rejected'=>'Recusado',
        'queued'=>'Na fila','cutting'=>'Corte','edgebanding'=>'Colagem de borda',
        'assembly'=>'Montagem','finishing'=>'Acabamento','ready'=>'Pronto',
        'delivered'=>'Entregue','scheduled'=>'Agendada','in_progress'=>'Em andamento',
        'pending'=>'Pendente','low'=>'Baixa','normal'=>'Normal','high'=>'Alta',
        'income'=>'Receita','expense'=>'Despesa',
    ][$value] ?? ucfirst($value);
}
function status_class(string $status): string {
    return match ($status) {
        'completed','approved','delivered','ready' => 'badge-success',
        'rejected','cancelled','high' => 'badge-danger',
        'in_progress','in_production','cutting','edgebanding','assembly','finishing','installation','sent' => 'badge-info',
        default => 'badge-muted'
    };
}
function status_badge(string $status): string {
    return '<span class="status-badge ' . status_class($status) . '">' . e(label_status($status)) . '</span>';
}
function post_str(string $key, int $max = 255): string {
    return mb_safe_trim((string)($_POST[$key] ?? ''), $max);
}
function mb_safe_trim(string $value, int $max): string {
    $v = trim($value);
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}
function post_id(string $key): ?int {
    $v = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $v !== false && $v !== null ? (int)$v : null;
}
function positive_or_zero(mixed $value, string $label): float {
    $v = str_replace(',', '.', trim((string)$value));
    if ($v === '' || !is_numeric($v) || !is_finite((float)$v) || (float)$v < 0) throw new InvalidArgumentException("{$label}: informe um número maior ou igual a zero.");
    return round((float)$v, 3);
}
function required(string $key, string $label, int $max = 180): string {
    $value = post_str($key, $max);
    if ($value === '') throw new InvalidArgumentException("O campo {$label} é obrigatório.");
    return $value;
}
function safe_date(string $key): ?string {
    $date = post_str($key, 16);
    if ($date === '') return null;
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) throw new InvalidArgumentException('Data inválida.');
    return $date;
}
function safe_datetime(string $key): string {
    $date = post_str($key, 30);
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $date);
    if (!$dt || $dt->format('Y-m-d\TH:i') !== $date) throw new InvalidArgumentException('Data e horário inválidos.');
    return $dt->format('Y-m-d H:i:s');
}
function safe_enum(string $key, array $allowed, string $default): string {
    $value = post_str($key, 45);
    if ($value === '') return $default;
    if (!in_array($value, $allowed, true)) throw new InvalidArgumentException('Valor inválido no campo: ' . $key);
    return $value;
}
function render(string $view, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}
function count_value(string $sql, array $params = []): float { return (float)(one($sql, $params)['v'] ?? 0); }
function quote_total(array $items, float $labor, float $markup, float $tax, float $discount): array {
    $materials = 0.0;
    foreach ($items as $item) $materials += (float)$item['quantity'] * (float)$item['unit_cost'];
    $materials = round($materials, 2);
    $base = round($materials + $labor, 2);
    $sale = round($base * (1 + $markup / 100), 2);
    $taxValue = round($sale * $tax / 100, 2);
    $total = max(0, round($sale + $taxValue - $discount, 2));
    return ['materials' => $materials, 'subtotal' => $base, 'sale' => $sale, 'tax' => $taxValue, 'total' => $total];
}
function svg_icon(string $name, int $size=19): string {
    $paths = [
        'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'box'=>'<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
        'layers'=>'<path d="m12 2 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 17l9 5 9-5"/>',
        'file'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'factory'=>'<rect x="3" y="10" width="18" height="11" rx="1"/><path d="M3 10V5l6 3V5l6 3V2h3v8M7 15h2M12 15h2M17 15h2"/>',
        'wallet'=>'<rect x="2" y="5" width="20" height="15" rx="2"/><path d="M2 10h20M17 15h2M6 2h13"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'truck'=>'<path d="M10 17h4V5H2v12h3M14 9h4l4 4v4h-3M14 9v8"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'chart'=>'<path d="M3 3v18h18M7 16V9M12 16V5M17 16v-7"/>',
        'scissors'=>'<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.12 8.12 13.76 13.76M14.8 9.2 21.88 2.12M8.12 15.88 12 12"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-2.83 2.83-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21h-4v-.17a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06-2.83-2.83.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3v-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06L7.04 4.3l.06.06a1.65 1.65 0 0 0 1.82.33A1.65 1.65 0 0 0 10 3.17V3h4v.17a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06 2.83 2.83-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21v4h-.09A1.65 1.65 0 0 0 19.4 15Z"/>',
        'logout'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'search'=>'<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
        'edit'=>'<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L9 17l-4 1 1-4Z"/>',
        'arrow'=>'<path d="M5 12h14M12 5l7 7-7 7"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'clock'=>'<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'alert'=>'<path d="m10.29 3.86-8.82 15.3A2 2 0 0 0 3.2 22h17.6a2 2 0 0 0 1.73-3l-8.82-15.3a2 2 0 0 0-3.46.16ZM12 9v4M12 17h.01"/>',
    ];
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['box']).'</svg>';
}
