<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * List projects.
     */
    public function index(Request $request)
    {
        $query = Project::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $query->whereNull('deleted_at');

        // Public listing: bounded page (plain-array shape kept for the
        // existing frontend). Newest first; ?limit narrows further.
        $limit = $request->filled('limit') ? (int) $request->limit : 200;

        return response()->json(
            $query->orderByDesc('created_at')->orderBy('id')->limit(min(max($limit, 1), 500))->get()
        );
    }

    /**
     * Show a single project.
     */
    public function show($id)
    {
        return response()->json(Project::whereNull('deleted_at')->findOrFail($id));
    }
}