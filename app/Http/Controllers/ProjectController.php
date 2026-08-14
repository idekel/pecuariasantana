<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    /**
     * List the projects the authenticated user owns or has been granted access to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $projects = Project::query()
            ->where('user_id', $user->id)
            ->orWhereHas('collaborators', fn ($query) => $query->whereKey($user->id))
            ->latest()
            ->get();

        return ProjectResource::collection($projects);
    }

    /**
     * Compare total sold quantity against total yielded quantity for a project, in product units.
     */
    public function balance(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->hasAccess($request->user()), 403);

        $totalYield = $project->yields()->sum('quantity');
        $totalSold = $project->sales()->sum('quantity');

        return response()->json([
            'project_id' => $project->id,
            'unit' => $project->type->yieldUnit(),
            'total_yield' => sprintf('%0.2f', $totalYield),
            'total_sold' => sprintf('%0.2f', $totalSold),
            'difference' => sprintf('%0.2f', $totalYield - $totalSold),
        ]);
    }
}
