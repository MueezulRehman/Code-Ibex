<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change temporary password</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 px-4 py-10 text-slate-900">
    <main class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
        <h1 class="text-2xl font-bold">Set a new password</h1>
        <p class="mt-2 text-sm text-slate-600">
            @if ($isRequired)
                Your Super Admin issued a temporary password. Change it now to continue.
            @else
                Confirm your current password and choose a new one.
            @endif
        </p>

        @if ($errors->any())
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.required.update') }}" class="mt-6 space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label for="temporary_password" class="block text-sm font-medium">{{ $isRequired ? 'Temporary password' : 'Current password' }}</label>
                <input id="temporary_password" name="temporary_password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium">New password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <p class="mt-1 text-xs text-slate-500">Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <button class="w-full rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white hover:bg-slate-700">Change password and continue</button>
        </form>
    </main>
</body>
</html>
