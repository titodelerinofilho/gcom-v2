# Repository Guidelines

## Estrutura e organização

O GCOM usa Symfony/Doctrine em `backend/` e Next.js/TypeScript em `frontend/`. `gcom-dts-producao/` é o legado de referência; preserve-o. Cada projeto mantém seus Dockerfiles em `docker/`; `compose.yml` fica na raiz.

Organize todas as camadas por assunto, com namespaces correspondentes: `Controller/Audit/`, `Entity/Audit/`, `Service/Audit/`, `Exception/Audit/`, `Repository/Audit/` e `Dto/Audit/Input/` ou `Output/`. Aplique também a Commands, Events, listeners e testes. Infraestrutura fica agrupada pelo assunto técnico. Listeners de logs permanecem em `EventListener/Logs/`.

Cada controller representa uma operação, em uma classe com `__invoke()`: por exemplo, `Controller/Audit/ListAudit.php`. Use `CreateAudit`, `UpdateAudit` ou `DeleteAudit` somente quando necessários. Não crie CRUD automaticamente nem reúna operações em controllers genéricos.

## Code style e legibilidade

PHP usa quatro espaços, `declare(strict_types=1)`, tipos explícitos, classes PascalCase e métodos/variáveis camelCase. Siga `backend/.php-cs-fixer.dist.php`, incluindo Symfony e regras risky. Frontend segue Prettier, ESLint e TypeScript.

Separe blocos lógicos com uma linha em branco: preparação, condições, execução e retorno. Condições consecutivas e declarações sem relação devem ficar separadas. Agrupe apenas variáveis relacionadas; declare-as perto do uso. Use nomes descritivos, chaves nos blocos e evite condições comprimidas numa linha.

Condições booleanas devem ser explícitas: `false === $enabled` ou `true === $enabled`, nunca `!$enabled` ou `if ($enabled)`. Use `null === $value` para nulidade e comparações estritas adequadas ao tipo; não dependa de truthiness. No TypeScript, aplique `false === enabled`. Operadores relacionais e lógicos continuam permitidos para compor condições explícitas.

## DTOs e design patterns

Use DTOs tipados de Input e Output por operação; valide Input com Symfony Validator. Prefira DTOs imutáveis (`readonly`). Não exponha entidades Doctrine nem arrays genéricos como contratos da API.

Controllers recebem Input, delegam ao serviço e apresentam Output. Serviços concentram regras de negócio; repositories concentram consultas. Prefira composição, injeção por construtor e responsabilidade única. Use Strategy para algoritmos intercambiáveis e eventos/listeners para efeitos desacoplados quando necessários; evite abstrações sem uso concreto.

Oracle passa pela camada Database/Statement, somente leitura. Declare SQL em variável antes de `statement->query()`. Logs são capturados por eventos e Monolog; nunca registre credenciais.

## Validação e contribuições

No backend, execute `composer style:check`, `composer test` (PHPUnit, banco exclusivo terminado em `_test`) e `php bin/console lint:container`. No frontend, execute `npm run format:check`, `npm run typecheck`, `npm run lint` e `npm run build`.

Na raiz, `make help` lista a automação Docker. Use `make phpcs`, `make test`, `make backend-check` e `make frontend-check`; `make check` reúne essas verificações. `make init` inicia a aplicação e aplica migrations; `make admin` cria o administrador interativamente.

Ao reorganizar classes, atualize namespaces, imports, serviços, mappings Doctrine e testes. Commits devem ter assunto curto e descritivo. PRs explicam comportamento, validação e migrations; inclua screenshots para mudanças visuais. Preserve snapshots financeiros, auditoria e segredos fora do Git.
