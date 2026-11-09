<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Support\Mobile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('customerProfile', 'addresses');
        return $this->ok($user);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if ($request->filled('alternate_mobile')) $request->merge(['alternate_mobile' => Mobile::normalize($request->alternate_mobile)]);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'alternate_mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500', 'area' => 'nullable|string|max:120', 'landmark' => 'nullable|string|max:150',
            'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $user->update(collect($data)->only('name', 'email')->all());
        $user->customerProfile()->updateOrCreate(['user_id' => $user->id], collect($data)->except('name', 'email')->all());
        return $this->ok($user->fresh()->load('customerProfile', 'addresses'), 'Profile updated');
    }

    public function addresses(Request $request)
    {
        return $this->ok($request->user()->addresses()->orderByDesc('is_default')->get());
    }

    public function storeAddress(Request $request)
    {
        $data = $this->validateAddress($request);
        $user = $request->user();
        if (! empty($data['is_default']) || ! $user->addresses()->exists()) {
            $user->addresses()->update(['is_default' => false]);
            $data['is_default'] = true;
        }
        return $this->created($user->addresses()->create($data), 'Address saved');
    }

    public function updateAddress(Request $request, CustomerAddress $address)
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $data = $this->validateAddress($request);
        if (! empty($data['is_default'])) $request->user()->addresses()->update(['is_default' => false]);
        $address->update($data);
        return $this->ok($address, 'Address updated');
    }

    public function destroyAddress(Request $request, CustomerAddress $address)
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->delete();
        return $this->ok(null, 'Address removed');
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => 'required|string|max:60', 'address' => 'required|string|max:500',
            'area' => 'nullable|string|max:120', 'landmark' => 'nullable|string|max:150',
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
        ]);
    }
}
