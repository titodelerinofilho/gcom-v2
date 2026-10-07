# Operação on-premise

## Configuração

Execute `make env` ou `make init` para preparar o `.env` a partir de `.env.example`.
APP_SECRET e senhas locais ausentes são gerados com OpenSSL; valores existentes são
preservados. O arquivo recebe permissão 600 e permanece fora do controle de versão.
Credenciais Oracle devem ser fornecidas pelo DBA, antes de consultar o Winthor.
O Compose injeta as credenciais nos containers, sem embuti-las nas imagens.
`backend/.env` contém somente valores locais fictícios. Para executar sem Docker,
sobrescreva-os em `backend/.env.local`.

PostgreSQL possui uma conta proprietária usada para bootstrap/backups e uma conta
`gcom_app` sem privilégios de superusuário para a aplicação e migrations.
`backend/docker/postgres/init-app.sh` cria a conta no primeiro startup do PostgreSQL.
`make init` também executa esse script por `make database-sync` antes das migrations,
sincronizando as senhas da aplicação e do proprietário com o `.env` em volumes existentes.
O script é transacional e não apaga bancos, tabelas ou dados. Após alterar credenciais,
use `make init` para recriar os containers com o novo ambiente e sincronizar as contas.

Apenas Nginx publica uma porta no IP configurado em `BIND_IP`. Os serviços da aplicação compartilham a rede padrão do Compose, sem portas públicas
para PostgreSQL, PHP-FPM ou Next.js. O backend alcança o Winthor na LAN.
O Compose mantém restart `unless-stopped` e healthchecks, sem limites de recursos
ou restrições extras. O profile `tools` reúne as ferramentas no mesmo arquivo;
o banco de testes fica em uma rede interna própria. Use HTTPS com certificado interno no proxy para o uso diário;
`cookie_secure=auto` acompanha o protocolo. Não publique esse ambiente na internet.

O backend recompila o cache Symfony na inicialização de cada container. Sessões e logs
permanecem no volume; cache de uma versão anterior não é reutilizado.

## Atualizações

```sh
docker compose build
docker compose exec backup /usr/local/bin/backup.sh
docker compose up -d postgres backend
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose up -d frontend nginx backup
docker compose ps
```

Para uma migration que altere contratos de forma incompatível, faça a manutenção com
Nginx interrompido. Migrations financeiras são irreversíveis; preserve backup e versões
das imagens para rollback planejado. Não use `docker compose down -v` em produção.

## Backup e recuperação

O serviço `backup` executa imediatamente e depois a cada `BACKUP_INTERVAL_SECONDS`
(86400 por padrão). Usa `pg_dump -Fc`, arquivo temporário, validação do índice do archive,
publicação e SHA-256. Retém arquivos por `BACKUP_RETENTION_DAYS` (30). A limpeza só ocorre
após um novo backup bem-sucedido. Falhas são logadas e tentadas novamente após 60 segundos.
Um healthcheck verifica a idade do último sucesso. O RPO nominal é de 24 horas;
reduza o intervalo ou adote WAL/PITR se precisar recuperar mudanças mais recentes.

```sh
# Executar manualmente e listar arquivos
docker compose exec backup /usr/local/bin/backup.sh
docker compose exec backup ls -lh /backups

# Copiar arquivos para outro disco/servidor autorizado
docker compose cp backup:/backups ./backups-export

# Validar recuperação em uma NOVA base; substitua o nome do arquivo
docker compose exec backup /usr/local/bin/restore.sh \
  /backups/gcom-20261007T120000Z.dump gcom_restore_validation
```

O restore confere SHA-256, cria uma base nova e executa `pg_restore` em transação única.
Recusa o nome da base principal e bases já existentes. Não apaga dados existentes.
Compare contagens, comissões e snapshots na base restaurada antes de planejar um corte.
O volume `backup_data` não substitui uma cópia em outro dispositivo. Defina responsável
pelo monitoramento do healthcheck, cópia externa e teste periódico de restauração.
Os dumps contêm dados comerciais; restrinja acesso ao Docker e ao destino externo.

## Logs e auditoria

Os listeners em `src/EventListener/Logs` capturam eventos HTTP, banco e auditoria.
`LOG_REQUESTS`, `LOG_RESPONSES`, `LOG_EXCEPTIONS` e `LOG_DATABASE` aceitam `1`/`0`;
a auditoria financeira permanece ativa. Monolog escreve um evento JSON por linha em streams:

| Canal | Stream / Nível mínimo |
| --- | --- |
| `database_queries` | stdout / info |
| `requests`, `responses` | stdout / info |
| `exceptions` | stderr / critical |
| `audit` | stdout / info |
| `message_transporter_consume`, `message_transporter_emit` | stdout / info (reservados para mensageria futura) |
| `deprecation`, `application` | stderr / notice e warning, respectivamente |

Todos os registros carregam `request_id`, `service=gcom-backend` e `environment`. Queries bem-sucedidas são INFO; falhas são ERROR. Exceptions são CRITICAL para passar pelo limiar solicitado, incluindo status HTTP para distinguir erros de negócio. PHP-FPM captura streams sem decorar linhas, preservando JSON para coletores.

Os logs dos containers usam a configuração do daemon Docker; o Compose não define políticas de rotação. Monolog não faz rotação nem cria arquivos em var/log. Loki ou OpenTelemetry Collector podem coletar os logs dos containers e extrair canal, nível e correlação. Não há collector, envio OTLP, tracing nem métricas Prometheus instalados nesta entrega; o formato prepara a coleta futura. Evite usar request_id como label de Loki: prefira service, environment e channel.

Senhas, tokens, cookies, DSNs e parâmetros SQL são removidos pelo processor. Corpos HTTP não são gravados integralmente. A auditoria financeira também fica no PostgreSQL, na mesma transação da operação; logs não comprovam commit.

```sh
docker compose logs --tail=100 backend nginx backup
```

## Testes

```sh
# Banco dedicado; nunca a base de produção
docker compose exec postgres createdb -U gcom_owner gcom_test
# Dê à conta gcom_app permissão de criação no schema da base de teste.
# Configure host/porta/usuário/senha separadamente; DATABASE_NAME deve terminar em _test.
APP_ENV=test DATABASE_HOST=HOST DATABASE_PORT=5432 DATABASE_NAME=gcom_test \
  DATABASE_USER=USER DATABASE_PASSWORD=PASS DATABASE_SERVER_VERSION=17 \
  php backend/bin/console doctrine:migrations:migrate --no-interaction
DATABASE_HOST=HOST DATABASE_PORT=5432 DATABASE_NAME=gcom_test \
  DATABASE_USER=USER DATABASE_PASSWORD=PASS DATABASE_SERVER_VERSION=17 \
  php backend/vendor/bin/phpunit -c backend/phpunit.dist.xml
```

PHPUnit recusa reiniciar bases sem sufixo `_test`. Testes funcionais usam PostgreSQL
real e um adaptador Oracle falso. Cobrem CSRF/permissões, aprovação por perfil Financeiro,
pagamento sem vínculo, vínculo posterior, unicidade, deduções e histórico imutável.
A integração real Oracle precisa de homologação separada.

Também é possível executar a suíte dentro da imagem de desenvolvimento:

```sh
docker build --target test -f backend/docker/php/Dockerfile -t gcom-tests backend
docker run --rm --network SUA_REDE_DO_BANCO \
  -e DATABASE_HOST=HOST -e DATABASE_PORT=5432 -e DATABASE_NAME=gcom_test \
  -e DATABASE_USER=USER -e DATABASE_PASSWORD=PASS -e DATABASE_SERVER_VERSION=17 gcom-tests
```

Execute as migrations na base de teste antes. A imagem `test` inclui PHPUnit e
PHP-CS-Fixer; a imagem `runtime` usada pelo Compose instala somente dependências de produção.

## Conexão Doctrine PostgreSQL

`config/packages/doctrine.yaml` configura `pdo_pgsql` com DATABASE_HOST, DATABASE_PORT, DATABASE_NAME, DATABASE_USER e DATABASE_PASSWORD, além da versão do PostgreSQL. Nenhuma URL de conexão é montada. O `.env` raiz e o backend usam os mesmos nomes DATABASE_*; o Compose apenas os repassa ao Doctrine. DATABASE_OWNER_USER/PASSWORD identificam a conta separada de inicialização e backups e são mapeados para as variáveis POSTGRES_* exigidas pela imagem oficial. Nome, host e porta do banco também são compartilhados com o backup. Senhas são passadas como valores, sem URL encoding.

## Conexão Oracle por SID

O legado usa SID WINT. A conexão local deve usar o descritor completo mostrado em .env.example, preservando host/porta e credenciais fornecidas pelo DBA. Easy Connect `//host:porta/nome` interpreta nome como SERVICE_NAME; um SID não é automaticamente um serviço registrado. Após alterar ORACLE_DSN, execute `docker compose up -d --no-deps --wait backend` e `docker compose restart nginx`. ORA-12514 indica serviço desconhecido pelo listener; o healthcheck /api/health valida a API, sem testar o Oracle.

## Formato numérico Oracle

A sessão do Winthor pode retornar NUMBER com vírgula decimal (inclusive frações como ,5). Result identifica as colunas NUMBER pelos metadados PDO e as normaliza para strings decimais com ponto, usando precisão arbitrária, antes de criar snapshots ou calcular. Texto, NULL e outros drivers são preservados. Não há ALTER SESSION nem escrita Oracle; Money mantém a validação estrita dos valores de entrada.
