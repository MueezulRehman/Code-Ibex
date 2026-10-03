@extends('manager.layout.master')
@section('title', 'Attendance')

@section('page-content')

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-lg font-display font-bold text-hut-dark">Attendance Records</h2>
        <a href="{{ route('manager.attendance.create') }}"
            class="bg-hut-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-hut-green/90">+ Record
            Attendance</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase border-b">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Staff Member</th>
                    <th class="px-4 py-3 text-left">Check In</th>
                    <th class="px-4 py-3 text-left">Check Out</th>
                    <th class="px-4 py-3 text-left">Hours</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($attendance as $record)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-gray-600">{{ $record->created_at->format('d M, Y') }}</td>
                        <td class="px-4 py-3 font-medium text-hut-dark">{{ $record->user->name }}</td>
                        <td class="px-4 py-3">{{ $record->check_in }}</td>
                        <td class="px-4 py-3">{{ $record->check_out ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            @if($record->check_out)
                                {{ \Carbon\Carbon::parse($record->check_in)->diff(\Carbon\Carbon::parse($record->check_out))->format('%h:%I') }}
                                hrs
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="action-dropdown" data-action-dropdown>
                                <button type="button" class="action-dropdown__trigger" data-action-dropdown-trigger aria-expanded="false" aria-label="Open actions for attendance record">
                                    Actions <span aria-hidden="true">▾</span>
                                </button>
                                <div class="action-dropdown__menu" role="menu" hidden>
                                    <a href="{{ route('manager.attendance.edit', $record) }}" class="action-dropdown__item" role="menuitem">Edit record</a>
                                    <form action="{{ route('manager.attendance.destroy', $record) }}" method="POST" class="action-dropdown__form" data-confirm="Delete this record?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" role="menuitem">Delete record</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No attendance records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $attendance->links() }}
    </div>

    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-action-dropdown-trigger]');
                if (trigger) {
                    const dropdown = trigger.closest('[data-action-dropdown]');
                    const menu = dropdown?.querySelector('.action-dropdown__menu');
                    const willOpen = !dropdown.classList.contains('is-open');

                    document.querySelectorAll('[data-action-dropdown]').forEach(function (item) {
                        item.classList.remove('is-open');
                        const button = item.querySelector('[data-action-dropdown-trigger]');
                        if (button) button.setAttribute('aria-expanded', 'false');
                        const itemMenu = item.querySelector('.action-dropdown__menu');
                        if (itemMenu) itemMenu.hidden = true;
                    });

                    dropdown.classList.toggle('is-open', willOpen);
                    trigger.setAttribute('aria-expanded', String(willOpen));
                    if (menu) menu.hidden = !willOpen;
                    return;
                }

                if (!event.target.closest('[data-action-dropdown]')) {
                    document.querySelectorAll('[data-action-dropdown]').forEach(function (item) {
                        item.classList.remove('is-open');
                        const button = item.querySelector('[data-action-dropdown-trigger]');
                        if (button) button.setAttribute('aria-expanded', 'false');
                        const menu = item.querySelector('.action-dropdown__menu');
                        if (menu) menu.hidden = true;
                    });
                }
            });
        </script>
    @endpush

@endsection