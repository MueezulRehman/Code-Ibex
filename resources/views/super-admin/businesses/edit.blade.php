@extends('super-admin.layout.master')

@section('title', 'Edit Business')

@section('page-content')
    <div class="max-w-4xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-hut-dark">Edit Business</h2>
            <p class="text-sm text-gray-500">Update the business profile, plan, modules, and custom domain.</p>
        </div>

        <form action="{{ route('admin.restaurants.update', $restaurant) }}" method="POST" enctype="multipart/form-data"
            class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Business Name</label>
                    <input type="text" name="name" value="{{ old('name', $restaurant->name) }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $restaurant->slug) }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                    <p class="text-xs text-gray-500 mt-1">Public URL: yoursite.com/<strong>{{ $restaurant->slug }}</strong>
                    </p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Business Type</label>
                    <select name="business_type_id" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach($businessTypes as $businessType)
                            <option value="{{ $businessType->id }}" {{ old('business_type_id', $restaurant->business_type_id) == $businessType->id ? 'selected' : '' }}>
                                {{ $businessType->display_name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Changing type keeps your current module selection. Add or remove
                        modules below as needed.</p>
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
                    <p class="text-xs text-gray-500 mt-2">The business type supplies recommendations; platform admins can
                        add or remove any active module within the plan limit. These settings enable modules for the
                        business; individual managers with explicit grants may still need access assigned separately.</p>
                    @if(($inactiveModules ?? collect())->isNotEmpty())
                        <div class="mt-4 rounded-xl border border-dashed border-amber-300 bg-amber-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Planned modules — not enabled</p>
                            <p class="mt-1 text-xs text-amber-700">These workflows are intentionally disabled until their routes,
                                tenant schema, and authorization are implemented.</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach($inactiveModules as $module)
                                    <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-white/70 px-3 py-2 text-sm text-amber-900">
                                        <i class="fas fa-lock text-xs" aria-hidden="true"></i>
                                        <span>{{ $module->name }}</span>
                                        <span class="ml-auto text-[10px] uppercase tracking-wide text-amber-600">Unavailable</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <p id="module-cap-notice" class="text-xs text-gray-500 mt-1"></p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="{{ old('email', $restaurant->email) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $restaurant->phone) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Custom Domain</label>
                    <input type="text" name="custom_domain" value="{{ old('custom_domain', $restaurant->custom_domain) }}"
                        placeholder="business.com" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Business Domain</label>
                    <input type="text" name="domain" value="{{ old('domain', $restaurant->domain) }}"
                        placeholder="example.com" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Tenant Database Configuration</label>
                    <textarea name="db_connection" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2"
                        placeholder='{"driver":"mysql","host":"127.0.0.1","database":"tenant_db","username":"tenant_user","password":"secret"}'>{{ old('db_connection', is_array($restaurant->db_connection) ? json_encode($restaurant->db_connection) : '') }}</textarea>
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
                        <option value="trial" {{ old('status', $restaurant->status) === 'trial' ? 'selected' : '' }}>Trial
                        </option>
                        <option value="active" {{ old('status', $restaurant->status) === 'active' ? 'selected' : '' }}>Active
                        </option>
                        <option value="suspended" {{ old('status', $restaurant->status) === 'suspended' ? 'selected' : '' }}>
                            Suspended</option>
                        <option value="cancelled" {{ old('status', $restaurant->status) === 'cancelled' ? 'selected' : '' }}>
                            Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Address</label>
                    <input type="text" name="address" value="{{ old('address', $restaurant->address) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>
                <div>
                    <x-page.file name="logo_path" label="Business logo" accept="image/*"
                        help="Upload a replacement only when the business logo changes." />
                    @if($restaurant->logo_path)
                        <p class="text-xs text-gray-500 mt-2">Current logo: <span
                                class="font-medium">{{ basename($restaurant->logo_path) }}</span></p>
                    @endif
                </div>
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

            @include('super-admin.businesses._homepage_fields', ['restaurant' => $restaurant])

            <div class="flex items-center gap-3">
                <button type="submit"
                    class="rounded-lg bg-hut-dark px-4 py-2 font-semibold text-white hover:bg-gray-800">Update
                    Business</button>
                <a href="{{ route('admin.restaurants.index') }}" class="text-sm text-gray-500 hover:text-hut-dark">←
                    Back</a>
            </div>
        </form>
    </div>

    @include('super-admin.businesses._module-plan-script')
@endsection