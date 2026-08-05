# Guia de testes

Pest sobre PHPUnit, rodando contra PostgreSQL real. Este documento descreve a estrutura,
as convenções de nomenclatura e as decisões de configuração que sustentam a suíte.

## Estrutura de diretórios

```
tests/
├── Feature/
│   ├── Auth/
│   ├── Infrastructure/
│   ├── Models/
│   └── .../<Área>/<Assunto>Test.php
├── Unit/
│   └── .../ espelha a estrutura de app/
├── Support/
│   └── ProblemJsonAssertions.php
├── Pest.php
└── TestCase.php
```

- `tests/Feature/<Área>/<Assunto>Test.php` — área é um agrupamento de domínio
  (`Auth`, `Infrastructure`, `Models`, `Vehicles`, ...), assunto é o que está sendo testado.
- `tests/Unit/` espelha a árvore de `app/` (ex.: `app/Services/CoverService.php` →
  `tests/Unit/Services/CoverServiceTest.php`), quando houver lógica pura o suficiente para
  não precisar de banco de dados.
- `tests/Support/` guarda infraestrutura de teste que não é, ela mesma, um teste
  (macros, factories auxiliares, etc.).

## Estilo dos testes: `it()` plano, sem `describe()`

```php
it('creates a valid, persisted user with a hashed password', function () {
    // ...
});
```

- Sempre `it(...)`, nunca `describe(...)` agrupando `it()`s — um arquivo por assunto já dá
  o agrupamento; `describe()` seria uma camada de indireção sem ganho aqui.
- Descrição em inglês, terceira pessoa, presente, sujeito é o sistema
  (`"creates a valid user"`, não `"should create a valid user"` nem `"test_creates..."`
  nem `"can create..."`). Lê-se como uma frase sobre o comportamento do sistema, não como
  uma instrução para o leitor.

## `Feature` vs `Unit`

Definido em `tests/Pest.php`:

```php
pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(Tests\TestCase::class)->in('Unit');
```

- `Feature` estende `Tests\TestCase` (boot completo da aplicação) **e** usa
  `RefreshDatabase` — toda migração é refeita a cada teste. Um teste que precisa do banco
  pertence aqui, nunca em `Unit`.
- `Unit` estende `Tests\TestCase` (aplicação boota, container de serviços disponível), mas
  **sem** `RefreshDatabase` — nenhuma conexão de banco é preparada. Um teste de `Unit` que
  tentar tocar o banco vai falhar de forma explícita, o que é o comportamento desejado: força
  a decisão "isso é `Feature`" a ser tomada corretamente.

## `assertProblemJson`: macro, não `expect()` extension

`tests/Support/ProblemJsonAssertions.php` registra um macro em `Illuminate\Testing\TestResponse`:

```php
$response->assertProblemJson(status: 422, errorKeys: ['placa', 'chassi']);
```

Verifica: status HTTP, header `Content-Type: application/problem+json` (comparação exata —
o Laravel não anexa `; charset=` a esse content type, diferente do `application/json`
padrão), `type` == `"about:blank"`, `status` no corpo bate com o parâmetro, `title` é string
não vazia, `instance` começa com `/`, e opcionalmente `title`/`detail` exatos e presença de
chaves em `errors`.

É um macro, e não uma extensão de `expect()`, porque o sujeito da asserção é sempre uma
`TestResponse` e toda outra asserção sobre essa resposta já é `$response->assertX(...)` —
o macro mantém a mesma cadeia fluente (`$response->assertProblemJson(...)->assertJsonPath(...)`),
em vez de forçar uma quebra de estilo só para essa checagem.

## Banco de dados de teste

A suíte roda contra `autoconf_vehicles_test`, um banco **separado** no mesmo servidor
PostgreSQL do ambiente de desenvolvimento (não é um container adicional) — necessário porque
o índice único parcial que garante "uma capa por veículo" não existe no SQLite, e testar essa
invariante em SQLite daria falso positivo.

**Volume novo (primeira vez subindo o stack):**

O `db` do `docker-compose.yml` já monta `./initdb:/docker-entrypoint-initdb.d:ro`. O Postgres
executa todo script desse diretório automaticamente, mas só na primeira inicialização de um
data directory vazio. `docker/initdb/10-create-test-database.sh` cria `autoconf_vehicles_test`
nesse momento — nada a fazer manualmente.

**Volume já existente (banco já rodando, caso comum em máquina de desenvolvimento):**

`/docker-entrypoint-initdb.d` não roda de novo contra um volume que já tem dados. Rode
manualmente uma vez:

```bash
docker exec autoconf-vehicles-api-db-1 psql -U postgres -c 'CREATE DATABASE autoconf_vehicles_test OWNER postgres'
```

## Por que não existe `.env.testing`

O carregamento de arquivos de ambiente do Laravel, quando `APP_ENV=testing`, **substitui**
o `.env` inteiro pelo `.env.testing` em vez de sobrepor só as chaves presentes nele. Um
`.env.testing` mínimo (só as chaves de banco, por exemplo) apagaria `APP_KEY` e qualquer
outra variável não repetida ali, e a suíte quebraria de forma difícil de diagnosticar.
`phpunit.xml` é a fonte única de verdade para o ambiente de teste: toda variável relevante
está declarada ali com `force="true"`, e nenhum arquivo `.env.testing` precisa existir.

`DB_HOST`, `DB_PORT`, `DB_USERNAME` e `DB_PASSWORD` são exceções deliberadas — não são
forçadas em `phpunit.xml`, para vir do ambiente onde o comando roda (o `.env` da máquina
local, ou as variáveis do container Docker). Isso permite que o mesmo `phpunit.xml` funcione
nos dois lugares sem precisar saber, em tempo de configuração, se a suíte vai rodar dentro
ou fora do Docker.

## A guarda de nome do banco em `tests/TestCase.php`

```php
if (preg_match('/_test(_\d+)?$/', $database) !== 1) {
    throw new RuntimeException(/* ... */);
}
```

`RefreshDatabase` roda `migrate:fresh`, que **derruba todas as tabelas** do banco configurado.
Se por engano a suíte rodar apontando para `autoconf_vehicles` (o banco de desenvolvimento),
o resultado seria perda de dados. A guarda recusa rodar contra qualquer banco cujo nome não
termine em `_test` — e o grupo opcional `(_\d+)?` existe para não quebrar o modo `--parallel`
do Pest, que sufixa o nome do banco por processo (`autoconf_vehicles_test_1`,
`autoconf_vehicles_test_2`, ...).

## Por que `CACHE_STORE=array` e `SESSION_DRIVER=array` só nos testes

Cada teste do Pest roda dentro de um único processo PHP, do início ao fim — então o driver
`array` (que vive só na memória daquele processo) sobrevive pela duração completa do teste
e depois é descartado, o que é exatamente o comportamento desejado para isolamento entre
testes. Isso não funciona para a aplicação real rodando fora dos testes, onde cada requisição
HTTP é um processo PHP novo e independente — por isso `.env.example` usa `file` para cache e
`database` para sessão: esses drivers persistem entre requisições, o `array` não persistiria
nada e a sessão/cache "desapareceria" a cada requisição.
