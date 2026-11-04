<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    use AuthorizesApi;

    /**
     * List properties with filtering.
     */
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

        // Exclude soft-deleted
        $query->whereNull('deleted_at');

        return response()->json($query->latest()->get());
    }

    /**
     * Show a single property.
     */
    public function show($id)
    {
        $property = Property::whereNull('deleted_at')->findOrFail($id);

        // Private financials stay hidden unless the caller can see unmasked records.
        $user = Auth::guard('sanctum')->user();
        if ($user && $user->hasPermission('view_sensitive_cnic')) {
            $property->makeVisible(['purchasePricePKR', 'sellingPricePKR', 'internalNotes']);
        }

        return response()->json($property);
    }

    /**
     * Store a new property with validation.
     */
    public function store(Request $request)
    {
        $this->requireAny($request, ['create_properties', 'edit_properties']);

        $data = $request->validate([
            'id' => 'required|string|unique:properties,id',
            'title' => 'required|string|max:255',
            'pricePKR' => 'required|numeric|min:0',
            'phase' => 'required|string|max:100',
            'sector' => 'required|string|max:100',
            'sizeSqYards' => 'required|numeric|min:0',
            'plotNumber' => 'nullable|string|max:50',
            'fileNumber' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:255',
            'block' => 'nullable|string|max:50',
            'roadWidthFeet' => 'nullable|numeric|min:0',
            'type' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'isFeatured' => 'nullable|boolean',
            'possessionReady' => 'nullable|boolean',
            'mdaApproved' => 'nullable|boolean',
            'westOpen' => 'nullable|boolean',
            'cornerPlot' => 'nullable|boolean',
            'parkFacing' => 'nullable|boolean',
            'mainBoulevard' => 'nullable|boolean',
            'images' => 'nullable|array',
            'features' => 'nullable|array',
            'contactPerson' => 'nullable|string|max:255',
            'contactPhone' => 'nullable|string|max:20',
            'assignedDealerId' => 'nullable|string|exists:dealers,id',
        ]);

        $property = Property::create($data);

        // Audit log
        $this->writeAudit($request, 'property_created', $property->id, "Property {$property->title} created");

        return response()->json($property, 201);
    }

    /**
     * Update a property with validation.
     */
    public function update(Request $request, $id)
    {
        $this->requireAny($request, ['edit_properties']);

        $property = Property::whereNull('deleted_at')->findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'pricePKR' => 'sometimes|required|numeric|min:0',
            'phase' => 'sometimes|required|string|max:100',
            'sector' => 'sometimes|required|string|max:100',
            'sizeSqYards' => 'sometimes|required|numeric|min:0',
            'plotNumber' => 'nullable|string|max:50',
            'fileNumber' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:255',
            'block' => 'nullable|string|max:50',
            'roadWidthFeet' => 'nullable|numeric|min:0',
            'type' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'isFeatured' => 'nullable|boolean',
            'possessionReady' => 'nullable|boolean',
            'mdaApproved' => 'nullable|boolean',
            'westOpen' => 'nullable|boolean',
            'cornerPlot' => 'nullable|boolean',
            'parkFacing' => 'nullable|boolean',
            'mainBoulevard' => 'nullable|boolean',
            'images' => 'nullable|array',
            'features' => 'nullable|array',
            'contactPerson' => 'nullable|string|max:255',
            'contactPhone' => 'nullable|string|max:20',
            'assignedDealerId' => 'nullable|string|exists:dealers,id',
        ]);

        $property->update($data);

        // Audit log
        $this->writeAudit($request, 'property_updated', $property->id, "Property {$property->title} updated");

        return response()->json($property);
    }

    /**
     * Soft delete a property.
     */
    public function destroy(Request $request, $id)
    {
        $this->requireAny($request, ['delete_properties']);

        $property = Property::whereNull('deleted_at')->findOrFail($id);
        $property->delete();

        $this->writeAudit($request, 'property_deleted', $property->id, "Property {$property->title} soft-deleted");

        return response()->json(['message' => 'Property deleted successfully']);
    }
}