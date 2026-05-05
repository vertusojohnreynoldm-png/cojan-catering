<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)->get();
        $menuItems = MenuItem::where('is_available', true)->with('category')->get();

        return view('customer.menu', compact('categories', 'menuItems'));
    }

    public function show($id)
    {
        $menuItem = MenuItem::with('category')->findOrFail($id);

        return view('customer.menu-item', compact('menuItem'));
    }

    public function byCategory($categoryId)
    {
        $categories = Category::where('is_active', true)->get();
        $category = Category::findOrFail($categoryId);
        $menuItems = MenuItem::where('category_id', $categoryId)
            ->where('is_available', true)
            ->with('category')
            ->get();

        return view('customer.menu', compact('categories', 'menuItems', 'category'));
    }
}