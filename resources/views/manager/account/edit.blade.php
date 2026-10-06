@extends('manager.layout.master')

@section('title', 'My Account')

@section('page-content')
<div class="max-w-4xl space-y-6">

    @if(session('success'))
        <div class="bg-hut-green/10 border border-hut-green/30 text-hut-dark text-sm rounded-lg p-3">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass-strong card-hover rounded-2xl p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-hut-dark">My Account</h2>
            <p class="text-sm text-gray-500">Update your own login name, email and phone number.</p>
        </div>

        @if($errors->any() && !$errors->has('current_password') && !$errors->has('password'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('manager.account.update') }}" method="POST" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded-lg bg-hut-dark px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <div class="glass-strong card-hover rounded-2xl p-6">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-hut-dark">Change Password</h2>
            <p class="text-sm text-gray-500">Use a strong password you don't use anywhere else.</p>
        </div>

        @if($errors->has('current_password') || $errors->has('password'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @if($errors->has('current_password'))
                        <li>{{ $errors->first('current_password') }}</li>
                    @endif
                    @if($errors->has('password'))
                        <li>{{ $errors->first('password') }}</li>
                    @endif
                </ul>
            </div>
        @endif

        <form action="{{ route('manager.account.password') }}" method="POST" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Current Password</label>
                    <input type="password" name="current_password" required class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div></div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">New Password</label>
                    <input type="password" name="password" required class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <input type="password" name="password_confirmation" required class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded-lg bg-hut-dark px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                    Update Password
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
