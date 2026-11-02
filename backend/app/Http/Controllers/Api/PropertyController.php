<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::query();

        if ($request->filled('phase') && $request->phase !== 'All') {
            $query->where('phase', $request->phase);
        }

        if ($request->filled('type') && $request->type !== 'All') {
            $query->where('type', $request->type);
        }

        if ($request->filled('dealerId')) {
            $query->where('assignedDealerId', $request->dealerId);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('plotNumber', 'like', "%{$s}%")
                  ->orWhere('sector', 'like', "%{$s}%");
            });
        }

        return response()->json($query->latest()->get());
    }

    public function show($id)
    {
        $property = Property::findOrFail($id);
        return response()->json($property);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|string',
            'title' => 'required|string',
            'pricePKR' => 'required|numeric',
            'phase' => 'required|string',
            'sector' => 'required|string',
            'sizeSqYards' => 'required|numeric',
        ]);

        $property = Property::create($request->all());
        return response()->json($property, 201);
    }

    public function update(Request $request, $id)
    {
        $property = Property::findOrFail($id);
        $property->update($request->all());
        return response()->json($property);
    }

    public function destroy($id)
    {
        $property = Property::findOrFail($id);
        $property->delete();
        return response()->json(['message' => 'Property deleted successfully']);
    }
}
