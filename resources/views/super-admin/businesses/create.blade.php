@extends('super-admin.layout.master')

@section('title', 'Register Business')

@section('page-content')
    <div class="max-w-4xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-hut-dark">Register a New Business</h2>
            <p class="text-sm text-gray-500">Create a business account, assign a custom domain, choose a type and modules,
                and generate the initial owner credentials.</p>
        </div>

        <form action="{{ route('admin.restaurants.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6"
            data-auto-apply-modules="1">
            @csrf

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Business Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug') }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                    <p class="text-xs text-gray-500 mt-1">Used in the public URL: yoursite.com/<strong>slug</strong></p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Business Type</label>
                    <select name="business_type_id" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach($businessTypes as $businessType)
                            <option value="{{ $businessType->id }}" {{ old('business_type_id') == $businessType->id ? 'selected' : '' }}>{{ $businessType->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Modules</label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($modules as $module)
                            <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                <input type="checkbox" name="enabled_modules[]" value="{{ $module->id }}"
                                    data-module-key="{{ $module->key }}" {{ in_array($module->id, old('enabled_modules', $selectedModules)) ? 'checked' : '' }} class="form-checkbox h-4 w-4 text-hut-dark" />
                                <span class="text-sm text-gray-700">{{ $module->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Defaults come from the selected business type. Limited by the
                        subscription plan’s module cap. Manager-specific access can be assigned after the business is
                        created.</p>
                    <p id="module-cap-notice" class="text-xs text-gray-500 mt-1"></p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Custom Domain</label>
                    <input type="text" name="custom_domain" value="{{ old('custom_domain') }}" placeholder="business.com"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Business Domain</label>
                    <input type="text" name="domain" value="{{ old('domain') }}" placeholder="example.com"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Tenant Database Configuration</label>
                    <textarea name="db_connection" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        placeholder='{"driver":"mysql","host":"127.0.0.1","database":"tenant_db","username":"tenant_user","password":"secret"}'>{{ old('db_connection') }}</textarea>
                    <p class="text-xs text-gray-500 mt-2">Leave blank to create a dedicated tenant database using the
                        configured tenant prefix and database credentials. If your host requires databases to be created
                        in its control panel, create an empty database, grant the configured database user access, and
                        enter its connection details here.</p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Plan</label>
                    <select name="plan" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @forelse($subscriptionPlans as $plan)
                            <option value="{{ $plan->slug }}" {{ old('plan', $selectedPlanSlug) === $plan->slug ? 'selected' : '' }}>{{ $plan->name }}@if($plan->max_modules) (max {{ $plan->max_modules }} modules)@endif
                            </option>
                        @empty
                            <option value="" selected>No subscription plans available yet</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="trial" {{ old('status', 'trial') === 'trial' ? 'selected' : '' }}>Trial</option>
                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Address</label>
                    <input type="text" name="address" value="{{ old('address') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <x-page.file name="logo_path" label="Business logo" accept="image/*"
                    help="Use a square PNG, JPG, or WEBP logo." />
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Customer Menu Template</label>
                    <select name="customer_template" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach($customerTemplates as $templateKey => $templateLabel)
                            <option value="{{ $templateKey }}" {{ $selectedTemplate === $templateKey ? 'selected' : '' }}>
                                {{ $templateLabel }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-2">Storefront design for this business’s public menu.</p>
                </div>
            </div>

            @include('super-admin.businesses._homepage_fields', ['restaurant' => null])

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <h3 class="mb-4 text-lg font-semibold text-hut-dark">Business Owner Account</h3>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Owner Name</label>
                        <input type="text" name="owner_name" value="{{ old('owner_name') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Owner Email</label>
                        <input type="email" name="owner_email" value="{{ old('owner_email') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Owner Phone</label>
                        <input type="text" name="owner_phone" value="{{ old('owner_phone') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Owner Password</label>
                        <input type="password" name="owner_password" autocomplete="new-password"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                    class="rounded-lg bg-hut-dark px-4 py-2 font-semibold text-white hover:bg-gray-800">Create
                    Business</button>
                <a href="{{ route('admin.restaurants.index') }}" class="text-sm text-gray-500 hover:text-hut-dark">←
                    Back</a>
            </div>
        </form>
    </div>

    @include('super-admin.businesses._module-plan-script')
@endsection