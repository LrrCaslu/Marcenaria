<?php
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/cutting.php';
$result = quote_total([['quantity'=>2,'unit_cost'=>100.00],['quantity'=>1,'unit_cost'=>50.00]], 100, 20, 5, 10);
assert(abs($result['total'] - 431.00) < 0.0001, 'Cálculo de orçamento incorreto');
assert(abs($result['materials'] - 250.00) < 0.0001, 'Subtotal incorreto');
$cut = optimize_cut([['label'=>'Lateral','width_mm'=>500,'height_mm'=>300,'quantity'=>2,'grain'=>'free']], 1000, 2000, 3);
assert(count($cut['unplaced']) === 0, 'Peças válidas não foram alocadas');
assert(count($cut['boards']) === 1, 'Esperava uma chapa');
$cut2 = optimize_cut([['label'=>'Peça grande','width_mm'=>2200,'height_mm'=>1200,'quantity'=>1,'grain'=>'vertical']], 1000, 2000, 3);
assert(count($cut2['unplaced']) === 1, 'Peça incompatível deveria ser reportada');
echo "Testes de cálculo e plano de corte: OK\n";
