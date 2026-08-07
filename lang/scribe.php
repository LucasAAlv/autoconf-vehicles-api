<?php

// Scribe-specific translation overrides (see `knuckleswtf/scribe`'s own
// `CustomTranslationsLoader`, which merges this file's `[group => [...]]`
// entries into its package defaults — no need to repeat every string here,
// only the ones actually being overridden).
//
// `auth.none` replaces the stock "This API is not authenticated." line:
// this app's `scribe.auth.enabled` config is `false` because there is no
// bearer/header/query token to inject (see `config/scribe.php`), but the
// API *is* authenticated, via a Sanctum SPA session cookie — the stock
// message would contradict the "Authentication" walkthrough already given
// in `intro_text`.
return [
    'auth' => [
        'none' => 'Esta API é autenticada via cookie de sessão de uma SPA Laravel Sanctum, não por um bearer/header/query token — veja a seção "Authentication" acima para o fluxo exato de login + CSRF a usar a partir desta documentação.',
    ],
];
