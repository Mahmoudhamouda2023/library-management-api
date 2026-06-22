<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FineResource;
use App\Models\Fine;
use Illuminate\Http\Request;

class FineController extends Controller
{
    public function index(Request $request)
    {
        $fines = Fine::query()
            ->with(['member', 'borrowing.book'])
            ->when($request->filled('member_id'), function ($query) use ($request) {
                $query->where('member_id', $request->input('member_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->latest()
            ->paginate(10);

        return FineResource::collection($fines);
    }

    public function show(Fine $fine)
    {
        $fine->load(['member', 'borrowing.book']);

        return response()->json([
            'data' => new FineResource($fine),
        ]);
    }

    public function pay(Fine $fine)
    {
        if ($fine->status === 'paid') {
            return response()->json([
                'message' => 'This fine has already been paid.',
            ], 422);
        }

        if ($fine->status === 'waived') {
            return response()->json([
                'message' => 'This fine has been waived and cannot be paid.',
            ], 422);
        }

        $fine->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $fine->load(['member', 'borrowing.book']);

        return response()->json([
            'message' => 'Fine paid successfully',
            'data' => new FineResource($fine),
        ]);
    }

    public function waive(Fine $fine)
    {
        if ($fine->status === 'paid') {
            return response()->json([
                'message' => 'Paid fines cannot be waived.',
            ], 422);
        }

        if ($fine->status === 'waived') {
            return response()->json([
                'message' => 'This fine has already been waived.',
            ], 422);
        }

        $fine->update([
            'status' => 'waived',
            'notes' => trim(($fine->notes ?? '') . "\nFine waived by library staff."),
        ]);

        $fine->load(['member', 'borrowing.book']);

        return response()->json([
            'message' => 'Fine waived successfully',
            'data' => new FineResource($fine),
        ]);
    }
}
