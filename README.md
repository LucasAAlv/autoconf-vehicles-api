# autoconf-vehicles-api

[![CI](https://github.com/LucasAAlv/autoconf-vehicles-api/actions/workflows/ci.yml/badge.svg)](https://github.com/LucasAAlv/autoconf-vehicles-api/actions/workflows/ci.yml)

Back-end Laravel 12 de um desafio técnico SaaS multiusuário para gestão de veículos.

## Sumário

- [Requisitos](#requisitos)
- [Instalação com Docker (recomendado)](#instalação-com-docker-recomendado)
- [Instalação manual (sem Docker)](#instalação-manual-sem-docker)
- [Dados de teste (seed)](#dados-de-teste-seed)
- [Autenticação](#autenticação)
- [Documentação interativa](#documentação-interativa)
- [Exemplos de uso (cURL)](#exemplos-de-uso-curl)
- [Testes](#testes)
- [Stack](#stack)

## Requisitos

- PHP 8.5+
- Composer
- PostgreSQL 17
- Docker (opcional, recomendado — evita instalar PostgreSQL localmente)

## Instalação com Docker (recomendado)

O Docker cuida só do PostgreSQL e do `php artisan serve`; o PHP/Composer do host ainda
fazem a instalação de dependências e a configuração inicial, porque o volume do
container monta a raiz do repositório e espera `vendor/` já existir.

```bash
# 1. Dependências PHP
composer install

# 2. Variáveis de ambiente e chave da aplicação
cp .env.example .env
php artisan key:generate

# 3. Sobe a aplicação (porta 8000) e o PostgreSQL (porta 5432)
docker compose --env-file .env -f docker/docker-compose.yml up --build -d

# 4. Migrações + dados de teste (usuários e veículos de exemplo)
php artisan migrate --seed
```

A API fica disponível em `http://localhost:8000`. O `docker/entrypoint.sh` já roda
`storage:link --force` automaticamente antes de subir o servidor, então as imagens
enviadas ficam acessíveis em `/storage/...` sem passo manual extra.

Para parar o stack: `docker compose -f docker/docker-compose.yml down` (adicione `-v`
para descartar também o volume do banco).

## Instalação manual (sem Docker)

Precisa de um PostgreSQL 17 rodando localmente, com as credenciais do `.env` já
apontando para ele.

```bash
# 1. Dependências PHP
composer install

# 2. Variáveis de ambiente e chave da aplicação
cp .env.example .env
php artisan key:generate
```

Edite o `.env` com as credenciais do seu PostgreSQL (`DB_HOST`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD`).

```bash
# 3. Migrações + dados de teste (usuários e veículos de exemplo)
php artisan migrate --seed

# 4. Link de storage público (obrigatório — upload de imagens usa o disco `public`)
php artisan storage:link

# 5. Servidor de desenvolvimento
php artisan serve
```

A API fica disponível em `http://localhost:8000`.

## Dados de teste (seed)

O `php artisan migrate --seed` (ou `php artisan db:seed` isoladamente) cria dois
usuários e 10 veículos de exemplo com imagens, prontos para testar a API sem precisar
cadastrar nada manualmente:

| Usuário | E-mail | Senha | Papel |
|---|---|---|---|
| Admin User | `admin@example.com` | `password` | admin (`is_admin = true`) — pode editar/excluir veículos de qualquer usuário |
| Test User | `test@example.com` | `password` | usuário comum — dono dos 10 veículos de exemplo |

## Autenticação

A API usa **Laravel Sanctum no modo SPA** (autenticação por cookie de sessão
`HttpOnly`, não por Bearer token). O fluxo é:

1. `GET /sanctum/csrf-cookie` — inicializa a sessão e devolve um cookie `XSRF-TOKEN`.
2. Toda requisição que muda estado (`POST`/`PUT`/`PATCH`/`DELETE`, incluindo
   `/api/auth/login` e `/api/auth/register`) precisa do header `X-XSRF-TOKEN` com o
   valor desse cookie **decodificado** (o cookie vem com URL-encoding).
3. O Sanctum só trata a requisição como "vinda do front-end" (e habilita sessão/CSRF)
   quando o header `Origin` ou `Referer` bate com um domínio listado em
   `SANCTUM_STATEFUL_DOMAINS` — um navegador manda isso sozinho; em `curl` precisa
   incluir o header manualmente.
4. O login regenera a sessão, então o cookie `XSRF-TOKEN` é **trocado** na resposta do
   login — chamadas seguintes precisam ler o cookie de novo, não reaproveitar o token
   usado no login.
5. Depois do login, o cookie de sessão já autentica as próximas chamadas — não existe
   token de acesso para guardar ou renovar manualmente.

Rotas de `/api/auth/register` e `/api/auth/login` têm rate limiting (`throttle:auth`).

## Documentação interativa

A documentação completa — todos os endpoints, parâmetros, exemplos de request/response
e um "Try It Out" que já resolve o handshake de CSRF do Sanctum — é gerada com
[Scribe](https://scribe.knuckles.wtf/) e fica disponível, com a aplicação rodando, em:

```
http://localhost:8000/docs
```

O mesmo comando também gera `public/docs/openapi.yaml` (OpenAPI) e
`public/docs/collection.json` (coleção Postman), ambos já commitados no repositório.

## Exemplos de uso (cURL)

Os exemplos abaixo cobrem o fluxo completo de autenticação e um `GET` autenticado.
Para o restante dos endpoints (CRUD de veículos, upload e gestão de imagens), veja a
[documentação interativa](#documentação-interativa) — ela tem exemplo de request e
response para cada um.

Todas as chamadas usam `-H "Origin: http://localhost:8000"` — sem isso o Sanctum não
reconhece a requisição como "do front-end" e não abre sessão (ver seção acima).

```bash
ORIGIN="http://localhost:8000"

# 1. Cookie de CSRF (obrigatório antes de qualquer chamada que muda estado)
curl -c cookies.txt -H "Origin: $ORIGIN" http://localhost:8000/sanctum/csrf-cookie

# 2. Extrai e decodifica o valor do cookie XSRF-TOKEN
XSRF_TOKEN=$(grep XSRF-TOKEN cookies.txt | tail -1 | cut -f7 | php -r 'echo urldecode(trim(fgets(STDIN)));')

# 3. Login com um dos usuários de teste do seed
curl -b cookies.txt -c cookies.txt -H "Origin: $ORIGIN" -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-XSRF-TOKEN: $XSRF_TOKEN" \
  -d '{"email":"test@example.com","password":"password"}'

# 4. O login trocou o cookie XSRF-TOKEN — extrai o valor novo antes de continuar
XSRF_TOKEN=$(grep XSRF-TOKEN cookies.txt | tail -1 | cut -f7 | php -r 'echo urldecode(trim(fgets(STDIN)));')

# 5. Usuário autenticado
curl -b cookies.txt -H "Origin: $ORIGIN" http://localhost:8000/api/auth/me -H "Accept: application/json"

# 6. Cadastro de veículo (mesmos cookies + X-XSRF-TOKEN do passo 4)
curl -b cookies.txt -c cookies.txt -H "Origin: $ORIGIN" -X POST http://localhost:8000/api/vehicles \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-XSRF-TOKEN: $XSRF_TOKEN" \
  -d '{
        "placa": "ABC1D23",
        "chassi": "9BWZZZ377VT004251",
        "marca": "Volkswagen",
        "modelo": "Gol",
        "versao": "1.0",
        "valor_venda": "45000.00",
        "cor": "Branco",
        "km": 12000,
        "cambio": "manual",
        "combustivel": "flex"
      }'

# 7. Listagem com filtro e ordenação
curl -b cookies.txt -H "Origin: $ORIGIN" "http://localhost:8000/api/vehicles?marca=Volkswagen&sort=-valor_venda" \
  -H "Accept: application/json"
```

## Testes

```bash
composer test
# ou, direto:
vendor/bin/pest
```

Os testes rodam contra PostgreSQL real, em um segundo banco (`autoconf_vehicles_test`) —
SQLite não é suportado porque o índice único parcial da invariante de capa de imagem não
existe nesse driver, e testar essa invariante em SQLite daria falso positivo. Toda a
configuração do ambiente de teste vive em `phpunit.xml` (não há `.env.testing`); veja
`.claude/context/testing.md` para o porquê e para as convenções da suíte.

`autoconf_vehicles_test` precisa existir no mesmo servidor Postgres usado em
desenvolvimento, antes de rodar a suíte:

- **Volume novo** (primeira vez subindo o stack Docker): nada a fazer — o `db` já monta
  `docker/initdb` em `/docker-entrypoint-initdb.d`, e o Postgres roda o script
  `10-create-test-database.sh` de lá automaticamente na primeira inicialização de um data
  directory vazio.
- **Volume já existente** (banco já rodando, o caso comum no dia a dia):
  `/docker-entrypoint-initdb.d` não roda de novo contra um volume com dados. Crie o banco
  manualmente uma vez:

  ```bash
  docker exec autoconf-vehicles-api-db-1 psql -U postgres -c 'CREATE DATABASE autoconf_vehicles_test OWNER postgres'
  ```

Outros scripts úteis: `composer test:unit`, `composer test:feature`, `composer lint`
(Pint, corrige) e `composer lint:test` (Pint, só verifica).

## Stack

- PHP 8.5+, Laravel 12
- Autenticação: Sanctum SPA (cookies `HttpOnly`, CSRF via `XSRF-TOKEN`/`X-XSRF-TOKEN`)
- Banco: PostgreSQL 17
- Docs: Scribe (OpenAPI + coleção Postman + UI interativa)
- Respostas de erro: RFC 7807 (`application/problem+json`)
