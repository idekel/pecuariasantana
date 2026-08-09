<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidSaleQuantityException;
use App\Exceptions\UnauthorizedProjectActionException;
use App\Http\Resources\SaleResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * List the sales history for a project, optionally filtered by date range.
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        abort_unless($project->hasAccess($request->user()), 403);

        $saleQuery = $project->sales();
        if ($request->has('start_date') && $request->has('end_date')) {
            $saleQuery->whereBetween('sold_on', [$request->start_date, $request->end_date]);
        }

        return SaleResource::collection(
            $saleQuery->latest('sold_on')->get()
        );
    }

    /**
     * Record a new sale for a project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'sold_on' => ['required', 'date'],
        ]);

        $sale = $this->handle(
            fn () => $project->recordSale($request->user(), $data['quantity'], $data['amount'], $data['sold_on'])
        );

        return (new SaleResource($sale))
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
        } catch (InvalidSaleQuantityException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }
    }
}
