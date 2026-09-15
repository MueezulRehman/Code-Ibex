@props(['title', 'section'])
<section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="font-semibold text-hut-dark">{{ $title }}</h2>
        <a href="{{ route('manager.hospital-reports.export', array_merge(request()->query(), ['section' => $section])) }}" class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-100">Export CSV</a>
    </div>
    {{ $slot }}
</section>
