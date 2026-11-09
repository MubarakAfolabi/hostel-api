<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Block;
use App\Models\Room;

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

    // One room with its bedspaces and the students in them
    public function show(Request $request, $block, $room)
    {
        $blocks = Block::where('block_number', $block)
            ->when($request->filled('hostel_id'), fn($q) => $q->where('hostel_id', $request->hostel_id))
            ->get();

        if ($blocks->isEmpty()) {
            return response()->json(['message' => 'Block not found'], 404);
        }

        if ($blocks->count() > 1) {
            return response()->json([
                'message' => 'More than one block has this number. Add ?hostel_id=...',
            ], 409);
        }

        $blockModel = $blocks->first();

        $roomModel = Room::where('block_id', $blockModel->block_id)
            ->where('room_number', $room)
            ->first();

        if (!$roomModel) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $beds = DB::table('beds')
            ->leftJoin('students', 'students.student_id', '=', 'beds.student_id')
            ->where('beds.room_id', $roomModel->room_id)
            ->orderBy('beds.bed_label')
            ->get([
                'beds.bed_id',
                'beds.bed_label',
                'beds.status',
                'beds.student_id',
                'students.first_name',
                'students.last_name',
                'students.department',
                'students.level',
            ]);

        return response()->json([
            'room_id'        => $roomModel->room_id,
            'room_number'    => $roomModel->room_number,
            'block_number'   => $blockModel->block_number,
            'gender_type'    => $blockModel->gender_type,
            'floor_number'   => $roomModel->floor_number,
            'room_type'      => $roomModel->room_type,
            'room_status'    => $roomModel->status,
            'capacity'       => $roomModel->capacity,
            'allocated_beds' => $beds->where('status', 'Allocated')->count(),
            'available_beds' => $beds->where('status', 'Available')->count(),
            'beds'           => $beds->map(fn($b) => [
                'bed_id'    => $b->bed_id,
                'bed_label' => $b->bed_label,
                'status'    => $b->status,
                'student'   => $b->student_id ? [
                    'matric_number' => $b->student_id,
                    'name'          => trim($b->first_name . ' ' . $b->last_name),
                    'department'    => $b->department,
                    'level'         => $b->level,
                ] : null,
            ])->values(),
        ]);
    }
}
