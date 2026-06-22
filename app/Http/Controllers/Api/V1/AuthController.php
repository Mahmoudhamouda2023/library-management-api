<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole('member');

        Member::create([
            'user_id' => $user->id,
            'membership_number' => 'MEM-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            'name' => $user->name,
            'email' => $user->email,
            'joined_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $token = $user->createToken('library-api-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => $this->userPayload($user->fresh(['member', 'author'])),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('library-api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $this->userPayload($user->load(['member', 'author'])),
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'data' => $this->userPayload($request->user()->load(['member', 'author'])),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:191',
            'bio' => 'nullable|string|max:5000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            $data['photo'] = $request->file('photo')->store('users/photos', 'public');
        }

        $user->update($data);

        if ($user->member) {
            $user->member->update([
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $data['phone'] ?? $user->phone,
            ]);
        }

        if ($user->author) {
            $authorData = [
                'name' => $user->name,
                'nationality' => $data['country'] ?? $user->country,
                'bio' => $data['bio'] ?? $user->bio,
            ];

            if (array_key_exists('photo', $data)) {
                $authorData['photo'] = $data['photo'];
            }

            $user->author->update($authorData);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $this->userPayload($user->fresh(['member', 'author'])),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'bio' => $user->bio,
            'photo' => $user->photo,
            'photo_url' => $user->photo ? asset('storage/' . $user->photo) : null,
            'avatar_url' => $user->photo ? asset('storage/' . $user->photo) : null,
            'profile_photo_url' => $user->photo ? asset('storage/' . $user->photo) : null,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'member' => $user->member ? [
                'id' => $user->member->id,
                'membership_number' => $user->member->membership_number,
                'name' => $user->member->name,
                'email' => $user->member->email,
                'phone' => $user->member->phone,
                'status' => $user->member->status,
            ] : null,
            'author' => $user->author ? [
                'id' => $user->author->id,
                'name' => $user->author->name,
                'nationality' => $user->author->nationality,
                'birth_date' => $user->author->birth_date,
                'bio' => $user->author->bio,
                'photo' => $user->author->photo,
                'photo_url' => $user->author->photo ? asset('storage/' . $user->author->photo) : null,
            ] : null,
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
