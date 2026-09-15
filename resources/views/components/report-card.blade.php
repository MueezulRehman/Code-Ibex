@props(['title', 'section'])
<section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="font-semibold text-hut-dark">{{ $title }}</h2>
        <a href="{{ route('manager.hospital-reports.export', array_merge(request()->query(), ['section' => $section])) }}" class="text-xs font-semibold text-amber-700 hover:underline">CSV</a>
    </div>
    {{ $slot }}
</section>
