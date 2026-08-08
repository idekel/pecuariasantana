<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
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
}
