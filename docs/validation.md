# Validação da entrega

Executada em 7 de outubro de 2026, com ambientes Docker temporários e dados fictícios.

- PHPUnit: **43 testes, 271 assertions**, aprovados em PHP 8.4.26 dentro do Docker com PostgreSQL 17; também aprovados no PHP local.
- Casos cobertos: CSRF/perfis, snapshots dos itens, deduções, aprovação por perfil Financeiro, pagamento sem RECNUM, vínculo posterior, duplicidades, histórico imutável, relatórios, múltiplos RECNUM do mesmo NUMTRANS, fórmula administrativa versionada, frete antes da margem, simulação/gravação idênticas, exportação PDF/XLSX/CSV, proteção de fórmulas em planilhas, eventos de banco/HTTP, conexão indisponível e rollback.
- PHP-CS-Fixer: nenhuma alteração pendente, incluindo migrations com as mesmas regras.
- Symfony: container/configuração YAML válidos; schema Doctrine sincronizado com as migrations.
- Frontend: build de produção, ESLint, TypeScript, Prettier e auditoria de dependências aprovados.
- Docker: imagens compiladas, PDO Oracle/PostgreSQL disponíveis e cinco serviços saudáveis. O backend reconstrói o cache ao iniciar para evitar incompatibilidades entre imagens e cache persistente.
- Navegador: login/logout, dashboard, pedidos, comissões, deduções, relatórios, auditoria, usuários, configuração de cálculo, detalhes e navegação mobile sem erros JavaScript ou overflow horizontal. Aprovação e confirmação sem NUMTRANS também exercitadas na interface.
- Backup: dump, SHA-256 e restore em base nova concluídos. A base restaurada preservou as duas comissões fictícias e o total líquido de R$ 320,00.
- Revisão do legado: pareamentos por filial/tabela (incluindo PE 31/33), clientes finais agrupados no principal, planos diferentes, combos com componentes de mesmo preço, QTMP por unidade, devoluções normal/ATG, estorno integral, prevenção de dedução duplicada e mudança de deduções após simulação cobertos por testes.
- Migração nova também aplicada desde uma base PostgreSQL vazia: 10 contextos iniciais e Pernambuco 31/33 confirmados; schema sincronizado.
- Navegador nesta revisão: configuração com 10 contextos e percentual ATG, consulta Winthor com gateway fictício, simulação/gravação e tabela de comparação por item, inclusive mobile sem overflow.
- Legado e AGENTS.md permaneceram sem alterações.

A camada PDO emitiu evento de conexão Oracle indisponível (ambiente fictício, ORA-12541);
a resposta 503 e os logs de banco/exception/response preservaram o mesmo request ID.
Todos os pontos de escrita de logs próprios ficam em `src/EventListener/Logs`.

As [prévias desktop](screenshots/dashboard.png), [mobile](screenshots/mobile.png),
[login](screenshots/login.png) e [pagamento](screenshots/payment.png) contêm dados fictícios.

Não houve acesso ao Winthor real nem importação de dados históricos. Esses pontos,
a conciliação financeira dos campos de PCLANC, o enquadramento PSD/ATG e os preços
de composição dos combos precisam ser
homologados com a empresa antes da substituição operacional do legado.

A identidade combina a logo DTS com GCOM, separadas por uma linha. Veja também
[configuração de cálculo](screenshots/calculation-settings.png),
[simulação](screenshots/calculation-preview.png) e [relatórios](screenshots/reports.png).
Exemplos exportados, todos com dados fictícios: [PDF](examples/gcom-comissoes.pdf),
[XLSX](examples/gcom-comissoes.xlsx) e [CSV](examples/gcom-comissoes.csv).

Veja a [revisão do fluxo e matriz de consultas](legacy-flow.md), a [comparação por item](screenshots/commission-price-comparison.png),
a [consulta Winthor](screenshots/winthor.png) e os [detalhes mobile](screenshots/commission-mobile.png).
As regras financeiras foram verificadas contra o legado; a homologação completa com pedidos reais ainda é necessária. Cancelamentos parciais bloqueiam nova comissão até conferência.

## Relatórios do menu legado e somente leitura

Testes cobrem débito pendente consumido na próxima comissão ATG, diferença contra PTABELA, comissão paga por cliente/pedido/modalidade e data de pagamento, devoluções/cancelamentos deduzidos por principal e seis exportações filtradas (PDF/XLSX/CSV para comissões e ajustes). Os filtros e os vínculos usam o PostgreSQL; SELECTs Oracle e controles de transação foram revisados, sem SQL de alteração ou execução da rotina 749.

## Logging em streams

Monolog validado com handlers JSON por canal em stdout/stderr; queries de sucesso em INFO e exceptions em CRITICAL. Testes confirmam correlação HTTP/PostgreSQL e ausência de senhas/valores sensíveis, sem depender de arquivos rotativos. YAML, container Symfony e configuração PHP-FPM válidos. O FPM usa decorate_workers_output=no e limite de linha 16384. Nenhum collector Loki/OTel ou exporter Prometheus foi instalado.

No navegador, cadastro de débito, geração ATG, pagamento, buscas por cliente/status/data, vínculos de abatimento e seis downloads filtrados foram exercitados, inclusive mobile sem overflow. [Relatório de débitos](screenshots/debt-reports.png) e [relatórios mobile](screenshots/reports-mobile.png) usam dados fictícios.

## Credenciais PostgreSQL separadas

Doctrine e Compose usam DATABASE_HOST/PORT/NAME/USER/PASSWORD, sem DATABASE_URL. Validada conexão real PostgreSQL 17 com senha fictícia contendo caracteres especiais, inicialização da conta da aplicação e SELECT com essa conta. Compose, YAML, container Symfony, migrations e schema passaram; PHPUnit: 43 testes, 271 assertions. DATABASE_OWNER_USER/PASSWORD ficam separados para inicialização e backups.

## Acesso inicial na porta 8080

Incluída rota /dashboard compartilhando a página inicial e a proteção de sessão. No Compose em execução, / e /dashboard retornam HTTP 200 e, sem sessão, chegam a /login no navegador. Renderização da visão geral e destaque do menu validados com API simulada; typecheck, lint, formatação dos arquivos alterados e build Docker passaram. O redirecionamento automático de / para /dashboard relatado não foi reproduzido em navegador com contexto limpo.

## Automação Makefile

Validado `make init` em projeto Compose separado, porta 18080, credenciais fictícias e volumes novos: migrations aplicadas, cinco serviços saudáveis, health API e /dashboard HTTP 200. `make backup` produziu backup e `make down` parou sem apagar volumes. Dados temporários da validação foram removidos depois. `make test backend-check`: 43 testes/271 assertions, schema, YAML e container Symfony válidos; PostgreSQL exclusivo gcom_test usa volume anônimo removido ao finalizar. `make frontend-check` passou em formatação, tipos, lint e build. `make phpcs` funciona e identifica três arquivos existentes com diferenças de estilo (UserManager, CreateAdminCommand e PaymentSnapshotFactory); a correção é explícita via make phpcs-fix.

## Compose único e geração de segredos

A configuração atual usa somente compose.yml; compose.tools.yml foi removido. Serviços da aplicação usam restart unless-stopped e healthchecks, sem limites de recursos ou restrições extras. Ferramentas ficam no profile tools. Validado make init em instalação isolada na porta 18081, com segredos locais gerados automaticamente, migrations e cinco serviços saudáveis. Repetir a preparação preserva os segredos; nomes antigos de variáveis migram mantendo os valores. Arquivo de ambiente com permissão 600. Oracle continua exigindo credenciais do DBA. PHPUnit: 43 testes/271 assertions; schema, YAML, container Symfony e frontend-check passaram. make tools-down preservou os serviços da aplicação em execução.

## Sincronização de credenciais e volume existente

Corrigida a ordem de make init: PostgreSQL saudável, database-sync, backend e migrations. O script PostgreSQL agora cria a conta somente se ausente e sincroniza as duas senhas por uma transação, sem apagar dados e sem senhas nos argumentos de psql. Em volume isolado já inicializado, validada alteração das senhas da aplicação/proprietário, autenticação TCP, rejeição da senha anterior e preservação de registro de controle após repetição. No ambiente local, o volume PostgreSQL foi recriado por solicitação explícita do usuário; volumes de backups/backend preservados, migrations aplicadas e os cinco serviços saudáveis.

## Oracle SID WINT

Resolvido ORA-12514 substituindo o serviço WINTHOR do Easy Connect pelo descritor SID WINT usado no legado, sem alterar usuário/senha ou host/porta. Backend recriado e Nginx reiniciado. Conexão real Oracle 19, SELECT 1 FROM DUAL e query de busca do OrderRepository validados via camada Database/Statement em transação somente leitura, usando cliente 0 para não exibir dados comerciais. /api/health continua verificando a API, sem garantir conectividade Oracle. Nenhuma escrita foi executada no Winthor.

## Nova comissão com busca Winthor

A tela agora exige cliente principal e período antes de buscar, sem carregar pedidos/deduções de todos os clientes ao abrir. API nova com controller invocável, DTOs tipados e Validator; exclui pedidos já vinculados a comissões. PHPUnit: 44 testes/290 assertions, incluindo autorização, filtros obrigatórios, datas inválidas/invertidas, período máximo, manutenção de importados disponíveis e exclusão após comissionar. Schema, YAML e container válidos. Browser com API simulada confirmou ausência de consulta inicial, filtros enviados, seleção de cliente final vinculado ao principal, importação na simulação, abatimento e registro, invalidação após alterar filtros e viewport mobile. Serviço novo validado contra Oracle com identificador de diagnóstico, somente leitura, sem exibir dados comerciais.

## Decimais Oracle e transparência das tabelas

Confirmado NLS_NUMERIC_CHARACTERS=,. no Oracle real. Corrigida a leitura de NUMBER por metadados PDO: vírgulas, frações sem zero e notação científica viram strings decimais com ponto, sem float e sem alterar sessão/dados Oracle. Campo textual 1,5 permanece intacto; NULL não vira zero; outros drivers não sofrem conversão. Simulação em memória dos três pedidos apresentados pelo usuário e nove itens passou, sem gravar comissão ou snapshots.

A lista informa filial/região, PSD/PSCF, plano/coluna e base/versão da regra antes de simular. ATG identifica PTABELA; combos mantêm PVENDA1 da composição. Pedidos sem pareamento válido são bloqueados na seleção. Browser com dados fictícios confirmou valores sem NaN e referências normal/ATG visíveis. [Nova comissão com tabelas](screenshots/new-commission-pricing.png). PHPUnit: 48 testes, 310 assertions; estilo dos arquivos alterados, schema, YAML e container válidos.

## Pagamento com valor manual

Validação em Docker: **49 testes, 341 assertions**, schema Doctrine sincronizado, YAML/container válidos e verificações de frontend aprovadas. Os arquivos PHP desta alteração passaram no PHP-CS-Fixer.

O teste funcional verifica permissão do financeiro, rejeição de divergência sem opção manual, tipo booleano da flag, justificativa obrigatória, cálculo preservado, dois valores na auditoria, valor pago no dashboard/relatórios e bloqueio de alterações posteriores no banco. O vínculo posterior RECNUM permanece disponível.

O navegador com API simulada confirmou valor padrão protegido, restauração ao desmarcar a opção, envio da flag/justificativa e exibição dos dois valores, sem overflow mobile. [Pagamento manual](screenshots/payment-manual.png) contém dados fictícios.
