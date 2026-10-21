<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Block;
use Illuminate\Support\Facades\DB;

class BlockController extends Controller
{
    // List of all blocks with a quick room summary
    public function index()
    {
        $counts = DB::table('room_status_view')
            ->select(
                'block_id',
                DB::raw('COUNT(*) as total_rooms'),
                DB::raw("SUM(room_status = 'Available') as available"),
                DB::raw("SUM(room_status = 'Occupied') as occupied"),
                DB::raw("SUM(room_status = 'Restricted') as restricted")
            )
            ->groupBy('block_id')
            ->get()
            ->keyBy('block_id');

        return Block::with('hostel:hostel_id,hostel_name')
            ->orderBy('block_number')
            ->get()
            ->map(function ($block) use ($counts) {
                $c = $counts->get($block->block_id);

                return [
                    'block_id'     => $block->block_id,
                    'hostel_name'  => $block->hostel?->hostel_name,
                    'block_number' => $block->block_number,
                    'gender_type'  => $block->gender_type,
                    'total_floors' => $block->total_floors,
                    'total_rooms'  => (int) ($c->total_rooms ?? 0),
                    'available'    => (int) ($c->available ?? 0),
                    'occupied'     => (int) ($c->occupied ?? 0),
                    'restricted'   => (int) ($c->restricted ?? 0),
                ];
            });
    }

    // Details of one block
    public function show(Request $request, $number)
    {
        $query = Block::with('hostel:hostel_id,hostel_name')
            ->where('block_number', $number);

        if ($request->filled('hostel_id')) {
            $query->where('hostel_id', $request->hostel_id);
        }

        $blocks = $query->get();

        if ($blocks->isEmpty()) {
            return response()->json(['message' => 'Block not found'], 404);
        }

        if ($blocks->count() > 1) {
            return response()->json([
                'message' => 'More than one block has this number. Add ?hostel_id=...',
            ], 409);
        }

        $block = $blocks->first();
        $id = $block->block_id;

        $rooms = DB::table('room_status_view')
            ->where('block_id', $id)
            ->orderBy('floor_number')
            ->orderBy('room_number')
            ->get()
            ->groupBy('floor_number');

        // Every floor from 1 to total_floors, even if it has no rooms yet
        $floorNumbers = collect(range(1, $block->total_floors))
            ->merge($rooms->keys())
            ->unique()
            ->sort()
            ->values();

        $floors = $floorNumbers->map(function ($n) use ($rooms) {
            $list = $rooms->get($n, collect());

            return [
                'floor_number' => $n,
                'total_rooms'  => $list->count(),
                'available'    => $list->where('room_status', 'Available')->count(),
                'occupied'     => $list->where('room_status', 'Occupied')->count(),
                'restricted'   => $list->where('room_status', 'Restricted')->count(),
                'rooms'        => $list->map(fn($r) => [
                    'room_id'         => $r->room_id,
                    'room_number'     => $r->room_number,
                    'room_type'       => $r->room_type,
                    'capacity'        => $r->capacity,
                    'room_status'     => $r->room_status,
                    'available_beds'  => (int) $r->available_beds,
                    'allocated_beds'  => (int) $r->allocated_beds,
                    'restricted_beds' => (int) $r->restricted_beds,
                ])->values(),
            ];
        });

        return response()->json([
            'block_id'         => $block->block_id,
            'hostel_name'      => $block->hostel?->hostel_name,
            'block_number'     => $block->block_number,
            'gender_type'      => $block->gender_type,
            'total_floors'     => $block->total_floors,
            'total_rooms'      => $rooms->flatten()->count(),
            'available_rooms'  => $floors->sum('available'),
            'occupied_rooms'   => $floors->sum('occupied'),
            'restricted_rooms' => $floors->sum('restricted'),
            'floors'           => $floors,
        ]);
    }
}
