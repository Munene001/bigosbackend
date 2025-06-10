<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    public function store(Request $request)
    {
        // Start database transaction

        try {
            // Validate request data
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
                'construction_status' => 'required|in:complete,unfinished',
            ]);

            // Create property record
            $property = Property::create($validated);
            if (!$property) {
                throw new \Exception('Failed to create property record');
            }

            // Handle primary image upload
            if (!$request->hasFile('primary_image')) {
                throw new \Exception('Primary image is required');
            }

            $primaryPath = $this->storeImage($request->file('primary_image'), $property->id, true);
            if (!$primaryPath) {
                throw new \Exception('Failed to upload primary image');
            }

            // Handle gallery images if present
            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $image) {
                    try {
                        $this->storeImage($image, $property->id, false);
                    } catch (\Exception $e) {
                        Log::error('Gallery image upload failed: ' . $e->getMessage());
                        // Continue processing other images even if one fails
                        continue;
                    }
                }
            }

            // Commit transaction if everything succeeded

            return response()->json([
                'message' => 'Property created successfully',
                'property' => $property,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'error' => 'Validation error',
                'messages' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {

            Log::error('Property creation failed: ' . $e->getMessage());

            // Clean up any uploaded files if property creation failed
            if (isset($property)) {
                try {
                    $property->images()->delete();
                    $property->delete();
                } catch (\Exception $cleanupException) {
                    Log::error('Cleanup failed: ' . $cleanupException->getMessage());
                }
            }

            return response()->json([
                'error' => 'Property creation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function storeImage($imageFile, $propertyId, $isPrimary)
    {
        try {
            // Validate file
            if (!$imageFile->isValid()) {
                throw new \Exception('Invalid file uploaded');
            }

            // Create directory if it doesn't exist
            $directory = public_path('bigos');
            if (!file_exists($directory)) {
                if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new \Exception("Cannot create directory: {$directory}");
                }
            }

            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();

            // Move uploaded file
            if (!$imageFile->move($directory, $filename)) {
                throw new \Exception("Failed to move uploaded file to {$directory}");
            }

            // Create image record
            $image = Image::create([
                'property_id' => $propertyId,
                'image_url' => asset("bigos/{$filename}"), // Fixed string interpolation
                'is_primary' => $isPrimary,
            ]);

            if (!$image) {
                // Delete the file if record creation failed
                if (file_exists("{$directory}/{$filename}")) {
                    unlink("{$directory}/{$filename}");
                }
                throw new \Exception('Failed to create image record');
            }

            return $filename;

        } catch (\Exception $e) {
            Log::error('Image upload failed: ' . $e->getMessage());
            throw $e; // Re-throw for handling in the calling method
        }
    }

    public function show($id)
    {
        $property = Property::with('images')->findOrFail($id);
        return response()->json($property);
    }

    public function index(Request $request)
    {
        $listingType = $request->query('listing_type');
        $query = Property::with('images');
        if ($listingType) {
            $query->where('listing_type', $listingType);
        }
        $properties = $query->get();
        $count = $listingType
        ? Property::where('listing_type', $listingType)->count()
        : Property::count();
        return response()->json(['properties' => $properties, 'count' => $count, 'listing_type' => $listingType]);
    }

    public function destroy($id)
    {
        $property = Property::findOrFail($id);
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
            'listing_type' => 'required|in:for sale,for rent',
            'construction_status' => 'required| in:complete, unfinished',
            'primary_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:250',
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:250',
        ]);

        $property->update($validated);
        if ($request->hasFile('primary_image')) {
            $existingPrimary = $property->images()->where('is_primary', true)->first();
            if ($existingPrimary) {
                $this->deleteImageFile($existingPrimary->image_url);
                $existingPrimary->delete();
            }
            $primaryImage = $request->file('primary_image');
            $filename = time() . '_' . Str::random(10) . '.' . $primaryImage->getClientOriginalExtension();
            $primaryImage->move(public_path('bigos'), $filename);
            $primaryUrl = asset('bigos/{$filename}');
            $property->images()->create([
                'image_url' => $primaryUrl,
                'is_primary' => true,

            ]);
        }
        if ($request->hasFile('gallery_images')) {
            $existingGallery = $property->images()->where('is_primary', false)->get();
            foreach ($request->file('gallery_images') as $image) {
                $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                $image->getClientOriginalExtension();
                $image->move(public_path('bigos'), $filename);
                $galleryUrl = asset('bigos/{$filename}');
                $property->images()->create([
                    'image_url' => $galleryUrl,
                    'is_primary' => false,
                ]);
            }
        }
        return response()->json(['message' => 'Property updated successfully', 'property' => $property->load('images')]);

    }
    private function deleteImageFile($imageUrl)
    {
        $path = str_replace(asset(''), '', $imageUrl);
        $fullPath = public_path($path);
        if (file_exists($fullPath)) {

            unlink($fullPath);
        }
    }
    public function deleteImage($image_id)
    {
        try {
            $image = Image::findOrFail($image_id);
            $this->deleteImageFile($image->image_url);
            $image->delete();

            return response()->json([
                'message' => 'Image deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete image',
                'error' => $e->getMessage(),
            ], 500);
            //throw $th;
        }
    }
}
