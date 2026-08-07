# Introdução

API para um SaaS multiusuário de gestão de veículos: autenticação, cadastro de veículos e gestão de imagens com capa única.

<aside>
    <strong>URL base</strong>: <code>http://localhost:8000</code>
</aside>

Esta documentação cobre todo endpoint exposto pela API Autoconf Vehicles: autenticação, CRUD de veículos e gestão de imagens de veículos.

<aside>Ao rolar a página, você verá exemplos de código para consumir a API em diferentes linguagens de programação na área escura à direita (ou como parte do conteúdo no celular).
É possível trocar a linguagem usada pelas abas no canto superior direito (ou pelo menu de navegação no canto superior esquerdo no celular).</aside>

## Autenticação

Esta API é uma **SPA Laravel Sanctum** — autenticada por um cookie de sessão `HttpOnly`, não por um bearer token. Não existe uma API key pra digitar nesta doc. Para se autenticar a partir desta página:

1. Chame **`POST /auth/register`** (ou use uma conta existente) e depois **`POST /auth/login`** usando o botão "Try It Out". Um login bem-sucedido define o cookie de sessão no seu navegador.
2. A partir daí, todo outro "Try It Out" nesta página busca automaticamente um cookie CSRF novo em `GET /sanctum/csrf-cookie` e envia seu valor de volta como o header `X-XSRF-TOKEN`, exatamente como o front-end SPA real faz — não é necessário copiar nenhum token manualmente.
3. `POST /auth/logout` limpa tanto a sessão quanto o token CSRF.

Uma requisição não autenticada a qualquer endpoint que exige sessão recebe uma resposta `401` `application/problem+json`.

