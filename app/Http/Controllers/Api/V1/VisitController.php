<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Field\MarkGoals;
use App\Actions\Field\RecordCheckEvent;
use App\Actions\Field\StorePhoto;
use App\Actions\Visits\AcceptVisit;
use App\Actions\Visits\CancelVisit;
use App\Actions\Visits\CreateVisit;
use App\Actions\Visits\DeclineVisit;
use App\Actions\Visits\UpdateVisit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListVisitsRequest;
use App\Http\Requests\Api\V1\MarkGoalsRequest;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Requests\Api\V1\StorePhotoRequest;
use App\Http\Requests\Api\V1\StoreVisitRequest;
use App\Http\Requests\Api\V1\UpdateVisitRequest;
use App\Http\Resources\Api\V1\VisitResource;
use App\Models\Visit;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VisitController extends Controller
{
    public function index(ListVisitsRequest $request): JsonResponse
    {
        return response()->json(VisitResource::index(
            $request->validated('day'),
            $request->validated('assignee'),
        ));
    }

    public function store(StoreVisitRequest $request, CreateVisit $create): JsonResponse
    {
        $visit = $create($request->validated());

        return response()->json(VisitResource::created($visit), 201);
    }

    public function show(int $visit): JsonResponse
    {
        return response()->json(VisitResource::show($this->visit($visit)));
    }

    public function update(UpdateVisitRequest $request, int $visit, UpdateVisit $update): JsonResponse
    {
        $saved = $update($this->visit($visit), $request->validated());

        return response()->json(VisitResource::show($saved));
    }

    public function destroy(int $visit, CancelVisit $cancel): Response
    {
        $cancel($this->visit($visit));

        return response()->noContent();
    }

    public function accept(int $visit, AcceptVisit $accept): JsonResponse
    {
        return response()->json(VisitResource::accepted($accept($this->visit($visit))));
    }

    public function decline(int $visit, DeclineVisit $decline): JsonResponse
    {
        return response()->json(VisitResource::declined($decline($this->visit($visit))));
    }

    public function event(StoreEventRequest $request, int $visit, RecordCheckEvent $record): JsonResponse
    {
        $model = $this->visit($visit);
        $event = $record($model, $request->string('type')->toString(), $request->input('lat'), $request->input('lng'));
        $model->refresh();

        return response()->json(VisitResource::event($event, $model));
    }

    public function photo(StorePhotoRequest $request, int $visit, StorePhoto $store): JsonResponse
    {
        $photo = $store($this->visit($visit), $request->file('photo'));

        return response()->json(VisitResource::photo($photo), 201);
    }

    public function goals(MarkGoalsRequest $request, int $visit, MarkGoals $mark): JsonResponse
    {
        $goals = $mark($this->visit($visit), $request->validated('goals'));

        return response()->json(VisitResource::goals($goals));
    }

    private function visit(int $id): Visit
    {
        $visit = Visit::query()->find($id);

        if ($visit === null) {
            throw new ApiException(ErrorCodes::VISIT_NOT_FOUND, 404);
        }

        return $visit;
    }
}
