<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Rules\PhilippineMobileNumber;
use App\Rules\ValidPersonName;
use App\Services\ServiceArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $cateringAreas = ServiceArea::cateringPsgcForDropdowns();
        // Profile's address isn't gated by service area (a customer can
        // live anywhere and still have an account, or book catering that
        // only ever ships menu-order-ineligible areas) — but the boat-only
        // barangays ARE still real places someone could live, so the
        // dropdown here uses the full, unfiltered PSGC list, not the
        // catering-eligible one.
        $allAreas = [];
        foreach (ServiceArea::provinces() as $province) {
            foreach (ServiceArea::municipalities($province) as $municipality) {
                $allAreas[$province][$municipality] = ServiceArea::barangays($province, $municipality);
            }
        }

        return view('customer.profile', compact('user', 'allAreas'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'             => ['required', 'string', 'max:255', new ValidPersonName],
            'email'            => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'            => ['nullable', 'string', 'max:20', new PhilippineMobileNumber],
            'province'         => ['nullable', 'string', Rule::in(ServiceArea::provinces())],
            'municipality'     => ['required_with:province', 'nullable', 'string'],
            'barangay'         => ['required_with:province', 'nullable', 'string'],
            'street'           => ['required_with:province', 'nullable', 'string', 'min:5'],
            'landmark'         => ['nullable', 'string', 'max:255'],
            'current_password' => 'required_with:password|nullable|current_password',
            'password'         => 'nullable|string|min:8|confirmed',
        ], [
            'street.min' => 'Please enter a more complete street/purok/house number.',
        ]);

        if ($request->filled('province')) {
            if (!in_array($request->municipality, ServiceArea::municipalities($request->province), true)) {
                return back()->withInput()->withErrors(['municipality' => 'Please choose a valid municipality.']);
            }
            if (!in_array($request->barangay, ServiceArea::barangays($request->province, $request->municipality), true)) {
                return back()->withInput()->withErrors(['barangay' => 'Please choose a valid barangay.']);
            }
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->filled('phone') ? PhilippineMobileNumber::normalize($request->phone) : $request->phone;

        if ($request->filled('province')) {
            $user->province = $request->province;
            $user->municipality = $request->municipality;
            $user->barangay = $request->barangay;
            $user->street = $request->street;
            $user->landmark = $request->landmark;
            $user->address = ServiceArea::formatAddress(
                $request->street, $request->barangay, $request->municipality, $request->province, $request->landmark
            );
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('customer.profile.edit')
            ->with('success', 'Profile updated successfully!');
    }
}
