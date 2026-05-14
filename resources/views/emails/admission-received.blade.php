<x-mail::message>
# @if($isApplicantCopy)Application Received@else New Admission Application@endif

@if($isApplicantCopy)
Dear **{{ $admission->guardian_name }}**,

Thank you for submitting an application to **{{ $school?->name ?? 'our school' }}**. We have received your application and will review it shortly.
@else
A new admission application has been submitted and is awaiting review.
@endif

<x-mail::panel>
**Applicant:** {{ $admission->first_name }} {{ $admission->last_name }}
**Class Applying For:** {{ $admission->applying_class ?? 'Not specified' }}
**Date of Birth:** {{ $admission->date_of_birth?->format('d M Y') ?? 'Not provided' }}
**Guardian:** {{ $admission->guardian_name }} ({{ $admission->guardian_phone }})
@if($admission->guardian_email)
**Guardian Email:** {{ $admission->guardian_email }}
@endif
**Submitted:** {{ $admission->created_at->format('d M Y, H:i') }}
</x-mail::panel>

@if($isApplicantCopy)
We will contact you at **{{ $admission->guardian_phone }}** with the outcome of your application. If you have any questions, please contact the school directly.
@endif

Thanks,<br>
{{ $school?->name ?? config('app.name') }}
</x-mail::message>
