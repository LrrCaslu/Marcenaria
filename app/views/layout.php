<?php
$isLogin = $view === 'login';
$u = current_user();
$menu = [
    'GERENCIAMENTO' => [
        ['dashboard','Visão geral','grid'], ['customers','Clientes','users'],['suppliers','Fornecedores','layers'],
        ['projects','Projetos','box'],['quotes','Orçamentos','file']
    ],
    'OPERAÇÃO' => [
        ['materials','Materiais','layers'],['stock','Estoque','box'],['cuts','Plano de corte','scissors'],
        ['production','Produção','factory'],['installations','Instalações','truck'],['calendar','Agenda','calendar']
    ],
    'ADMINISTRAÇÃO' => [
        ['finance','Financeiro','wallet'], ['reports','Relatórios','chart'], ['users','Usuários','settings']
    ]
];
?><!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0d1120">
<title><?=e($title ?? 'Painel')?> · <?=e(config('app_name'))?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?=e(asset('css/app.css'))?>">
</head><body class="<?= $isLogin ? 'auth-body' : 'dashboard-body' ?>">
<?php if (!$isLogin): ?>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
 <div class="brand"><div class="brand-symbol"><span>A</span></div><div><strong>AURORA<span class="brand-dot">.</span></strong><small>GESTÃO DE MARCENARIA</small></div></div>
 <div class="sidebar-content">
 <?php foreach ($menu as $group=>$links): ?>
  <div class="menu-label"><?=e($group)?></div>
  <?php foreach ($links as [$key,$name,$icon]): if ((in_array($key,['finance','reports'],true) && !allow(['finance'])) || ($key==='users' && !allow([]))) continue; ?>
   <a href="<?=e(url($key))?>" class="nav-link <?=($active ?? '')===$key?'is-active':''?>"><span class="nav-icon"><?=svg_icon($icon)?></span><span><?=e($name)?></span><?php if (($active ?? '')===$key):?><span class="active-mark"></span><?php endif;?></a>
  <?php endforeach; ?>
 <?php endforeach; ?>
 </div>
 <div class="sidebar-footer"><div class="env-dot"></div><div><strong>Ambiente de demonstração</strong><small>Marcenaria fictícia · v1.0</small></div></div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="main-pane">
 <header class="topbar">
  <div class="topbar-left"><button type="button" class="icon-button mobile-menu" id="openSidebar" aria-label="Abrir menu"><?=svg_icon('menu')?></button><span class="topbar-breadcrumb">Workspace <span>/</span> <b><?=e($title ?? 'Painel')?></b></span></div>
  <div class="topbar-right"><span class="today-label"><?=e(strftime_friendly())?></span><div class="user-pill"><span class="avatar"><?=e(strtoupper(substr((string)($u['name']??'A'),0,1)))?></span><div class="user-meta"><strong><?=e($u['name'] ?? '')?></strong><small><?=e(['admin'=>'Administrador','sales'=>'Comercial','production'=>'Produção','finance'=>'Financeiro'][$u['role']??'']??'Usuário')?></small></div></div><a href="<?=e(url('account'))?>" class="small-link" title="Minha conta"><?=svg_icon('settings',18)?></a><form method="post" action="<?=e(url('auth.logout'))?>" class="inline-form"><?=csrf_field()?><button type="submit" class="icon-button" aria-label="Sair" title="Sair"><?=svg_icon('logout',18)?></button></form></div>
 </header>
 <main class="content">
 <?php endif; ?>
 <?php foreach ($_SESSION['flash']??[] as $notice): ?><div class="notice notice-<?=e($notice['type'])?>" role="alert"><span><?=svg_icon($notice['type']==='danger'?'alert':'check')?></span><?=e($notice['message'])?><button class="notice-dismiss" type="button" onclick="this.parentElement.remove()">×</button></div><?php endforeach; unset($_SESSION['flash']); ?>
 <?=$content?>
 <?php if (!$isLogin): ?>
 </main><footer class="footer"><span>© <?=date('Y')?> Marcenaria Aurora · Sistema demonstrativo</span><span>Feito para organizar cada detalhe.</span></footer>
</div></div>
<?php endif; ?>
<script src="<?=e(asset('js/app.js'))?>" defer></script>
</body></html>
<?php
function strftime_friendly(): string {
    $days = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
    $months = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    $ts=time();return ucfirst($days[(int)date('w',$ts)]).', '.date('d',$ts).' de '.$months[(int)date('n',$ts)-1];
}
