<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        return response()->json($query->orderByDesc('created_at')->orderBy('id')->get());
    }

    /**
     * Show a single project.
     */
    public function show($id)
    {
        return response()->json(Project::whereNull('deleted_at')->findOrFail($id));
    }

    /**
     * Store a new project.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'isFeatured' => 'nullable|boolean',
            'images' => 'nullable|array',
            'features' => 'nullable|array',
        ]);

        $data['id'] = $data['id'] ?? 'proj-' . Str::uuid();
        $data['title'] = strip_tags($data['title']);
        $data['description'] = strip_tags($data['description'] ?? '');

        $project = Project::create($data);

        return response()->json($project, 201);
    }

    /**
     * Update a project.
     */
    public function update(Request $request, $id)
    {
        $project = Project::whereNull('deleted_at')->findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'isFeatured' => 'nullable|boolean',
            'images' => 'nullable|array',
            'features' => 'nullable|array',
        ]);

        if (isset($data['title'])) {
            $data['title'] = strip_tags($data['title']);
        }
        if (isset($data['description'])) {
            $data['description'] = strip_tags($data['description']);
        }

        $project->update($data);

        return response()->json($project);
    }

    /**
     * Soft delete a project.
     */
    public function destroy($id)
    {
        $project = Project::whereNull('deleted_at')->findOrFail($id);
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }
}