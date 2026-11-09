<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/cutting.php';
$route = (string)($_GET['route'] ?? 'dashboard');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        require dirname(__DIR__) . '/app/actions.php';
    } else {
        require dirname(__DIR__) . '/app/pages.php';
    }
} catch (InvalidArgumentException $ex) {
    flash($ex->getMessage(), 'danger');
    $base = explode('.', $route)[0] ?? '';
    $fallbacks = ['auth'=>'login','account'=>'account','customers'=>'customers','suppliers'=>'suppliers',
        'materials'=>'materials','stock'=>'stock','projects'=>'projects','quotes'=>'quotes',
        'workorders'=>'production','finance'=>'finance','installations'=>'installations',
        'cuts'=>'cuts','users'=>'users'];
    $back=(string)($_POST['_back']??'');
    redirect(in_array($back,$fallbacks,true)?$back:($fallbacks[$base]??'dashboard'));
 } catch (PDOException $ex) {
    error_log('[Aurora database] ' . $ex->getMessage());
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Banco indisponível</title><div style="font-family:sans-serif;margin:5rem auto;max-width:640px"><h1>Não foi possível acessar o banco</h1><p>Confira o serviço MySQL, as configurações em <code>config.local.php</code> e as tabelas instaladas.</p><p>Consulte o README do projeto.</p></div>';
    exit;
}
