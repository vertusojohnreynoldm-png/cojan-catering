<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CateringPackage;
use App\Models\MenuItem;
use App\Models\Utensil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PackageController extends Controller
{
    public function index()
    {
        $packages  = CateringPackage::with('menuItems', 'utensils')->orderBy('name')->get();
        $menuItems = MenuItem::orderBy('name')->get();
        $utensils  = Utensil::orderBy('name')->get();

        return view('admin.packages', compact('packages', 'menuItems', 'utensils'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                    => 'required|string|max:255',
            'pax'                     => 'required|integer|min:1',
            'price'                   => 'required|numeric|min:0',
            'description'             => 'nullable|string',
            'image'                   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'menu_item_quantities'    => 'array',
            'menu_item_quantities.*'  => 'nullable|integer|min:0',
            'utensil_quantities'      => 'array',
            'utensil_quantities.*'    => 'nullable|integer|min:0',
        ]);

        $menuItemQuantities = $this->positiveQuantities($request->input('menu_item_quantities', []));
        $utensilQuantities  = $this->positiveQuantities($request->input('utensil_quantities', []));

        if (empty($menuItemQuantities)) {
            return back()->withInput()->withErrors(['menu_item_quantities' => 'Select at least one menu item for this package.']);
        }

        if (empty($utensilQuantities)) {
            return back()->withInput()->withErrors(['utensil_quantities' => 'Select at least one utensil for this package.']);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('packages', 'public');
        }

        $package = CateringPackage::create([
            'name'         => $request->name,
            'pax'          => $request->pax,
            'price'        => $request->price,
            'description'  => $request->description,
            'image'        => $imagePath,
            'is_available' => $request->has('is_available'),
        ]);

        $package->menuItems()->sync($this->pivotSync($menuItemQuantities, 'quantity'));
        $package->utensils()->sync($this->pivotSync($utensilQuantities, 'quantity_needed'));

        return redirect()->route('admin.packages')->with('success', 'Package added successfully!');
    }

    public function edit($id)
    {
        $package   = CateringPackage::with('menuItems', 'utensils')->findOrFail($id);
        $menuItems = MenuItem::orderBy('name')->get();
        $utensils  = Utensil::orderBy('name')->get();

        $includedMenuItems = $package->menuItems->pluck('pivot.quantity', 'id');
        $includedUtensils  = $package->utensils->pluck('pivot.quantity_needed', 'id');

        return view('admin.package-edit', compact(
            'package', 'menuItems', 'utensils', 'includedMenuItems', 'includedUtensils'
        ));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                    => 'required|string|max:255',
            'pax'                     => 'required|integer|min:1',
            'price'                   => 'required|numeric|min:0',
            'description'             => 'nullable|string',
            'image'                   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'menu_item_quantities'    => 'array',
            'menu_item_quantities.*'  => 'nullable|integer|min:0',
            'utensil_quantities'      => 'array',
            'utensil_quantities.*'    => 'nullable|integer|min:0',
        ]);

        $menuItemQuantities = $this->positiveQuantities($request->input('menu_item_quantities', []));
        $utensilQuantities  = $this->positiveQuantities($request->input('utensil_quantities', []));

        if (empty($menuItemQuantities)) {
            return back()->withInput()->withErrors(['menu_item_quantities' => 'Select at least one menu item for this package.']);
        }

        if (empty($utensilQuantities)) {
            return back()->withInput()->withErrors(['utensil_quantities' => 'Select at least one utensil for this package.']);
        }

        $package   = CateringPackage::findOrFail($id);
        $imagePath = $package->image;

        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('packages', 'public');
        }

        $package->update([
            'name'         => $request->name,
            'pax'          => $request->pax,
            'price'        => $request->price,
            'description'  => $request->description,
            'image'        => $imagePath,
            'is_available' => $request->has('is_available'),
        ]);

        $package->menuItems()->sync($this->pivotSync($menuItemQuantities, 'quantity'));
        $package->utensils()->sync($this->pivotSync($utensilQuantities, 'quantity_needed'));

        return redirect()->route('admin.packages')->with('success', 'Package updated successfully!');
    }

    public function destroy($id)
    {
        $package = CateringPackage::findOrFail($id);

        if ($package->image) {
            Storage::disk('public')->delete($package->image);
        }

        // Soft-delete (mirrors MenuItem's pattern) so past orders referencing
        // this package keep working, and hide it from customers immediately.
        $package->update(['is_available' => false]);
        $package->delete();

        return redirect()->route('admin.packages')->with('success', 'Package deleted successfully!');
    }

    /**
     * Strip zero/blank entries from a {id: quantity} submission — a quantity
     * of 0 means "not included in this package".
     */
    private function positiveQuantities(array $quantities): array
    {
        return array_filter($quantities, fn($qty) => (int) $qty > 0);
    }

    /**
     * Reshape a flat {id: quantity} map into the [id => ['column' => quantity]]
     * form belongsToMany::sync() needs to populate an extra pivot column.
     */
    private function pivotSync(array $quantities, string $pivotColumn): array
    {
        $sync = [];

        foreach ($quantities as $id => $qty) {
            $sync[$id] = [$pivotColumn => (int) $qty];
        }

        return $sync;
    }
}
