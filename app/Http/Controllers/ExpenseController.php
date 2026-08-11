<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseType;
use App\Exceptions\UnauthorizedProjectActionException;
use App\Http\Resources\ExpenseResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /**
     * List the expense history for a project within a date range.
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        abort_unless($project->hasAccess($request->user()), 403);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $expenses = $project->expenses()
            ->whereBetween('incurred_on', [$data['start_date'], $data['end_date']])
            ->latest('incurred_on')
            ->get();

        return ExpenseResource::collection($expenses);
    }

    /**
     * Record a new expense for a project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'type' => ['required', Rule::enum(ExpenseType::class)],
            'incurred_on' => ['required', 'date'],
        ]);

        $expense = $this->handle(
            fn () => $project->recordExpense($request->user(), $data['amount'], $data['type'], $data['incurred_on'])
        );

        return (new ExpenseResource($expense))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
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
        }
    }
}
