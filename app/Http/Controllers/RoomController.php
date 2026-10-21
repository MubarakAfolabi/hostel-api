<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    private const STATUS_MAP = [
        'utility'    => 'Utility',
        'available'  => 'Available',
        'allocated'  => 'Allocated',
        'restricted' => 'Restricted',
    ];

    public function index(Request $request)
    {
        $request->validate([
            'status'   => 'nullable|string',
            'search'   => 'nullable|string|max:10',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table('room_status_view as r')
            ->join('blocks as b', 'b.block_id', '=', 'r.block_id')
            ->select(
                'r.room_id',
                'r.room_number',
                'b.block_number',
                'b.hostel_id',
                'b.gender_type',
                'r.floor_number',
                'r.room_type',
                'r.capacity',
                'r.allocated_beds',
                'r.available_beds',
                'r.room_status'
            );

        // Block number ("all" or empty means no filter)
        if (is_numeric($request->input('block'))) {
            $query->where('b.block_number', (int) $request->input('block'));
        }

        // Only needed if two hostels share a block number
        if ($request->filled('hostel_id')) {
            $query->where('b.hostel_id', $request->hostel_id);
        }

        // Floor ("all" or empty means no filter)
        if (is_numeric($request->input('floor'))) {
            $query->where('r.floor_number', (int) $request->input('floor'));
        }

        // Status ("all" or empty means no filter)
        $status = strtolower((string) $request->input('status', 'all'));
        if ($status !== '' && $status !== 'all') {
            if (!isset(self::STATUS_MAP[$status])) {
                return response()->json(['message' => 'Invalid status'], 422);
            }
            $query->where('r.room_status', self::STATUS_MAP[$status]);
        }

        // Search by room number (starts with, digits only)
        if ($request->filled('search')) {
            $digits = preg_replace('/\D/', '', $request->search);
            if ($digits !== '') {
                $query->where('r.room_number', 'like', $digits . '%');
            }
        }

        return $query
            ->orderBy('b.block_number')
            ->orderBy('r.floor_number')
            ->orderBy('r.room_number')
            ->paginate((int) $request->input('per_page', 20))
            ->through(fn($r) => [
                'room_id'        => $r->room_id,
                'room_number'    => $r->room_number,
                'block_number'   => $r->block_number,
                'floor_number'   => $r->floor_number,
                'gender_type'    => $r->gender_type,
                'room_type'      => $r->room_type,
                'capacity'       => $r->capacity,
                'allocated_beds' => (int) $r->allocated_beds,
                'available_beds' => (int) $r->available_beds,
                'room_status'    => $r->room_status,
            ]);
    }
}
