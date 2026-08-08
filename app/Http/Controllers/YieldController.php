<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidYieldQuantityException;
use App\Exceptions\UnauthorizedProjectActionException;
use App\Http\Resources\YieldResource;
use App\Models\Project;
use App\Models\YieldRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class YieldController extends Controller
{
    /**
     * List the yields recorded for a project.
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        abort_unless($project->hasAccess($request->user()), 403);

        $yieldQuery = $project->yields();
        if ($request->has('start_date') && $request->has('end_date')) {
            $yieldQuery->whereBetween('produced_on', [$request->start_date, $request->end_date]);
        }
        return YieldResource::collection(
            $yieldQuery->latest('produced_on')->get()
        );
    }

    /**
     * Sum the yields recorded for a project within a date range.
     */
    public function summary(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->hasAccess($request->user()), 403);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $total = $project->yields()
            ->whereBetween('produced_on', [$data['start_date'], $data['end_date']])
            ->sum('quantity');

        return response()->json([
            'project_id' => $project->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'unit' => $project->type->yieldUnit(),
            'total' => sprintf('%0.2f', $total),
        ]);
    }

    /**
     * Record a new yield for a project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->validated($request);

        $yield = $this->handle(
            fn() => $project->recordYield($request->user(), $data['quantity'], $data['produced_on'])
        );

        return (new YieldResource($yield))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a single yield.
     */
    public function show(Request $request, YieldRecord $yield): YieldResource
    {
        abort_unless($yield->project->hasAccess($request->user()), 403);

        return new YieldResource($yield);
    }

    /**
     * Update an existing yield.
     */
    public function update(Request $request, YieldRecord $yield): YieldResource
    {
        $data = $this->validated($request);

        $yield = $this->handle(
            fn() => $yield->project->updateYield($yield, $request->user(), $data['quantity'], $data['produced_on'])
        );

        return new YieldResource($yield);
    }

    /**
     * Delete an existing yield.
     */
    public function destroy(Request $request, YieldRecord $yield): Response
    {
        $this->handle(fn() => $yield->project->deleteYield($yield, $request->user()));

        return response()->noContent();
    }

    /**
     * Validate the request payload for creating or updating a yield.
     *
     * @return array{quantity: int|float, produced_on: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'quantity' => ['required', 'numeric', 'min:0'],
            'produced_on' => ['required', 'date'],
        ]);
    }

    /**
     * Run a project business-logic action, translating domain exceptions into HTTP responses.
     */
    private function handle(callable $action): mixed
    {
        try {
            return $action();
        } catch (UnauthorizedProjectActionException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidYieldQuantityException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }
    }
}
