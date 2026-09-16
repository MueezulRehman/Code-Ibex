@extends('manager.layout.master')
@section('title', 'Clinical Notes')
@section('page-content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-lg font-bold text-hut-dark">Admission Clinical Notes</h1>
        <p class="text-sm text-gray-500">Progress and nursing documentation stays linked to the inpatient admission.</p>
    </div>
    @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($canRecord)
        <form method="POST" action="{{ route('manager.hospital-clinical-notes.store') }}" class="grid items-start gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm md:grid-cols-4">
            @csrf
            <div class="md:col-span-2"><label for="clinical-note-admission" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Admission</label><select id="clinical-note-admission" name="hospital_admission_id" required class="w-full rounded-lg border border-gray-200"><option value="">Select an active admission</option>@foreach($admissions as $admission)<option value="{{ $admission->id }}">{{ $admission->admission_number }} — {{ $admission->patient?->name }}</option>@endforeach</select></div>
            <div><label for="clinical-note-type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Note type</label><select id="clinical-note-type" name="note_type" required class="w-full rounded-lg border border-gray-200">@foreach($allowedTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
            <div class="page-field"><label for="clinical-note-recorded-date">Recorded at</label><div class="date-time-picker" data-date-time-picker><div class="date-field"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 4v3M18 4v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12H4V7a1 1 0 0 1 1-1Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><input id="clinical-note-recorded-date" type="text" datepicker datepicker-buttons datepicker-autoselect-today data-date-input value="{{ now()->format('m/d/Y') }}" placeholder="Select date" required></div><div class="date-field"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round"/></svg></span><input type="time" step="1" data-time-input value="{{ now()->format('H:i:s') }}" required></div><input type="hidden" name="recorded_at" data-date-time-value value="{{ now()->format('Y-m-d H:i:s') }}"></div></div>
            <div class="md:col-span-3"><label for="clinical-note-content" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Clinical note</label><textarea id="clinical-note-content" name="content" required maxlength="20000" rows="4" placeholder="Document assessment, progress, intervention, or observation" class="w-full rounded-lg border border-gray-200"></textarea></div>
            <div class="flex items-start"><button class="w-full rounded-lg bg-hut-dark px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800">Record Note</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3">Admission</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Content</th><th class="px-3 py-3">Recorded</th><th class="px-5 py-3">Author</th>@if($canCorrect)<th class="px-5 py-3">Actions</th>@endif</tr></thead>
            <tbody class="divide-y">
            @forelse($notes as $note)
                <tr class="{{ $note->status === 'void' ? 'bg-gray-50 text-gray-400' : '' }}">
                    <td class="px-5 py-3 align-top">{{ $note->admission?->admission_number }}<div class="text-xs text-gray-500">{{ $note->admission?->patient?->name }}</div></td>
                    <td class="px-3 py-3 align-top"><span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ ucfirst($note->note_type) }}</span><div class="mt-1 text-xs uppercase">{{ $note->status }}</div></td>
                    <td class="max-w-xl whitespace-pre-wrap px-3 py-3 align-top">{{ $note->content }}</td>
                    <td class="whitespace-nowrap px-3 py-3 align-top">{{ $note->recorded_at?->format('d M Y H:i') }}</td>
                    <td class="px-5 py-3 align-top">{{ $note->author?->name }}</td>
                    @if($canCorrect)
                        <td class="px-5 py-3 align-top">
                            @if($note->status === 'active')
                                <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-hut-green hover:bg-slate-50" onclick="openClinicalNoteModal('{{ $note->id }}')">Correct or void</button>
                                <div id="clinical-note-modal-{{ $note->id }}" class="clinical-modal" hidden>
                                    <div class="clinical-modal__backdrop" onclick="closeClinicalNoteModal('{{ $note->id }}')"></div>
                                    <div class="clinical-modal__panel" role="dialog" aria-modal="true" aria-labelledby="clinical-note-title-{{ $note->id }}">
                                        <div class="clinical-modal__header"><div><p class="page-eyebrow">Audit-safe action</p><h2 id="clinical-note-title-{{ $note->id }}" class="text-lg font-bold text-hut-dark">Correct clinical note</h2></div><button type="button" class="clinical-modal__close" onclick="closeClinicalNoteModal('{{ $note->id }}')" aria-label="Close">×</button></div>
                                        <form method="POST" action="{{ route('manager.hospital-clinical-notes.update', $note) }}" class="grid gap-4 p-5">
                                            @csrf @method('PATCH')
                                            <div class="page-field"><label>Recorded at</label><div class="date-time-picker" data-date-time-picker><div class="date-field"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 4v3M18 4v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12H4V7a1 1 0 0 1 1-1Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><input type="text" datepicker datepicker-buttons datepicker-autoselect-today data-date-input value="{{ $note->recorded_at?->format('m/d/Y') }}" placeholder="Select date" required></div><div class="date-field"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round"/></svg></span><input type="time" step="1" data-time-input value="{{ $note->recorded_at?->format('H:i:s') }}" required></div><input type="hidden" name="recorded_at" data-date-time-value value="{{ $note->recorded_at?->format('Y-m-d H:i:s') }}"></div></div>
                                            <div class="page-field"><label>Clinical note</label><textarea name="content" required rows="5">{{ $note->content }}</textarea></div>
                                            <div class="page-field"><label>Reason for correction</label><textarea name="correction_notes" required rows="3" placeholder="Explain what changed and why"></textarea></div>
                                            <div class="clinical-modal__actions"><button class="rounded-lg bg-hut-dark px-4 py-2.5 text-sm font-semibold text-white">Save correction</button></div>
                                        </form>
                                        <form method="POST" action="{{ route('manager.hospital-clinical-notes.void', $note) }}" class="border-t border-slate-100 p-5">
                                            @csrf @method('PATCH')
                                            <div class="page-field"><label>Void reason</label><input name="void_reason" required placeholder="Explain why this note is invalid"></div>
                                            <button class="mt-3 rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">Void note</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                            @if($note->events->isNotEmpty())<details class="mt-2 text-xs"><summary class="cursor-pointer text-gray-500">Audit ({{ $note->events->count() }})</summary><ul class="mt-1 space-y-1">@foreach($note->events as $event)<li>{{ ucfirst($event->action) }} by {{ $event->changedBy?->name }} — {{ $event->changed_at?->format('d M Y H:i') }}<br>{{ $event->notes }}</li>@endforeach</ul></details>@endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canCorrect ? 6 : 5 }}" class="px-5 py-10 text-center text-gray-500">No clinical notes available for your authorized admissions.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-4">{{ $notes->links() }}</div>
    </div>
</div>
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flowbite-datepicker@1.3.2/dist/css/datepicker.min.css">
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flowbite-datepicker@1.3.2/dist/js/datepicker-full.min.js"></script>
<script>
document.querySelectorAll('[data-date-input]').forEach(function (input) {
    if (window.Datepicker && !input.datepicker) {
        input.datepicker = new Datepicker(input, { autoselectToday: true, todayBtn: true, clearBtn: true, format: 'mm/dd/yyyy' });
    }
    input.addEventListener('focus', function () { this.datepicker?.show(); });
    input.addEventListener('click', function () { this.datepicker?.show(); });
});
document.querySelectorAll('[data-date-time-picker]').forEach(function (picker) {
    var date = picker.querySelector('[data-date-input]'), time = picker.querySelector('[data-time-input]'), target = picker.querySelector('[data-date-time-value]');
    function sync() { var parts = date.value.split('/'); if (parts.length === 3 && time.value) target.value = parts[2] + '-' + parts[0].padStart(2, '0') + '-' + parts[1].padStart(2, '0') + ' ' + (time.value.length === 5 ? time.value + ':00' : time.value); }
    date.addEventListener('changeDate', function () { sync(); window.setTimeout(function () { date.datepicker?.hide(); }, 0); }); date.addEventListener('change', sync); time.addEventListener('change', sync);
    picker.closest('form').addEventListener('submit', sync);
});
document.addEventListener('focusin', function (event) {
    if (event.target.matches('[data-date-input]')) window.setTimeout(function () { event.target.datepicker?.show(); }, 0);
});
document.addEventListener('click', function (event) {
    if (!event.target.closest('.datepicker-dropdown .day')) return;
    window.setTimeout(function () {
        document.querySelectorAll('[data-date-input]').forEach(function (input) { input.datepicker?.hide(); });
        document.querySelectorAll('.datepicker-dropdown').forEach(function (dropdown) { dropdown.classList.remove('active'); });
    }, 0);
});
function openClinicalNoteModal(id) {
    var modal = document.getElementById('clinical-note-modal-' + id);
    if (modal) {
        if (modal.parentElement !== document.body) document.body.appendChild(modal);
        modal.hidden = false;
        document.body.classList.add('overflow-hidden');
    }
}
function closeClinicalNoteModal(id) {
    var modal = document.getElementById('clinical-note-modal-' + id);
    if (modal) {
        modal.hidden = true;
        document.body.classList.remove('overflow-hidden');
    }
}
</script>
@endpush
@endsection
