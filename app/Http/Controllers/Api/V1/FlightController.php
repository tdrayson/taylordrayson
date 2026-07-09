<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Flights\CreateFlight;
use App\Actions\Flights\DeleteFlight;
use App\Actions\Flights\UpdateFlight;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRequest;
use App\Http\Requests\Api\V1\StoreFlightRequest;
use App\Http\Requests\Api\V1\UpdateFlightRequest;
use App\Http\Resources\V1\FlightResource;
use App\Models\Flight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FlightController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return FlightResource::collection(
            $request->applyTo(Flight::query()->with(['airline', 'origin', 'destination']))
                ->orderByDesc('occurred_at')
                ->paginate($request->perPage())
        );
    }

    public function store(StoreFlightRequest $request, CreateFlight $createFlight): JsonResponse
    {
        $result = $createFlight($request->validated());

        return FlightResource::make($result['flight']->load(['airline', 'origin', 'destination']))
            ->response()
            ->setStatusCode($result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function show(Flight $flight): FlightResource
    {
        return FlightResource::make($flight->load(['airline', 'origin', 'destination']));
    }

    public function update(UpdateFlightRequest $request, Flight $flight, UpdateFlight $updateFlight): FlightResource
    {
        return FlightResource::make(
            $updateFlight($flight, $request->validated())->load(['airline', 'origin', 'destination'])
        );
    }

    public function destroy(Flight $flight, DeleteFlight $deleteFlight): Response
    {
        $deleteFlight($flight);

        return response()->noContent();
    }
}
