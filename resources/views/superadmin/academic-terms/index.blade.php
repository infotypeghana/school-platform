@extends('layouts.superadmin')

@section('title', 'Academic Terms')
@section('page-title', 'Academic Terms')

@section('content')

<div class="flex items-center justify-between mb-5">
  <p class="text-sm text-gray-500">{{ $terms->total() }} term(s) configured</p>
  <a href="{{ route('superadmin.academic-terms.create') }}"
     class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
    + Add Term
  </a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Term</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Academic Year</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Start Date</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">End Date</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Grace Ends</th>
        <th class="px-4 py-3 text-center font-semibold text-gray-600">Current</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($terms as $term)
        <tr class="hover:bg-gray-50 transition-colors {{ $term->is_current ? 'bg-blue-50/40' : '' }}">
          <td class="px-4 py-3 font-medium text-gray-900">{{ $term->term_name }}</td>
          <td class="px-4 py-3 text-gray-600">{{ $term->academicYear?->year_label }}</td>
          <td class="px-4 py-3 text-gray-600">{{ $term->start_date->format('d M Y') }}</td>
          <td class="px-4 py-3 text-gray-600">{{ $term->end_date->format('d M Y') }}</td>
          <td class="px-4 py-3 text-gray-500 text-xs">{{ $term->graceEndsAt()->format('d M Y') }}</td>
          <td class="px-4 py-3 text-center">
            @if($term->is_current)
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Current</span>
            @else
              <span class="text-gray-300 text-xs">—</span>
            @endif
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-2">
              <a href="{{ route('superadmin.academic-terms.edit', $term) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('superadmin.academic-terms.destroy', $term) }}"
                    onsubmit="return confirm('Delete this term?')" class="inline">
                @csrf @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">No terms configured.</td></tr>
      @endforelse
    </tbody>
  </table>
  @if($terms->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $terms->links() }}</div>
  @endif
</div>
@endsection
