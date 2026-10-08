# GCOM

Nova aplicação on-premise, com API Symfony 7.4, PHP 8.4, Doctrine/PostgreSQL 17,
integração Oracle por PDO e frontend Next.js 16 com TypeScript.
O sistema anterior permanece em `gcom-dts-producao/` como referência.

## Organização

```text
backend/       API, entidades, repositories, serviços, listeners e migrations
frontend/      Next.js App Router, componentes e estilos
backend/docker/  PHP-FPM, Nginx, PostgreSQL e scripts de backup
frontend/docker/ Dockerfile do Next.js
compose.yml    Ambiente de execução local
docs/         Arquitetura, operação, contrato API e migração
```

## Primeira execução

Com Docker Engine, Docker Compose v2, GNU Make e OpenSSL instalados, execute
`make init`. O comando prepara o `.env`, gera APP_SECRET e senhas PostgreSQL
ausentes, compila os serviços e aplica migrations. Segredos já configurados são
preservados; o arquivo recebe permissão 600. Use `make admin` para criar o primeiro
administrador interativamente. `make env` prepara somente o ambiente.
Antes das migrations, `make init` sincroniza as contas PostgreSQL com o `.env`,
inclusive quando o volume já existe, preservando os dados. `make database-sync`
executa essa etapa separadamente usando o ambiente do container PostgreSQL.
Para outro arquivo de ambiente, use `make init ENV_FILE=/caminho/ambiente.env`.

1. Execute `make env` para gerar os segredos locais ou deixe `make init` fazer isso.
   O Doctrine recebe host, porta, nome, usuário e senha separadamente.
2. Antes de consultar o Winthor, configure `ORACLE_DSN`, `ORACLE_USER` e `ORACLE_PASSWORD`
   fornecidos pelo DBA com uma conta de leitura
   para `PCPEDC`, `PCPEDI`, `PCPRODUT`, `PCCLIENT`, `PCPLPAG`, `PCTABPR` e `PCLANC`. Se a conta não for
   proprietária do schema Winthor, o DBA deve fornecer sinônimos para esses nomes.
3. Execute:

```sh
docker compose up -d --build postgres backend
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console app:user:create-admin
docker compose up -d --build frontend nginx backup
```

Acesse `http://localhost:8080`. Crie os demais usuários na tela **Usuários**.
Não há usuários ou senhas de demonstração no ambiente de produção.

`BIND_IP` começa em `127.0.0.1`. Para acesso pela LAN, configure o IP privado da máquina
(ex.: `192.168.1.20`), ajuste `DEFAULT_URI` e permita a porta somente para a sub-rede
interna no firewall. PostgreSQL, PHP-FPM e Next.js não publicam portas no host.
Imagens precisam de internet durante o build; a aplicação não usa CDN, fontes remotas
ou serviços externos em runtime. Prepare/exporte as imagens antes de instalar numa rede isolada.
A imagem Oracle está preparada para Linux x86-64.

## Lançamento de comissão

Em **Comissões → Nova comissão**, informe o código do **cliente principal**, a
**modalidade**, a **praça dos pedidos** e as **datas inicial/final**. Comissão normal
aceita praças PSCF e exige vínculo pelo CODREVENDA; praças PSD exigem ATG. O filtro
usa o CODPRACA do pedido, permitindo escolher pedidos de outra praça sem depender
da praça atual do cliente. O cálculo usa o par PSD/PSCF da praça escolhida, mantendo
a tabela original no snapshot. Praça e vínculo são conferidos novamente no lançamento. Clique em **Buscar pedidos no Winthor**; a busca usa a data
do pedido e inclui clientes vinculados ao principal por CODREVENDA. Em ATG, inclui
também o próprio cliente sem revenda. Nenhuma consulta de pedidos ocorre antes de preencher esses filtros.
Pedidos já vinculados a comissões ficam fora da seleção. A lista mostra, por pedido,
filial, tabela/região, pareamento PSD/PSCF, plano/coluna de preço e referência da modalidade.
Pedidos sem pareamento ou plano válido ficam bloqueados para seleção.

Selecione os pedidos e a modalidade normal/ATG, depois clique em **Simular comissão**.
A simulação importa os pedidos selecionados e todos os itens para snapshots no GCOM,
aplica a regra do administrador e mostra os títulos vencidos do cliente e dos vinculados,
sem descontá-los diretamente. No quadro **Conferência no Winthor**, selecione as devoluções
que deseja aplicar e simule novamente. Somente as selecionadas são importadas; as demais
continuam disponíveis. Débitos já registrados e cancelamentos permanecem automáticos.
Confira o resultado e registre a comissão; a justificativa do lançamento é opcional.
Um usuário Financeiro aprova antes do pagamento, inclusive quando criou a comissão.
Ele também pode **Reprovar comissão**, com justificativa obrigatória, enquanto ela estiver
pendente ou aprovada sem pagamento. A reprovação preserva o histórico e libera pedidos e
deduções para um novo lançamento. Consultas e importações não alteram o Winthor.

## Funcionalidades disponíveis

- Usuários próprios, senhas com hash, perfis, desativação, sessões e proteção CSRF.
- Importação de pedidos faturados e todos os itens, mantendo dados originais no PostgreSQL.
- Comissões com múltiplos pedidos do mesmo cliente principal, comparação por item e deduções pendentes automáticas.
- Aprovação por perfil Financeiro, confirmação do pagamento e vínculo opcional com a rotina 749.
- `RECNUM` identifica diretamente a linha de `PCLANC` associada à rotina 749. É possível
  confirmar sem `RECNUM` e vinculá-lo depois. Os detalhes da linha também mostram o `NUMTRANS`
  e são preservados como snapshot. A localização do lançamento não comprova, por si só,
  a conciliação do valor, beneficiário ou baixa financeira.
- Dashboard, evolução mensal, ranking de clientes, relatórios PDF/XLSX/CSV e comprovante imprimível.
- Auditoria de alterações e logs Monolog capturados por listeners em `EventListener/Logs`, com identificador de requisição.
- Camada `Database` para Oracle: conexão, statement, resultado e transação; consultas despacham eventos também no Doctrine/PostgreSQL.
- Backup diário, retenção configurável e restauração em uma base nova.

## Desenvolvimento e qualidade

O Makefile executa ferramentas em containers PHP 8.4 e Node 24, sem exigir PHP,
Composer ou Node instalados no host. Veja `make help`. Comandos principais:

```sh
make deps             # Dependências de desenvolvimento PHP e Node
make phpcs            # Verificação com PHP-CS-Fixer (não PHP_CodeSniffer)
make phpcs-fix        # Correção de estilo PHP
make test             # Migrations, PHPUnit e schema em banco descartável
make backend-check    # YAML e container Symfony
make frontend-check   # Prettier, TypeScript, ESLint e build
make check            # Todas as verificações
make format           # PHP-CS-Fixer e Prettier, alterando os arquivos locais
make up               # Iniciar serviços
make status           # Consultar estado
make logs             # Acompanhar logs
make backup           # Backup imediato
make down             # Parar sem apagar volumes
make tools-down       # Limpar containers de ferramentas/testes
```

`compose.yml` reúne a aplicação, as ferramentas e o PostgreSQL `gcom_test`.
As ferramentas ficam no profile `tools` e só iniciam quando solicitadas pelo Makefile. Os testes usam credenciais fictícias fixas e integração Oracle
simulada; o banco de testes não publica portas e seus dados são descartados ao
remover o container e seu volume anônimo. As ferramentas montam o código local e usam o UID/GID do usuário do host.
`make test` remove o banco de testes e seu volume ao finalizar, inclusive em caso de falha.
`make tools-down` remove somente os containers de ferramentas/testes.
Instalações, formatação e build podem alterar dependências/artefatos locais.

Para executar diretamente com ferramentas instaladas no host:

```sh
cd backend
composer install
composer style:fix
composer style:check
composer test
php bin/console lint:container

cd ../frontend
npm ci
npm run dev
npm run typecheck
npm run lint
npm run format:check
npm run build
```

Para desenvolvimento fora do Docker, use PHP 8.4+ com `pdo_pgsql`, `intl`, `mbstring`
e `pdo_oci` + Instant Client para integração real. Defina `backend/.env.local`
com o PostgreSQL local e execute `php -S 127.0.0.1:8000 -t public public/index.php`
dentro de `backend/`. O Next.js encaminha `/api` para essa porta; `BACKEND_URL`
pode sobrescrever o destino. Não use o servidor PHP de desenvolvimento na implantação.

Os testes funcionais exigem PostgreSQL com uma base dedicada terminada em `_test`.
Aplique as migrations nessa base com `APP_ENV=test`, depois execute PHPUnit com
`DATABASE_NAME` apontando para ela e host/porta/usuário/senha separados. Os testes reiniciam somente essa base e substituem
as consultas Oracle por um test double. Consulte [testes e operação](docs/operations.md).

## Identidade visual

A interface identifica o produto como **GCOM**. O nome da empresa aparece no workspace
conforme o cadastro em Configurações → Empresa. O prefixo cadastrado vale somente para
novas comissões; códigos e snapshots anteriores são preservados.

## Regras ainda sujeitas a homologação

O administrador configura **percentuais normal/ATG/devoluções, base, pareamentos por filial/tabela, frete e desconto na referência**
em **Cálculo da comissão**. A regra inicial calcula `(venda dos itens − referência PSD − frete) × 80%`;
débitos/devoluções são descontados depois. Pares PSD/PSCF: 1/2, 5/6, 7/8, 30/32 e 31/33; valide exceções por filial antes de operar.
Também há bases por PTABELA do item e venda. Simulação e gravação usam o mesmo calculador;
o operador não informa nem sobrescreve o valor. Regras e memória por item ficam versionadas
e imutáveis. Alterações valem apenas para novas comissões.

As novas importações preservam PCTABPR por região/plano e PCPLPAG.NUMPR. Pedidos antigos
sem essas informações não podem usar PSD; não há fallback para outra tabela. Combos PSD
usam composição por filial/tabela e QTMP por unidade, conforme confirmação do usuário. Desconto na referência exige
PERCENTUALDESC preservado. Relatórios exportam PDF, XLSX e CSV com o período selecionado;
PDF aceita até 1.000 comissões por arquivo para limitar uso de memória.

Não há importação automática do MySQL antigo, recuperação de senha
por email ou pagamentos parciais nesta versão. Cancelamentos integrais de pedidos já
comissionados geram dedução na próxima comissão; cancelamentos parciais exigem conferência. Cada pagamento
sugere o valor líquido calculado; alterações exigem opção manual e justificativa, preservando ambos os valores e a auditoria. A diferença não cria saldo ou parcelamento automático; um `RECNUM` só pode ser vinculado a uma comissão.

Antes da substituição operacional, valide pedidos e `PCLANC` contra o Winthor real,
compare cálculos históricos e execute o plano de migração. Não foram reutilizadas
credenciais do legado. Veja [arquitetura](docs/architecture.md), [operação e backups](docs/operations.md)
e [plano de migração](docs/migration.md).

A revisão das consultas e regras está em [fluxo do legado](docs/legacy-flow.md).
A tela **Consultas Winthor** reúne pedidos elegíveis, devoluções, cancelamentos e títulos em atraso.

Relatórios permitem buscar comissões pagas por cliente principal, pedido e modalidade ATG,
e débitos/devoluções/cancelamentos pendentes ou deduzidos, indicando a comissão do abatimento.
As exportações PDF/XLSX/CSV mantêm os filtros da busca. A integração Winthor é somente leitura;
lançamentos e baixas do GCOM são persistidos no PostgreSQL.

Logs estruturados usam os canais Monolog em stdout/stderr; a configuração de rotação depende do daemon Docker.
Consulte [operação e coleta de logs](docs/operations.md#logs-e-auditoria).

## Imagens de distribuição e instalação

O workflow `.github/workflows/publish.yml` valida o projeto e publica duas imagens
privadas no GHCR, vinculadas a este repositório: `ghcr.io/titodelerinofilho/gcom-v2/backend`
e `ghcr.io/titodelerinofilho/gcom-v2/frontend`. Tags `v*` publicam a versão e o commit;
a execução manual publica a tag `sha-<commit completo>`. Publicação usa exclusivamente
`GITHUB_TOKEN` automático com `packages: write`. As imagens suportam Linux amd64.

O repositório independente `gcom-installer` contém o Compose de distribuição com
imagens prontas e comandos `make install`, `make pull VERSION=...` e `make update VERSION=...`.
Download de pacotes privados exige PAT GitHub classic com `read:packages` e acesso aos
pacotes. PostgreSQL e backups usam volumes persistentes; atualização faz backup antes
das migrations e só registra a nova versão após os healthchecks.

A migration de empresa não altera comissões anteriores. Em instalações existentes,
cadastre a empresa com `php bin/console app:enterprise:configure` ou na tela Empresa.
Até o cadastro, a identificação e o prefixo de novas comissões usam `GCOM`.
