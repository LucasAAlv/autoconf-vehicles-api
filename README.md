# autoconf-vehicles-api

Back-end Laravel 12 de um desafio técnico SaaS multiusuário para gestão de veículos.

## Requisitos

- PHP 8.2+
- Composer
- PostgreSQL 17
- Docker (opcional, recomendado)

## Configuração

### 1. Instalar dependências

```bash
composer install
```

### 2. Variáveis de ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Edite o `.env` com as credenciais do banco de dados PostgreSQL.

### 3. Banco de dados

```bash
php artisan migrate
```

### 4. Link de storage público (passo obrigatório)

O upload de imagens usa o disco `public` do Laravel. Para que os arquivos enviados
fiquem acessíveis em `/storage/...`, é necessário criar o symlink de `public/storage`
apontando para `storage/app/public`:

```bash
php artisan storage:link
```

> Ao rodar via Docker, o `docker/entrypoint.sh` executa `storage:link --force`
> automaticamente antes de subir o servidor.

### 5. Iniciar o servidor de desenvolvimento

```bash
php artisan serve
```

A API ficará disponível em `http://localhost:8000`.

## Docker

```bash
docker compose -f docker/docker-compose.yml up --build
```

## Testes

```bash
php artisan test
```

Os testes rodam contra PostgreSQL (configurado no `.env.testing`). SQLite não é suportado
devido ao índice único parcial da invariante de capa de imagem (ADR-012).

## Stack

- PHP 8.2+, Laravel 12
- Autenticação: Sanctum (cookies HttpOnly)
- Banco: PostgreSQL 17
- Respostas de erro: RFC 7807 (`application/problem+json`)
