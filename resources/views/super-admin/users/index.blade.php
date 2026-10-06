@extends('super-admin.layout.master')

@section('title', 'User Access')

@section('page-content')
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-hut-dark">User Access</h2>
        <p class="mt-1 text-sm text-gray-500">Review every business, its tenant database readiness, and internal login accounts. A manager can only be added when its tenant database is ready.</p>
    </div>

    @if (session('temporary_password'))
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950" role="status">
            <h3 class="font-semibold">Temporary password set for {{ session('temporary_password.user_name') }}</h3>
            <p class="mt-1 text-sm">Share this securely with the user. It is shown once; they must replace it after signing in.</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <code class="select-all rounded-lg border border-amber-300 bg-white px-3 py-2 font-mono text-base">{{ session('temporary_password.password') }}</code>
                <span class="text-sm">Login phone: {{ session('temporary_password.user_phone') }}</span>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-5 flex flex-wrap gap-3 rounded-xl border border-gray-200 bg-white p-4">
        <label class="sr-only" for="business-user-search">Search businesses or users</label>
        <input id="business-user-search" type="search" name="q" value="{{ $search }}" placeholder="Search business, user name, phone, or email" class="min-w-60 flex-1 rounded-lg border border-gray-300 px-3 py-2">
        <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full min-w-[1000px] text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-3">Business</th>
                    <th class="px-4 py-3">Tenant database</th>
                    <th class="px-4 py-3">Internal login accounts</th>
                    <th class="px-4 py-3">Add manager login</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($businesses as $business)
                    @php
                        $tenantStatus = $business->tenant_health['status'];
                        $tenantReady = $business->tenant_health['ready'];
                        $tenantLabel = match ($tenantStatus) {
                            'ready' => 'Ready',
                            'not_configured' => 'Not configured',
                            'schema_incomplete' => 'Schema incomplete',
                            'migrations_pending' => 'Migrations pending',
                            'not_initialized' => 'Not initialized',
                            default => 'Unreachable',
                        };
                        $tenantStatusClass = $tenantReady
                            ? 'bg-green-100 text-green-800'
                            : 'bg-amber-100 text-amber-900';
                    @endphp
                    <tr class="align-top">
                        <td class="px-4 py-4">
                            <div class="font-semibold text-hut-dark">{{ $business->name }}</div>
                            <div class="mt-1 text-xs text-gray-500">#{{ $business->id }} · {{ $business->slug }}</div>
                        </td>
                        <td class="px-4 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $tenantStatusClass }}">{{ $tenantLabel }}</span>
                            @if ($business->tenant_health['database'])
                                <div class="mt-2 break-all text-xs text-gray-500">{{ $business->tenant_health['database'] }}</div>
                            @endif
                            @unless ($tenantReady)
                                <p class="mt-2 max-w-56 text-xs text-gray-500">
                                    @if ($tenantStatus === 'not_configured')
                                        No tenant connection has been saved. Configure one under Businesses → Edit.
                                    @elseif ($tenantStatus === 'unreachable')
                                        The database could not be reached. Check the database name, host, user grants, and password in Businesses → Edit.
                                    @else
                                        The database exists but is not fully initialized. Run the tenant migration/provisioning step before adding a manager.
                                    @endif
                                </p>
                            @endunless
                        </td>
                        <td class="px-4 py-4">
                            @forelse ($business->users as $account)
                                <div class="mb-4 rounded-lg border border-gray-200 p-3 last:mb-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-hut-dark">{{ $account->name }}</span>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs capitalize text-gray-700">{{ str_replace('_', ' ', $account->role) }}</span>
                                        @if ($account->must_change_password)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Password change required</span>
                                        @else
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Password set</span>
                                        @endif
                                        @unless ($account->is_active)
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Inactive</span>
                                        @endunless
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $account->phone }}{{ $account->email ? ' · '.$account->email : '' }}</div>
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-xs font-semibold text-amber-900">Set temporary password</summary>
                                        <form method="POST" action="{{ route('admin.users.password.reset', $account) }}" class="mt-2 grid max-w-72 gap-2" onsubmit="return confirm('Set this temporary password? The user must change it after signing in.');">
                                            @csrf
                                            <label class="sr-only" for="temporary-password-{{ $account->id }}">Temporary password for {{ $account->name }}</label>
                                            <input id="temporary-password-{{ $account->id }}" name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="Temporary password" class="rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                            <label class="sr-only" for="temporary-password-confirmation-{{ $account->id }}">Confirm temporary password for {{ $account->name }}</label>
                                            <input id="temporary-password-confirmation-{{ $account->id }}" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Confirm password" class="rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                            <p class="text-xs text-gray-500">12+ chars, uppercase, lowercase, number, and symbol.</p>
                                            <button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-50">Set temporary password</button>
                                        </form>
                                    </details>
                                </div>
                            @empty
                                <span class="text-sm text-amber-800">No internal login account</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-4">
                            @if ($tenantReady)
                                <details>
                                    <summary class="cursor-pointer rounded-lg border border-blue-200 px-3 py-2 text-center text-xs font-semibold text-blue-900 hover:bg-blue-50">Add manager</summary>
                                    <form method="POST" action="{{ route('admin.users.businesses.managers.store', $business) }}" class="mt-3 grid min-w-64 gap-2">
                                        @csrf
                                        <label class="sr-only" for="manager-name-{{ $business->id }}">Manager name</label>
                                        <input id="manager-name-{{ $business->id }}" name="name" required maxlength="255" value="{{ old('name') }}" placeholder="Full name" class="rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                        <label class="sr-only" for="manager-phone-{{ $business->id }}">Manager login phone</label>
                                        <input id="manager-phone-{{ $business->id }}" name="phone" type="tel" required maxlength="20" value="{{ old('phone') }}" placeholder="Unique login phone" class="rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                        <label class="sr-only" for="manager-email-{{ $business->id }}">Manager email</label>
                                        <input id="manager-email-{{ $business->id }}" name="email" type="email" maxlength="150" value="{{ old('email') }}" placeholder="Email (optional)" class="rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                        <label class="sr-only" for="manager-password-{{ $business->id }}">Temporary password</label>
                                        <input id="manager-password-{{ $business->id }}" name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="Temporary password" class="rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                        <label class="sr-only" for="manager-password-confirmation-{{ $business->id }}">Confirm temporary password</label>
                                        <input id="manager-password-confirmation-{{ $business->id }}" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Confirm password" class="rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                        <p class="text-xs text-gray-500">12+ chars, uppercase, lowercase, number, and symbol. User must change it at first login.</p>
                                        <button class="rounded-lg bg-hut-dark px-3 py-2 text-xs font-semibold text-white">Create manager login</button>
                                    </form>
                                </details>
                            @else
                                <span class="text-xs text-gray-500">Available when tenant is ready</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">No businesses match this search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $businesses->links() }}</div>
@endsection
