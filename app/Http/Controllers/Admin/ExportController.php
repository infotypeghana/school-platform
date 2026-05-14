<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\Student;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    // ── Students ──────────────────────────────────────────────────────────────

    public function students(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Admission No', 'First Name', 'Last Name', 'Class', 'Gender',
                'Date of Birth', 'Status', 'Admission Date',
                'Guardian Name', 'Guardian Phone', 'Guardian Email', 'Address',
            ]);

            Student::with('schoolClass')
                ->when($request->class_id, fn ($q) => $q->where('school_class_id', $request->class_id))
                ->when($request->status,   fn ($q) => $q->where('status', $request->status))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->each(function (Student $s) use ($out) {
                    fputcsv($out, [
                        $s->admission_number,
                        $s->first_name,
                        $s->last_name,
                        $s->schoolClass?->full_name,
                        $s->gender,
                        $s->date_of_birth?->format('Y-m-d'),
                        $s->status,
                        $s->admission_date?->format('Y-m-d'),
                        $s->guardian_name,
                        $s->guardian_phone,
                        $s->guardian_email,
                        $s->address,
                    ]);
                });

            fclose($out);
        }, 'students-export-' . now()->format('Ymd') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Fees ──────────────────────────────────────────────────────────────────

    public function fees(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Receipt No', 'Student', 'Admission No', 'Class', 'Term',
                'Fee Type', 'Amount Due', 'Amount Paid', 'Balance', 'Status', 'Due Date',
            ]);

            Fee::with(['student.schoolClass', 'term'])
                ->when($request->term_id, fn ($q) => $q->where('term_id', $request->term_id))
                ->when($request->status,  fn ($q) => $q->where('status', $request->status))
                ->orderBy('created_at')
                ->each(function (Fee $f) use ($out) {
                    fputcsv($out, [
                        $f->receipt_number,
                        $f->student?->full_name,
                        $f->student?->admission_number,
                        $f->student?->schoolClass?->full_name,
                        $f->term?->term_name,
                        $f->fee_type,
                        number_format($f->amount, 2),
                        number_format($f->amount_paid, 2),
                        number_format($f->balance, 2),
                        $f->status,
                        $f->due_date?->format('Y-m-d'),
                    ]);
                });

            fclose($out);
        }, 'fees-export-' . now()->format('Ymd') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Attendance ────────────────────────────────────────────────────────────

    public function attendance(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Date', 'Student', 'Admission No', 'Class', 'Status', 'Term',
            ]);

            Attendance::with(['student.schoolClass', 'term'])
                ->when($request->term_id,  fn ($q) => $q->where('term_id', $request->term_id))
                ->when($request->class_id, fn ($q) => $q->where('school_class_id', $request->class_id))
                ->when($request->status,   fn ($q) => $q->where('status', $request->status))
                ->orderBy('date')
                ->each(function (Attendance $a) use ($out) {
                    fputcsv($out, [
                        $a->date?->format('Y-m-d'),
                        $a->student?->full_name,
                        $a->student?->admission_number,
                        $a->student?->schoolClass?->full_name,
                        $a->status,
                        $a->term?->term_name,
                    ]);
                });

            fclose($out);
        }, 'attendance-export-' . now()->format('Ymd') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Assessments ───────────────────────────────────────────────────────────

    public function assessments(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Student', 'Admission No', 'Class', 'Term',
                'Subject', 'CA Score', 'Exam Score', 'Total', 'Grade', 'Remark', 'Position',
            ]);

            Assessment::with(['student.schoolClass', 'subject', 'term'])
                ->when($request->term_id,  fn ($q) => $q->where('term_id', $request->term_id))
                ->when($request->class_id, fn ($q) => $q->where('school_class_id', $request->class_id))
                ->orderBy('term_id')
                ->each(function (Assessment $a) use ($out) {
                    fputcsv($out, [
                        $a->student?->full_name,
                        $a->student?->admission_number,
                        $a->student?->schoolClass?->full_name,
                        $a->term?->term_name,
                        $a->subject?->name,
                        $a->ca_score,
                        $a->exam_score,
                        $a->total_score,
                        $a->grade,
                        $a->remark,
                        $a->position_in_class,
                    ]);
                });

            fclose($out);
        }, 'assessments-export-' . now()->format('Ymd') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
