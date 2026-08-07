# Introduction

API para um SaaS multiusuário de gestão de veículos: autenticação, cadastro de veículos e gestão de imagens com capa única.

<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>

This documentation covers every endpoint exposed by the Autoconf Vehicles API: authentication, vehicle CRUD, and vehicle image management.

<aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>

## Authentication

This API is a **Laravel Sanctum SPA** — it is authenticated by an `HttpOnly` session cookie, not a bearer token. There is no API key to type into these docs. To authenticate from this page:

1. Call **`POST /auth/register`** (or use an existing account) and then **`POST /auth/login`** using the "Try It Out" button. A successful login sets the session cookie in your browser.
2. From then on, every other "Try It Out" call on this page automatically fetches a fresh CSRF cookie from `GET /sanctum/csrf-cookie` and sends its value back as the `X-XSRF-TOKEN` header, exactly like the real front-end SPA does — you don't need to copy any token by hand.
3. `POST /auth/logout` clears both the session and the CSRF token.

An unauthenticated request to any endpoint that requires a session receives a `401` `application/problem+json` response.

