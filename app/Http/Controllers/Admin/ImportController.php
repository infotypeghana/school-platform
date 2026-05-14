<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CSV import for Students and Teachers.
 *
 * No third-party packages — uses PHP's built-in fgetcsv().
 *
 * Template downloads expose a pre-formatted CSV the admin can fill in,
 * then upload. Rows are validated per-row; errors are reported with row numbers
 * so the admin can fix the file and re-upload.
 *
 * Student columns:
 *   first_name*, last_name*, date_of_birth (YYYY-MM-DD)*, gender (male/female)*,
 *   class_name (must match an existing class name), guardian_name*, guardian_phone*,
 *   guardian_email, address, admission_date (YYYY-MM-DD), status (active/inactive/graduated)
 *
 * Teacher columns:
 *   first_name*, last_name*, email*, phone*, date_of_birth (YYYY-MM-DD),
 *   gender (male/female), address, qualification, subject_specialization, employment_date
 */
class ImportController extends Controller
{
    // ── Students ──────────────────────────────────────────────────────────────

    public function studentForm(): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        return view('admin.students.import', compact('classes'));
    }

    public function studentTemplate(): Response
    {
        $headers = [
            'first_name', 'last_name', 'date_of_birth', 'gender',
            'class_name', 'guardian_name', 'guardian_phone',
            'guardian_email', 'address', 'admission_date', 'status',
        ];

        $example = [
            'Kofi', 'Mensah', '2012-05-20', 'male',
            'Basic 4', 'Ama Mensah', '0241234567',
            'ama.mensah@gmail.com', 'Accra, Ghana', '2024-09-01', 'active',
        ];

        $csv = implode(',', $headers) . "\n" . implode(',', $example) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_import_template.csv"',
        ]);
    }

    public function studentImport(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $classes = SchoolClass::pluck('id', 'name')->toArray(); // ['Basic 4' => 5, ...]
        $tenantId = app('currentTenant')->id;

        [$imported, $errors] = $this->parseCsv(
            $request->file('csv_file')->getRealPath(),
            fn (array $row, int $lineNum) => $this->importStudentRow($row, $lineNum, $tenantId, $classes),
        );

        $msg = "Imported {$imported} student(s).";
        if (! empty($errors)) {
            $msg .= ' Errors on rows: ' . implode(', ', array_keys($errors)) . '.';
            return redirect()->route('admin.students.import')
                ->with('import_errors', $errors)
                ->with('success', $msg);
        }

        return redirect()->route('admin.students')
            ->with('success', $msg);
    }

    // ── Teachers ─────────────────────────────────────────────────────────────

    public function teacherForm(): View
    {
        return view('admin.teachers.import');
    }

    public function teacherTemplate(): Response
    {
        $headers = [
            'first_name', 'last_name', 'email', 'phone',
            'gender', 'qualification', 'specialization', 'joined_date',
        ];

        $example = [
            'Abena', 'Owusu', 'abena@school.edu.gh', '0241234567',
            'female', 'B.Ed Mathematics', 'Mathematics', '2020-01-10',
        ];

        $csv = implode(',', $headers) . "\n" . implode(',', $example) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="teachers_import_template.csv"',
        ]);
    }

    public function teacherImport(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $tenantId = app('currentTenant')->id;

        [$imported, $errors] = $this->parseCsv(
            $request->file('csv_file')->getRealPath(),
            fn (array $row, int $lineNum) => $this->importTeacherRow($row, $lineNum, $tenantId),
        );

        $msg = "Imported {$imported} teacher(s).";
        if (! empty($errors)) {
            $msg .= ' Errors on rows: ' . implode(', ', array_keys($errors)) . '.';
            return redirect()->route('admin.teachers.import')
                ->with('import_errors', $errors)
                ->with('success', $msg);
        }

        return redirect()->route('admin.teachers')
            ->with('success', $msg);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Parse a CSV file, calling $rowHandler for each data row.
     * Returns [importedCount, errors].
     *
     * @return array{0: int, 1: array<int, string>}
     */
    private function parseCsv(string $path, callable $rowHandler): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [0, [1 => 'Could not open the uploaded file. Please try again.']];
        }

        $headers  = null;
        $imported = 0;
        $errors   = [];
        $lineNum  = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;

            // First row = headers
            if ($headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }

            // Skip blank rows
            if (count(array_filter($row)) === 0) {
                continue;
            }

            // Pad short rows
            $row    = array_pad($row, count($headers), '');
            $mapped = array_combine($headers, array_map('trim', $row));

            $result = $rowHandler($mapped, $lineNum);

            if ($result === true) {
                $imported++;
            } elseif (is_string($result)) {
                $errors[$lineNum] = $result;
            }
        }

        fclose($handle);
        return [$imported, $errors];
    }

    /** Validate and insert a single student row. Returns true | error string. */
    private function importStudentRow(array $row, int $lineNum, int $tenantId, array $classes): bool|string
    {
        $validator = Validator::make($row, [
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'date_of_birth'  => 'required|date_format:Y-m-d',
            'gender'         => 'required|in:male,female',
            'guardian_name'  => 'required|string|max:150',
            'guardian_phone' => 'required|string|max:20',
            'guardian_email' => 'nullable|email|max:150',
            'address'        => 'nullable|string|max:255',
            'admission_date' => 'nullable|date_format:Y-m-d',
            'status'         => 'nullable|in:active,graduated,withdrawn,suspended',
        ]);

        if ($validator->fails()) {
            return 'Row ' . $lineNum . ': ' . implode('; ', $validator->errors()->all());
        }

        // Resolve class name → ID
        $classId = null;
        if (! empty($row['class_name'])) {
            $classId = $classes[$row['class_name']] ?? null;
            if ($classId === null) {
                return "Row {$lineNum}: Class '{$row['class_name']}' not found.";
            }
        }

        try {
            Student::create([
                'tenant_id'        => $tenantId,
                'school_class_id'  => $classId,
                'first_name'       => $row['first_name'],
                'last_name'        => $row['last_name'],
                'date_of_birth'    => $row['date_of_birth'],
                'gender'           => $row['gender'],
                'guardian_name'    => $row['guardian_name'],
                'guardian_phone'   => $row['guardian_phone'],
                'guardian_email'   => $row['guardian_email'] ?: null,
                'address'          => $row['address']        ?: null,
                'admission_date'   => $row['admission_date'] ?: null,
                'status'           => $row['status']         ?: 'active',
            ]);
        } catch (\Throwable $e) {
            return "Row {$lineNum}: DB error — {$e->getMessage()}";
        }

        return true;
    }

    /** Validate and insert a single teacher row. Returns true | error string. */
    private function importTeacherRow(array $row, int $lineNum, int $tenantId): bool|string
    {
        $validator = Validator::make($row, [
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'email'          => 'required|email|max:150',
            'phone'          => 'required|string|max:20',
            'gender'         => 'nullable|in:male,female',
            'qualification'  => 'nullable|string|max:200',
            'specialization' => 'nullable|string|max:150',
            'joined_date'    => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return 'Row ' . $lineNum . ': ' . implode('; ', $validator->errors()->all());
        }

        // Duplicate email check (within tenant)
        if (Teacher::where('tenant_id', $tenantId)->where('email', $row['email'])->exists()) {
            return "Row {$lineNum}: Email '{$row['email']}' already exists.";
        }

        try {
            Teacher::create([
                'tenant_id'      => $tenantId,
                'first_name'     => $row['first_name'],
                'last_name'      => $row['last_name'],
                'email'          => $row['email'],
                'phone'          => $row['phone'],
                'gender'         => $row['gender']         ?: null,
                'qualification'  => $row['qualification']  ?: null,
                'specialization' => $row['specialization'] ?: null,
                'joined_date'    => $row['joined_date']    ?: null,
            ]);
        } catch (\Throwable $e) {
            return "Row {$lineNum}: DB error — {$e->getMessage()}";
        }

        return true;
    }
}
