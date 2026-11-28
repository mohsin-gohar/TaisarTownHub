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
        $filters = $request->validate([
            'status' => 'nullable|string|max:50',
            'limit' => 'nullable|integer|min:1|max:5000',
        ]);

        $query = Project::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->whereNull('deleted_at');

        // Public listing: bounded page (plain-array shape kept for the
        // existing frontend). Newest first; ?limit narrows further.
        $limit = (int) ($filters['limit'] ?? 200);

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