<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleImage\StoreVehicleImageRequest;
use App\Http\Resources\VehicleImageResource;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Services\VehicleImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ResponseExample;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(
    name: 'Images',
    description: 'Upload e gestão de imagens de um veículo, incluindo a capa única (`is_cover`). Assim como `update`/`delete` de veículos, apenas o dono do veículo ou um usuário `is_admin` pode gerenciar suas imagens.',
)]
#[Authenticated]
class VehicleImageController extends Controller
{
    public function __construct(private readonly VehicleImageService $vehicleImageService)
    {
    }

    /**
     * Upload one or more images for a vehicle.
     *
     * `VehiclePolicy::manageImages` gates this the same way `update`/`delete`
     * already gate their own actions: only the vehicle's owner or an
     * `is_admin` user may add images to it.
     */
    #[UrlParam('vehicle_id', 'integer', 'Id do veículo.', example: 1)]
    #[BodyParam('files', 'file[]', 'Um ou mais arquivos de imagem (jpeg, jpg, png, gif ou webp; até 2MB cada). Enviado como multipart/form-data.', required: true)]
    #[ResponseFromApiResource(
        VehicleImageResource::class,
        model: VehicleImage::class,
        status: 201,
        collection: true,
        description: 'Imagens criadas. A primeira imagem já enviada para um veículo é automaticamente marcada como `is_cover`.',
    )]
    #[ResponseExample(status: 422, content: [
        'type' => 'about:blank',
        'title' => 'Unprocessable Content',
        'status' => 422,
        'detail' => 'Cada imagem deve ser de um dos seguintes tipos: jpeg, jpg, png, gif ou webp.',
        'instance' => '/api/vehicles/1/images',
        'errors' => [
            'files.0' => ['Cada imagem deve ser de um dos seguintes tipos: jpeg, jpg, png, gif ou webp.'],
        ],
    ])]
    #[ResponseExample(status: 403, content: [
        'type' => 'about:blank',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'This action is unauthorized.',
        'instance' => '/api/vehicles/1/images',
    ])]
    #[ResponseExample(status: 404, content: [
        'type' => 'about:blank',
        'title' => 'Not Found',
        'status' => 404,
        'detail' => 'The requested resource was not found.',
        'instance' => '/api/vehicles/999/images',
    ])]
    public function store(StoreVehicleImageRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('manageImages', $vehicle);

        $images = $this->vehicleImageService->upload($vehicle, $request->file('files'));

        return VehicleImageResource::collection($images)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Set the cover of a vehicle to one of its own images.
     *
     * `{imageId}` is resolved manually (`vehicle_id`-scoped query +
     * `findOrFail`) instead of via implicit nested route-model binding:
     * nested binding would need `{vehicle}` and `{image}` to be related
     * through a named Eloquent relationship matching the route segment,
     * whereas here the check that the image actually belongs to this
     * vehicle *is* the interesting behaviour (an image from a different
     * vehicle must 404, not 403), so it is spelled out explicitly.
     * `findOrFail` throws a `ModelNotFoundException`, which the exception
     * handler in `bootstrap/app.php` already rewrites into a 404
     * `application/problem+json` response — nothing extra to do here for
     * either "wrong vehicle" or "no such image".
     */
    #[UrlParam('vehicle_id', 'integer', 'Id do veículo.', example: 1)]
    #[UrlParam('imageId', 'integer', 'Id da imagem (deve pertencer ao veículo informado).', example: 1)]
    #[ResponseFromApiResource(
        VehicleImageResource::class,
        model: VehicleImage::class,
        description: 'Imagem promovida a capa. Qualquer imagem anteriormente marcada como capa deste veículo deixa de sê-lo, de forma transacional.',
    )]
    #[ResponseExample(status: 403, content: [
        'type' => 'about:blank',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'This action is unauthorized.',
        'instance' => '/api/vehicles/1/images/1/cover',
    ])]
    #[ResponseExample(status: 404, content: [
        'type' => 'about:blank',
        'title' => 'Not Found',
        'status' => 404,
        'detail' => 'The requested resource was not found.',
        'instance' => '/api/vehicles/1/images/999/cover',
    ], description: 'Também retornado quando a imagem existe mas pertence a outro veículo.')]
    public function setCover(Vehicle $vehicle, int $imageId): VehicleImageResource
    {
        $this->authorize('manageImages', $vehicle);

        $image = VehicleImage::query()
            ->where('vehicle_id', $vehicle->id)
            ->findOrFail($imageId);

        $image = $this->vehicleImageService->setCover($vehicle, $image);

        return new VehicleImageResource($image);
    }

    /**
     * Delete a single image belonging to a vehicle.
     *
     * The image is looked up through `$vehicle->images()` rather than a
     * global `VehicleImage::findOrFail()` (or implicit route-model binding
     * on `{imageId}`): this guarantees an image belonging to a different
     * vehicle 404s exactly like one that doesn't exist at all, instead of
     * leaking a 403 (or succeeding) for an id that is real but not "this
     * vehicle's".
     */
    #[UrlParam('vehicle_id', 'integer', 'Id do veículo.', example: 1)]
    #[UrlParam('imageId', 'integer', 'Id da imagem (deve pertencer ao veículo informado).', example: 1)]
    #[ResponseExample(status: 204, content: '', description: 'Imagem excluída do banco e o arquivo físico removido do storage.')]
    #[ResponseExample(status: 403, content: [
        'type' => 'about:blank',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'This action is unauthorized.',
        'instance' => '/api/vehicles/1/images/1',
    ])]
    #[ResponseExample(status: 404, content: [
        'type' => 'about:blank',
        'title' => 'Not Found',
        'status' => 404,
        'detail' => 'The requested resource was not found.',
        'instance' => '/api/vehicles/1/images/999',
    ], description: 'Também retornado quando a imagem existe mas pertence a outro veículo.')]
    public function destroy(Vehicle $vehicle, string $imageId): Response
    {
        $this->authorize('manageImages', $vehicle);

        $image = $vehicle->images()->findOrFail($imageId);

        $this->vehicleImageService->destroy($image);

        return response()->noContent();
    }
}
