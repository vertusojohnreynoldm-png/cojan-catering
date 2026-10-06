<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Utensil;
use Illuminate\Http\Request;

class UtensilController extends Controller
{
    public function index()
    {
        $utensils = Utensil::orderBy('quantity')->get();
        $lowStock = Utensil::whereColumn('quantity', '<=', 'low_stock_threshold')->get();

        return view('admin.utensils', compact('utensils', 'lowStock'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                => 'required|string|max:255',
            'quantity'            => 'required|integer|min:0',
            'unit'                => 'required|string|max:50',
            'low_stock_threshold' => 'required|integer|min:1',
        ]);

        Utensil::create([
            'name'                => $request->name,
            'quantity'            => $request->quantity,
            'unit'                => $request->unit,
            'low_stock_threshold' => $request->low_stock_threshold,
        ]);

        return redirect()->route('admin.utensils')->with('success', 'Utensil added successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity'            => 'required|integer|min:0',
            'unit'                => 'required|string|max:50',
            'low_stock_threshold' => 'required|integer|min:1',
        ]);

        $utensil = Utensil::findOrFail($id);
        $utensil->update([
            'quantity'            => $request->quantity,
            'unit'                => $request->unit,
            'low_stock_threshold' => $request->low_stock_threshold,
        ]);

        return redirect()->route('admin.utensils')->with('success', 'Utensil updated successfully!');
    }

    public function addStock(Request $request, $id)
    {
        $request->validate([
            'add_quantity' => 'required|integer|min:1',
        ]);

        $utensil = Utensil::findOrFail($id);
        $utensil->increment('quantity', $request->add_quantity);

        return redirect()->route('admin.utensils')->with('success', 'Stock added successfully!');
    }
}
