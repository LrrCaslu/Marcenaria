<?php
ob_start();
require __DIR__ . '/quote_document.php';
$html = ob_get_clean();
$toolbarCss = '<style>.toolbar{position:sticky;top:0;z-index:9;background:#142a23;color:#fff;padding:15px;display:flex;align-items:center;justify-content:center;gap:12px;font:13px Arial,sans-serif}.toolbar a,.toolbar button{border:0;background:#b5f2b9;color:#173324;border-radius:6px;padding:9px 13px;font-weight:bold;text-decoration:none;cursor:pointer}@media print{.toolbar{display:none!important}body{margin:15mm!important}}</style>';
$toolbar = '<div class="toolbar"><strong>'.e($quote['code']).'</strong><button type="button" onclick="window.print()">Imprimir / salvar PDF</button><a href="'.e(url('quotes.pdf',['id'=>$quote['id']])).'">Baixar PDF nativo*</a><a href="'.e(url('quotes')).'">Voltar</a></div>';
echo str_replace('<body>', '<body>'.$toolbar, str_replace('</head>', $toolbarCss.'</head>', $html));
