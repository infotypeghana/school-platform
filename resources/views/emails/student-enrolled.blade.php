<x-mail::message>
# Enrolment Confirmed

Dear **{{ $student->guardian_name ?? 'Parent/Guardian' }}**,

We are pleased to confirm that **{{ $student->full_name }}** has been successfully enrolled at **{{ $school->name }}**.

<x-mail::panel>
**Student Details**

| Field | Value |
|-------|-------|
| Full Name | {{ $student->full_name }} |
| Admission Number | {{ $student->admission_number ?? 'Pending' }} |
| Class | {{ $student->schoolClass?->full_name ?? 'Not yet assigned' }} |
| Enrolment Date | {{ $student->admission_date?->format('d F Y') ?? now()->format('d F Y') }} |
</x-mail::panel>

Please keep this admission number safe — it is required for fee payments, report card collection, and portal login.

If you have any questions, please contact the school directly.

Warm regards,<br>
**{{ $school->name }}**

@if($school->phone ?? false)
📞 {{ $school->phone }}
@endif
@if($school->email ?? false)
✉ {{ $school->email }}
@endif

<x-mail::subcopy>
This is an automated message from the {{ $school->name }} School Management System. If you did not expect this email, please contact the school immediately.
</x-mail::subcopy>
</x-mail::message>
