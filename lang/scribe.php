<?php

// Scribe-specific translation overrides (see `knuckleswtf/scribe`'s own
// `CustomTranslationsLoader`, which merges this file's `[group => [...]]`
// entries into its package defaults). This file overrides every UI/theme
// string Scribe ships (see `vendor/knuckleswtf/scribe/lang/scribe.php` for
// the full stock English set) so the generated docs never mix English
// chrome with the Portuguese content coming from this app's own attributes.
//
// `auth.none` replaces the stock "This API is not authenticated." line:
// this app's `scribe.auth.enabled` config is `false` because there is no
// bearer/header/query token to inject (see `config/scribe.php`), but the
// API *is* authenticated, via a Sanctum SPA session cookie — the stock
// message would contradict the "Authentication" walkthrough already given
// in `intro_text`.
return [
    'labels' => [
        'search' => 'Buscar',
        'base_url' => 'URL base',
    ],

    'auth' => [
        'none' => 'Esta API é autenticada via cookie de sessão de uma SPA Laravel Sanctum, não por um bearer/header/query token — veja a seção "Authentication" acima para o fluxo exato de login + CSRF a usar a partir desta documentação.',
        'details' => 'Todo endpoint autenticado é marcado com um selo `requires authentication` na documentação abaixo.',
    ],

    'headings' => [
        'introduction' => 'Introdução',
        'auth' => 'Autenticando requisições',
    ],

    'endpoint' => [
        'request' => 'Requisição',
        'headers' => 'Cabeçalhos',
        'url_parameters' => 'Parâmetros de URL',
        'body_parameters' => 'Parâmetros do corpo',
        'query_parameters' => 'Parâmetros de query',
        'response' => 'Resposta',
        'response_fields' => 'Campos da resposta',
        'example_request' => 'Requisição de exemplo',
        'example_response' => 'Resposta de exemplo',
        'responses' => [
            'binary' => 'Dado binário',
            'empty' => 'Resposta vazia',
        ],
    ],

    'try_it_out' => [
        'open' => 'Testar agora ⚡',
        'cancel' => 'Cancelar 🛑',
        'send' => 'Enviar requisição 💥',
        'loading' => '⏱ Enviando...',
        'received_response' => 'Resposta recebida',
        'request_failed' => 'Requisição falhou com erro',
        'error_help' => <<<'TEXT'
            Dica: verifique se você está corretamente conectado à rede.
            Se você é responsável por esta API, confirme que ela está rodando e que o CORS está habilitado.
            Você pode checar o console do Dev Tools para informações de depuração.
            TEXT,
    ],

    'links' => [
        'postman' => 'Ver coleção Postman',
        'openapi' => 'Ver especificação OpenAPI',
    ],
];
