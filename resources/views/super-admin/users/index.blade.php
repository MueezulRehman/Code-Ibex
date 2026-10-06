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

    @if ($errors->getBag('default')->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
            @foreach ($errors->getBag('default')->all() as $error)
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
                                    <button type="button" data-open-dialog="user-dialog-{{ $account->id }}" class="mt-3 rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-900 hover:bg-blue-50">Edit account</button>

                                    <dialog id="user-dialog-{{ $account->id }}" style="position: fixed; inset: 50% auto auto 50%; transform: translate(-50%, -50%); margin: 0;" class="max-h-[90vh] w-[min(95vw,42rem)] overflow-y-auto rounded-2xl border border-white/70 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/50">
                                        <div class="border-b border-slate-200 px-6 py-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-hut-dark">Manage {{ $account->name }}</h3>
                                                    <p class="mt-1 text-xs text-gray-500">{{ $business->name }} · {{ str_replace('_', ' ', $account->role) }}</p>
                                                </div>
                                                <button type="button" data-close-dialog class="rounded-lg px-3 py-1 text-xl text-gray-500 hover:bg-gray-100" aria-label="Close">×</button>
                                            </div>
                                        </div>
                                        <div class="grid gap-6 p-6">
                                            <form method="POST" action="{{ route('admin.users.update', $account) }}" class="grid gap-3">
                                                @csrf
                                                @method('PATCH')
                                                <h4 class="font-semibold text-hut-dark">Edit account details</h4>
                                                <label class="grid gap-1 text-sm">
                                                    <span>Name</span>
                                                    <input name="name" value="{{ $account->name }}" required maxlength="100" class="rounded-lg border border-gray-300 px-3 py-2">
                                                </label>
                                                <label class="grid gap-1 text-sm">
                                                    <span>Login phone</span>
                                                    <input name="phone" type="tel" value="{{ $account->phone }}" required maxlength="20" class="rounded-lg border border-gray-300 px-3 py-2">
                                                </label>
                                                <label class="grid gap-1 text-sm">
                                                    <span>Email <span class="text-gray-500">(optional)</span></span>
                                                    <input name="email" type="email" value="{{ $account->email }}" maxlength="150" class="rounded-lg border border-gray-300 px-3 py-2">
                                                </label>
                                                <button class="justify-self-start rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Save account details</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.users.password.reset', $account) }}" class="grid gap-3 border-t border-slate-200 pt-5" data-password-form onsubmit="return confirm('Set this temporary password? The user must change it after signing in.');">
                                                @csrf
                                                <input type="hidden" name="_dialog_id" value="user-dialog-{{ $account->id }}">
                                                <h4 class="font-semibold text-hut-dark">Set temporary password</h4>
                                                <label class="grid gap-1 text-sm">
                                                    <span>Temporary password</span>
                                                    <input name="password" type="password" required minlength="12" autocomplete="new-password" data-password-input aria-describedby="reset-password-rules-{{ $account->id }}" class="rounded-lg border border-gray-300 px-3 py-2">
                                                </label>
                                                @if ($errors->hasBag('resetPassword') && $errors->getBag('resetPassword')->has('password'))
                                                    <p class="-mt-2 text-xs text-red-700" role="alert">{{ $errors->getBag('resetPassword')->first('password') }}</p>
                                                @endif
                                                <ul id="reset-password-rules-{{ $account->id }}" data-password-checklist class="grid gap-1 text-xs text-gray-500" aria-label="Password requirements">
                                                    <li data-password-rule="length"><span data-rule-icon aria-hidden="true">○</span> At least 12 characters</li>
                                                    <li data-password-rule="uppercase"><span data-rule-icon aria-hidden="true">○</span> At least one uppercase letter</li>
                                                    <li data-password-rule="lowercase"><span data-rule-icon aria-hidden="true">○</span> At least one lowercase letter</li>
                                                    <li data-password-rule="number"><span data-rule-icon aria-hidden="true">○</span> At least one number</li>
                                                    <li data-password-rule="symbol"><span data-rule-icon aria-hidden="true">○</span> At least one symbol</li>
                                                </ul>
                                                <label class="grid gap-1 text-sm">
                                                    <span>Confirm password</span>
                                                    <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" data-password-confirmation class="rounded-lg border border-gray-300 px-3 py-2">
                                                </label>
                                                @if ($errors->hasBag('resetPassword') && $errors->getBag('resetPassword')->has('password_confirmation'))
                                                    <p class="-mt-2 text-xs text-red-700" role="alert">{{ $errors->getBag('resetPassword')->first('password_confirmation') }}</p>
                                                @endif
                                                <p data-password-feedback class="hidden text-xs text-red-700" role="status" aria-live="polite"></p>
                                                <button class="justify-self-start rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-50">Set temporary password</button>
                                            </form>
                                        </div>
                                    </dialog>
                                </div>
                            @empty
                                <span class="text-sm text-amber-800">No internal login account</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-4">
                            @if ($tenantReady)
                                <button type="button" data-open-dialog="manager-dialog-{{ $business->id }}" class="rounded-lg border border-blue-200 px-3 py-2 text-center text-xs font-semibold text-blue-900 hover:bg-blue-50">Add manager</button>
                                <dialog id="manager-dialog-{{ $business->id }}" style="position: fixed; inset: 50% auto auto 50%; transform: translate(-50%, -50%); margin: 0;" class="max-h-[90vh] w-[min(95vw,34rem)] overflow-y-auto rounded-2xl border border-white/70 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/50">
                                    <div class="border-b border-slate-200 px-6 py-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 class="text-lg font-semibold text-hut-dark">Add manager to {{ $business->name }}</h3>
                                                <p class="mt-1 text-xs text-gray-500">The manager must change this temporary password at first login.</p>
                                            </div>
                                            <button type="button" data-close-dialog class="rounded-lg px-3 py-1 text-xl text-gray-500 hover:bg-gray-100" aria-label="Close">×</button>
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('admin.users.businesses.managers.store', $business) }}" class="grid gap-3 p-6" data-password-form>
                                        @csrf
                                        <input type="hidden" name="_dialog_id" value="manager-dialog-{{ $business->id }}">
                                        <label class="grid gap-1 text-sm">
                                            <span>Full name</span>
                                            <input name="name" required maxlength="255" value="{{ old('name') }}" class="rounded-lg border border-gray-300 px-3 py-2">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span>Unique login phone</span>
                                            <input name="phone" type="tel" required maxlength="20" value="{{ old('phone') }}" class="rounded-lg border border-gray-300 px-3 py-2">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span>Email <span class="text-gray-500">(optional)</span></span>
                                            <input name="email" type="email" maxlength="150" value="{{ old('email') }}" class="rounded-lg border border-gray-300 px-3 py-2">
                                        </label>
                                        <label class="grid gap-1 text-sm">
                                            <span>Temporary password</span>
                                            <input name="password" type="password" required minlength="12" autocomplete="new-password" data-password-input aria-describedby="manager-password-rules-{{ $business->id }}" class="rounded-lg border border-gray-300 px-3 py-2">
                                        </label>
                                        @if ($errors->hasBag('managerCreate') && $errors->getBag('managerCreate')->has('password'))
                                            <p class="-mt-2 text-xs text-red-700" role="alert">{{ $errors->getBag('managerCreate')->first('password') }}</p>
                                        @endif
                                        <ul id="manager-password-rules-{{ $business->id }}" data-password-checklist class="grid gap-1 text-xs text-gray-500" aria-label="Password requirements">
                                            <li data-password-rule="length"><span data-rule-icon aria-hidden="true">○</span> At least 12 characters</li>
                                            <li data-password-rule="uppercase"><span data-rule-icon aria-hidden="true">○</span> At least one uppercase letter</li>
                                            <li data-password-rule="lowercase"><span data-rule-icon aria-hidden="true">○</span> At least one lowercase letter</li>
                                            <li data-password-rule="number"><span data-rule-icon aria-hidden="true">○</span> At least one number</li>
                                            <li data-password-rule="symbol"><span data-rule-icon aria-hidden="true">○</span> At least one symbol</li>
                                        </ul>
                                        <label class="grid gap-1 text-sm">
                                            <span>Confirm password</span>
                                            <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" data-password-confirmation class="rounded-lg border border-gray-300 px-3 py-2">
                                        </label>
                                        @if ($errors->hasBag('managerCreate') && $errors->getBag('managerCreate')->has('password_confirmation'))
                                            <p class="-mt-2 text-xs text-red-700" role="alert">{{ $errors->getBag('managerCreate')->first('password_confirmation') }}</p>
                                        @endif
                                        <p data-password-feedback class="hidden text-xs text-red-700" role="status" aria-live="polite"></p>
                                        <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Create manager login</button>
                                    </form>
                                </dialog>
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

@push('scripts')
    <script>
        document.addEventListener('click', (event) => {
            const opener = event.target.closest('[data-open-dialog]');
            if (opener) {
                document.getElementById(opener.dataset.openDialog)?.showModal();
                return;
            }

            const closer = event.target.closest('[data-close-dialog]');
            if (closer) {
                closer.closest('dialog')?.close();
            }
        });

        const passwordRules = {
            length: (value) => value.length >= 12,
            uppercase: (value) => /\p{Lu}/u.test(value),
            lowercase: (value) => /\p{Ll}/u.test(value),
            number: (value) => /\p{N}/u.test(value),
            symbol: (value) => /[\p{P}\p{S}]/u.test(value),
        };

        document.querySelectorAll('[data-password-form]').forEach((form) => {
            const passwordInput = form.querySelector('[data-password-input]');
            const confirmationInput = form.querySelector('[data-password-confirmation]');
            const feedback = form.querySelector('[data-password-feedback]');

            const updatePasswordGuidance = () => {
                const value = passwordInput.value;
                const results = Object.fromEntries(
                    Object.entries(passwordRules).map(([rule, check]) => [rule, check(value)])
                );

                form.querySelectorAll('[data-password-rule]').forEach((item) => {
                    const passed = results[item.dataset.passwordRule];
                    item.classList.toggle('font-semibold', passed);
                    item.classList.toggle('text-green-800', passed);
                    item.classList.toggle('text-gray-500', !passed);
                    item.querySelector('[data-rule-icon]').textContent = passed ? '✓' : '○';
                });

                if (!feedback) {
                    return results;
                }

                const messages = [];
                if (Object.values(results).some((passed) => !passed)) {
                    messages.push('Meet all password requirements above.');
                }
                if (confirmationInput.value && confirmationInput.value !== value) {
                    messages.push('The password confirmation does not match.');
                }
                feedback.textContent = messages.join(' ');
                feedback.classList.toggle('hidden', messages.length === 0);

                return results;
            };

            passwordInput.addEventListener('input', updatePasswordGuidance);
            confirmationInput.addEventListener('input', updatePasswordGuidance);
            form.addEventListener('submit', (event) => {
                const results = updatePasswordGuidance();
                const validPassword = Object.values(results).every(Boolean);
                const validConfirmation = confirmationInput.value === passwordInput.value;

                if (!validPassword || !validConfirmation) {
                    event.preventDefault();
                    (validPassword ? confirmationInput : passwordInput).focus();
                }
            });
        });

        const invalidDialogId = @json(old('_dialog_id'));
        if (invalidDialogId) {
            document.getElementById(invalidDialogId)?.showModal();
        }
    </script>
@endpush
