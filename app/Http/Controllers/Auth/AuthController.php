<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ResponseExample;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group(
    name: 'Auth',
    description: <<<'DESC'
        Registro, login, logout e o usuário autenticado atual. Esta API é uma **SPA
        Sanctum**: um `login` bem-sucedido (ou `register` seguido de `login`) define um
        cookie de sessão `HttpOnly`, e toda requisição autenticada subsequente precisa
        carregar um header `X-XSRF-TOKEN` válido, obtido a partir do cookie `XSRF-TOKEN`
        entregue por `GET /sanctum/csrf-cookie` — veja a seção "Authentication" acima.
        DESC,
)]
class AuthController extends Controller
{
    /**
     * Create a new user account.
     *
     * This does not also start an authenticated session: registration and
     * login are kept as two separate, explicit steps, matching the plain
     * `POST /auth/register` contract this endpoint promises. `User::create()`
     * only ever receives the request's own validated fields, so a payload
     * that also sends `is_admin` (or any other attribute) has no way to
     * influence the created record.
     */
    #[Unauthenticated]
    #[BodyParam('name', 'string', 'Nome completo do usuário.', example: 'Maria Souza')]
    #[BodyParam('email', 'string', 'E-mail único do usuário.', example: 'maria.souza@example.com')]
    #[BodyParam('password', 'string', 'Senha do usuário, sujeita às regras padrão de senha do Laravel.', example: 'Str0ng!Passw0rd')]
    #[BodyParam('password_confirmation', 'string', 'Deve ser idêntica a `password`.', example: 'Str0ng!Passw0rd')]
    #[ResponseExample(status: 201, content: [
        'id' => 1,
        'name' => 'Maria Souza',
        'email' => 'maria.souza@example.com',
        'created_at' => '2026-08-06T12:00:00.000000Z',
        'updated_at' => '2026-08-06T12:00:00.000000Z',
    ], description: 'Conta criada com sucesso. Note que isso não autentica o usuário — é necessário chamar `POST /auth/login` em seguida.')]
    // The "(and 1 more error)" suffix is Laravel's own `ValidationException`
    // message format, always in English regardless of the `errors` member's
    // language — kept as-is here to match real behavior, not a translation
    // gap in this app.
    #[ResponseExample(status: 422, content: [
        'type' => 'about:blank',
        'title' => 'Unprocessable Content',
        'status' => 422,
        'detail' => 'Já existe uma conta cadastrada com esse e-mail. (and 1 more error)',
        'instance' => '/api/auth/register',
        'errors' => [
            'email' => ['Já existe uma conta cadastrada com esse e-mail.'],
            'password' => ['A confirmação de senha não corresponde.'],
        ],
    ], description: 'Payload inválido — e-mail já cadastrado, senha e confirmação não coincidem, ou campo obrigatório ausente.')]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->accountData());

        return response()->json($user, 201);
    }

    /**
     * Authenticate a user for the SPA's stateful (cookie) session.
     *
     * `ValidationException::withMessages()` on a bad attempt is deliberate,
     * not just convenient: this app's exception renderer already turns any
     * `ValidationException` into the same `application/problem+json` 422
     * envelope used by Form Request validation, so a credentials failure
     * looks identical to any other validation failure to API consumers
     * without the controller having to build that response by hand.
     */
    #[Unauthenticated]
    #[BodyParam('email', 'string', 'E-mail cadastrado.', example: 'maria.souza@example.com')]
    #[BodyParam('password', 'string', 'Senha da conta.', example: 'Str0ng!Passw0rd')]
    #[ResponseExample(status: 200, content: [
        'id' => 1,
        'name' => 'Maria Souza',
        'email' => 'maria.souza@example.com',
        'created_at' => '2026-08-06T12:00:00.000000Z',
        'updated_at' => '2026-08-06T12:00:00.000000Z',
    ], description: 'Login bem-sucedido. A resposta carrega o cookie de sessão `HttpOnly`; o corpo é o usuário autenticado.')]
    #[ResponseExample(status: 422, content: [
        'type' => 'about:blank',
        'title' => 'Unprocessable Content',
        'status' => 422,
        'detail' => 'Essas credenciais não correspondem aos nossos registros.',
        'instance' => '/api/auth/login',
        'errors' => [
            'email' => ['Essas credenciais não correspondem aos nossos registros.'],
        ],
    ], description: 'Credenciais inválidas. Mesmo formato `application/problem+json` usado por qualquer outra falha de validação (422).')]
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->credentials())) {
            throw ValidationException::withMessages([
                'email' => ['Essas credenciais não correspondem aos nossos registros.'],
            ]);
        }

        // Regenerates the session id so a session fixated before login
        // (e.g. one an attacker forced onto the victim) can't be reused
        // now that it carries an authenticated identity.
        $request->session()->regenerate();

        return response()->json(Auth::user());
    }

    /**
     * Return the currently authenticated user.
     *
     * This route sits behind `auth:sanctum`, so a guest never reaches this
     * method at all: Sanctum's own guard throws `AuthenticationException`
     * first, which the app's exception renderer already turns into a
     * problem+json 401 (wired in `bootstrap/app.php`).
     */
    #[Authenticated]
    #[ResponseExample(status: 200, content: [
        'id' => 1,
        'name' => 'Maria Souza',
        'email' => 'maria.souza@example.com',
        'created_at' => '2026-08-06T12:00:00.000000Z',
        'updated_at' => '2026-08-06T12:00:00.000000Z',
    ])]
    #[ResponseExample(status: 401, content: [
        'type' => 'about:blank',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
        'instance' => '/api/auth/me',
    ], description: 'Nenhuma sessão válida (cookie ausente/expirado, ou `X-XSRF-TOKEN` ausente/incorreto).')]
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * End the SPA's authenticated session.
     *
     * The three steps mirror `login()`'s own care around sessions, in
     * reverse: log the guard out, destroy the session data, and rotate the
     * CSRF token, so neither the session id nor the CSRF token an attacker
     * observed before logout stays valid afterwards. `204 No Content` is
     * used rather than `200` because there is no representation to return
     * for a logout.
     */
    #[Authenticated]
    #[ResponseExample(status: 204, content: '', description: 'Sessão, dados de sessão e token CSRF invalidados com sucesso.')]
    #[ResponseExample(status: 401, content: [
        'type' => 'about:blank',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
        'instance' => '/api/auth/logout',
    ])]
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
