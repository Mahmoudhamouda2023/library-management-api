<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = Member::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('membership_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->latest()
            ->paginate(10);

        return MemberResource::collection($members);
    }

    public function store(StoreMemberRequest $request)
    {
        $member = Member::create($request->validated());

        return response()->json([
            'message' => 'Member created successfully',
            'data' => new MemberResource($member),
        ], 201);
    }

    public function show(Member $member)
    {
        return response()->json([
            'data' => new MemberResource($member),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $member->update($request->validated());

        return response()->json([
            'message' => 'Member updated successfully',
            'data' => new MemberResource($member),
        ]);
    }

    public function destroy(Member $member)
    {
        $member->delete();

        return response()->json([
            'message' => 'Member deleted successfully',
        ]);
    }
}
