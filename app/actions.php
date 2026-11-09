<?php
// Todas as alterações passam por requisições POST com token CSRF.
if ($route === 'auth.login') {
    if (logged_in()) redirect('dashboard');
    $email = strtolower(post_str('email', 190));
    $password = (string)($_POST['password'] ?? '');
    $attempts = $_SESSION['login_attempts'] ?? ['count'=>0,'until'=>0];
    if ($attempts['until'] > time()) {
        flash('Muitas tentativas. Tente novamente em alguns minutos.', 'danger');
        redirect('login');
    }
    $user = one('SELECT id, name, email, password_hash, role, active FROM users WHERE email = ?', [$email]);
    if (!$user || !(bool)$user['active'] || !password_verify($password, $user['password_hash'])) {
        $attempts['count']++;
        if ($attempts['count'] >= 6) $attempts = ['count'=>0,'until'=>time()+300];
        $_SESSION['login_attempts'] = $attempts;
        flash('E-mail ou senha incorretos.', 'danger');
        redirect('login');
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        execSql('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['user'] = array_intersect_key($user, array_flip(['id','name','email','role']));
    unset($_SESSION['login_attempts']);
    redirect('dashboard');
}
if ($route === 'auth.logout') {
    require_auth();
    $_SESSION = [];
    session_regenerate_id(true);
    flash('Sessão encerrada.');
    redirect('login');
}
require_auth();

switch ($route) {
    case 'customers.save':
        require_role(['sales']);
        $id = post_id('id');
        $values = [required('name','nome'), safe_enum('kind',['PF','PJ'],'PF'), post_str('document',22), post_str('email',190),post_str('phone',30),post_str('address'),post_str('city',100),strtoupper(post_str('state',2)),post_str('notes',5000)];
        if ($values[3] && !filter_var($values[3], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-mail inválido.');
        if ($id) execSql('UPDATE customers SET name=?,kind=?,document=?,email=?,phone=?,address=?,city=?,state=?,notes=? WHERE id=?', [...$values,$id]);
        else execSql('INSERT INTO customers (name,kind,document,email,phone,address,city,state,notes) VALUES (?,?,?,?,?,?,?,?,?)', $values);
        flash($id ? 'Cliente atualizado.' : 'Cliente cadastrado.'); redirect('customers');
    case 'customers.archive':
        require_role(['sales']);
        $id=post_id('id'); if (!$id) throw new InvalidArgumentException('Cliente inválido.');
        execSql('UPDATE customers SET active=1-active WHERE id=?', [$id]);
        flash('Situação do cliente alterada.'); redirect('customers');
    case 'suppliers.save':
        require_role(['sales','production']);
        $id=post_id('id');
        $v=[required('name','nome'),post_str('document',22),post_str('contact',120),post_str('email',190),post_str('phone',30),post_str('notes',5000)];
        if ($v[3] && !filter_var($v[3],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-mail inválido.');
        if ($id) execSql('UPDATE suppliers SET name=?,document=?,contact=?,email=?,phone=?,notes=? WHERE id=?', [...$v,$id]);
        else execSql('INSERT INTO suppliers (name,document,contact,email,phone,notes) VALUES (?,?,?,?,?,?)',$v);
        flash('Fornecedor salvo.'); redirect('suppliers');
    case 'suppliers.archive':
        require_role(['sales','production']);
        $id=post_id('id'); if (!$id) throw new InvalidArgumentException('Fornecedor inválido.');
        execSql('UPDATE suppliers SET active=1-active WHERE id=?',[$id]); flash('Situação alterada.'); redirect('suppliers');
    case 'materials.save':
        require_role(['production']);
        $id=post_id('id');
        $sku=required('sku','código SKU',50);
        $name=required('name','nome');
        $category=safe_enum('category',['chapa','ferragem','fita','acabamento','insumo','outro'],'outro');
        $unit=required('unit','unidade',15);
        $cost=round(positive_or_zero($_POST['cost'] ?? '', 'Custo'),2);
        $minimum=positive_or_zero($_POST['minimum_stock'] ?? '', 'Estoque mínimo');
        $supplierId=post_id('supplier_id');
        $dimensions=[];
        foreach (['width_mm','height_mm','thickness_mm'] as $key) {
            $raw=trim((string)($_POST[$key] ?? ''));
            $dimensions[]=$raw === '' ? null : positive_or_zero($raw, $key);
        }
        $v=[$sku,$name,$category,$unit,$cost,$minimum,...$dimensions,$supplierId];
        if ($id) {
            execSql('UPDATE materials SET sku=?,name=?,category=?,unit=?,cost=?,minimum_stock=?,width_mm=?,height_mm=?,thickness_mm=?,supplier_id=? WHERE id=?', [...$v,$id]);
        } else {
            execSql('INSERT INTO materials (sku,name,category,unit,cost,minimum_stock,width_mm,height_mm,thickness_mm,supplier_id) VALUES (?,?,?,?,?,?,?,?,?,?)', $v);
        }
        flash('Material salvo.'); redirect('materials');
    case 'materials.archive':
        require_role(['production']);
        $id=post_id('id'); if (!$id) throw new InvalidArgumentException('Material inválido.');
        execSql('UPDATE materials SET active=1-active WHERE id=?',[$id]); flash('Situação do material alterada.'); redirect('materials');
    case 'stock.move':
        require_role(['production']);
        $id=post_id('material_id');
        if (!$id) throw new InvalidArgumentException('Selecione um material.');
        $type=safe_enum('type',['in','out'],'in');
        $quantity=positive_or_zero($_POST['quantity'] ?? '', 'Quantidade');
        if ($quantity <= 0) throw new InvalidArgumentException('A quantidade precisa ser positiva.');
        $reason=required('reason','motivo');
        db()->beginTransaction();
        try {
            $m=one('SELECT stock FROM materials WHERE id=? AND active=1 FOR UPDATE',[$id]);
            if (!$m) throw new InvalidArgumentException('Material inexistente ou inativo.');
            $before=(float)$m['stock']; $delta=$type === 'out' ? -$quantity : $quantity;
            $after=round($before+$delta,3);
            if ($after < -0.0001) throw new InvalidArgumentException('Estoque insuficiente para esta saída.');
            execSql('UPDATE materials SET stock=? WHERE id=?',[$after,$id]);
            execSql('INSERT INTO stock_movements(material_id,delta,before_qty,after_qty,reason,user_id) VALUES (?,?,?,?,?,?)',[$id,$delta,$before,$after,$reason,current_user()['id']]);
            db()->commit();
        } catch (Throwable $ex) { db()->rollBack(); throw $ex; }
        flash('Movimentação registrada com histórico.'); redirect('stock');
    case 'projects.save':
        require_role(['sales','production']);
        $id=post_id('id'); $customer=post_id('customer_id');
        if (!$customer || !one('SELECT id FROM customers WHERE id=?',[$customer])) throw new InvalidArgumentException('Selecione um cliente válido.');
        $v=[$customer,required('title','projeto'),post_str('environment',120),post_str('description',6000),post_str('measurements',6000),safe_enum('status',['planning','approved','in_production','installation','completed','cancelled'],'planning'),safe_date('due_date')];
        if ($id && one('SELECT q.id FROM quotes q WHERE q.project_id=? AND q.customer_id<>? LIMIT 1',[$id,$customer])) throw new InvalidArgumentException('Há orçamentos associados a outro cliente. Alinhe os vínculos antes de mudar o projeto.');
        if ($id) execSql('UPDATE projects SET customer_id=?,title=?,environment=?,description=?,measurements=?,status=?,due_date=? WHERE id=?',[...$v,$id]);
        else execSql('INSERT INTO projects(customer_id,title,environment,description,measurements,status,due_date,created_by) VALUES (?,?,?,?,?,?,?,?)',[...$v,current_user()['id']]);
        flash('Projeto salvo.'); redirect('projects');
    case 'quotes.save':
        require_role(['sales']);
        $id=post_id('id');
        if ($id) {
            $old=one('SELECT status FROM quotes WHERE id=?',[$id]);
            if (!$old || !in_array($old['status'],['draft','sent'],true)) throw new InvalidArgumentException('Apenas orçamentos em rascunho ou enviados podem ser editados.');
        }
        $customer=post_id('customer_id');
        if (!$customer || !one('SELECT id FROM customers WHERE id=?',[$customer])) throw new InvalidArgumentException('Cliente inválido.');
        $project=post_id('project_id');
        if ($project && !one('SELECT id FROM projects WHERE id=? AND customer_id=?',[$project,$customer])) throw new InvalidArgumentException('O projeto precisa pertencer ao cliente escolhido.');
        $title=required('title','título');
        $markup=round(positive_or_zero($_POST['markup_pct'] ?? '', 'Acréscimo'),2);
        $labor=round(positive_or_zero($_POST['labor_cost'] ?? '', 'Mão de obra'),2);
        $tax=round(positive_or_zero($_POST['tax_pct'] ?? '', 'Imposto'),2);
        $discount=round(positive_or_zero($_POST['discount'] ?? '', 'Desconto'),2);
        if ($markup > 1000 || $tax > 100) throw new InvalidArgumentException('Percentual fora do intervalo permitido.');
        $inputItems=$_POST['items'] ?? [];
        if (!is_array($inputItems) || count($inputItems)>100) throw new InvalidArgumentException('Lista de itens inválida (máximo 100).');
        $items=[];
        foreach ($inputItems as $item) {
            if (!is_array($item)) continue;
            $desc=mb_safe_trim((string)($item['description'] ?? ''),255);
            if ($desc === '') continue;
            $q=positive_or_zero($item['quantity'] ?? '', 'Quantidade do item');
            $price=round(positive_or_zero($item['unit_cost'] ?? '', 'Valor unitário'),2);
            if ($q <= 0) throw new InvalidArgumentException('Quantidade de item deve ser positiva.');
            $materialId=filter_var($item['material_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
            if ($materialId && !one('SELECT id FROM materials WHERE id=?',[$materialId])) throw new InvalidArgumentException('Material inválido no orçamento.');
            $items[]=['description'=>$desc,'material_id'=>$materialId,'quantity'=>$q,'unit'=>mb_safe_trim((string)($item['unit'] ?? 'un'),15) ?: 'un','unit_cost'=>$price];
        }
        if (!$items) throw new InvalidArgumentException('Adicione pelo menos um item ao orçamento.');
        $totals=quote_total($items,$labor,$markup,$tax,$discount);
        if ($discount > $totals['sale'] + $totals['tax']) throw new InvalidArgumentException('O desconto não pode superar o valor calculado.');
        $v=[$customer,$project,$title,$markup,$labor,$tax,$discount,$totals['subtotal'],$totals['total'],post_str('notes',5000),safe_date('valid_until')];
        db()->beginTransaction();
        try {
            if ($id) {
                $locked=one('SELECT status FROM quotes WHERE id=? FOR UPDATE',[$id]);
                if (!$locked || !in_array($locked['status'],['draft','sent'],true)) throw new InvalidArgumentException('O orçamento foi finalizado durante a edição. Atualize a página.');
                execSql('UPDATE quotes SET customer_id=?,project_id=?,title=?,markup_pct=?,labor_cost=?,tax_pct=?,discount=?,subtotal=?,total=?,notes=?,valid_until=? WHERE id=?',[...$v,$id]);
                execSql('DELETE FROM quote_items WHERE quote_id=?',[$id]);
            } else {
                execSql('INSERT INTO quotes(code,customer_id,project_id,title,markup_pct,labor_cost,tax_pct,discount,subtotal,total,notes,valid_until,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    ['TMP-'.bin2hex(random_bytes(6)),...$v,current_user()['id']]);
                $id=(int)db()->lastInsertId();
                execSql('UPDATE quotes SET code=? WHERE id=?',[sprintf('ORC-%06d',$id),$id]);
            }
            foreach ($items as $item) execSql('INSERT INTO quote_items(quote_id,material_id,description,quantity,unit,unit_cost) VALUES (?,?,?,?,?,?)',
                [$id,$item['material_id'],$item['description'],$item['quantity'],$item['unit'],$item['unit_cost']]);
            db()->commit();
        } catch(Throwable $ex) { db()->rollBack(); throw $ex; }
        flash('Orçamento salvo e valores recalculados no servidor.'); redirect('quotes');
    case 'quotes.status':
        require_role(['sales']);
        $id=post_id('id'); $status=safe_enum('status',['draft','sent','approved','rejected'],'draft');
        $q=$id ? one('SELECT status FROM quotes WHERE id=?',[$id]) : null;
        if (!$q) throw new InvalidArgumentException('Orçamento não encontrado.');
        if (in_array($q['status'],['approved','rejected'],true)) throw new InvalidArgumentException('Orçamento já finalizado.');
        if ($status==='draft' && $q['status']==='sent') throw new InvalidArgumentException('Não é permitido voltar para rascunho.');
        execSql('UPDATE quotes SET status=? WHERE id=?',[$status,$id]);
        flash('Situação do orçamento alterada.'); redirect('quotes');
    case 'quotes.order':
        require_role(['sales','production']);
        $id=post_id('id');
        db()->beginTransaction();
        try {
            $q=$id ? one('SELECT * FROM quotes WHERE id=? FOR UPDATE',[$id]) : null;
            if (!$q || $q['status'] !== 'approved') throw new InvalidArgumentException('Apenas orçamentos aprovados podem gerar produção.');
            if (one('SELECT id FROM work_orders WHERE quote_id=?',[$id])) throw new InvalidArgumentException('Já existe uma ordem de produção para esse orçamento.');
            $projectId=$q['project_id'];
            if (!$projectId) {
                execSql('INSERT INTO projects(customer_id,title,environment,status,created_by) VALUES (?,?,?,?,?)',[$q['customer_id'],$q['title'],'','approved',current_user()['id']]);
                $projectId=(int)db()->lastInsertId();
                execSql('UPDATE quotes SET project_id=? WHERE id=?',[$projectId,$id]);
            }
            execSql('INSERT INTO work_orders(code,project_id,quote_id,title,status,priority) VALUES (?,?,?,?,?,?)',
                ['TMP-'.bin2hex(random_bytes(6)),$projectId,$id,$q['title'],'queued','normal']);
            $orderId=(int)db()->lastInsertId();
            execSql('UPDATE work_orders SET code=? WHERE id=?',[sprintf('OP-%06d',$orderId),$orderId]);
            execSql('UPDATE projects SET status=? WHERE id=?',['approved',$projectId]);
            execSql('INSERT INTO work_order_events(work_order_id,old_status,new_status,user_id) VALUES (?,?,?,?)',[$orderId,null,'queued',current_user()['id']]);
            db()->commit();
        } catch(Throwable $ex) { db()->rollBack(); throw $ex; }
        flash('Ordem de produção criada a partir do orçamento aprovado.'); redirect('production');
    case 'workorders.save':
        require_role(['production']);
        $id=post_id('id'); $project=post_id('project_id');
        if (!$project || !one('SELECT id FROM projects WHERE id=?',[$project])) throw new InvalidArgumentException('Projeto inválido.');
        $v=[$project,required('title','descrição'),safe_enum('priority',['low','normal','high'],'normal'),post_id('assigned_to'),safe_date('due_date'),post_str('notes',5000)];
        if ($id) {
            $origin=one('SELECT project_id,quote_id FROM work_orders WHERE id=?',[$id]);
            if (!$origin) throw new InvalidArgumentException('Ordem não encontrada.');
            if ($origin['quote_id'] && (int)$origin['project_id']!==$project) throw new InvalidArgumentException('A ordem gerada por orçamento não pode trocar de projeto.');
            execSql('UPDATE work_orders SET project_id=?,title=?,priority=?,assigned_to=?,due_date=?,notes=? WHERE id=?',[...$v,$id]);
        } else {
            execSql('INSERT INTO work_orders(code,project_id,title,priority,assigned_to,due_date,notes) VALUES (?,?,?,?,?,?,?)',
                ['TMP-'.bin2hex(random_bytes(6)),...$v]);
            $id=(int)db()->lastInsertId();
            execSql('UPDATE work_orders SET code=? WHERE id=?',[sprintf('OP-%06d',$id),$id]);
            execSql('INSERT INTO work_order_events(work_order_id,new_status,user_id) VALUES (?,?,?)',[$id,'queued',current_user()['id']]);
        }
        flash('Ordem de produção salva.'); redirect('production');
    case 'workorders.status':
        require_role(['production']);
        $id=post_id('id');
        $status=safe_enum('status',['queued','cutting','edgebanding','assembly','finishing','ready','delivered','cancelled'],'queued');
        db()->beginTransaction();
        try {
            $wo=$id ? one('SELECT status,project_id FROM work_orders WHERE id=? FOR UPDATE',[$id]) : null;
            if (!$wo) throw new InvalidArgumentException('Ordem de produção não encontrada.');
            if (in_array($wo['status'],['delivered','cancelled'],true)) throw new InvalidArgumentException('Esta ordem já está encerrada.');
            if ($status !== $wo['status']) {
                execSql('UPDATE work_orders SET status=? WHERE id=?',[$status,$id]);
                execSql('INSERT INTO work_order_events(work_order_id,old_status,new_status,user_id) VALUES (?,?,?,?)',[$id,$wo['status'],$status,current_user()['id']]);
                if (in_array($status,['cutting','edgebanding','assembly','finishing','ready'],true)) execSql('UPDATE projects SET status=? WHERE id=? AND status NOT IN ("completed","cancelled")',['in_production',$wo['project_id']]);
            }
            db()->commit();
        } catch(Throwable $ex) { db()->rollBack(); throw $ex; }
        flash('Etapa de produção atualizada.'); redirect('production');
    case 'finance.save':
        require_role(['finance']);
        $id=post_id('id');
        $kind=safe_enum('kind',['income','expense'],'expense');
        $amount=round(positive_or_zero($_POST['amount'] ?? '', 'Valor'),2);
        if ($amount <= 0) throw new InvalidArgumentException('Valor precisa ser positivo.');
        $customer=post_id('customer_id'); $project=post_id('project_id');
        $v=[required('description','descrição',220),$kind,required('category','categoria',80),$amount,safe_date('due_date'),$customer,$project];
        if (!$v[4]) throw new InvalidArgumentException('Informe a data de vencimento.');
        if ($id) execSql('UPDATE finance SET description=?,kind=?,category=?,amount=?,due_date=?,customer_id=?,project_id=? WHERE id=?',[...$v,$id]);
        else execSql('INSERT INTO finance(description,kind,category,amount,due_date,customer_id,project_id,created_by) VALUES (?,?,?,?,?,?,?,?)',[...$v,current_user()['id']]);
        flash('Lançamento financeiro salvo.'); redirect('finance');
    case 'finance.paid':
        require_role(['finance']);
        $id=post_id('id'); if (!$id) throw new InvalidArgumentException('Lançamento inválido.');
        execSql('UPDATE finance SET paid_at=IF(paid_at IS NULL,CURDATE(),NULL) WHERE id=?',[$id]);
        flash('Situação do pagamento atualizada.'); redirect('finance');
    case 'installations.save':
        require_role(['production']);
        $id=post_id('id'); $project=post_id('project_id');
        if (!$project || !one('SELECT id FROM projects WHERE id=?',[$project])) throw new InvalidArgumentException('Projeto inválido.');
        $v=[$project,safe_datetime('planned_at'),required('team','equipe'),post_str('address'),post_str('checklist',5000),post_str('notes',5000)];
        if ($id) execSql('UPDATE installations SET project_id=?,planned_at=?,team=?,address=?,checklist=?,notes=? WHERE id=?',[...$v,$id]);
        else execSql('INSERT INTO installations(project_id,planned_at,team,address,checklist,notes) VALUES (?,?,?,?,?,?)',$v);
        flash('Instalação salva.'); redirect('installations');
    case 'installations.status':
        require_role(['production']);
        $id=post_id('id'); $status=safe_enum('status',['scheduled','in_progress','completed','pending'],'scheduled');
        $install=$id ? one('SELECT project_id,status FROM installations WHERE id=?',[$id]) : null;
        if (!$install) throw new InvalidArgumentException('Instalação não encontrada.');
        if ($install['status']==='completed' && $status!=='completed') throw new InvalidArgumentException('Instalações concluídas não podem ser reabertas.');
        db()->beginTransaction();
        try {
            execSql('UPDATE installations SET status=?,completed_at=IF(?="completed",NOW(),NULL) WHERE id=?',[$status,$status,$id]);
            if ($status==='completed') execSql('UPDATE projects SET status="completed" WHERE id=?',[$install['project_id']]);
            elseif ($status==='in_progress') execSql('UPDATE projects SET status="installation" WHERE id=? AND status NOT IN ("completed","cancelled")',[$install['project_id']]);
            db()->commit();
        } catch(Throwable $ex) { db()->rollBack(); throw $ex; }
        flash('Instalação atualizada.'); redirect('installations');
    case 'cuts.save':
        require_role(['production']);
        $project=post_id('project_id'); $material=post_id('material_id');
        if (!$project || !one('SELECT id FROM projects WHERE id=?',[$project])) throw new InvalidArgumentException('Projeto inválido.');
        if (!$material || !one('SELECT id FROM materials WHERE id=? AND category="chapa" AND width_mm>0 AND height_mm>0',[$material])) throw new InvalidArgumentException('Escolha uma chapa com dimensões cadastradas.');
        $w=round(positive_or_zero($_POST['width_mm'] ?? '','Largura'),1);
        $h=round(positive_or_zero($_POST['height_mm'] ?? '','Altura'),1);
        $n=filter_var($_POST['quantity'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>500]]);
        if ($w<=0 || $h<=0 || !$n) throw new InvalidArgumentException('Medidas e quantidade precisam ser positivas (máximo 500).');
        $v=[$project,$material,required('label','identificação'),$w,$h,$n,safe_enum('grain',['free','vertical','horizontal'],'free')];
        foreach (['edge_top','edge_bottom','edge_left','edge_right'] as $key) $v[] = isset($_POST[$key]) ? 1 : 0;
        execSql('INSERT INTO cut_pieces(project_id,material_id,label,width_mm,height_mm,quantity,grain,edge_top,edge_bottom,edge_left,edge_right) VALUES (?,?,?,?,?,?,?,?,?,?,?)',$v);
        flash('Peça adicionada ao plano de corte.'); redirect('cuts',['project_id'=>$project,'material_id'=>$material]);
    case 'cuts.delete':
        require_role(['production']);
        $id=post_id('id'); $piece=$id ? one('SELECT project_id,material_id FROM cut_pieces WHERE id=?',[$id]) : null;
        if (!$piece) throw new InvalidArgumentException('Peça inexistente.');
        execSql('DELETE FROM cut_pieces WHERE id=?',[$id]); flash('Peça removida.'); redirect('cuts',['project_id'=>$piece['project_id'],'material_id'=>$piece['material_id']]);
    case 'account.password':
        $old=(string)($_POST['current_password'] ?? '');
        $password=(string)($_POST['new_password'] ?? '');
        $confirm=(string)($_POST['confirm_password'] ?? '');
        if (strlen($password)<10) throw new InvalidArgumentException('A senha precisa ter pelo menos 10 caracteres.');
        if ($password!==$confirm) throw new InvalidArgumentException('A confirmação da nova senha não corresponde.');
        $u=one('SELECT password_hash FROM users WHERE id=?',[current_user()['id']]);
        if (!$u || !password_verify($old,$u['password_hash'])) throw new InvalidArgumentException('A senha atual está incorreta.');
        execSql('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),current_user()['id']]);
        session_regenerate_id(true);
        flash('Senha alterada com sucesso.'); redirect('account');
    case 'users.save':
        require_role([]); // somente administradores
        $id=post_id('id'); $name=required('name','nome',120); $email=strtolower(required('email','e-mail',190));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-mail inválido.');
        $role=safe_enum('role',['admin','sales','production','finance'],'sales');
        $active=isset($_POST['active']) ? 1 : 0;
        if ($id && (int)current_user()['id']===$id && (!$active || $role!=='admin')) throw new InvalidArgumentException('Não é possível desativar ou rebaixar o próprio administrador.');
        $password=(string)($_POST['password'] ?? '');
        if ($password!=='' && strlen($password)<10) throw new InvalidArgumentException('A senha precisa ter pelo menos 10 caracteres.');
        if ($id) {
            execSql('UPDATE users SET name=?,email=?,role=?,active=? WHERE id=?',[$name,$email,$role,$active,$id]);
            if ($password!=='') execSql('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
            if ((int)current_user()['id']===$id) $_SESSION['user']['name']=$name;
        } else {
            if ($password==='') throw new InvalidArgumentException('Informe uma senha para o novo usuário.');
            execSql('INSERT INTO users(name,email,password_hash,role,active) VALUES (?,?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$active]);
        }
        flash('Usuário salvo.'); redirect('users');
    default:
        http_response_code(404);
        render('not_found', ['title'=>'Página inexistente','active'=>'']);
}
