<div class="auth-grid">
  <div class="auth-hero">
    <div class="brand" style="padding:0"><div class="brand-symbol"><span>A</span></div><div><strong>AURORA<span class="brand-dot">.</span></strong><small>GESTÃO DE MARCENARIA</small></div></div>
    <div><div class="eyebrow">SEU ATELIÊ, EM ORDEM.</div><h1>Cada projeto merece <span>um processo perfeito.</span></h1><p>Clientes, orçamentos, produção, estoque e financeiro no mesmo lugar. Do primeiro contato à última instalação.</p></div>
    <div class="auth-motif">A.</div>
  </div>
  <div class="auth-right">
    <div class="eyebrow">BEM-VINDO DE VOLTA</div>
    <h2>Acesse sua conta.</h2><p>Entre com suas credenciais para acessar o painel.</p>
    <form method="post" action="<?=e(url('auth.login'))?>">
      <?=csrf_field()?><div class="field"><label for="email">E-mail</label><input id="email" type="email" name="email" value="admin@aurora.local" autocomplete="username" required></div>
      <div class="field"><label for="password">Senha</label><input id="password" type="password" name="password" placeholder="Digite sua senha" autocomplete="current-password" required></div>
      <button class="btn-aurora" type="submit">Acessar painel <?=svg_icon('arrow')?></button>
    </form>
    <div class="auth-demo"><strong class="text-accent">ACESSO DE DEMONSTRAÇÃO</strong><br>E-mail: <b>admin@aurora.local</b><br>Senha: <b>Aurora@2026!</b><br><span class="muted">Substitua a senha antes de publicar o sistema.</span></div>
  </div>
</div>
