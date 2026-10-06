@extends('super-admin.layout.master')

@section('title', 'Businesses')

@section('page-content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-start gap-3">
                <span class="mt-1 flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-hut-blue"
                    aria-hidden="true"><i class="fas fa-building"></i></span>
                <div>
                    <h2 class="text-2xl font-semibold text-hut-dark">Businesses</h2>
                <p class="text-sm text-gray-500">Register and manage business accounts from the platform admin panel.</p>
                </div>
            </div>
            <a href="{{ route('admin.restaurants.create') }}"
                class="inline-flex items-center rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">+
                Add Business</a>
        </div>

        <div class="glass-strong overflow-hidden rounded-2xl">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Domain
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Plan
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Database
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Modules
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($restaurants as $restaurant)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <form action="{{ route('admin.restaurants.enter', $restaurant) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="font-medium text-hut-dark hover:text-hut-green hover:underline text-left"
                                        title="Manage this business (POS, menu, staff, etc.)">
                                        {{ $restaurant->name }}
                                    </button>
                                </form>
                                <div class="text-sm text-gray-500">{{ $restaurant->slug }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $restaurant->domain ?? $restaurant->custom_domain ?? 'No domain' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $restaurant->subscription?->plan?->name ?? 'No active plan' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $restaurant->hasTenantDatabase() ? 'Tenant DB' : 'Shared DB' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div>{{ is_array($restaurant->enabled_modules) ? count($restaurant->enabled_modules) : 0 }} enabled</div>
                                @if($restaurant->businessType?->name === 'Professional Hospital')
                                    <div class="mt-1 text-xs font-semibold text-emerald-700">Professional Hospital bundle</div>
                                @endif
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $restaurant->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($restaurant->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <form action="{{ route('admin.restaurants.enter', $restaurant) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                            class="text-hut-green hover:text-hut-green/70 font-medium">Manage</button>
                                    </form>
                                    <a href="{{ route('admin.restaurants.edit', $restaurant) }}"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-hut-yellow hover:bg-hut-yellow/10 hover:text-amber-600"
                                        aria-label="Edit business" title="Edit business">
                                        <x-icons.edit class="h-4 w-4" />
                                    </a>
                                    <a href="{{ route('admin.restaurants.manager-access', $restaurant) }}"
                                        class="text-hut-blue hover:text-hut-dark">Access</a>
                                    <a href="{{ route('admin.restaurants.ceo-access', $restaurant) }}"
                                        class="text-purple-600 hover:text-purple-800">CEO</a>
                                    <form action="{{ route('admin.restaurants.destroy', $restaurant) }}" method="POST"
                                        onsubmit="return confirm('Delete this business?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 hover:text-red-700"
                                            aria-label="Delete business" title="Delete business">
                                            <x-icons.trash class="h-4 w-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No businesses registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <x-pagination :paginator="$restaurants" />
        </div>
    </div>
@endsection