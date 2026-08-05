# Registro de Decisões Técnicas

Formato: Contexto → Decisão → Trade-off.

---

## ADR-001 — Dois repositórios: API REST + SPA

Exigência do desafio. `autoconf-vehicles-api` e `autoconf-vehicles-web`, sem monorepo.
**Trade-off:** mudanças de contrato exigem commits coordenados e não há garantia em tempo
de compilação de que front e back concordam. Mitigado pelo documento OpenAPI como fonte
única de verdade.

## ADR-002 — Autenticação via Sanctum SPA (cookies de sessão)

O Sanctum oferece sessão stateful para SPAs de primeira parte ou Personal Access Tokens.
**Decisão:** sessão em cookie `HttpOnly`; CSRF via cookie `XSRF-TOKEN` e header
`X-XSRF-TOKEN`. Todas as rotas protegidas usam o guard `auth:sanctum`.

**Trade-off:** o token é inacessível ao JavaScript, então uma falha de XSS não consegue
roubar a sessão — esse é o motivo principal da escolha. O custo é ergonomia de revisão: o
botão *Authorize* da documentação não consegue injetar cookies (navegadores proíbem
scripts de definir o header `Cookie`), então é preciso chamar o login antes, e os exemplos
em cURL precisam de cookie jar.

**Compatibilidade futura:** PAT pode ser adicionado depois como segundo tipo de credencial
no mesmo guard (M8). Para manter isso barato: sempre `auth:sanctum` (nunca `auth:web`),
usuário lido via `$request->user()`, e `AuthController@login` enxuto.

## ADR-003 — PostgreSQL 17

A regra de integridade mais difícil do desafio é "exatamente uma imagem de capa por
veículo". Índice único parcial resolve isso no banco; PostgreSQL e SQLite suportam,
MySQL não.
**Decisão:** PostgreSQL 17, fixado por tag no compose, com volume nomeado.
**Trade-off:** menos comum que MySQL em lojas Laravel — neutralizado pelo ADR-004. Em
troca, a restrição de capa é expressa honestamente, sem a gambiarra de `NULL` no lugar de
`false`.

## ADR-004 — Docker nos dois repositórios

Cada repositório tem seu próprio `docker-compose.yml`. O stack da API roda a aplicação PHP
mais o PostgreSQL; o stack web roda a aplicação React. Não é necessária rede Docker
compartilhada, porque o único cliente da API é o navegador — não o container do front.

**Consequência crítica — quem é o cliente:**
- O container da API alcança o banco pelo **nome do serviço**: `DB_HOST=db`.
- O navegador alcança a API pela **porta publicada no host**:
  `VITE_API_BASE_URL=http://localhost:8000/api`. Nunca o nome do serviço Docker.
- Ambas as origens ficam em `localhost` para o cookie de sessão ser same-site
  (cookies ignoram porta). `SANCTUM_STATEFUL_DOMAINS` **compara a porta**, então precisa
  listar `localhost:5173` e `localhost:8000` explicitamente.
- O Vite precisa subir com `--host 0.0.0.0` para ser alcançável fora do container.

## ADR-005 — Capa única garantida por índice único parcial

Índice único parcial em `vehicle_images` (`vehicle_id` onde `is_cover` é verdadeiro), mais
um service que troca a capa dentro de uma transação. O banco garante a invariante; o
service dá o caminho de erro limpo.
**Trade-off:** prende o schema à semântica do PostgreSQL e obriga a suíte de testes a rodar
contra PostgreSQL, não SQLite.

## ADR-006 — Containers: `php-cli` + `artisan serve`, Vite dev server

O entregável é um desafio rodado localmente, não um deploy.
**Trade-off:** nenhum dos dois é como se faria em produção, e isso é deliberado — o
ambiente otimiza para o avaliador clonar e rodar, e para edição ao vivo durante a
apresentação. A topologia de produção pode ser descrita verbalmente.

## ADR-007 — Erros no formato RFC 7807

Toda resposta de erro é `application/problem+json` com `type`, `title`, `status`, `detail`,
`instance`, mais o membro de extensão `errors` em validações 422. Renderizador único em
`bootstrap/app.php`, com a montagem do corpo extraída para `App\Exceptions\Problem` (classe
simples, não uma facade).
**Trade-off:** ~60 linhas a mais que o padrão do Laravel. Em troca, contrato de erro
padronizado em vez de vazar o formato do framework, e o interceptor do front lê um só
formato.

**Correção de status/headers HTTP:** `Handler::render()` chama `prepareException()` antes de
rodar qualquer callback registrado, e esse método reescreve incondicionalmente
`ModelNotFoundException` → `NotFoundHttpException` e `AuthorizationException` →
`AccessDeniedHttpException`. Os callbacks originais para esses dois tipos nunca eram
alcançados — toda 403/404 real caía no 500 padrão do Laravel em vez do problem+json esperado;
o mesmo valia para 405 e 419, que não tinham callback nenhum. A correção: um callback para
`NotFoundHttpException` (cobre rota não encontrada e model-not-found, sempre com detail
genérico — a mensagem real do Laravel vaza a classe do model e o id) e um callback para
`Symfony\Component\HttpKernel\Exception\HttpExceptionInterface` (cobre 403, 405 com header
`Allow`, 419 — `TokenMismatchException` não implementa essa interface, mas
`prepareException()` já a reescreve como `HttpException(419, ...)` antes do callback rodar —
e 429 com header `Retry-After`, o que tornou o callback dedicado a
`ThrottleRequestsException` redundante e ele foi removido). Cada callback devolve `null` para
requisições que não pedem JSON, para que a navegação comum em rota web continue recebendo
HTML do Laravel.

**Mudança de contrato:** o `title` do 401 passa de `Unauthenticated` (string fixa) para
`Unauthorized` (frase-motivo real do status 401, vinda de `Response::$statusTexts`). O
`detail` continua `Unauthenticated.` — mensagem própria do Laravel, inalterada.

## ADR-008 — Filtros e ordenação escritos à mão com allow-list

Em vez de `spatie/laravel-query-builder`. Sem dependência, e a allow-list **é** a resposta
sobre injeção de SQL que se quer poder dar em voz alta na apresentação.

## ADR-009 — Documentação com Scribe

Escolhido no lugar do l5-swagger porque gera documentação interativa, arquivo OpenAPI e
coleção Postman a partir de uma fonte só — três entregáveis do desafio — e trata o
handshake de CSRF do Sanctum no *try it out*.

## ADR-010 — Auditoria por colunas `created_by` / `updated_by`

Preenchidas por observer. Sem pacote de auditoria.

## ADR-011 — Stack do front-end

TypeScript, TanStack Query, React Hook Form + Zod, React Router, MUI.
MUI escolhido porque tabela, diálogo, snackbar e campos de formulário vêm prontos — os
quatro são necessários. O schema Zod espelha as regras dos Form Requests.
**Trade-off:** MUI tem visual reconhecível; em 5 dias, velocidade vence originalidade.

## ADR-012 — Testes com Pest, contra PostgreSQL

Padrão do Laravel 12. Rodam contra PostgreSQL porque o índice único parcial não existe no
SQLite — testar a invariante de capa em SQLite daria falso positivo.

## ADR-013 — Git e idioma

Branches por feature com pull requests; Conventional Commits em inglês. Código e commits em
inglês; READMEs e este documento em português. Os campos do domínio permanecem em
português (`placa`, `chassi`, `marca`, `modelo`, `versao`, `valor_venda`, `cor`, `km`,
`cambio`, `combustivel`) — são parte do contrato definido no desafio.

## ADR-014 — Harness de testes: Pest, `phpunit.xml` como fonte única de verdade

Pest 4 substitui os testes em PHPUnit puro do skeleton, mantendo `phpunit/phpunit` como
motor de execução por baixo (é o próprio Laravel 12 que faz essa escolha). Duas suítes,
com comportamento deliberadamente diferente:

- `Feature` estende `Tests\TestCase` (boot completo da aplicação) e usa `RefreshDatabase`
  (migra o banco a cada teste). Roda contra `autoconf_vehicles_test`, um segundo banco no
  mesmo servidor PostgreSQL do ambiente de desenvolvimento — não um container adicional.
- `Unit` estende `Tests\TestCase` também, mas sem `RefreshDatabase` — nenhuma conexão de
  banco é preparada. Um teste `Unit` que precisar do banco pertence a `Feature`.

`phpunit.xml` é a única fonte de configuração do ambiente de teste (sem `.env.testing`):
o carregamento de arquivos de ambiente do Laravel, quando `APP_ENV=testing`, substitui o
`.env` inteiro em vez de sobrepor chaves, então um `.env.testing` incompleto apagaria
`APP_KEY` e quebraria a suíte de forma difícil de diagnosticar. `DB_HOST`/`DB_PORT`/
`DB_USERNAME`/`DB_PASSWORD` são as únicas variáveis não forçadas ali, para vir do ambiente
onde o comando roda (host ou container) — o mesmo `phpunit.xml` funciona nos dois lugares.

`tests/TestCase.php` recusa rodar a suíte contra qualquer banco cujo nome não termine em
`_test` (ou `_test_N` para o modo `--parallel`), porque `RefreshDatabase` derruba todas as
tabelas do banco configurado — sem essa guarda, um erro de configuração apontaria
`migrate:fresh` para o banco de desenvolvimento.

**Trade-off:** mais uma dependência de teste (`pestphp/pest-plugin-laravel`); em troca,
sintaxe mais legível (`it(...)` plano) e o plugin de arquitetura (`ArchTest`) sem esforço
extra.

---

# Decisões em aberto

| ID | Decisão | Situação |
|----|---------|----------|
| OPEN-01 | PAT como segunda credencial | Adiada para M8; só se sobrar tempo |
| OPEN-02 | CI no GitHub Actions | Adiada; menor valor por hora entre os bônus |
| OPEN-03 | E2E com Playwright | Adiada para W9 |
| OPEN-04 | Ao excluir a capa, promover outra imagem ou ficar sem capa | Decidir ao implementar M4 |
| OPEN-05 | Verificação de e-mail (`email_verified_at`, `MustVerifyEmail`) | Fora de escopo do desafio; coluna removida do baseline, adicionar se sobrar tempo |

---

# Limitações conhecidas

- `artisan serve` é single-threaded; suficiente para uso local, não para produção.
- Sem fila para processamento de imagens: o upload é síncrono.
- Sem redimensionamento nem geração de thumbnails.
- Rate limiting em memória; em produção exigiria Redis.
- Migrações do baseline (`0001_01_01_*`) trazem só `users` e `sessions` — `cache`/`cache_locks`,
  `jobs`/`job_batches`/`failed_jobs` e `password_reset_tokens` do skeleton padrão do Laravel foram
  removidas: `QUEUE_CONNECTION=sync` e `CACHE_STORE=file` tornam as duas primeiras sem uso, e não há
  fluxo de recuperação de senha no escopo do desafio. `sessions` permanece porque é o armazenamento
  real da sessão do ADR-002 (`SESSION_DRIVER=database`).
