<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(): JsonResponse
    {
        // HAPUS manual validation - middleware sudah handle
        try {
            $products = Product::all()->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'image_url' => $product->image ? asset('storage/' . $product->image) : null,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // HAPUS manual validation - middleware sudah handle
        try {
            $validatedData = $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
            ]);

            // Upload gambar (wajib ada)
            $path = $request->file('image')->store('products', 'public');
            $validatedData['image'] = $path;

            $product = Product::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(string $id): JsonResponse
    {
        // HAPUS manual validation - middleware sudah handle
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'image_url' => $product->image ? asset('storage/' . $product->image) : null,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // HAPUS manual validation - middleware sudah handle
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        try {
            $validatedData = $request->validate([
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'price' => 'sometimes|required|numeric|min:0',
                'stock' => 'sometimes|required|integer|min:0',
            ]);

            // Hanya update image jika ada file baru
            if ($request->hasFile('image')) {
                // Hapus file lama
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }

                // Upload gambar baru
                $path = $request->file('image')->store('products', 'public');
                $validatedData['image'] = $path;
            }

            $product->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        // HAPUS manual validation - middleware sudah handle
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        // Hapus file gambar
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }
}
