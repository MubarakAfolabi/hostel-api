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
                DB::raw("SUM(room_status = 'Allocated') as allocated"),
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
                    'allocated'    => (int) ($c->allocated ?? 0),
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
                'allocated'    => $list->where('room_status', 'Allocated')->count(),
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
            'allocated_rooms'  => $floors->sum('allocated'),
            'restricted_rooms' => $floors->sum('restricted'),
            'floors'           => $floors,
        ]);
    }

    // Floor-by-floor summary of one block
    public function floors(Request $request, $number)
    {
        $query = Block::where('block_number', $number);

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

        $rows = DB::table('room_status_view')
            ->where('block_id', $block->block_id)
            ->selectRaw("
            floor_number,
            COUNT(*) as total_rooms,
            SUM(room_status = 'Available')  as available_rooms,
            SUM(room_status = 'Allocated') as allocated_rooms,
            SUM(room_status = 'Restricted') as restricted_rooms,
            SUM(room_type = 'S') as small_rooms,
            SUM(room_type = 'L') as large_rooms
        ")
            ->groupBy('floor_number')
            ->get()
            ->keyBy('floor_number');

        // Every floor from 1 to total_floors, even if it has no rooms yet
        $floors = collect(range(1, $block->total_floors))->map(function ($n) use ($rows) {
            $r = $rows->get($n);

            return [
                'floor_number'     => $n,
                'total_rooms'      => (int) ($r->total_rooms ?? 0),
                'available_rooms'  => (int) ($r->available_rooms ?? 0),
                'allocated_rooms'  => (int) ($r->allocated_rooms ?? 0),
                'restricted_rooms' => (int) ($r->restricted_rooms ?? 0),
                'small_rooms'      => (int) ($r->small_rooms ?? 0),
                'large_rooms'      => (int) ($r->large_rooms ?? 0),
            ];
        });

        return response()->json([
            'block_id'         => $block->block_id,
            'block_number'     => $block->block_number,
            'gender_type'      => $block->gender_type,
            'total_floors'     => $block->total_floors,
            'total_rooms'      => $floors->sum('total_rooms'),
            'available_rooms'  => $floors->sum('available_rooms'),
            'allocated_rooms'  => $floors->sum('allocated_rooms'),
            'restricted_rooms' => $floors->sum('restricted_rooms'),
            'small_rooms'      => $floors->sum('small_rooms'),
            'large_rooms'      => $floors->sum('large_rooms'),
            'floors'           => $floors,
        ]);
    }
}
