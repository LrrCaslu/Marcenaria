<?php
/** Heurística de encaixe em prateleiras (shelf packing). NÃO substitui software CNC. */
function optimize_cut(array $pieces, float $boardW, float $boardH, float $kerf = 3.2): array {
    if ($boardW <= 0 || $boardH <= 0 || $kerf < 0) throw new InvalidArgumentException('Dimensões da chapa inválidas.');
    $flat = [];
    foreach ($pieces as $p) {
        $count = min(500, max(0, (int)$p['quantity']));
        for ($i = 0; $i < $count; $i++) {
            $flat[] = ['label' => (string)$p['label'], 'w' => (float)$p['width_mm'], 'h' => (float)$p['height_mm'], 'rotatable' => ($p['grain'] ?? 'free') === 'free'];
        }
    }
    usort($flat, fn($a, $b) => max($b['w'],$b['h']) <=> max($a['w'],$a['h']));
    $boards = []; $unplaced = []; $usedArea = 0;
    foreach ($flat as $p) {
        if ($p['w'] <= 0 || $p['h'] <= 0) continue;
        $orientations = [[$p['w'], $p['h']]];
        if ($p['rotatable'] && $p['w'] !== $p['h']) $orientations[] = [$p['h'], $p['w']];
        $placed = false;
        foreach ($boards as &$board) {
            foreach ($orientations as [$w, $h]) {
                $x = $board['x']; $y = $board['y']; $shelfH = $board['shelfH'];
                if ($x + $w <= $boardW && $y + $h <= $boardH && ($shelfH === 0 || $h <= $shelfH)) {
                    $board['pieces'][] = ['x'=>$x,'y'=>$y,'w'=>$w,'h'=>$h,'label'=>$p['label']];
                    $board['x'] = $x + $w + $kerf; $board['shelfH'] = max($shelfH, $h + $kerf);
                    $usedArea += $w*$h; $placed = true; break;
                }
                $nextY = $board['y'] + $board['shelfH'];
                if ($nextY + $h <= $boardH && $w <= $boardW) {
                    $board['pieces'][] = ['x'=>0,'y'=>$nextY,'w'=>$w,'h'=>$h,'label'=>$p['label']];
                    $board['x'] = $w + $kerf; $board['y'] = $nextY; $board['shelfH'] = $h + $kerf;
                    $usedArea += $w*$h; $placed = true; break;
                }
            }
            if ($placed) break;
        }
        unset($board);
        if ($placed) continue;
        $orientation = null;
        foreach ($orientations as [$w, $h]) {
            if ($w <= $boardW && $h <= $boardH) { $orientation = [$w, $h]; break; }
        }
        if (!$orientation) { $unplaced[] = $p; continue; }
        [$w, $h] = $orientation;
        $boards[] = ['pieces' => [['x'=>0,'y'=>0,'w'=>$w,'h'=>$h,'label'=>$p['label']]], 'x'=>$w+$kerf, 'y'=>0, 'shelfH'=>$h+$kerf];
        $usedArea += $w*$h;
    }
    return ['boards'=>$boards,'unplaced'=>$unplaced,'used_area'=>$usedArea,
        'utilization'=>count($boards) ? round($usedArea / (count($boards)*$boardW*$boardH)*100, 1) : 0];
}
