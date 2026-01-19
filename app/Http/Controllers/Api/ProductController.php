<?php

namespace App\Http\Controllers\Api;

use App\Contracts\FileStorageServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly FileStorageServiceInterface $storage
    )
    {
    }

    public function index(Request $request)
    {

        $search = trim((string)$request->query('search', ''));
        $categoryId = $request->query('category_id');

        $query = Product::query()
            ->with('category'); // so category name is available in API response

        // Optional filter by category_id
        if (!empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        // Search by product fields OR category name
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Paginate (so your frontend pagination works)
        return $query->orderByDesc('id')->paginate(config('settings.records_per_page'));
    }

    public function store(StoreProductRequest $request)
    {

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storage->storeImage(
                $request->file('image'),
                'products'
            );
        }

        $product = Product::create($request->validated());
        return response()->json($product->load('category:id,name'), 201);
    }

    public function show(Product $product)
    {
        return $product->load('category:id,name');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {

        // Replace image if provided
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_path) {
                $this->storage->delete($product->image_path);
            }

            $data['image_path'] = $this->storage->storeImage(
                $request->file('image'),
                'products'
            );
        }


        $product->update($request->validated());
        return $product->load('category:id,name');
    }

    public function destroy(Product $product)
    {

        // Delete image
        if ($product->image_path) {
            $this->storage->delete($product->image_path);
        }

        $product->delete();
        return response()->noContent();
    }
}
