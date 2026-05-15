{{-- Settings sub-navigation tabs --}}
@php
  $tabs = [
    ['label' => 'School Profile', 'route' => 'admin.settings.school'],
    ['label' => 'Grading',        'route' => 'admin.settings.grading'],
    ['label' => 'Calendar',       'route' => 'admin.settings.calendar'],
    ['label' => 'Website',        'route' => 'admin.settings.website'],
    ['label' => 'Account',        'route' => 'admin.settings.account'],
  ];
@endphp
<div class="flex gap-1 mb-6 border-b border-gray-200 -mt-2 flex-wrap">
  @foreach($tabs as $tab)
    @php $active = request()->routeIs($tab['route']); @endphp
    <a href="{{ route($tab['route']) }}"
       class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
              {{ $active
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700' }}">
      {{ $tab['label'] }}
    </a>
  @endforeach
</div>
