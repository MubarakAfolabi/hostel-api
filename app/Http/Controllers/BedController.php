<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Room;
use App\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BedController extends Controller
{
    // Puts a student in a vacant bedspace
    public function assign(Request $request, string $bedId)
    {
        $data = $request->validate([
            'student_id' => 'required|string|max:10',
        ]);

        try {
            return DB::transaction(function () use ($bedId, $data) {
                // Lock the bed so two admins can't take it at the same moment
                $bed = Bed::whereKey($bedId)->lockForUpdate()->first();

                if (!$bed) {
                    return response()->json(['message' => 'Bedspace not found.'], 404);
                }

                if ($bed->status !== 'Available' || $bed->student_id !== null) {
                    return response()->json([
                        'message' => 'This bedspace has just been taken. Refresh and pick another.',
                    ], 409);
                }

                $student = Student::find($data['student_id']);

                if (!$student) {
                    return response()->json(['message' => 'Student not found.'], 404);
                }

                if (Bed::where('student_id', $student->student_id)->exists()) {
                    return response()->json(['message' => 'This student already has a bedspace.'], 409);
                }

                $room = Room::with('block')->find($bed->room_id);

                if (in_array($room->status, ['Utility', 'Restricted'], true)) {
                    return response()->json(['message' => 'This room is not open for allocation.'], 422);
                }

                if ($student->gender === null) {
                    return response()->json([
                        'message' => 'This student has no gender recorded, so they cannot be matched to a block.',
                    ], 422);
                }

                if ($student->gender !== $room->block->gender_type) {
                    return response()->json([
                        'message' => "Block {$room->block->block_number} is for {$room->block->gender_type} students.",
                    ], 422);
                }

                // The database triggers update the room's status after this
                $bed->update([
                    'student_id' => $student->student_id,
                    'status'     => 'Allocated',
                ]);

                return response()->json([
                    'bed_id'    => $bed->bed_id,
                    'bed_label' => $bed->bed_label,
                    'student'   => trim($student->first_name . ' ' . $student->last_name),
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two requests gave the same student a bed at the same moment
            return response()->json(['message' => 'This student has just been given another bedspace.'], 409);
        }
    }
}
