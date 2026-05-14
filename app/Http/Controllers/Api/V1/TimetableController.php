<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Timetable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    /**
     * GET /api/v1/timetable
     *
     * Returns timetable for a class or the authenticated user's class (teacher).
     *
     * Query params:
     *   class_id — required
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|integer|exists:school_classes,id',
        ]);

        $entries = Timetable::with(['subject:id,name', 'teacher:id,first_name,last_name'])
            ->where('school_class_id', $request->get('class_id'))
            ->orderBy('day_of_week')
            ->orderBy('period_number')
            ->get();

        // Group by day number, map to named day
        $grouped = [];
        foreach (Timetable::DAYS as $dayNum => $dayName) {
            $dayEntries = $entries->where('day_of_week', $dayNum)->values();
            $grouped[]  = [
                'day_number' => $dayNum,
                'day_name'   => $dayName,
                'periods'    => $dayEntries->map(fn ($e) => [
                    'id'            => $e->id,
                    'period_number' => $e->period_number,
                    'label'         => $e->subject?->name ?? $e->label ?? 'Period',
                    'start_time'    => $e->start_time,
                    'end_time'      => $e->end_time,
                    'subject'       => $e->subject ? ['id' => $e->subject->id, 'name' => $e->subject->name] : null,
                    'teacher'       => $e->teacher
                        ? ['id' => $e->teacher->id, 'full_name' => $e->teacher->first_name . ' ' . $e->teacher->last_name]
                        : null,
                ]),
            ];
        }

        return response()->json(['timetable' => $grouped]);
    }
}
