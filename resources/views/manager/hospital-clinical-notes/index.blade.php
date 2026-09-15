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
        <form method="POST" action="{{ route('manager.hospital-clinical-notes.store') }}" class="grid gap-3 rounded-xl border border-gray-200 bg-white p-5 md:grid-cols-4">
            @csrf
            <select name="hospital_admission_id" required class="rounded-lg border-gray-200 md:col-span-2">
                <option value="">Admission</option>
                @foreach($admissions as $admission)<option value="{{ $admission->id }}">{{ $admission->admission_number }} — {{ $admission->patient?->name }}</option>@endforeach
            </select>
            <select name="note_type" required class="rounded-lg border-gray-200">
                @foreach($allowedTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach
            </select>
            <input type="datetime-local" name="recorded_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded-lg border-gray-200">
            <textarea name="content" required maxlength="20000" placeholder="Document assessment, progress, intervention, or observation" class="min-h-24 rounded-lg border-gray-200 md:col-span-3"></textarea>
            <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Record Note</button>
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
                                <details><summary class="cursor-pointer text-xs font-semibold text-hut-green">Correct / void</summary>
                                    <form method="POST" action="{{ route('manager.hospital-clinical-notes.update', $note) }}" class="mt-2 grid min-w-64 gap-2">
                                        @csrf @method('PATCH')
                                        <input type="datetime-local" name="recorded_at" value="{{ $note->recorded_at?->format('Y-m-d\TH:i') }}" required class="rounded border-gray-200 text-xs">
                                        <textarea name="content" required class="rounded border-gray-200 text-xs">{{ $note->content }}</textarea>
                                        <textarea name="correction_notes" required placeholder="Reason for correction" class="rounded border-gray-200 text-xs"></textarea>
                                        <button class="rounded bg-hut-dark px-2 py-1 text-xs font-semibold text-white">Save correction</button>
                                    </form>
                                    <form method="POST" action="{{ route('manager.hospital-clinical-notes.void', $note) }}" class="mt-2 flex gap-2">
                                        @csrf @method('PATCH')
                                        <input name="void_reason" required placeholder="Void reason" class="min-w-0 flex-1 rounded border-gray-200 text-xs">
                                        <button class="rounded bg-red-700 px-2 py-1 text-xs font-semibold text-white">Void</button>
                                    </form>
                                </details>
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
@endsection
