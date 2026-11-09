<?php
declare(strict_types=1);
/** Instalador CLI para MySQL 8+: php tools/install.php */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente linha de comando.'); }
$root=dirname(__DIR__);
if (in_array('--help',$argv,true)) {
    echo "Uso: php tools/install.php [--schema-only]\nCria o banco e as tabelas; por padrão insere dados e usuários fictícios quando a tabela users está vazia.\n";
    exit;
}
$cfgFile=$root.'/config.local.php';
if (!is_file($cfgFile)) {
    echo "ERRO: Copie config.example.php para config.local.php e configure suas credenciais MySQL.\n";
    exit(1);
}
$config=require $cfgFile;
if (!extension_loaded('pdo_mysql')) {echo "ERRO: extensão pdo_mysql não instalada/ativada.\n";exit(1);}
$name=(string)($config['db_name']??'');
if (!preg_match('/^[a-zA-Z0-9_]+$/D',$name)) {echo "ERRO: Nome do banco inválido.\n";exit(1);}
try {
    $dsn='mysql:host='.$config['db_host'].';port='.($config['db_port']??'3306').';charset=utf8mb4';
    $pdo=new PDO($dsn,$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$name}`");
    $schema=file_get_contents($root.'/database/schema.sql');
    $statements=preg_split('/;\s*(?:\r?\n|$)/',$schema);
    $n=0;
    foreach($statements as $statement) {if(trim($statement)==='')continue;$pdo->exec(trim($statement));$n++;}
    echo "Tabelas verificadas ({$n} comandos SQL).\n";
    if(in_array('--schema-only',$argv,true)) {echo "Modo apenas estrutura concluído.\n";exit;}
    if((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()>0) {echo "Já existem usuários. Dados de demonstração não foram duplicados.\n";exit;}
    $pdo->beginTransaction();
    $insert=function(string $sql,array $params) use ($pdo): int {
        $stmt=$pdo->prepare($sql);$stmt->execute($params);return (int)$pdo->lastInsertId();
    };
    $now=date('Y-m-d');
    $addDate=static fn(int $days): string=>date('Y-m-d',strtotime(($days>=0?'+':'').$days.' days'));
    $admin=$insert('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Administrador Aurora','admin@aurora.local',password_hash('Aurora@2026!',PASSWORD_DEFAULT),'admin']);
    $seller=$insert('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Camila Vendas','comercial@aurora.local',password_hash('Demo@2026!',PASSWORD_DEFAULT),'sales']);
    $maker=$insert('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Rafael Produção','producao@aurora.local',password_hash('Demo@2026!',PASSWORD_DEFAULT),'production']);
    $finance=$insert('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Marina Financeiro','financeiro@aurora.local',password_hash('Demo@2026!',PASSWORD_DEFAULT),'finance']);
    $customers=[];
    foreach([
        ['Ana Martins','PF','111.222.333-44','ana@example.test','(47) 99999-1001','Rua das Palmeiras, 120','Itajaí','SC','Cozinha em tons de carvalho'],
        ['Carlos Oliveira','PF','222.333.444-55','carlos@example.test','(47) 99999-1002','Av. das Flores, 450','Balneário Piçarras','SC','Preferência por MDF branco'],
        ['Studio Horizonte Arquitetura','PJ','12.345.678/0001-90','studio@example.test','(47) 99999-1003','Rua do Design, 51','Itapema','SC','Parceiro arquiteto'],
        ['Fernanda Costa','PF','333.444.555-66','fernanda@example.test','(47) 99999-1004','Rua Azul, 38','Penha','SC','Quarto e home office'],
    ] as $c) $customers[]=$insert('INSERT INTO customers(name,kind,document,email,phone,address,city,state,notes) VALUES(?,?,?,?,?,?,?,?,?)',$c);
    $suppliers=[];
    foreach([
        ['Madeireira Modelo Ltda','11.222.333/0001-55','Luciana','atendimento@madeireira.example.test','(47) 3333-1000','Fornecedor fictício de MDF'],
        ['Ferragens Sul Demo','22.333.444/0001-66','Marcelo','vendas@ferragens.example.test','(47) 3333-2000','Acessórios e corrediças'],
        ['Acabamentos Atlântico','33.444.555/0001-77','Cláudia','contato@acabamentos.example.test','(47) 3333-3000','Fitas e acabamento'],
    ] as $s) $suppliers[]=$insert('INSERT INTO suppliers(name,document,contact,email,phone,notes) VALUES(?,?,?,?,?,?)',$s);
    $materials=[];
    $catalog=[
        ['MDF-BRA18','MDF Branco TX 18mm','chapa','chapa',218.90,5,2750,1850,18,$suppliers[0],18],
        ['MDF-CAR18','MDF Carvalho Natural 18mm','chapa','chapa',294.50,4,2750,1850,18,$suppliers[0],12],
        ['MDF-GRA15','MDF Grafite 15mm','chapa','chapa',249.90,3,2750,1850,15,$suppliers[0],7],
        ['DOB-35A','Dobradiça caneco 35 mm','ferragem','un',8.80,20,null,null,null,$suppliers[1],98],
        ['COR-450','Corrediça telescópica 450 mm','ferragem','par',32.70,12,null,null,null,$suppliers[1],24],
        ['FITA-BR22','Fita de borda branca 22 mm','fita','m',1.80,80,null,null,null,$suppliers[2],230],
        ['FITA-CAR22','Fita de borda carvalho 22 mm','fita','m',2.20,70,null,null,null,$suppliers[2],134],
        ['PUX-160','Puxador perfil 160 mm','ferragem','un',17.50,15,null,null,null,$suppliers[1],36],
        ['PAR-35','Parafuso chipboard 3,5x40','insumo','un',0.13,300,null,null,null,$suppliers[1],1500],
        ['COLA-1K','Cola para madeira 1 kg','insumo','kg',22.00,4,null,null,null,$suppliers[2],9],
        ['MDF-NU06','MDF Cru 6mm','chapa','chapa',115.00,3,2750,1850,6,$suppliers[0],8],
    ];
    foreach($catalog as $m) {
        [$sku,$nm,$cat,$unit,$cost,$minimum,$w,$h,$thick,$sup,$stock]=$m;
        $id=$insert('INSERT INTO materials(sku,name,category,unit,cost,minimum_stock,width_mm,height_mm,thickness_mm,supplier_id,stock) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$sku,$nm,$cat,$unit,$cost,$minimum,$w,$h,$thick,$sup,0]);
        $materials[$sku]=$id;
        $insert('INSERT INTO stock_movements(material_id,delta,before_qty,after_qty,reason,user_id) VALUES(?,?,?,?,?,?)',[$id,$stock,0,$stock,'Saldo inicial fictício',$admin]);
        $pdo->prepare('UPDATE materials SET stock=? WHERE id=?')->execute([$stock,$id]);
    }
    $projects=[];
    foreach([
        [$customers[0],'Cozinha Essenza','Cozinha','MDF carvalho com branco. Puxadores perfil.','Parede principal: 3200 mm. Pé-direito: 2600 mm.','in_production',$addDate(16)],
        [$customers[1],'Dormitório Sereno','Dormitório','Guarda-roupa casal com seis portas.','Largura 2800 mm, altura 2450 mm.','planning',$addDate(28)],
        [$customers[2],'Recepção Studio Horizonte','Recepção','Balcão com acabamento grafite e nichos.','Balcão em L de 2400 x 1700 mm.','approved',$addDate(12)],
        [$customers[3],'Closet Jardim','Closet','Closet sob medida com iluminação futura.','2700 x 2200 mm.','installation',$addDate(6)],
    ] as $p) $projects[]=$insert('INSERT INTO projects(customer_id,title,environment,description,measurements,status,due_date,created_by) VALUES(?,?,?,?,?,?,?,?)',[...$p,$admin]);
    $createQuote=function(int $customerId,?int $projectId,string $title,string $status,float $markup,float $labor,float $tax,float $discount,array $items) use($insert,$pdo,$admin,$addDate):int {
        $base=0;
        foreach($items as $item) $base+=$item[2]*$item[4];
        $base=round($base+$labor,2);$sale=round($base*(1+$markup/100),2);$total=round($sale+round($sale*$tax/100,2)-$discount,2);
        $q=$insert('INSERT INTO quotes(code,customer_id,project_id,title,status,markup_pct,labor_cost,tax_pct,discount,subtotal,total,notes,valid_until,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            ['TMP-'.$title,$customerId,$projectId,$title,$status,$markup,$labor,$tax,$discount,$base,$total,'Valores de demonstração. Sujeito a conferência técnica.',$addDate(15),$admin]);
        $pdo->prepare('UPDATE quotes SET code=? WHERE id=?')->execute([sprintf('ORC-%06d',$q),$q]);
        foreach($items as $item) $insert('INSERT INTO quote_items(quote_id,material_id,description,quantity,unit,unit_cost) VALUES(?,?,?,?,?,?)',[$q,...$item]);
        return $q;
    };
    $q1=$createQuote($customers[0],$projects[0],'Cozinha Essenza','approved',38,1700,5,200,[
        [$materials['MDF-CAR18'],'MDF Carvalho Natural 18mm',3,'chapa',294.50],
        [$materials['MDF-BRA18'],'MDF Branco TX 18mm',2,'chapa',218.90],
        [$materials['DOB-35A'],'Dobradiças caneco 35 mm',16,'un',8.80],
        [$materials['COR-450'],'Corrediça telescópica 450 mm',6,'par',32.70],
    ]);
    $q2=$createQuote($customers[1],$projects[1],'Guarda-roupa Sereno','sent',33,2200,5,0,[
        [$materials['MDF-BRA18'],'MDF Branco TX 18mm',5,'chapa',218.90],
        [$materials['DOB-35A'],'Dobradiça caneco 35 mm',20,'un',8.80],
        [$materials['COR-450'],'Corrediça telescópica 450 mm',4,'par',32.70],
    ]);
    $q3=$createQuote($customers[2],$projects[2],'Balcão recepção e nichos','approved',40,1900,4.5,150,[
        [$materials['MDF-GRA15'],'MDF Grafite 15mm',4,'chapa',249.90],
        [$materials['MDF-NU06'],'MDF Cru 6mm',2,'chapa',115.00],
    ]);
    // ORDENS: exemplo aprovado com projeto e uma ordem avulsa.
    $order=function(?int $quoteId,int $projectId,string $title,string $status,string $priority,int $assignee,string $due)use($insert,$pdo,$admin):int{
        $id=$insert('INSERT INTO work_orders(code,project_id,quote_id,title,status,priority,assigned_to,due_date) VALUES(?,?,?,?,?,?,?,?)',['TMP-'.uniqid(),$projectId,$quoteId,$title,$status,$priority,$assignee,$due]);
        $pdo->prepare('UPDATE work_orders SET code=? WHERE id=?')->execute([sprintf('OP-%06d',$id),$id]);
        $insert('INSERT INTO work_order_events(work_order_id,old_status,new_status,user_id) VALUES(?,?,?,?)',[$id,'queued',$status,$admin]);
        return $id;
    };
    $order($q1,$projects[0],'Produção cozinha Essenza','assembly','high',$maker,$addDate(12));
    $order($q3,$projects[2],'Balcão e nichos para recepção','cutting','normal',$maker,$addDate(9));
    $order(null,$projects[3],'Ajustes finais Closet Jardim','ready','normal',$maker,$addDate(3));
    $entries=[
      ['Sinal cozinha Essenza','income','Projeto',3000,$addDate(-8),$addDate(-8),$customers[0],$projects[0]],
      ['Saldo previsto cozinha Essenza','income','Projeto',4100,$addDate(18),null,$customers[0],$projects[0]],
      ['Recebimento parcial balcão','income','Projeto',2500,$addDate(-2),$addDate(-2),$customers[2],$projects[2]],
      ['Compra de MDF e ferragens','expense','Materiais',1680,$addDate(-6),$addDate(-6),null,$projects[0]],
      ['Aluguel do galpão','expense','Fixo',2400,$addDate(5),null,null,null],
      ['Energia elétrica','expense','Fixo',550,$addDate(-3),$addDate(-3),null,null],
      ['Frete e logística','expense','Logística',280,$addDate(13),null,null,$projects[2]],
    ];
    foreach($entries as $f) $insert('INSERT INTO finance(description,kind,category,amount,due_date,paid_at,customer_id,project_id,created_by) VALUES(?,?,?,?,?,?,?,?,?)',[...$f,$finance]);
    foreach([
        [$projects[3],$addDate(4).' 09:00:00','Rafael e João','Rua Azul, 38 – Penha / SC','Verificar ferragens e limpeza final','Confirmar acesso à residência','scheduled'],
        [$projects[2],$addDate(14).' 08:30:00','Equipe de montagem A','Rua do Design, 51 – Itapema / SC','Conferir nichos e acabamento','Solicitar acesso à recepção','scheduled'],
    ] as $i) $insert('INSERT INTO installations(project_id,planned_at,team,address,checklist,notes,status) VALUES(?,?,?,?,?,?,?)',$i);
    foreach([
      [$projects[0],$materials['MDF-CAR18'],'Lateral esquerda',550,720,2,'vertical',1,1,0,1],
      [$projects[0],$materials['MDF-CAR18'],'Base',850,560,2,'free',0,0,1,1],
      [$projects[0],$materials['MDF-CAR18'],'Prateleira',810,540,3,'free',1,0,0,0],
      [$projects[1],$materials['MDF-BRA18'],'Porta armário',450,2400,4,'vertical',1,1,1,1],
      [$projects[1],$materials['MDF-BRA18'],'Prateleira',780,480,6,'free',1,0,0,0],
      [$projects[2],$materials['MDF-GRA15'],'Frente balcão',1200,650,2,'horizontal',1,1,1,1],
      [$projects[2],$materials['MDF-GRA15'],'Tampo',1300,550,1,'horizontal',1,1,1,1],
    ] as $p) $insert('INSERT INTO cut_pieces(project_id,material_id,label,width_mm,height_mm,quantity,grain,edge_top,edge_bottom,edge_left,edge_right) VALUES(?,?,?,?,?,?,?,?,?,?,?)',$p);
    $pdo->commit();
    echo "Dados fictícios adicionados.\n\nAcesso: admin@aurora.local / Aurora@2026!\n\nATENÇÃO: troque a senha ao colocar em produção.\n";
} catch(Throwable $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'Falha na instalação: '.$e->getMessage()."\n"); exit(1);
}
