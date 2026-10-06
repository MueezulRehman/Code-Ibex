@extends('super-admin.layout.master')

@section('title', 'User Access')

@section('page-content')
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-hut-dark">User Access</h2>
        <p class="mt-1 text-sm text-gray-500">Set a one-time temporary password for an internal user. They must choose a new password at their next login.</p>
    </div>

    @if (session('temporary_password'))
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950" role="status">
            <h3 class="font-semibold">Temporary password set for {{ session('temporary_password.user_name') }}</h3>
            <p class="mt-1 text-sm">Share this securely with the user. This is shown once; they will be required to replace it after signing in.</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <code class="select-all rounded-lg border border-amber-300 bg-white px-3 py-2 font-mono text-base">{{ session('temporary_password.password') }}</code>
                <span class="text-sm">Login phone: {{ session('temporary_password.user_phone') }}</span>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-5 flex flex-wrap gap-3 rounded-xl border border-gray-200 bg-white p-4">
        <label class="sr-only" for="user-search">Search users</label>
        <input id="user-search" type="search" name="q" value="{{ $search }}" placeholder="Search name, phone, or email" class="min-w-60 flex-1 rounded-lg border border-gray-300 px-3 py-2">
        <label class="sr-only" for="user-role">Filter by role</label>
        <select id="user-role" name="role" class="rounded-lg border border-gray-300 px-3 py-2">
            <option value="">All internal roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected($selectedRole === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Business</th>
                    <th class="px-4 py-3">Password status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-hut-dark">{{ $user->name }}</div>
                            <div class="text-xs text-gray-500">{{ $user->phone }}{{ $user->email ? ' · '.$user->email : '' }}</div>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $user->role) }}</td>
                        <td class="px-4 py-3">{{ $user->restaurant?->name ?? 'Platform' }}</td>
                        <td class="px-4 py-3">
                            @if ($user->must_change_password)
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Change required</span>
                            @else
                                <span class="text-gray-500">Normal</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.users.password.reset', $user) }}" class="ml-auto grid max-w-64 gap-2" onsubmit="return confirm('Set this temporary password? The user must change it after signing in.');">
                                @csrf
                                <label class="sr-only" for="temporary-password-{{ $user->id }}">Temporary password for {{ $user->name }}</label>
                                <input id="temporary-password-{{ $user->id }}" name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="Temporary password" class="rounded-lg border border-gray-300 px-3 py-2 text-left text-xs">
                                <label class="sr-only" for="temporary-password-confirmation-{{ $user->id }}">Confirm temporary password for {{ $user->name }}</label>
                                <input id="temporary-password-confirmation-{{ $user->id }}" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Confirm password" class="rounded-lg border border-gray-300 px-3 py-2 text-left text-xs">
                                <p class="text-left text-xs text-gray-500">12+ chars, uppercase, lowercase, number, and symbol.</p>
                                <button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-50">Set temporary password</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No internal user accounts match this search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
