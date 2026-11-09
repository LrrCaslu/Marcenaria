# Marcenaria Aurora — ERP demonstrativo

Sistema web funcional em **PHP 8.3+**, **MySQL 8**, HTML5, CSS3 e JavaScript. A empresa, os clientes e os materiais pré-cadastrados são **fictícios**. Inclui interface dark responsiva, permissões por função, transações no estoque e gerador de propostas.

> **Estado do projeto:** versão MVP funcional para testes locais. Não é um produto final certificado para uso fiscal, industrial ou operação crítica. Veja as limitações abaixo.

## Instalacao recomendada no ServBay (Windows)

1. Extraia a pasta em `C:\ServBay\www\marcenaria-aurora`.
2. No ServBay, instale/ative **PHP 8.3+** e **MySQL 8**.
3. Execute `INSTALAR_SERVBAY_WINDOWS.bat` (duplo clique). Informe as credenciais **reais** do MySQL do ServBay. O script verifica a conexao e cria o banco, tabelas e dados ficticios.
4. No menu **Sites** do ServBay, adicione um site PHP com dominio local `aurora.servbay.demo` e **raiz web `C:\ServBay\www\marcenaria-aurora\public`**. Habilite o PHP 8.3+ para o site.
5. Visite o dominio local que cadastrou. Consulte `LEIA-ME-SERVBAY.txt` para instrucoes resumidas.

**Importante:** sem acesso ao ServBay do seu computador, a senha de MySQL e o cadastro do dominio precisam ser feitos localmente. O pacote inclui um assistente que realiza todo o restante. Nao aponte o site para a pasta raiz do projeto.

## Requisitos

- PHP **8.3 ou superior**, com a extensão **PDO MySQL (`pdo_mysql`)** habilitada.
- MySQL **8.0 ou superior** (ou versão MariaDB compatível, não verificada).
- Permissão para criar um banco de dados com o usuário do MySQL, ou criar o banco antes e conceder as permissões necessárias.
- Navegador moderno. Conexão com a internet para os **CDNs opcionais** (Bootstrap 5, Chart.js, FullCalendar, Google Fonts). A interface principal tem CSS próprio e permanece utilizável sem CDN; gráficos e calendário visual exigem essas bibliotecas.
- **Opcional:** Composer para instalar o Dompdf e habilitar download de PDF diretamente pelo servidor. Sem Composer, a proposta pode ser impressa ou salva como PDF pelo navegador.

## Instalação local

1. Descompacte o ZIP e abra um terminal na pasta `marcenaria-aurora`.
2. Copie o arquivo `config.example.php` para `config.local.php`. Em Windows/PowerShell:

   ```powershell
   Copy-Item config.example.php config.local.php
   ```

   Em macOS/Linux:

   ```bash
   cp config.example.php config.local.php
   ```

3. Ajuste as credenciais do MySQL no `config.local.php` (host, porta, nome, usuário e senha).
4. Instale tabelas e dados fictícios:

   ```bash
   php tools/install.php
   ```

   Para criar **apenas tabelas**, sem dados de demonstração: `php tools/install.php --schema-only` (será necessário criar uma conta administradora por outro procedimento antes de entrar). O instalador não repete os dados fictícios se já existir algum usuário.

5. Inicie o servidor local:

   ```bash
   php -S 127.0.0.1:8000 -t public public/router.php
   ```

6. Abra **http://127.0.0.1:8000**. No Windows, após configurar `config.local.php`, também é possível executar `INICIAR_WINDOWS.bat` para instalar e iniciar o servidor.

### Acesso de demonstração

| Perfil | E-mail | Senha inicial |
|---|---|---|
| Administrador | `admin@aurora.local` | `Aurora@2026!` |
| Comercial | `comercial@aurora.local` | `Demo@2026!` |
| Produção | `producao@aurora.local` | `Demo@2026!` |
| Financeiro | `financeiro@aurora.local` | `Demo@2026!` |

**IMPORTANTE:** essas senhas estão nos arquivos de demonstração. Troque-as em **Minha conta** antes de usar qualquer dado real, ou instale sem dados fictícios e configure usuários por um processo apropriado.

### ServBay, Apache ou Nginx

Aponte a **raiz pública do site** para a pasta `marcenaria-aurora/public`, nunca para a raiz do repositório. A aplicação usa links `index.php?route=...`, portanto **não depende de regras de rewrite**. Use a versão PHP configurada com a extensão MySQL. O arquivo `config.local.php`, código PHP da aplicação e scripts de instalação permanecem fora da pasta pública.

### PDF nativo (opcional)

Instale o Composer e execute na raiz:

```bash
composer install
```

Depois clique em **Baixar PDF nativo** na proposta. Caso não tenha Composer, utilize **Imprimir / salvar PDF** (Ctrl+P → Salvar como PDF).

## Módulos implementados

| Módulo | Operações disponíveis |
|---|---|
| Autenticação | Login, logout, hash de senha, bloqueio básico por sessão, alteração de senha, papéis (admin/comercial/produção/financeiro) |
| Dashboard | Indicadores, últimas propostas, andamento da produção, gráfico financeiro dos lançamentos pagos |
| Clientes | Cadastro, edição, busca e arquivamento |
| Fornecedores | Cadastro, edição e arquivamento |
| Materiais | Catálogo, dimensões de chapas, preços de custo, estoque mínimo, associação a fornecedores |
| Estoque | Entrada/saída com transação SQL e bloqueio de linha, histórico e prevenção de saldo negativo |
| Projetos | Cliente, ambiente, especificações, medições, status, prazo |
| Orçamentos | Itens variáveis, cálculo de custos/mão de obra/acréscimo/impostos/desconto, status, proposta imprimível, PDF opcional, aprovação e geração de OP |
| Produção | Ordem, prioridade, responsável, etapas, histórico de eventos, atualização do projeto |
| Corte | Peças, dimensões, quantidade, fita de borda, encaixe heurístico por chapa e SVG do plano |
| Instalações | Agendamento, equipe, endereço, checklist textual e conclusão |
| Financeiro | Contas a pagar/receber, baixa/reabertura, vencimentos e resultados de caixa |
| Agenda | Calendário visual de entregas, OPs e instalações (FullCalendar via CDN) |
| Relatórios | Vendas aprovadas por cliente, saldo realizado por projeto, distribuição de OPs |
| Usuários | Cadastro, atualização, papéis e ativação pelo administrador |

**Fluxo recomendado:** Cliente → Projeto → Orçamento (itens) → Aprovar → Gerar OP → Atualizar produção → Agendar instalação → Concluir. As movimentações de estoque e os lançamentos financeiros são registros separados e explícitos para evitar baixas/faturas automáticas sem confirmação.

## Segurança e integridade

- Queries com parâmetros SQL via PDO; tokens CSRF em formulários; saída HTML escapada; `password_hash` / `password_verify`; cookies de sessão HttpOnly/SameSite; renovação de ID ao autenticar e alterar senha.
- Verificação de papel nas ações; checagem de conta ativa a cada requisição; edições de orçamento aprovado bloqueadas; transações para conversão de orçamento em OP e para movimentar estoque.
- Saldo de material não pode ser editado pelo cadastro: apenas por movimentação registrada.
- Sem upload de arquivos nem acesso à pasta de configuração pela web.
- Não há exclusão em cascata para clientes e históricos comerciais, evitando perda de registros por engano.

## Limitações conhecidas / próximos passos

Este projeto é uma **base operacional de demonstração**, não um ERP comercial finalizado. Ainda não implementa: edição avançada de listas de peças, importação de projetos SketchUp/Promob, geração de arquivos CNC, otimização industrial de corte, cálculo de aproveitamento por sobras reutilizáveis, baixa automática de itens por ordem de produção, reserva de estoque, compras e cotações de fornecedores, gestão tributária ou emissão de NF-e/NFS-e, integrações bancárias/Pix, multiempresa, assinatura eletrônica, upload de documentos ou contratos, auditoria genérica de todas as tabelas, recuperação de senha por e-mail, paginação completa ou testes end-to-end.

O **plano de corte** é uma heurística simples por fileiras, com folga de serra de 3,2 mm e rotação apenas para peças com veio livre. Não substitui software industrial; valide medidas, esquadrejamento, tolerâncias e regras da máquina antes de cortar qualquer chapa.

O controle de financeiro mostra **movimentações realizadas** (lançamentos baixados) e títulos pendentes. Não representa contabilidade formal ou lucro líquido completo.

**Para ambiente de produção:** configure HTTPS, backups automatizados, permissões de banco mínimas, isolamento de hospedagem, logs, hardening de servidor, rate limiting compartilhado e política de retenção/LGPD, além de revisão de segurança e testes funcionais no ambiente real.

## Testes e estrutura

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/test_calculations.php
find . -name '*.php' -exec php -l {} \;
```

```text
app/
  actions.php        ações POST, validações e permissões
  core.php           PDO, autenticação, utilitários e renderização
  cutting.php        algoritmo heurístico para plano de corte
  pages.php          consultas e páginas GET
  views/             interface PHP/HTML
config.example.php   exemplo de configuração local
config.local.php     criado pelo usuário e NÃO distribuído
composer.json        Dompdf opcional
public/
  index.php          front controller HTTP
  router.php         roteamento para php -S
  assets/            CSS e JS da interface
  database/          (na raiz do projeto, não em public)
database/schema.sql  estrutura MySQL
 tools/install.php   instala banco e dados fictícios pelo terminal
 tests/              testes de cálculos sem MySQL
```

Desenvolvido para português brasileiro. Os valores do banco de demonstração não representam preços de mercado.
