<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function roomStats()
    {
        $s = DB::table('room_status_view')
            ->selectRaw("
                COUNT(*) as total_rooms,
                COALESCE(SUM(room_status = 'Available'), 0)  as available_rooms,
                COALESCE(SUM(room_status = 'Allocated'), 0)  as allocated_rooms,
                COALESCE(SUM(room_status = 'Restricted'), 0) as restricted_rooms
            ")
            ->first();

        return response()->json([
            'total_rooms'      => (int) $s->total_rooms,
            'available_rooms'  => (int) $s->available_rooms,
            'allocated_rooms'  => (int) $s->allocated_rooms,
            'restricted_rooms' => (int) $s->restricted_rooms,
        ]);
    }
}
