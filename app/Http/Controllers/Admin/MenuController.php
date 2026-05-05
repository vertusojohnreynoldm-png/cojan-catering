<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    public function index()
    {
        $menuItems  = MenuItem::with('category')->orderBy('category_id')->get();
        $categories = Category::all();

        return view('admin.menu', compact('menuItems', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('menu', 'public');
        }

        MenuItem::create([
            'category_id'  => $request->category_id,
            'name'         => $request->name,
            'description'  => $request->description,
            'price'        => $request->price,
            'image'        => $imagePath,
            'is_available' => $request->has('is_available'),
        ]);

        return redirect()->route('admin.menu')->with('success', 'Menu item added successfully!');
    }

    public function edit($id)
    {
        $menuItem   = MenuItem::findOrFail($id);
        $categories = Category::all();

        return view('admin.menu-edit', compact('menuItem', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $menuItem  = MenuItem::findOrFail($id);
        $imagePath = $menuItem->image;

        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('menu', 'public');
        }

        $menuItem->update([
            'category_id'  => $request->category_id,
            'name'         => $request->name,
            'description'  => $request->description,
            'price'        => $request->price,
            'image'        => $imagePath,
            'is_available' => $request->has('is_available'),
        ]);

        return redirect()->route('admin.menu')->with('success', 'Menu item updated successfully!');
    }

    public function destroy($id)
    {
        $menuItem = MenuItem::findOrFail($id);

        if ($menuItem->image) {
            Storage::disk('public')->delete($menuItem->image);
        }

        $menuItem->delete();

        return redirect()->route('admin.menu')->with('success', 'Menu item deleted successfully!');
    }
}