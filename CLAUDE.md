# autoconf-vehicles-api

Back-end Laravel 12 de um desafio técnico SaaS multiusuário para gestão de veículos.

**Contexto completo:** `.claude/context/Desafio Técnico — Api Laravel + React (veículos).pdf`
**Decisões técnicas:** `.claude/context/decisions.md` — consulte antes de propor alternativas de arquitetura.

## Stack

- PHP 8.2+, Laravel 12, PSR-12 (`pint.json`)
- **Auth:** Sanctum SPA — cookies `HttpOnly`, CSRF via `XSRF-TOKEN` / `X-XSRF-TOKEN` (ADR-002)
- **Banco:** PostgreSQL 17 — obrigatório por causa do índice único parcial da capa (ADR-003)
- **Testes:** Pest contra PostgreSQL — SQLite daria falso positivo na invariante de capa (ADR-012)
- **Docs:** Scribe — gera OpenAPI + coleção Postman + UI interativa de uma vez só (ADR-009)
- **Erros:** RFC 7807 (`application/problem+json`) com membro `errors` em 422 (ADR-007)
- **Infraestrutura:** Docker por repositório, `artisan serve` + Vite dev server (ADR-004, ADR-006)

## Domínio

**Vehicle** — `placa` (Mercosul única), `chassi` (17 chars, único), `marca`, `modelo`, `versao`,
`valor_venda` (decimal 15,2), `cor`, `km` (int ≥ 0), `cambio` (manual|automatico),
`combustivel` (gasolina|alcool|flex|diesel|hibrido|eletrico), `user_id`, `timestamps`,
`created_by`, `updated_by` (preenchidos por observer — ADR-010).

**VehicleImage** — `vehicle_id`, `path` (relativo ao storage), `is_cover` (bool), `timestamps`.
Invariante: exatamente uma imagem com `is_cover = true` por veículo, garantida por índice único
parcial no PostgreSQL + service com transação (ADR-005).

## Endpoints — Base URL `/api`

**Auth**
- `POST /auth/register|login|logout`, `GET /auth/me`

**Veículos**
- `GET /vehicles` — `q`, `marca`, `modelo`, `placa`, `sort=km,-valor_venda`, `page`, `per_page`
- `POST|GET|PUT|PATCH|DELETE /vehicles/{id}`

**Imagens**
- `POST /vehicles/{id}/images` — upload múltiplo (`files[]`, multipart/form-data)
- `PATCH /vehicles/{id}/images/{imageId}/cover`
- `DELETE /vehicles/{id}/images/{imageId}`

## Arquitetura

- Controllers enxutos; lógica em Services/Actions (ADR-001)
- Form Requests para todas as validações; filtros e ordenação escritos à mão com allow-list — sem `spatie/laravel-query-builder` (ADR-008)
- `VehiclePolicy` — apenas dono (`user_id`) ou `is_admin` pode editar/excluir
- Ao excluir veículo ou imagem: remover arquivo físico do storage
- CORS restrito ao domínio do front-end; rate limiting nas rotas de auth

## Convenções (ADR-013)

- Conventional Commits em inglês
- Código e commits em inglês; READMEs em português
- Campos do domínio permanecem em português (`placa`, `chassi`, `marca`, `valor_venda` etc.)

## Prioridade de entrega

1. Auth (register/login)
2. CRUD Vehicle
3. Upload e gestão de imagens com capa única
4. Listagem com paginação, filtros e ordenação
