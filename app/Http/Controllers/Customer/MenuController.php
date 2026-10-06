<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\BusinessHours;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->withCount(['menuItems' => fn($q) => $q->where('is_available', true)])
            ->get();
        $menuItems = MenuItem::where('is_available', true)->with('category')->get();
        $totalAvailableCount = MenuItem::where('is_available', true)->count();

        return view('customer.menu', array_merge(
            compact('categories', 'menuItems', 'totalAvailableCount'),
            $this->businessHoursContext()
        ));
    }

    public function show($id)
    {
        $menuItem = MenuItem::with('category')->findOrFail($id);

        return view('customer.menu-item', compact('menuItem'));
    }

    public function byCategory($categoryId)
    {
        $categories = Category::where('is_active', true)
            ->withCount(['menuItems' => fn($q) => $q->where('is_available', true)])
            ->get();
        $category = Category::findOrFail($categoryId);
        $menuItems = MenuItem::where('category_id', $categoryId)
            ->where('is_available', true)
            ->with('category')
            ->get();
        $totalAvailableCount = MenuItem::where('is_available', true)->count();

        return view('customer.menu', array_merge(
            compact('categories', 'menuItems', 'category', 'totalAvailableCount'),
            $this->businessHoursContext()
        ));
    }

    private function businessHoursContext(): array
    {
        return [
            'orderingOpen'      => BusinessHours::isMenuOrderingOpen(),
            'closesInMinutes'   => BusinessHours::minutesUntilClose(),
            'businessHoursText' => BusinessHours::hoursLabel(),
        ];
    }
}