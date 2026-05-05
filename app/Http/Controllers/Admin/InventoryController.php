<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $inventory  = Inventory::with('menuItem.category')
            ->orderBy('quantity')
            ->get();

        $lowStock = Inventory::with('menuItem')
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->get();

        return view('admin.inventory', compact('inventory', 'lowStock'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'menu_item_id'        => 'required|exists:menu_items,id',
            'quantity'            => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:1',
            'unit'                => 'required|string|max:50',
        ]);

        Inventory::updateOrCreate(
            ['menu_item_id' => $request->menu_item_id],
            [
                'quantity'            => $request->quantity,
                'low_stock_threshold' => $request->low_stock_threshold,
                'unit'                => $request->unit,
            ]
        );

        return redirect()->route('admin.inventory')
            ->with('success', 'Inventory updated successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity'            => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:1',
            'unit'                => 'required|string|max:50',
        ]);

        $inventory = Inventory::findOrFail($id);
        $inventory->update([
            'quantity'            => $request->quantity,
            'low_stock_threshold' => $request->low_stock_threshold,
            'unit'                => $request->unit,
        ]);

        return redirect()->route('admin.inventory')
            ->with('success', 'Inventory updated successfully!');
    }

    public function addStock(Request $request, $id)
    {
        $request->validate([
            'add_quantity' => 'required|integer|min:1',
        ]);

        $inventory = Inventory::findOrFail($id);
        $inventory->increment('quantity', $request->add_quantity);

        return redirect()->route('admin.inventory')
            ->with('success', 'Stock added successfully!');
    }
}