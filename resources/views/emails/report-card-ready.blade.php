<x-mail::message>
# Report Card Ready

Dear **{{ $student?->guardian_name ?? 'Parent/Guardian' }}**,

The {{ $term?->term_name ?? '' }} report card for **{{ $student?->full_name }}** is now ready at **{{ $school?->name ?? 'the school' }}**.

<x-mail::panel>
**Student:** {{ $student?->full_name }} ({{ $student?->admission_number }})
**Term:** {{ $term?->term_name }} · {{ $term?->academicYear?->year_label }}
**Class:** {{ $reportCard->schoolClass?->full_name ?? '—' }}
@if($reportCard->overall_position)
**Position:** {{ $reportCard->overall_position }} out of {{ $reportCard->out_of }}
@endif
</x-mail::panel>

Please visit the school or log into the parent portal to collect or view the report card.

Thanks,<br>
{{ $school?->name ?? config('app.name') }}
</x-mail::message>
