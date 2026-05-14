<x-mail::message>
# Payment Receipt

Dear **{{ $student?->guardian_name ?? 'Parent/Guardian' }}**,

A payment has been recorded for **{{ $student?->full_name }}** at **{{ $school?->name ?? 'the school' }}**.

<x-mail::panel>
**Student:** {{ $student?->full_name }} ({{ $student?->admission_number }})
**Fee Type:** {{ ucfirst(str_replace('_', ' ', $fee->fee_type)) }}
**Amount Paid:** GHS {{ number_format($amountJustPaid, 2) }}
**Outstanding Balance:** GHS {{ number_format($fee->balance, 2) }}
**Status:** {{ ucfirst($fee->status) }}
**Date:** {{ now()->format('d M Y, H:i') }}
</x-mail::panel>

@if($fee->balance > 0)
A balance of **GHS {{ number_format($fee->balance, 2) }}** remains outstanding. Please ensure this is settled by the due date.
@else
This fee is now **fully paid**. Thank you!
@endif

Thanks,<br>
{{ $school?->name ?? config('app.name') }}
</x-mail::message>
