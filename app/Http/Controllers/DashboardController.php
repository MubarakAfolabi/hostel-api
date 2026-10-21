<?php

namespace App\Http\Controllers;

use App\Models\Room;

class DashboardController extends Controller
{
    public function roomStats()
    {
        return response()->json([
            'total_rooms'           => Room::count(),
            'available_rooms'       => Room::available()->count(),
            'fully_allocated_rooms' => Room::fullyAllocated()->count(),
        ]);
    }
}
