<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('properties')
            ->orderBy('name')
            ->paginate(25);

        return response()->json($categories);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('properties');

        return response()->json($category);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'property_ids' => ['sometimes', 'array'],
            'property_ids.*' => ['integer', 'exists:properties,id'],
        ]);

        $category = Category::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
        ]);

        if (isset($data['property_ids'])) {
            $category->properties()->sync($data['property_ids']);
        }

        return response()->json($category->load('properties'), 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:categories,slug,' . $category->id],
            'description' => ['sometimes', 'nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'property_ids' => ['sometimes', 'array'],
            'property_ids.*' => ['integer', 'exists:properties,id'],
        ]);

        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update(collect($data)->except('property_ids')->all());

        if (array_key_exists('property_ids', $data)) {
            $category->properties()->sync($data['property_ids']);
        }

        return response()->json($category->load('properties'));
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['success' => true]);
    }
}
