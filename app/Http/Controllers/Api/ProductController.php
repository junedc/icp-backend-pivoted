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
    ) {}

    public function index(Request $request)
    {
        return Product::query()
            ->with('category:id,name')
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->string('search') . '%'))
            ->latest()
            ->paginate(10);
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
