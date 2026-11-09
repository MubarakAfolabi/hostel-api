<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    // Live search by name or matric number, at most 5 results
    public function search(Request $request)
    {
        $data = $request->validate([
            'q'           => 'required|string|min:2|max:50',
            'gender'      => 'nullable|in:Male,Female',
            'unallocated' => 'nullable|boolean',
        ]);

        // Stop % and _ typed by the user from acting as wildcards
        $q = addcslashes(trim($data['q']), '%_\\');

        $rows = DB::table('students')
            ->leftJoin('beds', 'beds.student_id', '=', 'students.student_id')
            ->where(function ($w) use ($q) {
                $w->where('students.student_id', 'like', $q . '%')
                    ->orWhere('students.first_name', 'like', $q . '%')
                    ->orWhere('students.last_name', 'like', $q . '%')
                    ->orWhereRaw("CONCAT(students.first_name, ' ', students.last_name) LIKE ?", ['%' . $q . '%']);
            })
            ->when(!empty($data['gender']), fn($query) => $query->where('students.gender', $data['gender']))
            ->when($request->boolean('unallocated'), fn($query) => $query->whereNull('beds.student_id'))
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->limit(5)
            ->get([
                'students.student_id',
                'students.first_name',
                'students.last_name',
                'students.department',
                'students.level',
                'students.gender',
                'beds.bed_id',
            ]);

        return $rows->map(fn($s) => [
            'matric_number' => $s->student_id,
            'name'          => trim($s->first_name . ' ' . $s->last_name),
            'department'    => $s->department,
            'level'         => $s->level,
            'gender'        => $s->gender,
            'allocated'     => $s->bed_id !== null,
        ])->values();
    }
}
