@extends('layouts.website')

@section('title', 'Academics — ' . (app('currentTenant')?->name ?? 'School'))

@section('content')
@php $tenant = app('currentTenant'); @endphp

<section class="bg-gradient-to-br from-blue-700 to-blue-900 text-white py-20 text-center">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-4xl font-extrabold mb-4">Academic Programmes</h1>
    <p class="text-blue-100 text-lg">Delivering the Ghana Education Service curriculum with excellence at every level.</p>
  </div>
</section>

{{-- Curriculum --}}
<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
  <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">Our Curriculum</h2>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @foreach([
      ['Basic School (KG–P6)', 'Foundation in literacy, numeracy, and social skills. Aligned to the GES Primary curriculum with emphasis on holistic child development.', 'bg-blue-50 border-blue-200'],
      ['Junior High School (JHS 1–3)', 'Preparation for the BECE. Core and elective subjects delivered by qualified teachers with regular CA and end-of-term examinations.', 'bg-violet-50 border-violet-200'],
      ['Co-curricular Activities', 'Sports, arts, science clubs, debate, and cultural activities to develop well-rounded individuals beyond the classroom.', 'bg-emerald-50 border-emerald-200'],
    ] as [$title, $desc, $style])
      <div class="rounded-2xl border p-6 {{ $style }}">
        <h3 class="font-bold text-gray-900 mb-3">{{ $title }}</h3>
        <p class="text-gray-600 text-sm leading-relaxed">{{ $desc }}</p>
      </div>
    @endforeach
  </div>
</section>

{{-- Assessment structure --}}
<section class="bg-gray-50 py-16">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2 text-center">Assessment Structure</h2>
    <p class="text-gray-500 text-center mb-8">In line with GES guidelines, student performance is assessed as follows:</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center gap-3 mb-2">
          <span class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">30%</span>
          <h3 class="font-semibold text-gray-900">Continuous Assessment (CA)</h3>
        </div>
        <p class="text-sm text-gray-500">Class tests, homework, quizzes, and projects throughout the term.</p>
      </div>
      <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center gap-3 mb-2">
          <span class="w-10 h-10 bg-violet-600 text-white rounded-full flex items-center justify-center font-bold">70%</span>
          <h3 class="font-semibold text-gray-900">End-of-Term Examination</h3>
        </div>
        <p class="text-sm text-gray-500">A comprehensive written examination at the end of each term.</p>
      </div>
    </div>

    {{-- Grade scale --}}
    <div class="mt-8">
      <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Ghana Grading Scale (A1–F9)</h3>
      <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 text-center text-xs">
        @foreach([['A1','80–100','Excellent'],['B2','70–79','Very Good'],['B3','60–69','Good'],['C4','55–59','Credit'],['C5','50–54','Credit'],['C6','45–49','Credit'],['D7','40–44','Pass'],['F9','0–39','Fail']] as [$g,$r,$l])
          <div class="bg-white border border-gray-200 rounded-lg p-2">
            <div class="font-bold text-blue-700 text-sm">{{ $g }}</div>
            <div class="text-gray-500 text-xs">{{ $r }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
@endsection
