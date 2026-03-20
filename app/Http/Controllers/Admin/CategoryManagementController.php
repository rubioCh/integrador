<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryManagementController extends Controller
{
    public function store(Request $request): RedirectResponse
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

        return redirect()->route('admin.categories')->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'property_ids' => ['sometimes', 'array'],
            'property_ids.*' => ['integer', 'exists:properties,id'],
        ]);

        $category->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
        ]);

        if (array_key_exists('property_ids', $data)) {
            $category->properties()->sync($data['property_ids']);
        }

        return redirect()->route('admin.categories')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories')->with('success', 'Categoría eliminada correctamente.');
    }
}
