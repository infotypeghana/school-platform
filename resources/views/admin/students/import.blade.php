@extends('layouts.admin')

@section('title', 'Import Students')
@section('page-title', 'Import Students')

@section('content')

<div class="max-w-2xl">

  <div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.students') }}"
       class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">← Students</a>
    <h3 class="text-sm font-semibold text-gray-700">Bulk import from CSV</h3>
  </div>

  {{-- Success / errors --}}
  @if(session('success'))
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
      {{ session('success') }}
    </div>
  @endif

  @if(session('import_errors'))
    <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
      <p class="text-sm font-semibold text-red-700 mb-2">Import errors — fix the rows below and re-upload:</p>
      <ul class="text-xs text-red-600 space-y-1 list-disc list-inside">
        @foreach(session('import_errors') as $line => $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Template download --}}
  <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5 flex items-start gap-3">
    <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div class="flex-1">
      <p class="text-sm font-semibold text-blue-800">Step 1 — Download the template</p>
      <p class="text-xs text-blue-700 mt-0.5">Fill it in with your student data, then upload. Do not change the column headers.</p>
      <a href="{{ route('admin.students.import.template') }}"
         class="inline-block mt-2 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors">
        ⬇ Download Template CSV
      </a>
    </div>
  </div>

  {{-- Column reference --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-5">
    <p class="text-xs font-semibold text-gray-600 mb-2">Column reference</p>
    <div class="overflow-x-auto">
      <table class="w-full text-xs">
        <thead>
          <tr class="border-b border-gray-100">
            <th class="text-left py-1.5 pr-4 font-semibold text-gray-500">Column</th>
            <th class="text-left py-1.5 pr-4 font-semibold text-gray-500">Required</th>
            <th class="text-left py-1.5 font-semibold text-gray-500">Notes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach([
            ['first_name',    true,  'Student first name'],
            ['last_name',     true,  'Student last name'],
            ['date_of_birth', true,  'Format: YYYY-MM-DD (e.g. 2012-05-20)'],
            ['gender',        true,  'male or female'],
            ['class_name',    false, 'Must match an existing class name exactly (e.g. Basic 4)'],
            ['guardian_name', true,  'Parent or guardian full name'],
            ['guardian_phone',true,  'Ghanaian number (e.g. 0241234567)'],
            ['guardian_email',false, 'Optional'],
            ['address',       false, 'Optional'],
            ['admission_date',false, 'Format: YYYY-MM-DD — defaults to today'],
            ['status',        false, 'active, inactive, or graduated — defaults to active'],
          ] as [$col, $req, $note])
            <tr>
              <td class="py-1.5 pr-4 font-mono text-gray-700">{{ $col }}</td>
              <td class="py-1.5 pr-4">
                @if($req)
                  <span class="text-red-500 font-semibold">Yes</span>
                @else
                  <span class="text-gray-400">No</span>
                @endif
              </td>
              <td class="py-1.5 text-gray-500">{{ $note }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Available class names --}}
    @if($classes->isNotEmpty())
      <p class="text-xs font-semibold text-gray-600 mt-3 mb-1">Available class names:</p>
      <div class="flex flex-wrap gap-1">
        @foreach($classes as $class)
          <code class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-xs">{{ $class->name }}</code>
        @endforeach
      </div>
    @endif
  </div>

  {{-- Upload form --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <p class="text-sm font-semibold text-gray-700 mb-4">Step 2 — Upload filled CSV</p>

    <form method="POST" action="{{ route('admin.students.import.store') }}" enctype="multipart/form-data" class="space-y-4">
      @csrf

      @error('csv_file')
        <div class="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">{{ $message }}</div>
      @enderror

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">CSV File *</label>
        <input type="file" name="csv_file" accept=".csv,.txt" required
               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        <p class="text-xs text-gray-400 mt-1">Max 2 MB. UTF-8 CSV file.</p>
      </div>

      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
        Import Students
      </button>
    </form>
  </div>

</div>
@endsection
