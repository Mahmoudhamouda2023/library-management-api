<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublisherRequestResource;
use App\Models\Author;
use App\Models\PublisherRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublisherRequestController extends Controller
{
    public function myRequest(Request $request)
    {
        $publisherRequest = PublisherRequest::query()
            ->with(['user', 'reviewer'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (!$publisherRequest) {
            return response()->json([
                'message' => 'No publisher request found.',
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => new PublisherRequestResource($publisherRequest),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin') || $user->hasRole('librarian')) {
            return response()->json([
                'message' => 'Staff accounts cannot request a publisher account.',
            ], 422);
        }

        $hasPendingRequest = PublisherRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingRequest) {
            return response()->json([
                'message' => 'You already have a pending publisher request.',
            ], 422);
        }

        if ($user->hasRole('publisher')) {
            return response()->json([
                'message' => 'You are already a publisher.',
            ], 422);
        }

        $data = $request->validate([
            'display_name' => 'required|string|max:191',
            'nationality' => 'nullable|string|max:191',
            'birth_date' => 'nullable|date',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('authors/photos', 'public');
        }

        $publisherRequest = PublisherRequest::create([
            'user_id' => $user->id,
            'display_name' => $data['display_name'],
            'nationality' => $data['nationality'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'bio' => $data['bio'] ?? null,
            'photo' => $data['photo'] ?? null,
            'status' => 'pending',
        ]);

        $publisherRequest->load(['user', 'reviewer']);

        return response()->json([
            'message' => 'Publisher request submitted successfully',
            'data' => new PublisherRequestResource($publisherRequest),
        ], 201);
    }

    public function index(Request $request)
    {
        $publisherRequests = PublisherRequest::query()
            ->with(['user', 'reviewer'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->latest()
            ->paginate(10);

        return PublisherRequestResource::collection($publisherRequests);
    }

    public function show(PublisherRequest $publisherRequest)
    {
        $publisherRequest->load(['user', 'reviewer']);

        return response()->json([
            'data' => new PublisherRequestResource($publisherRequest),
        ]);
    }

    public function approve(Request $request, PublisherRequest $publisherRequest)
    {
        if ($publisherRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        DB::transaction(function () use ($request, $publisherRequest) {
            $publisherRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            // بعد الموافقة يصبح الحساب ناشرًا فقط، ولا يبقى حساب قارئ عادي حتى لا تظهر له عمليات الحجز وطلب الناشر.
            $publisherRequest->user->syncRoles(['publisher']);

            Author::updateOrCreate(
                ['user_id' => $publisherRequest->user_id],
                [
                    'name' => $publisherRequest->display_name,
                    'nationality' => $publisherRequest->nationality,
                    'birth_date' => $publisherRequest->birth_date,
                    'bio' => $publisherRequest->bio,
                    'photo' => $publisherRequest->photo ?: $publisherRequest->user->photo,
                ]
            );
        });

        $publisherRequest->refresh();
        $publisherRequest->load(['user', 'reviewer']);

        return response()->json([
            'message' => 'Publisher request approved successfully',
            'data' => new PublisherRequestResource($publisherRequest),
        ]);
    }

    public function reject(Request $request, PublisherRequest $publisherRequest)
    {
        if ($publisherRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending requests can be rejected.',
            ], 422);
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $publisherRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        $publisherRequest->load(['user', 'reviewer']);

        return response()->json([
            'message' => 'Publisher request rejected successfully',
            'data' => new PublisherRequestResource($publisherRequest),
        ]);
    }
}
