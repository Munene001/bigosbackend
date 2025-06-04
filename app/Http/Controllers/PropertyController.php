<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'location_url' => 'nullable|string|max:500',
            'unit_type' => 'required|string|max:50',
            'furnished' => 'required|in:Yes,No',
            'price_ksh' => 'required|numeric',
            'bedroom_count' => 'required|integer',
            'bathroom_count' => 'required|integer',
            'garage_count' => 'required|integer',
            'description' => 'required|string|max:1200',
            'features' => 'required|string|max:1200',
            'amenities' => 'required|string|max:1200',
        ]);
        $validated['created_at'] = now();
        $property = Property::create($validated);

        return response()->json(['message' => 'Property created successfully', 'property' => $property], 201);
    }
    public function show($id)
    {
        $property = Property::findOrFail($id);
        return response()->json($property);
    }

    public function index()
    {
        $properties = Property::all();
        return response()->json($properties);

    }

    public function destroy($id)
    {
        Property::findOrFail($id)->delete();
        return response()->json(['message' => 'Property deleted successfully']);
    }
    public function update(Request $request, $id)
    {
        $property = Property::findOrFail($id);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'location_url' => 'nullable|string|max:500',
            'unit_type' => 'required|string|max:50',
            'furnished' => 'required|in:Yes,No',
            'price_ksh' => 'required|numeric',
            'bedroom_count' => 'required|integer',
            'bathroom_count' => 'required|integer',
            'garage_count' => 'required|integer',
            'description' => 'required|string|max:1200',
            'features' => 'required|string|max:1200',
            'amenities' => 'required|string|max:1200',
        ]);
        $property->update($validated);
        return response()->json(['message' => 'Property updated succesfully', 'property' => $property]);
    }
    //
}
