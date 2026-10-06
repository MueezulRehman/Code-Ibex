                    @if($moduleEnabled('hospital-emergency') || $moduleEnabled('medical') || $moduleEnabled('pharmacy') || $moduleEnabled('hospital-medication') || $moduleEnabled('hospital-laboratory') || $moduleEnabled('hospital-radiology') || $moduleEnabled('hospital-departments') || $moduleEnabled('hospital-admissions') || $moduleEnabled('hospital-wards-beds') || $moduleEnabled('hospital-nursing') || $moduleEnabled('hospital-staff-rosters') || $moduleEnabled('hospital-vital-signs') || $moduleEnabled('hospital-clinical-notes') || $moduleEnabled('hospital-care-plans') || $moduleEnabled('hospital-operating-theatre') || $moduleEnabled('hospital-billing') || $moduleEnabled('insurance'))
                        <div class="pt-4 pb-1">
                            <p class="nav-section-label">Hospital operations</p>
                        </div>
                    @endif

                    @if($moduleEnabled('hospital-emergency'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.hospital-emergency.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.hospital-emergency.index') }}" title="Emergency and trauma"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.hospital-emergency.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-truck-medical text-lg"></i><span class="flex-1 truncate">Emergency &amp; Trauma</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Emergency and trauma">
                                <a href="{{ route('manager.hospital-emergency.index') }}" class="{{ request()->routeIs('manager.hospital-emergency.*') ? 'is-active' : '' }}"><i class="fas fa-triage"></i><span>Emergency intake &amp; triage</span></a>
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('medical') || $moduleEnabled('pharmacy'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.medicines.*') || request()->routeIs('manager.purchases.*') || request()->routeIs('manager.controlled-medicines.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.medicines.index') }}" title="Pharmacy"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.medicines.*') || request()->routeIs('manager.purchases.*') || request()->routeIs('manager.controlled-medicines.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-pills text-lg"></i><span class="flex-1 truncate">Pharmacy</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Pharmacy">
                                @if($moduleEnabled('medical')) <a href="{{ route('manager.medicines.index') }}" class="{{ request()->routeIs('manager.medicines.*') ? 'is-active' : '' }}"><i class="fas fa-pills"></i><span>Medicines</span></a>@endif
                                @if($moduleEnabled('pharmacy') || $moduleEnabled('medical')) <a href="{{ route('manager.purchases.index') }}" class="{{ request()->routeIs('manager.purchases.*') ? 'is-active' : '' }}"><i class="fas fa-box-open"></i><span>Purchases</span></a>@endif
                                @if($moduleEnabled('controlled-medicines')) <a href="{{ route('manager.controlled-medicines.index') }}" class="{{ request()->routeIs('manager.controlled-medicines.*') ? 'is-active' : '' }}"><i class="fas fa-file-prescription"></i><span>Controlled medicines</span></a>@endif
                                @if($moduleEnabled('hospital-medication')) <a href="{{ route('manager.hospital-medication.index') }}" class="{{ request()->routeIs('manager.hospital-medication.*') ? 'is-active' : '' }}"><i class="fas fa-syringe"></i><span>Medication administration</span></a>@endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('hospital-laboratory') || $moduleEnabled('hospital-radiology'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.laboratory.*') || request()->routeIs('manager.radiology.*') ? 'has-active' : '' }}">
                            <a href="{{ $moduleEnabled('hospital-laboratory') ? route('manager.laboratory.index') : route('manager.radiology.index') }}" title="Laboratory and diagnostics"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.laboratory.*') || request()->routeIs('manager.radiology.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-flask text-lg"></i><span class="flex-1 truncate">Laboratory &amp; Diagnostics</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Laboratory and diagnostics">
                                @if($moduleEnabled('hospital-laboratory')) <a href="{{ route('manager.laboratory.index') }}" class="{{ request()->routeIs('manager.laboratory.*') ? 'is-active' : '' }}"><i class="fas fa-flask"></i><span>Laboratory</span></a>@endif
                                @if($moduleEnabled('hospital-radiology')) <a href="{{ route('manager.radiology.index') }}" class="{{ request()->routeIs('manager.radiology.*') ? 'is-active' : '' }}"><i class="fas fa-x-ray"></i><span>Radiology</span></a>@endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('hospital-departments') || $moduleEnabled('hospital-admissions') || $moduleEnabled('hospital-wards-beds') || $moduleEnabled('hospital-nursing') || $moduleEnabled('hospital-staff-rosters') || $moduleEnabled('hospital-vital-signs') || $moduleEnabled('hospital-clinical-notes') || $moduleEnabled('hospital-care-plans'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.departments.*') || request()->routeIs('manager.admissions.*') || request()->routeIs('manager.wards.*') || request()->routeIs('manager.nursing.*') || request()->routeIs('manager.staff-rosters.*') || request()->routeIs('manager.vitals.*') || request()->routeIs('manager.hospital-clinical-notes.*') || request()->routeIs('manager.hospital-care-plans.*') ? 'has-active' : '' }}">
                            <a href="{{ $moduleEnabled('hospital-admissions') ? route('manager.admissions.index') : route('manager.departments.index') }}" title="Inpatient care"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.departments.*') || request()->routeIs('manager.admissions.*') || request()->routeIs('manager.wards.*') || request()->routeIs('manager.nursing.*') || request()->routeIs('manager.staff-rosters.*') || request()->routeIs('manager.vitals.*') || request()->routeIs('manager.hospital-clinical-notes.*') || request()->routeIs('manager.hospital-care-plans.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-bed-pulse text-lg"></i><span class="flex-1 truncate">Inpatient Care</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Inpatient care">
                                @if($moduleEnabled('hospital-departments')) <a href="{{ route('manager.departments.index') }}" class="{{ request()->routeIs('manager.departments.*') ? 'is-active' : '' }}"><i class="fas fa-building-columns"></i><span>Departments</span></a>@endif
                                @if($moduleEnabled('hospital-admissions')) <a href="{{ route('manager.admissions.index') }}" class="{{ request()->routeIs('manager.admissions.*') ? 'is-active' : '' }}"><i class="fas fa-bed-pulse"></i><span>Admissions</span></a>@endif
                                @if($moduleEnabled('hospital-wards-beds')) <a href="{{ route('manager.wards.index') }}" class="{{ request()->routeIs('manager.wards.*') ? 'is-active' : '' }}"><i class="fas fa-bed"></i><span>Wards &amp; beds</span></a>@endif
                                @if($moduleEnabled('hospital-nursing')) <a href="{{ route('manager.nursing.index') }}" class="{{ request()->routeIs('manager.nursing.*') ? 'is-active' : '' }}"><i class="fas fa-user-nurse"></i><span>Nursing assignments</span></a>@endif
                                @if($moduleEnabled('hospital-staff-rosters')) <a href="{{ route('manager.staff-rosters.index') }}" class="{{ request()->routeIs('manager.staff-rosters.*') ? 'is-active' : '' }}"><i class="fas fa-calendar-days"></i><span>Staff rosters</span></a>@endif
                                @if($moduleEnabled('hospital-vital-signs')) <a href="{{ route('manager.vitals.index') }}" class="{{ request()->routeIs('manager.vitals.*') ? 'is-active' : '' }}"><i class="fas fa-heart-pulse"></i><span>Vital signs</span></a>@endif
                                @if($moduleEnabled('hospital-clinical-notes')) <a href="{{ route('manager.hospital-clinical-notes.index') }}" class="{{ request()->routeIs('manager.hospital-clinical-notes.*') ? 'is-active' : '' }}"><i class="fas fa-notes-medical"></i><span>Clinical notes</span></a>@endif
                                @if($moduleEnabled('hospital-care-plans')) <a href="{{ route('manager.hospital-care-plans.index') }}" class="{{ request()->routeIs('manager.hospital-care-plans.*') ? 'is-active' : '' }}"><i class="fas fa-clipboard-list"></i><span>Care plans</span></a>@endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('hospital-operating-theatre'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.operating-theatre.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.operating-theatre.index') }}" title="Operating theatre"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.operating-theatre.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-person-surgery text-lg"></i><span class="flex-1 truncate">Operating Theatre</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Operating theatre">
                                <a href="{{ route('manager.operating-theatre.index') }}" class="{{ request()->routeIs('manager.operating-theatre.*') ? 'is-active' : '' }}"><i class="fas fa-person-surgery"></i><span>Rooms &amp; surgeries</span></a>
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('hospital-billing') || $moduleEnabled('insurance'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.hospital-billing.*') || request()->routeIs('manager.insurance.*') ? 'has-active' : '' }}">
                            <a href="{{ $moduleEnabled('hospital-billing') ? route('manager.hospital-billing.index') : route('manager.insurance.index') }}" title="Billing and insurance"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.hospital-billing.*') || request()->routeIs('manager.insurance.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-file-invoice-dollar text-lg"></i><span class="flex-1 truncate">Billing &amp; Insurance</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Billing and insurance">
                                @if($moduleEnabled('hospital-billing')) <a href="{{ route('manager.hospital-billing.index') }}" class="{{ request()->routeIs('manager.hospital-billing.*') ? 'is-active' : '' }}"><i class="fas fa-file-invoice-dollar"></i><span>Hospital billing</span></a>@endif
                                @if($moduleEnabled('hospital-billing')) <a href="{{ route('manager.hospital-billing.insurance.index') }}" class="{{ request()->routeIs('manager.hospital-billing.insurance.*') ? 'is-active' : '' }}"><i class="fas fa-id-card"></i><span>Patient insurance</span></a>@endif
                                @if($moduleEnabled('insurance')) <a href="{{ route('manager.insurance.index') }}" class="{{ request()->routeIs('manager.insurance.*') ? 'is-active' : '' }}"><i class="fas fa-shield-heart"></i><span>Providers &amp; claims</span></a>@endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('reports') || $moduleEnabled('hospital-admissions'))
                        <p class="nav-section-label">Reports</p>
                        <div class="nav-dropdown {{ request()->routeIs('manager.reports.*') || request()->routeIs('manager.hospital-reports.*') ? 'has-active' : '' }}">
                            <a href="{{ $moduleEnabled('hospital-admissions') ? route('manager.hospital-reports.index') : route('manager.reports.index') }}" title="Reports"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.reports.*') || request()->routeIs('manager.hospital-reports.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-chart-column text-lg"></i><span class="flex-1 truncate">Reports</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Hospital reports">
                                @if($moduleEnabled('hospital-admissions')) <a href="{{ route('manager.hospital-reports.index') }}" class="{{ request()->routeIs('manager.hospital-reports.*') ? 'is-active' : '' }}"><i class="fas fa-chart-line"></i><span>Hospital operations</span></a>@endif
                                @if($moduleEnabled('reports')) <a href="{{ route('manager.reports.index') }}" class="{{ request()->routeIs('manager.reports.*') ? 'is-active' : '' }}"><i class="fas fa-table"></i><span>All reports</span></a>@endif
                            </div>
                        </div>
                    @endif
