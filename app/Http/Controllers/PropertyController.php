<?php

namespace App\Http\Controllers;

use App\Models\Image;
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
            'primary_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:250',
            'gallery_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:250',
        ]);

        $property = Property::create($validated);

        $primaryImage = $request->file('primary_image');
        $filename = time() . '_' . str_replace(' ', '_', $primaryImage->getClientOriginalName());
        $primaryImage->move(public_path('bigos'), $filename);
        $primaryUrl = 'https://kevsbuilders.co.ke/bigos/' . $filename;
        Image::create(['property_id' => $property->id, 'image_url' => $primaryUrl, 'is_primary' => true]);

        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $image) {
                $filename = time() . '_' . str_replace(' ', '_', $image->getClientOriginalName());
                $image->move(public_path('bigos'), $filename);
                $galleryUrl = 'https://kevsbuilders.co.ke/bigos/' . $filename;
                Image::create(['property_id' => $property->id, 'image_url' => $galleryUrl, 'is_primary' => false]);
            }
        }

        return response()->json(['message' => 'Property created successfully', 'property' => $property], 201);
    }

    public function show($id)
    {
        $property = Property::with('images')->findOrFail($id);
        return response()->json($property);
    }

    public function index()
    {
        $properties = Property::with('images')->get();
        return response()->json($properties);
    }

    public function destroy($id)
    {
        $property = Property::findOrFail($id);
        $images = Image::where('property_id', $id)->get();
        foreach ($images as $image) {
            $path = str_replace('https://kevsbuilders.co.ke/bigos/', public_path('bigos/'), $image->image_url);
            if (file_exists($path)) {
                unlink($path);
            }
            $image->delete();
        }
        $property->delete();
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
            'primary_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:250',
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:250',
        ]);

        $property->update($validated);

        if ($request->hasFile('primary_image')) {
            $image = Image::where('property_id', $id)->where('is_primary', true)->first();
            if ($image) {
                $oldPath = str_replace('https://kevsbuilders.co.ke/bigos/', public_path('bigos/'), $image->image_url);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
                $image->delete();
            }
            $primaryImage = $request->file('primary_image');
            $filename = time() . '_' . str_replace(' ', '_', $primaryImage->getClientOriginalName());
            $primaryImage->move(public_path('bigos'), $filename);
            $primaryUrl = 'https://kevsbuilders.co.ke/bigos/' . $filename;
            Image::create(['property_id' => $id, 'image_url' => $primaryUrl, 'is_primary' => true]);
        }

        if ($request->hasFile('gallery_images')) {
            $oldImages = Image::where('property_id', $id)->where('is_primary', false)->get();
            foreach ($oldImages as $oldImage) {
                $oldPath = str_replace('https://kevsbuilders.co.ke/bigos/', public_path('bigos/'), $oldImage->image_url);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
                $oldImage->delete();
            }
            foreach ($request->file('gallery_images') as $image) {
                $filename = time() . '_' . str_replace(' ', '_', $image->getClientOriginalName());
                $image->move(public_path('bigos'), $filename);
                $galleryUrl = 'https://kevsbuilders.co.ke/bigos/' . $filename;
                Image::create(['property_id' => $id, 'image_url' => $galleryUrl, 'is_primary' => false]);
            }
        }

        return response()->json(['message' => 'Property updated successfully', 'property' => $property]);
    }
}
