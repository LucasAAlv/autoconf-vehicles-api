<?php

namespace App\Http\Controllers;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Http\Requests\Vehicle\IndexVehicleRequest;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\VehicleImageService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ResponseExample;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(
    name: 'Vehicles',
    description: 'CRUD de veículos. Qualquer usuário autenticado pode listar/visualizar qualquer veículo; apenas o dono (`user_id`) ou um usuário `is_admin` pode atualizar ou excluir.',
)]
#[Authenticated]
class VehicleController extends Controller
{
    /**
     * Default page size when the client doesn't send `per_page`.
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Hard cap on `per_page` so a client can't request an unbounded page
     * size — a value above this is clamped down to it rather than
     * rejected or ignored.
     */
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly VehicleImageService $vehicleImageService)
    {
    }

    /**
     * List vehicles, paginated.
     *
     * `VehiclePolicy::viewAny` already allows any authenticated user, so
     * this lists every vehicle in the system, not just the caller's own —
     * consistent with `show()`, which likewise doesn't gate on ownership.
     *
     * `Vehicle::query()` is ordered by `id` (so pagination is deterministic)
     * and paginated. `q`/`marca`/`modelo`/`placa` filtering is delegated to
     * `Vehicle::scopeFilter()` (issue #30) so this method stays a thin
     * pass-through of the validated input; sorting (issue #31) extends this
     * same query rather than replacing it, via `Vehicle::scopeSort()`, and is
     * chained *before* the trailing `orderBy('id')` so the requested fields
     * take precedence and `id` only breaks ties among rows equal on all of
     * them.
     *
     * `per_page` is clamped to `MAX_PER_PAGE` instead of erroring, so a
     * client asking for an unbounded page size just gets the cap back.
     * `VehicleResource::collection()` on a paginator carries `total`,
     * `current_page`, `last_page` and `per_page` through automatically in
     * the response's `meta` envelope, so no custom collection class is
     * needed to satisfy those fields.
     *
     * Note: unlike `show()`/`store()`/`update()`, this list view never eager-loads
     * `creator`/`updater`/`images`, so those keys are absent from each item here —
     * fetch `GET /vehicles/{vehicle}` for the full shape.
     */
    #[QueryParam('q', 'string', 'Busca livre (placa, chassi, marca, modelo ou versão).', required: false, example: 'Corolla')]
    #[QueryParam('marca', 'string', 'Filtra por marca exata.', required: false, example: 'Toyota')]
    #[QueryParam('modelo', 'string', 'Filtra por modelo exato.', required: false, example: 'Corolla')]
    #[QueryParam('placa', 'string', 'Filtra por placa exata.', required: false, example: 'ABC1D23')]
    #[QueryParam('sort', 'string', 'Lista separada por vírgulas de campos ordenáveis (`km`, `valor_venda`, `marca`, `modelo`, `created_at`), cada um opcionalmente prefixado com `-` para ordem decrescente.', required: false, example: 'km,-valor_venda')]
    #[QueryParam('page', 'integer', 'Número da página (1-indexado).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Itens por página (padrão 15, máximo 100 — valores maiores são reduzidos ao limite).', required: false, example: 15)]
    #[ResponseFromApiResource(
        VehicleResource::class,
        model: Vehicle::class,
        collection: true,
        paginate: 15,
        description: 'Página de veículos. `creator`/`updater`/`images` não são carregados nesta listagem.',
    )]
    #[ResponseExample(status: 401, content: [
        'type' => 'about:blank',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
        'instance' => '/api/vehicles',
    ])]
    public function index(IndexVehicleRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vehicle::class);

        $perPage = min(
            (int) ($request->validated('per_page') ?? self::DEFAULT_PER_PAGE),
            self::MAX_PER_PAGE,
        );

        $vehicles = Vehicle::query()
            ->filter($request->validated())
            ->sort($request->validated('sort'))
            ->orderBy('id')
            ->paginate($perPage);

        return VehicleResource::collection($vehicles);
    }

    /**
     * Create a new vehicle owned by the authenticated user.
     *
     * `user_id` is not part of `$request->validated()` — it is not
     * validated input at all, it is assigned directly from the
     * authenticated user, so a client can never influence it by including
     * it in the payload (`Vehicle::$fillable` also excludes it, as a second
     * line of defense). `created_by`/`updated_by` are stamped separately by
     * `VehicleObserver` on the `creating` event.
     */
    #[BodyParam('placa', 'string', 'Placa única (formato Mercosul ou tradicional).', example: 'ABC1D23')]
    #[BodyParam('chassi', 'string', 'Chassi único, exatamente 17 caracteres alfanuméricos (VIN).', example: '9BWZZZ377VT004251')]
    #[BodyParam('marca', 'string', 'Marca do veículo.', example: 'Toyota')]
    #[BodyParam('modelo', 'string', 'Modelo do veículo.', example: 'Corolla')]
    #[BodyParam('versao', 'string', 'Versão/trim do veículo.', example: 'XEi 2.0')]
    #[BodyParam('valor_venda', 'number', 'Valor de venda em reais, mínimo 0.01.', example: 129900.00)]
    #[BodyParam('cor', 'string', 'Cor do veículo.', example: 'Prata')]
    #[BodyParam('km', 'integer', 'Quilometragem, inteiro não negativo.', example: 15000)]
    #[BodyParam('cambio', 'string', 'Tipo de câmbio.', enum: Cambio::class, example: 'manual')]
    #[BodyParam('combustivel', 'string', 'Tipo de combustível.', enum: Combustivel::class, example: 'flex')]
    #[ResponseFromApiResource(
        VehicleResource::class,
        model: Vehicle::class,
        status: 201,
        with: ['creator', 'updater', 'images'],
        description: 'Veículo criado. `images` vem vazio logo após a criação; `creator`/`updater` refletem o usuário autenticado.',
    )]
    #[ResponseExample(status: 422, content: [
        'type' => 'about:blank',
        'title' => 'Unprocessable Content',
        'status' => 422,
        'detail' => 'The placa has already been taken. (and 1 more error)',
        'instance' => '/api/vehicles',
        'errors' => [
            'placa' => ['Já existe um veículo cadastrado com essa placa.'],
            'chassi' => ['Já existe um veículo cadastrado com esse chassi.'],
        ],
    ])]
    #[ResponseExample(status: 401, content: [
        'type' => 'about:blank',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
        'instance' => '/api/vehicles',
    ])]
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = new Vehicle($request->validated());
        $vehicle->user_id = $request->user()->id;
        $vehicle->save();

        // Loaded (rather than left unset) so the response shape matches
        // `show()` exactly: both always carry the audit relations and the
        // `images` collection (empty right after creation), never just
        // when they happen to already be in memory.
        $vehicle->load(['creator', 'updater', 'images']);

        return (new VehicleResource($vehicle))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single vehicle.
     *
     * `VehiclePolicy::view` currently allows any authenticated user
     * regardless of ownership, so this call trivially passes today — it is
     * wired in now so a later issue that tightens the policy doesn't need
     * to touch this controller.
     */
    #[UrlParam('vehicle_id', 'integer', 'Id do veículo.', example: 1)]
    #[ResponseFromApiResource(
        VehicleResource::class,
        model: Vehicle::class,
        with: ['creator', 'updater', 'images'],
    )]
    #[ResponseExample(status: 404, content: [
        'type' => 'about:blank',
        'title' => 'Not Found',
        'status' => 404,
        'detail' => 'The requested resource was not found.',
        'instance' => '/api/vehicles/999',
    ])]
    #[ResponseExample(status: 401, content: [
        'type' => 'about:blank',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
        'instance' => '/api/vehicles/1',
    ])]
    public function show(Vehicle $vehicle): VehicleResource
    {
        $this->authorize('view', $vehicle);

        $vehicle->load(['creator', 'updater', 'images']);

        return new VehicleResource($vehicle);
    }

    /**
     * Update a vehicle. Both `PUT` and `PATCH` route here and behave
     * identically: every field in `UpdateVehicleRequest` is `sometimes`, so
     * an absent field is simply left untouched by `fill()` rather than
     * nulled out — there is no "PUT replaces everything" distinction.
     *
     * `updated_by` is not set here — `VehicleObserver` stamps it from the
     * authenticated user on the `updating` event.
     */
    #[Endpoint(
        title: 'Atualizar um veículo',
        description: <<<'DESC'
            `PUT` e `PATCH` chegam ambos aqui e se comportam de forma idêntica: todo campo em
            `UpdateVehicleRequest` é `sometimes`, então um campo ausente simplesmente permanece
            intocado pelo `fill()` em vez de ser zerado — não há distinção de "PUT substitui tudo".
            `updated_by` não é setado pelo controller — `VehicleObserver` o carimba a partir do
            usuário autenticado no evento `updating`.
            DESC,
    )]
    #[UrlParam('vehicle_id', 'integer', 'Id do veículo.', example: 1)]
    #[BodyParam('placa', 'string', 'Placa única (formato Mercosul ou tradicional).', required: false, example: 'ABC1D23')]
    #[BodyParam('chassi', 'string', 'Chassi único, exatamente 17 caracteres alfanuméricos (VIN).', required: false, example: '9BWZZZ377VT004251')]
    #[BodyParam('marca', 'string', 'Marca do veículo.', required: false, example: 'Toyota')]
    #[BodyParam('modelo', 'string', 'Modelo do veículo.', required: false, example: 'Corolla')]
    #[BodyParam('versao', 'string', 'Versão/trim do veículo.', required: false, example: 'XEi 2.0')]
    #[BodyParam('valor_venda', 'number', 'Valor de venda em reais, mínimo 0.01.', required: false, example: 134900.00)]
    #[BodyParam('cor', 'string', 'Cor do veículo.', required: false, example: 'Preto')]
    #[BodyParam('km', 'integer', 'Quilometragem, inteiro não negativo.', required: false, example: 18000)]
    #[BodyParam('cambio', 'string', 'Tipo de câmbio.', required: false, enum: Cambio::class, example: 'automatico')]
    #[BodyParam('combustivel', 'string', 'Tipo de combustível.', required: false, enum: Combustivel::class, example: 'flex')]
    #[ResponseFromApiResource(
        VehicleResource::class,
        model: Vehicle::class,
        with: ['creator', 'updater', 'images'],
        description: 'Veículo atualizado. Campos ausentes no payload permanecem inalterados (PUT e PATCH se comportam de forma idêntica).',
    )]
    #[ResponseExample(status: 403, content: [
        'type' => 'about:blank',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'This action is unauthorized.',
        'instance' => '/api/vehicles/1',
    ], description: 'Usuário autenticado não é o dono do veículo nem `is_admin`.')]
    #[ResponseExample(status: 422, content: [
        'type' => 'about:blank',
        'title' => 'Unprocessable Content',
        'status' => 422,
        'detail' => 'The placa has already been taken.',
        'instance' => '/api/vehicles/1',
        'errors' => [
            'placa' => ['Já existe um veículo cadastrado com essa placa.'],
        ],
    ])]
    #[ResponseExample(status: 404, content: [
        'type' => 'about:blank',
        'title' => 'Not Found',
        'status' => 404,
        'detail' => 'The requested resource was not found.',
        'instance' => '/api/vehicles/999',
    ])]
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        $vehicle->fill($request->validated());
        $vehicle->save();

        $vehicle->load(['creator', 'updater', 'images']);

        return new VehicleResource($vehicle);
    }

    /**
     * Delete a vehicle.
     *
     * `vehicle_images.vehicle_id` is `cascadeOnDelete()` at the raw
     * PostgreSQL level, so `VehicleImage` rows disappear automatically along
     * with the vehicle — but that cascade never fires an Eloquent event, so
     * `VehicleImageService::deleteAllForVehicle()` is called, inside the same
     * transaction, to also clean up the physical files from the public disk
     * (deferred to after the commit, so a rollback never leaves the vehicle
     * gone but its images' files still on disk, or vice versa).
     */
    #[UrlParam('vehicle_id', 'integer', 'Id do veículo.', example: 1)]
    #[ResponseExample(status: 204, content: '', description: 'Veículo (e suas imagens, no banco e no storage) excluído com sucesso.')]
    #[ResponseExample(status: 403, content: [
        'type' => 'about:blank',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'This action is unauthorized.',
        'instance' => '/api/vehicles/1',
    ])]
    #[ResponseExample(status: 404, content: [
        'type' => 'about:blank',
        'title' => 'Not Found',
        'status' => 404,
        'detail' => 'The requested resource was not found.',
        'instance' => '/api/vehicles/999',
    ])]
    public function destroy(Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);

        DB::transaction(function () use ($vehicle) {
            $this->vehicleImageService->deleteAllForVehicle($vehicle);

            $vehicle->delete();
        });

        return response()->noContent();
    }
}
