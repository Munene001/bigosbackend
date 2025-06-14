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

        try {

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

            $property = Property::create($validated);
            if (!$property) {
                throw new \Exception('Failed to create property record');
            }

            if (!$request->hasFile('primary_image')) {
                throw new \Exception('Primary image is required');
            }

            $primaryPath = $this->storeImage($request->file('primary_image'), $property->id, true);
            if (!$primaryPath) {
                throw new \Exception('Failed to upload primary image');
            }

            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $image) {
                    try {
                        $this->storeImage($image, $property->id, false);
                    } catch (\Exception $e) {
                        Log::error('Gallery image upload failed: ' . $e->getMessage());
                        continue;
                    }
                }
            }

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

            if (!$imageFile->isValid()) {
                throw new \Exception('Invalid file uploaded');
            }

            $directory = public_path('bigos');
            if (!file_exists($directory)) {
                if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new \Exception("Cannot create directory: {$directory}");
                }
            }

            $filename = time() . '_' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();

            if (!$imageFile->move($directory, $filename)) {
                throw new \Exception("Failed to move uploaded file to {$directory}");
            }

            $image = Image::create([
                'property_id' => $propertyId,
                'image_url' => asset("bigos/{$filename}"), // Fixed string interpolation
                'is_primary' => $isPrimary,
            ]);

            if (!$image) {

                if (file_exists("{$directory}/{$filename}")) {
                    unlink("{$directory}/{$filename}");
                }
                throw new \Exception('Failed to create image record');
            }

            return $filename;

        } catch (\Exception $e) {
            Log::error('Image upload failed: ' . $e->getMessage());
            throw $e;
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
    public function filter(Request $request)
    {
        try {
            // 1. Get all filter values with proper null checking
            $filters = [
                'bedrooms' => $request->filled('bedrooms') ? (int) $request->input('bedrooms') : null,
                'bathrooms' => $request->filled('bathrooms') ? (int) $request->input('bathrooms') : null,
                'location' => $request->filled('location') ? $request->input('location') : null,
                'min_price' => (float) $request->input('min_price', 0),
                'max_price' => (float) $request->input('max_price', 1000000000),
                'furnished' => $request->filled('furnished') ? $request->input('furnished') : null,
                'construction_status' => $request->filled('construction_status') ? $request->input('construction_status') : null,
                'page' => (int) $request->input('page', 1),
                'per_page' => (int) $request->input('per_page', 10),
            ];

            // 2. Start the query
            $query = Property::with('images');

            // 3. Apply each filter if it has a value
            if (!is_null($filters['bedrooms'])) {
                $query->where('bedroom_count', $filters['bedrooms']);
            }

            if (!is_null($filters['bathrooms'])) {
                $query->where('bathroom_count', $filters['bathrooms']);
            }

            if (!is_null($filters['location'])) {
                $query->where('location', 'LIKE', '%' . $filters['location'] . '%');
            }

            $query->whereBetween('price_ksh', [
                $filters['min_price'],
                $filters['max_price'],
            ]);

            if (!is_null($filters['furnished']) && in_array($filters['furnished'], ['Yes', 'No'])) {
                $query->where('furnished', $filters['furnished']);
            }

            if (!is_null($filters['construction_status']) && in_array($filters['construction_status'], ['complete', 'unfinished'])) {
                $query->where('construction_status', $filters['construction_status']);
            }

            // 4. Get paginated results
            $results = $query->paginate(
                $filters['per_page'],
                ['*'],
                'page',
                $filters['page']
            );

            // 5. Return the response with applied filters
            return response()->json([
                'success' => true,
                'properties' => $results->items(),
                'total' => $results->total(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'applied_filters' => array_filter($filters, function ($value) {
                    return !is_null($value);
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Filtering failed',
                'error' => $e->getMessage(),
            ], status: 500);
        }
    }

}
