<?php
if ($route === 'login') {
    if (logged_in()) redirect('dashboard');
    render('login',['title'=>'Entrar','active'=>'login']);
    exit;
}
require_auth();
$getId = static fn(string $key): ?int => filter_var($_GET[$key] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;

switch ($route) {
    case 'dashboard':
        $kpis = [
            'sales' => count_value('SELECT COALESCE(SUM(total),0) v FROM quotes WHERE status="approved" AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())'),
            'projects' => count_value('SELECT COUNT(*) v FROM projects WHERE status NOT IN ("completed","cancelled")'),
            'orders' => count_value('SELECT COUNT(*) v FROM work_orders WHERE status NOT IN ("delivered","cancelled")'),
            'pending' => count_value('SELECT COUNT(*) v FROM finance WHERE kind="income" AND paid_at IS NULL'),
            'low_stock' => count_value('SELECT COUNT(*) v FROM materials WHERE active=1 AND stock<=minimum_stock'),
        ];
        $orders=rows('SELECT w.*,p.title AS project_title,c.name AS customer_name FROM work_orders w JOIN projects p ON p.id=w.project_id JOIN customers c ON c.id=p.customer_id ORDER BY FIELD(w.status,"cutting","edgebanding","assembly","finishing","queued","ready","delivered","cancelled"), w.due_date IS NULL,w.due_date ASC LIMIT 6');
        $recent=rows('SELECT q.*,c.name customer_name FROM quotes q JOIN customers c ON c.id=q.customer_id ORDER BY q.id DESC LIMIT 5');
        $chart=rows('SELECT DATE_FORMAT(paid_at,"%Y-%m") month, SUM(IF(kind="income",amount,0)) income, SUM(IF(kind="expense",amount,0)) expense FROM finance WHERE paid_at IS NOT NULL AND paid_at>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) GROUP BY month ORDER BY month');
        render('dashboard',compact('kpis','orders','recent','chart')+['title'=>'Visão geral','active'=>'dashboard']); break;
    case 'customers':
        $search=trim((string)($_GET['q'] ?? ''));
        $clients=$search!=='' ? rows('SELECT * FROM customers WHERE name LIKE ? OR document LIKE ? ORDER BY active DESC,name LIMIT 200',['%'.$search.'%','%'.$search.'%']) : rows('SELECT * FROM customers ORDER BY active DESC,name LIMIT 200');
        $edit=$getId('edit') ? one('SELECT * FROM customers WHERE id=?',[$getId('edit')]) : null;
        render('customers',compact('clients','edit','search')+['title'=>'Clientes','active'=>'customers']); break;
    case 'suppliers':
        $suppliers=rows('SELECT * FROM suppliers ORDER BY active DESC,name LIMIT 200');
        $edit=$getId('edit')? one('SELECT * FROM suppliers WHERE id=?',[$getId('edit')]):null;
        render('suppliers',compact('suppliers','edit')+['title'=>'Fornecedores','active'=>'suppliers']); break;
    case 'materials':
        $materials=rows('SELECT m.*,s.name supplier_name FROM materials m LEFT JOIN suppliers s ON s.id=m.supplier_id ORDER BY m.active DESC,m.name LIMIT 300');
        $suppliers=rows('SELECT id,name FROM suppliers WHERE active=1 ORDER BY name');
        $edit=$getId('edit')?one('SELECT * FROM materials WHERE id=?',[$getId('edit')]):null;
        render('materials',compact('materials','suppliers','edit')+['title'=>'Materiais','active'=>'materials']); break;
    case 'stock':
        $materials=rows('SELECT id,name,sku,stock,unit,minimum_stock FROM materials WHERE active=1 ORDER BY name');
        $movements=rows('SELECT sm.*,m.name material_name,m.unit,u.name user_name FROM stock_movements sm JOIN materials m ON m.id=sm.material_id JOIN users u ON u.id=sm.user_id ORDER BY sm.id DESC LIMIT 100');
        render('stock',compact('materials','movements')+['title'=>'Estoque','active'=>'stock']); break;
    case 'projects':
        $projects=rows('SELECT p.*,c.name customer_name FROM projects p JOIN customers c ON c.id=p.customer_id ORDER BY p.id DESC LIMIT 200');
        $clients=rows('SELECT id,name FROM customers WHERE active=1 ORDER BY name');
        $edit=$getId('edit')?one('SELECT * FROM projects WHERE id=?',[$getId('edit')]):null;
        render('projects',compact('projects','clients','edit')+['title'=>'Projetos','active'=>'projects']); break;
    case 'quotes':
        $quotes=rows('SELECT q.*,c.name customer_name,p.title project_title,w.id work_order_id FROM quotes q JOIN customers c ON c.id=q.customer_id LEFT JOIN projects p ON p.id=q.project_id LEFT JOIN work_orders w ON w.quote_id=q.id ORDER BY q.id DESC LIMIT 200');
        $clients=rows('SELECT id,name FROM customers WHERE active=1 ORDER BY name');
        $projects=rows('SELECT id,title,customer_id FROM projects WHERE status!="cancelled" ORDER BY title');
        $materials=rows('SELECT id,name,unit,cost FROM materials WHERE active=1 ORDER BY name');
        $edit=$getId('edit')?one('SELECT * FROM quotes WHERE id=?',[$getId('edit')]):null;
        $editItems=$edit?rows('SELECT * FROM quote_items WHERE quote_id=?',[$edit['id']]):[];
        render('quotes',compact('quotes','clients','projects','materials','edit','editItems')+['title'=>'Orçamentos','active'=>'quotes']); break;
    case 'quotes.print':
    case 'quotes.pdf':
        $id=$getId('id');
        $quote=$id?one('SELECT q.*,c.name customer_name,c.kind,c.document,c.phone,c.email,c.address,c.city,c.state,p.title project_title FROM quotes q JOIN customers c ON c.id=q.customer_id LEFT JOIN projects p ON p.id=q.project_id WHERE q.id=?',[$id]):null;
        if (!$quote) {http_response_code(404); render('not_found',['title'=>'Orçamento não encontrado','active'=>'quotes']);break;}
        $items=rows('SELECT * FROM quote_items WHERE quote_id=?',[$id]);
        if ($route==='quotes.pdf') {
            $autoload=dirname(__DIR__).'/vendor/autoload.php';
            if (!is_file($autoload)) {flash('PDF nativo exige composer install. Use “Imprimir / salvar em PDF”.','danger');redirect('quotes.print',['id'=>$id]);}
            require $autoload;
            ob_start(); require __DIR__.'/views/quote_document.php'; $html=ob_get_clean();
            $options = new Dompdf\Options(); $options->set('isRemoteEnabled',false);
            $dompdf=new Dompdf\Dompdf($options); $dompdf->loadHtml($html,'UTF-8'); $dompdf->setPaper('A4'); $dompdf->render();
            $dompdf->stream($quote['code'].'.pdf',['Attachment'=>true]); exit;
        }
        require __DIR__.'/views/quote_print.php'; break;
    case 'production':
        $orders=rows('SELECT w.*,p.title project_title,c.name customer_name,u.name assignee FROM work_orders w JOIN projects p ON p.id=w.project_id JOIN customers c ON c.id=p.customer_id LEFT JOIN users u ON u.id=w.assigned_to ORDER BY FIELD(w.status,"cutting","edgebanding","assembly","finishing","queued","ready","delivered","cancelled"),w.due_date IS NULL,w.due_date LIMIT 200');
        $projects=rows('SELECT id,title FROM projects WHERE status NOT IN ("completed","cancelled") ORDER BY title');
        $staff=rows('SELECT id,name FROM users WHERE active=1 AND role IN ("admin","production") ORDER BY name');
        $edit=$getId('edit')?one('SELECT * FROM work_orders WHERE id=?',[$getId('edit')]):null;
        $history=$getId('history')?rows('SELECT e.*,u.name user_name FROM work_order_events e JOIN users u ON u.id=e.user_id WHERE e.work_order_id=? ORDER BY e.id DESC',[$getId('history')]):[];
        render('production',compact('orders','projects','staff','edit','history')+['title'=>'Produção','active'=>'production']); break;
    case 'finance':
        require_role(['finance']);
        $transactions=rows('SELECT f.*,c.name customer_name,p.title project_title FROM finance f LEFT JOIN customers c ON c.id=f.customer_id LEFT JOIN projects p ON p.id=f.project_id ORDER BY f.due_date DESC,f.id DESC LIMIT 200');
        $totals=one('SELECT SUM(IF(kind="income" AND paid_at IS NOT NULL,amount,0)) revenue,SUM(IF(kind="expense" AND paid_at IS NOT NULL,amount,0)) expenses,SUM(IF(kind="income" AND paid_at IS NULL,amount,0)) receivable,SUM(IF(kind="expense" AND paid_at IS NULL,amount,0)) payable FROM finance');
        $clients=rows('SELECT id,name FROM customers WHERE active=1 ORDER BY name');
        $projects=rows('SELECT id,title FROM projects ORDER BY title LIMIT 300');
        $edit=$getId('edit')?one('SELECT * FROM finance WHERE id=?',[$getId('edit')]):null;
        render('finance',compact('transactions','totals','clients','projects','edit')+['title'=>'Financeiro','active'=>'finance']); break;
    case 'installations':
        $installations=rows('SELECT i.*,p.title project_title,c.name customer_name FROM installations i JOIN projects p ON p.id=i.project_id JOIN customers c ON c.id=p.customer_id ORDER BY i.planned_at DESC LIMIT 200');
        $projects=rows('SELECT id,title FROM projects WHERE status NOT IN ("cancelled") ORDER BY title');
        $edit=$getId('edit')?one('SELECT * FROM installations WHERE id=?',[$getId('edit')]):null;
        render('installations',compact('installations','projects','edit')+['title'=>'Instalações','active'=>'installations']);break;
    case 'cuts':
        $projects=rows('SELECT id,title FROM projects WHERE status!="cancelled" ORDER BY id DESC');
        $boards=rows('SELECT id,name,sku,width_mm,height_mm,thickness_mm FROM materials WHERE category="chapa" AND active=1 AND width_mm>0 AND height_mm>0 ORDER BY name');
        $projectId=$getId('project_id') ?: (int)($projects[0]['id'] ?? 0);
        $materialId=$getId('material_id') ?: (int)($boards[0]['id'] ?? 0);
        $pieces=$projectId && $materialId ? rows('SELECT * FROM cut_pieces WHERE project_id=? AND material_id=? ORDER BY id',[$projectId,$materialId]):[];
        $board=one('SELECT * FROM materials WHERE id=? AND category="chapa"',[$materialId]);
        $plan=$board && $pieces ? optimize_cut($pieces,(float)$board['width_mm'],(float)$board['height_mm']) : null;
        $edgeMeters=0.0;
        foreach ($pieces as $p) $edgeMeters+=($p['edge_top']+$p['edge_bottom'])*(float)$p['width_mm']*(int)$p['quantity']/1000+($p['edge_left']+$p['edge_right'])*(float)$p['height_mm']*(int)$p['quantity']/1000;
        render('cuts',compact('projects','boards','projectId','materialId','pieces','board','plan','edgeMeters')+['title'=>'Plano de corte','active'=>'cuts']);break;
    case 'calendar':
        render('calendar',['title'=>'Agenda','active'=>'calendar']);break;
    case 'api.calendar':
        header('Content-Type: application/json; charset=utf-8');
        $events=[];
        foreach (rows('SELECT w.id,w.title,w.due_date FROM work_orders w WHERE w.due_date IS NOT NULL AND status NOT IN ("cancelled","delivered")') as $w) $events[]=['title'=>'OP · '.$w['title'],'start'=>$w['due_date'],'color'=>'#7c84ff','url'=>url('production',['history'=>$w['id']])];
        foreach (rows('SELECT i.id,p.title,i.planned_at FROM installations i JOIN projects p ON p.id=i.project_id WHERE i.status!="completed"') as $i) $events[]=['title'=>'Instalação · '.$i['title'],'start'=>str_replace(' ','T',$i['planned_at']),'color'=>'#16a98f','url'=>url('installations')];
        foreach (rows('SELECT id,title,due_date FROM projects WHERE due_date IS NOT NULL AND status NOT IN ("completed","cancelled")') as $p) $events[]=['title'=>'Projeto · '.$p['title'],'start'=>$p['due_date'],'color'=>'#eeb969','url'=>url('projects',['edit'=>$p['id']])];
        echo json_encode($events,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG);exit;
    case 'reports':
        require_role(['finance']);
        $byCustomer=rows('SELECT c.name,COUNT(q.id) as quotes,COALESCE(SUM(IF(q.status="approved",q.total,0)),0) approved_sales FROM customers c LEFT JOIN quotes q ON q.customer_id=c.id GROUP BY c.id,c.name ORDER BY approved_sales DESC LIMIT 12');
        $byProject=rows('SELECT p.id,p.title,c.name customer_name,COALESCE((SELECT SUM(IF(f.kind="income",f.amount,-f.amount)) FROM finance f WHERE f.project_id=p.id AND f.paid_at IS NOT NULL),0) cash_result FROM projects p JOIN customers c ON c.id=p.customer_id ORDER BY p.id DESC LIMIT 20');
        $counts=rows('SELECT status,COUNT(*) n FROM work_orders GROUP BY status ORDER BY n DESC');
        render('reports',compact('byCustomer','byProject','counts')+['title'=>'Relatórios','active'=>'reports']);break;
    case 'users':
        require_role([]);
        $users=rows('SELECT id,name,email,role,active,created_at FROM users ORDER BY active DESC,name');
        $edit=$getId('edit')?one('SELECT id,name,email,role,active FROM users WHERE id=?',[$getId('edit')]):null;
        render('users',compact('users','edit')+['title'=>'Usuários','active'=>'users']);break;
    case 'account':
        render('account',['title'=>'Minha conta','active'=>'account']);break;
    default:
        http_response_code(404);
        render('not_found',['title'=>'Página inexistente','active'=>'']);
}
