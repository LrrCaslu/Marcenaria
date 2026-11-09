'use strict';
const sidebar = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebarBackdrop');
const toggle = (value) => { sidebar?.classList.toggle('open', value); backdrop?.classList.toggle('open', value); };
document.getElementById('openSidebar')?.addEventListener('click', () => toggle(true));
backdrop?.addEventListener('click', () => toggle(false));
document.addEventListener('keydown', e => { if (e.key === 'Escape') toggle(false); });
document.querySelectorAll('[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!confirm(form.dataset.confirm)) event.preventDefault();
  });
});
// Campos monetários mantêm a convenção decimal com ponto no HTML;
// a formatação em reais aparece em componentes de leitura.
const brl = value => new Intl.NumberFormat('pt-BR', {style:'currency',currency:'BRL'}).format(Number(value)||0);
window.aurora = {brl};
