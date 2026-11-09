<div class="page-heading"><div><div class="eyebrow">PLANEJAMENTO E PRAZOS</div><h1>Agenda</h1><p>Instalações, vencimentos de produção e entregas de projeto.</p></div><div class="page-actions"><a class="btn-aurora" href="<?=e(url('installations',['new'=>1]))?>#editor"><?=svg_icon('plus',16)?> Agendar instalação</a></div></div>
<div class="panel panel-pad"><div style="display:flex;gap:13px;flex-wrap:wrap;margin-bottom:23px"><span class="status-badge badge-success">Instalações</span><span class="status-badge badge-info">Ordens de produção</span><span class="status-badge badge-muted">Prazos dos projetos</span></div><div id="fullcalendar" style="min-height:600px"></div><p id="calendar-fallback" class="hint" style="display:none">O calendário visual precisa carregar a biblioteca FullCalendar. Consulte os prazos nos módulos Projetos, Produção e Instalações.</p></div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 if(!window.FullCalendar){document.getElementById('calendar-fallback').style.display='block';return;}
 const calendar=new FullCalendar.Calendar(document.getElementById('fullcalendar'),{initialView:window.innerWidth<700?'listMonth':'dayGridMonth',locale:'pt-br',height:'auto',firstDay:0,headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,listMonth'},buttonText:{today:'Hoje',month:'Mês',week:'Semana',list:'Lista'},events:<?=json_encode(url('api.calendar'),JSON_HEX_TAG)?>});calendar.render();
});
</script>
