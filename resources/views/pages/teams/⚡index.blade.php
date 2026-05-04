<?php

use App\Models\Team;
use App\Models\TeamMember;
use App\Services\PhilippineLocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('My Teams')] class extends Component
{
    use WithFileUploads;

    #[Url(as: 'team')]
    public ?int $selectedTeamId = null;

    public ?int $editingTeamId = null;

    public ?int $editingMemberId = null;

    public ?string $status = null;

    public array $provinceOptions = [];

    public array $teamCityOptions = [];

    public array $teamBarangayOptions = [];

    public array $memberCityOptions = [];

    public array $memberBarangayOptions = [];

    public string $team_name = '';

    public string $team_province_code = '';

    public string $team_province = '';

    public string $team_city_code = '';

    public string $team_city = '';

    public string $team_barangay_code = '';

    public string $team_barangay = '';

    public $logo;

    public string $member_name = '';

    public string $member_nickname = '';

    public string $member_gender = '';

    public string $member_age = '';

    public string $member_province_code = '';

    public string $member_province = '';

    public string $member_city_code = '';

    public string $member_city = '';

    public string $member_barangay_code = '';

    public string $member_barangay = '';

    public string $member_role = 'member';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->ensureCaptainAccess();
        $this->provinceOptions = $this->locationService()->provinces()->all();
        $this->selectedTeamId ??= $this->teams->first()?->id;
    }

    /**
     * Teams owned by the authenticated user.
     */
    #[Computed]
    public function teams()
    {
        $this->ensureCaptainAccess();

        return Auth::user()
            ->ownedTeams()
            ->with(['members' => fn ($query) => $query->withCount('matchStats')])
            ->withCount(['members', 'registrations'])
            ->latest()
            ->get();
    }

    /**
     * The team currently being managed.
     */
    #[Computed]
    public function selectedTeam(): ?Team
    {
        return $this->teams->firstWhere('id', $this->selectedTeamId)
            ?? $this->teams->first();
    }

    /**
     * Switch the roster management panel to another team.
     */
    public function selectTeam(int $teamId): void
    {
        if (! $this->teams->contains('id', $teamId)) {
            return;
        }

        if ($this->editingTeamId !== null && $this->editingTeamId !== $teamId) {
            $this->cancelTeamEdit();
        }

        if ($this->editingMemberId !== null) {
            $this->cancelMemberEdit();
        }

        $this->selectedTeamId = $teamId;
    }

    /**
     * Load an existing team into the team form for editing.
     */
    public function editTeam(int $teamId): void
    {
        $this->status = null;

        $team = $this->teams->firstWhere('id', $teamId);

        if (! $team) {
            return;
        }

        $this->selectedTeamId = $team->id;
        $this->editingMemberId = null;
        $this->populateTeamForm($team);
        $this->editingTeamId = $team->id;
        $this->resetValidation();
    }

    /**
     * Refresh team-level city options when a province changes.
     */
    public function updatedTeamProvinceCode(string $value): void
    {
        $this->team_province = $this->findLocationName($this->provinceOptions, $value);
        $this->team_city_code = '';
        $this->team_city = '';
        $this->team_barangay_code = '';
        $this->team_barangay = '';
        $this->teamBarangayOptions = [];
        $this->teamCityOptions = $value !== ''
            ? $this->locationService()->citiesByProvince($value)->all()
            : [];
    }

    /**
     * Refresh team-level barangay options when a city changes.
     */
    public function updatedTeamCityCode(string $value): void
    {
        $this->team_city = $this->findLocationName($this->teamCityOptions, $value);
        $this->team_barangay_code = '';
        $this->team_barangay = '';
        $this->teamBarangayOptions = $value !== ''
            ? $this->locationService()->barangaysByCity($value)->all()
            : [];
    }

    /**
     * Persist the selected team barangay label.
     */
    public function updatedTeamBarangayCode(string $value): void
    {
        $this->team_barangay = $this->findLocationName($this->teamBarangayOptions, $value);
    }

    /**
     * Refresh member-level city options when a province changes.
     */
    public function updatedMemberProvinceCode(string $value): void
    {
        $this->member_province = $this->findLocationName($this->provinceOptions, $value);
        $this->member_city_code = '';
        $this->member_city = '';
        $this->member_barangay_code = '';
        $this->member_barangay = '';
        $this->memberBarangayOptions = [];
        $this->memberCityOptions = $value !== ''
            ? $this->locationService()->citiesByProvince($value)->all()
            : [];
    }

    /**
     * Refresh member-level barangay options when a city changes.
     */
    public function updatedMemberCityCode(string $value): void
    {
        $this->member_city = $this->findLocationName($this->memberCityOptions, $value);
        $this->member_barangay_code = '';
        $this->member_barangay = '';
        $this->memberBarangayOptions = $value !== ''
            ? $this->locationService()->barangaysByCity($value)->all()
            : [];
    }

    /**
     * Persist the selected member barangay label.
     */
    public function updatedMemberBarangayCode(string $value): void
    {
        $this->member_barangay = $this->findLocationName($this->memberBarangayOptions, $value);
    }

    /**
     * Register a new team for the authenticated user.
     */
    public function createTeam(): void
    {
        $this->ensureCaptainAccess();
        $this->status = null;
        $this->hydrateTeamLocationState();

        $validated = $this->validate([
            'team_name' => ['required', 'string', 'max:255'],
            'team_province_code' => ['required', 'string'],
            'team_province' => ['required', 'string', 'max:255'],
            'team_city_code' => ['required', 'string'],
            'team_city' => ['required', 'string', 'max:255'],
            'team_barangay_code' => ['required', 'string'],
            'team_barangay' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', Rule::imageFile(allowSvg: true)->max(2048)],
        ]);

        $team = Auth::user()->ownedTeams()->create([
            'name' => $validated['team_name'],
            'address' => $validated['team_barangay'],
            'city' => $validated['team_city'],
            'province' => $validated['team_province'],
            'country_name' => 'Philippines',
            'logo_path' => $this->logo?->store('team-logos', 'public'),
        ]);

        $this->resetTeamForm();
        $this->resetValidation();
        $this->refreshTeamState();

        $this->selectedTeamId = $team->id;
        $this->status = 'team-created';
    }

    /**
     * Update an existing team owned by the authenticated user.
     */
    public function updateTeam(): void
    {
        $this->ensureCaptainAccess();
        $this->status = null;

        $team = $this->teams->firstWhere('id', $this->editingTeamId);

        if (! $team) {
            return;
        }

        $this->hydrateTeamLocationState();

        $validated = $this->validate([
            'team_name' => ['required', 'string', 'max:255'],
            'team_province_code' => ['required', 'string'],
            'team_province' => ['required', 'string', 'max:255'],
            'team_city_code' => ['required', 'string'],
            'team_city' => ['required', 'string', 'max:255'],
            'team_barangay_code' => ['required', 'string'],
            'team_barangay' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', Rule::imageFile(allowSvg: true)->max(2048)],
        ]);

        $logoPath = $team->logo_path;

        if ($this->logo) {
            $team->deleteStoredLogo();
            $logoPath = $this->logo->store('team-logos', 'public');
        }

        $team->update([
            'name' => $validated['team_name'],
            'address' => $validated['team_barangay'],
            'city' => $validated['team_city'],
            'province' => $validated['team_province'],
            'country_name' => 'Philippines',
            'logo_path' => $logoPath,
        ]);

        $teamId = $team->id;

        $this->editingTeamId = null;
        $this->resetTeamForm();
        $this->resetValidation();
        $this->refreshTeamState();

        $this->selectedTeamId = $teamId;
        $this->status = 'team-updated';
    }

    /**
     * Add a roster member to the selected team.
     */
    public function addMember(): void
    {
        $this->ensureCaptainAccess();
        $this->status = null;

        $team = $this->selectedTeam;

        if (! $team) {
            return;
        }

        $this->hydrateMemberLocationState();

        $validated = $this->validate([
            'member_name' => ['required', 'string', 'max:255'],
            'member_nickname' => ['required', 'string', 'max:255'],
            'member_gender' => ['required', 'string', 'max:50'],
            'member_age' => ['required', 'integer', 'min:1', 'max:99'],
            'member_province_code' => ['required', 'string'],
            'member_province' => ['required', 'string', 'max:255'],
            'member_city_code' => ['required', 'string'],
            'member_city' => ['required', 'string', 'max:255'],
            'member_barangay_code' => ['required', 'string'],
            'member_barangay' => ['required', 'string', 'max:255'],
            'member_role' => ['required', Rule::in(['captain', 'spirit_captain', 'member'])],
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'name' => $validated['member_name'],
            'nickname' => $validated['member_nickname'],
            'gender' => $validated['member_gender'],
            'age' => (int) $validated['member_age'],
            'address' => $this->formatPhilippineAddress(
                $validated['member_barangay'],
                $validated['member_city'],
                $validated['member_province'],
            ),
            'role' => $validated['member_role'],
        ]);

        $this->resetMemberForm();
        $this->resetValidation();
        $this->refreshTeamState();

        $this->selectedTeamId = $team->id;
        $this->status = 'member-created';
    }

    /**
     * Load a roster member into the member form for editing.
     */
    public function editMember(int $memberId): void
    {
        $this->ensureCaptainAccess();
        $this->status = null;

        $team = $this->selectedTeam;

        if (! $team) {
            return;
        }

        $member = $team->members()->findOrFail($memberId);

        $this->populateMemberForm($member);
        $this->editingMemberId = $member->id;
        $this->resetValidation();
    }

    /**
     * Update a roster member for the selected team.
     */
    public function updateMember(): void
    {
        $this->ensureCaptainAccess();
        $this->status = null;

        $team = $this->selectedTeam;

        if (! $team) {
            return;
        }

        $member = $team->members()->find($this->editingMemberId);

        if (! $member) {
            return;
        }

        $this->hydrateMemberLocationState();

        $validated = $this->validate([
            'member_name' => ['required', 'string', 'max:255'],
            'member_nickname' => ['required', 'string', 'max:255'],
            'member_gender' => ['required', 'string', 'max:50'],
            'member_age' => ['required', 'integer', 'min:1', 'max:99'],
            'member_province_code' => ['required', 'string'],
            'member_province' => ['required', 'string', 'max:255'],
            'member_city_code' => ['required', 'string'],
            'member_city' => ['required', 'string', 'max:255'],
            'member_barangay_code' => ['required', 'string'],
            'member_barangay' => ['required', 'string', 'max:255'],
            'member_role' => ['required', Rule::in(['captain', 'spirit_captain', 'member'])],
        ]);

        $member->update([
            'name' => $validated['member_name'],
            'nickname' => $validated['member_nickname'],
            'gender' => $validated['member_gender'],
            'age' => (int) $validated['member_age'],
            'address' => $this->formatPhilippineAddress(
                $validated['member_barangay'],
                $validated['member_city'],
                $validated['member_province'],
            ),
            'role' => $validated['member_role'],
        ]);

        $teamId = $team->id;

        $this->editingMemberId = null;
        $this->resetMemberForm();
        $this->resetValidation();
        $this->refreshTeamState();

        $this->selectedTeamId = $teamId;
        $this->status = 'member-updated';
    }

    /**
     * Delete a roster member from the selected team.
     */
    public function deleteMember(int $memberId): void
    {
        $this->ensureCaptainAccess();
        $this->status = null;

        $team = $this->selectedTeam;

        if (! $team) {
            return;
        }

        $member = $team->members()->findOrFail($memberId);

        if ($member->matchStats()->exists()) {
            $this->status = 'member-delete-blocked';

            return;
        }

        $member->delete();

        if ($this->editingMemberId === $memberId) {
            $this->cancelMemberEdit();
        }

        $this->refreshTeamState();

        $this->status = 'member-deleted';
    }

    /**
     * Delete one of the authenticated user's teams when it has no tournament history.
     */
    public function deleteTeam(int $teamId): void
    {
        $this->ensureCaptainAccess();
        $this->status = null;

        $team = $this->teams->firstWhere('id', $teamId);

        if (! $team) {
            return;
        }

        if ($team->hasRecordedActivity()) {
            $this->status = 'team-delete-blocked';

            return;
        }

        $deletedSelectedTeam = $this->selectedTeamId === $team->id;

        if ($this->editingTeamId === $team->id) {
            $this->cancelTeamEdit();
        }

        if ($deletedSelectedTeam) {
            $this->cancelMemberEdit();
        }

        $team->deleteStoredLogo();
        $team->delete();

        $this->refreshTeamState();

        if ($deletedSelectedTeam) {
            $this->selectedTeamId = $this->teams->first()?->id;
        }

        $this->status = 'team-deleted';
    }

    /**
     * Exit team edit mode and clear the form.
     */
    public function cancelTeamEdit(): void
    {
        $this->ensureCaptainAccess();
        $this->editingTeamId = null;
        $this->resetTeamForm();
        $this->resetValidation();
    }

    /**
     * Exit member edit mode and clear the form.
     */
    public function cancelMemberEdit(): void
    {
        $this->ensureCaptainAccess();
        $this->editingMemberId = null;
        $this->resetMemberForm();
        $this->resetValidation();
    }

    /**
     * Ensure only captain accounts can manage teams.
     */
    protected function ensureCaptainAccess(): void
    {
        abort_unless(Auth::user()?->isCaptain(), 403);
    }

    /**
     * Normalize optional text fields before persistence.
     */
    protected function normalizeNullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Resolve a location option label by code.
     *
     * @param  list<array{code: string, name: string}>  $options
     */
    protected function findLocationName(array $options, string $code): string
    {
        foreach ($options as $option) {
            if (($option['code'] ?? null) === $code) {
                return (string) ($option['name'] ?? '');
            }
        }

        return '';
    }

    /**
     * Resolve a location option code by label.
     *
     * @param  list<array{code: string, name: string}>  $options
     */
    protected function findLocationCode(array $options, ?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        foreach ($options as $option) {
            if (strcasecmp((string) ($option['name'] ?? ''), $name) === 0) {
                return (string) ($option['code'] ?? '');
            }
        }

        return '';
    }

    /**
     * Ensure team dropdown state is fully hydrated before validation.
     */
    protected function hydrateTeamLocationState(): void
    {
        if ($this->team_province_code !== '' && $this->teamCityOptions === []) {
            $this->teamCityOptions = $this->locationService()->citiesByProvince($this->team_province_code)->all();
        }

        if ($this->team_city_code !== '' && $this->teamBarangayOptions === []) {
            $this->teamBarangayOptions = $this->locationService()->barangaysByCity($this->team_city_code)->all();
        }

        $this->team_province = $this->findLocationName($this->provinceOptions, $this->team_province_code);
        $this->team_city = $this->findLocationName($this->teamCityOptions, $this->team_city_code);
        $this->team_barangay = $this->findLocationName($this->teamBarangayOptions, $this->team_barangay_code);
    }

    /**
     * Ensure member dropdown state is fully hydrated before validation.
     */
    protected function hydrateMemberLocationState(): void
    {
        if ($this->member_province_code !== '' && $this->memberCityOptions === []) {
            $this->memberCityOptions = $this->locationService()->citiesByProvince($this->member_province_code)->all();
        }

        if ($this->member_city_code !== '' && $this->memberBarangayOptions === []) {
            $this->memberBarangayOptions = $this->locationService()->barangaysByCity($this->member_city_code)->all();
        }

        $this->member_province = $this->findLocationName($this->provinceOptions, $this->member_province_code);
        $this->member_city = $this->findLocationName($this->memberCityOptions, $this->member_city_code);
        $this->member_barangay = $this->findLocationName($this->memberBarangayOptions, $this->member_barangay_code);
    }

    /**
     * Format a readable Philippine address string from dropdown selections.
     */
    protected function formatPhilippineAddress(string $barangay, string $city, string $province): string
    {
        return collect([$barangay, $city, $province])
            ->filter()
            ->implode(', ');
    }

    /**
     * Parse a persisted Philippine address back into its dropdown parts.
     *
     * @return array{barangay: string, city: string, province: string}
     */
    protected function parsePhilippineAddress(?string $address): array
    {
        $parts = collect(explode(',', (string) $address))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();

        return [
            'barangay' => (string) ($parts->get(0) ?? ''),
            'city' => (string) ($parts->get(1) ?? ''),
            'province' => (string) ($parts->get(2) ?? ''),
        ];
    }

    /**
     * Populate the team form from an existing team record.
     */
    protected function populateTeamForm(Team $team): void
    {
        $this->resetTeamForm();

        $this->team_name = (string) $team->name;
        $this->team_province = (string) ($team->province ?? '');
        $this->team_city = (string) ($team->city ?? '');
        $this->team_barangay = (string) ($team->address ?? '');
        $this->team_province_code = $this->findLocationCode($this->provinceOptions, $this->team_province);
        $this->teamCityOptions = $this->team_province_code !== ''
            ? $this->locationService()->citiesByProvince($this->team_province_code)->all()
            : [];
        $this->team_city_code = $this->findLocationCode($this->teamCityOptions, $this->team_city);
        $this->teamBarangayOptions = $this->team_city_code !== ''
            ? $this->locationService()->barangaysByCity($this->team_city_code)->all()
            : [];
        $this->team_barangay_code = $this->findLocationCode($this->teamBarangayOptions, $this->team_barangay);
    }

    /**
     * Populate the member form from an existing roster record.
     */
    protected function populateMemberForm(TeamMember $member): void
    {
        $this->resetMemberForm();

        $address = $this->parsePhilippineAddress($member->address);

        $this->member_name = (string) $member->name;
        $this->member_nickname = (string) ($member->nickname ?? '');
        $this->member_gender = (string) ($member->gender ?? '');
        $this->member_age = (string) ($member->age ?? '');
        $this->member_province = $address['province'];
        $this->member_city = $address['city'];
        $this->member_barangay = $address['barangay'];
        $this->member_role = (string) $member->role;
        $this->member_province_code = $this->findLocationCode($this->provinceOptions, $this->member_province);
        $this->memberCityOptions = $this->member_province_code !== ''
            ? $this->locationService()->citiesByProvince($this->member_province_code)->all()
            : [];
        $this->member_city_code = $this->findLocationCode($this->memberCityOptions, $this->member_city);
        $this->memberBarangayOptions = $this->member_city_code !== ''
            ? $this->locationService()->barangaysByCity($this->member_city_code)->all()
            : [];
        $this->member_barangay_code = $this->findLocationCode($this->memberBarangayOptions, $this->member_barangay);
    }

    /**
     * Resolve the configured location service.
     */
    protected function locationService(): PhilippineLocationService
    {
        return app(PhilippineLocationService::class);
    }

    /**
     * Reset the team registration form.
     */
    protected function resetTeamForm(): void
    {
        $this->reset(
            'team_name',
            'team_province_code',
            'team_province',
            'team_city_code',
            'team_city',
            'team_barangay_code',
            'team_barangay',
            'logo',
        );

        $this->teamCityOptions = [];
        $this->teamBarangayOptions = [];
    }

    /**
     * Reset the member entry form.
     */
    protected function resetMemberForm(): void
    {
        $this->reset(
            'member_name',
            'member_nickname',
            'member_gender',
            'member_age',
            'member_province_code',
            'member_province',
            'member_city_code',
            'member_city',
            'member_barangay_code',
            'member_barangay',
        );

        $this->memberCityOptions = [];
        $this->memberBarangayOptions = [];
        $this->member_role = 'member';
    }

    /**
     * Clear cached computed data after writes.
     */
    protected function refreshTeamState(): void
    {
        unset($this->teams);
        unset($this->selectedTeam);
    }
}; ?>

@php
    $teams = $this->teams;
    $selectedTeam = $this->selectedTeam;
    $editingTeamId = $this->editingTeamId;
    $editingMemberId = $this->editingMemberId;
    $provinceOptions = $this->provinceOptions;
    $teamCityOptions = $this->teamCityOptions;
    $teamBarangayOptions = $this->teamBarangayOptions;
    $memberCityOptions = $this->memberCityOptions;
    $memberBarangayOptions = $this->memberBarangayOptions;
@endphp

<div class="space-y-6">
    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:heading size="xl">{{ __('My Teams') }}</flux:heading>
        <flux:text class="mt-2 max-w-3xl">
            {{ __('Create your team profile, assign its Philippine location and logo, and maintain the roster that will be used for tournament registration and scoring.') }}
        </flux:text>

        @if ($status)
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
                @switch($status)
                    @case('team-created')
                        {{ __('Team registered successfully.') }}
                        @break
                    @case('team-updated')
                        {{ __('Team details updated successfully.') }}
                        @break
                    @case('team-deleted')
                        {{ __('Team deleted successfully.') }}
                        @break
                    @case('team-delete-blocked')
                        {{ __('This team is already tied to tournament records and cannot be deleted.') }}
                        @break
                    @case('member-created')
                        {{ __('Team member added successfully.') }}
                        @break
                    @case('member-updated')
                        {{ __('Team member updated successfully.') }}
                        @break
                    @case('member-deleted')
                        {{ __('Team member deleted successfully.') }}
                        @break
                    @case('member-delete-blocked')
                        {{ __('This roster member already has recorded match stats and cannot be deleted.') }}
                        @break
                    @default
                        {{ __('Saved.') }}
                @endswitch
            </div>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1.75fr)]">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ $editingTeamId ? __('Edit Team') : __('Register Team') }}
                </h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ $editingTeamId
                        ? __('Update the team name, location, and logo for the selected squad.')
                        : __('Pick the province, municipality or city, and barangay directly from the Philippine location dataset before entering members.') }}
                </p>
            </div>

            <form wire:submit="{{ $editingTeamId ? 'updateTeam' : 'createTeam' }}" class="space-y-4">
                <flux:input
                    wire:model="team_name"
                    :label="__('Team Name')"
                    type="text"
                    required
                    autocomplete="organization"
                />

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Province') }}
                        <select
                            wire:model.live="team_province_code"
                            required
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">{{ __('Select province') }}</option>
                            @foreach ($provinceOptions as $province)
                                <option value="{{ $province['code'] }}">{{ $province['name'] }}</option>
                            @endforeach
                        </select>
                        @error('team_province_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Municipality / City') }}
                        <select
                            wire:model.live="team_city_code"
                            required
                            @disabled($teamCityOptions === [])
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:disabled:bg-zinc-900 dark:disabled:text-zinc-500"
                        >
                            <option value="">{{ __('Select municipality or city') }}</option>
                            @foreach ($teamCityOptions as $city)
                                <option value="{{ $city['code'] }}">{{ $city['name'] }}</option>
                            @endforeach
                        </select>
                        @error('team_city_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                </div>

                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Barangay') }}
                    <select
                        wire:model.live="team_barangay_code"
                        required
                        @disabled($teamBarangayOptions === [])
                        class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:disabled:bg-zinc-900 dark:disabled:text-zinc-500"
                    >
                        <option value="">{{ __('Select barangay') }}</option>
                        @foreach ($teamBarangayOptions as $barangay)
                            <option value="{{ $barangay['code'] }}">{{ $barangay['name'] }}</option>
                        @endforeach
                    </select>
                    @error('team_barangay_code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Country is set automatically to Philippines.') }}
                </p>

                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Team Logo') }}
                    <input
                        wire:model="logo"
                        type="file"
                        accept="image/*"
                        class="mt-2 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:file:bg-white dark:file:text-zinc-900"
                    />
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $editingTeamId
                            ? __('Upload a new square JPG, PNG, SVG, or WEBP logo up to 2 MB to replace the current file.')
                            : __('Upload a square JPG, PNG, SVG, or WEBP logo up to 2 MB.') }}
                    </p>
                    @error('logo')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled" wire:target="createTeam,updateTeam,logo">
                        {{ $editingTeamId ? __('Save Team Changes') : __('Register Team') }}
                    </flux:button>

                    @if ($editingTeamId)
                        <button
                            type="button"
                            wire:click="cancelTeamEdit"
                            class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-50 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-950"
                        >
                            {{ __('Cancel') }}
                        </button>
                    @endif
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Team Directory') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Select a team to manage its roster.') }}</p>
                </div>
                <span class="rounded-md border border-neutral-200 px-3 py-1 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                    {{ trans_choice('{0} No teams|{1} :count team|[2,*] :count teams', $teams->count(), ['count' => $teams->count()]) }}
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($teams as $team)
                    @php
                        $logoUrl = $team->logoUrl();
                        $teamDeleteLocked = $team->registrations_count > 0
                            || $team->members->contains(fn ($member) => $member->match_stats_count > 0);
                        $badge = str($team->name)
                            ->explode(' ')
                            ->take(2)
                            ->map(fn ($word) => str($word)->substr(0, 1))
                            ->implode('');
                    @endphp

                    <div
                        wire:key="team-selector-{{ $team->id }}"
                        class="rounded-xl border p-4 text-left transition {{ $selectedTeam?->id === $team->id
                            ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-950'
                            : 'border-neutral-200 bg-white hover:border-zinc-400 dark:border-neutral-700 dark:bg-zinc-900 dark:hover:border-zinc-500' }}"
                    >
                        <button
                            type="button"
                            wire:click="selectTeam({{ $team->id }})"
                            class="w-full text-left"
                        >
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-neutral-200 bg-zinc-100 text-sm font-semibold text-zinc-700 dark:border-neutral-700 dark:bg-zinc-800 dark:text-zinc-200">
                                    @if ($logoUrl)
                                        <img
                                            src="{{ $logoUrl }}"
                                            alt="{{ $team->name }}"
                                            class="h-full w-full object-cover"
                                        >
                                    @else
                                        {{ $badge }}
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <div class="truncate text-base font-semibold text-zinc-900 dark:text-white">{{ $team->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $team->locationLabel() }}</div>
                                    @if ($team->address)
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $team->address }}</div>
                                    @endif
                                </div>
                            </div>
                        </button>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __('Members: :count', ['count' => $team->members_count]) }}</span>
                                <span>{{ __('Registrations: :count', ['count' => $team->registrations_count]) }}</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="editTeam({{ $team->id }})"
                                    class="rounded-full border px-3 py-1 text-[11px] font-medium transition {{ $editingTeamId === $team->id
                                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                                        : 'border-neutral-300 text-zinc-700 hover:border-neutral-400 hover:bg-zinc-50 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-950' }}"
                                >
                                    {{ $editingTeamId === $team->id ? __('Editing') : __('Edit Team') }}
                                </button>

                                @if ($teamDeleteLocked)
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                                        {{ __('Locked: has tournament records') }}
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="deleteTeam({{ $team->id }})"
                                        onclick="return confirm('{{ __('Delete this team and its roster?') }}')"
                                        class="rounded-full border border-red-200 px-3 py-1 text-[11px] font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:text-red-300 dark:hover:bg-red-950/40"
                                    >
                                        {{ __('Delete Team') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                        {{ __('No teams yet. Register your first team to start building the roster.') }}
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        @php
            $teamDeleteLocked = $selectedTeam
                ? $selectedTeam->registrations_count > 0 || $selectedTeam->members->contains(fn ($member) => $member->match_stats_count > 0)
                : false;
        @endphp

        <div class="mb-4">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Input Team Members') }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ $selectedTeam
                    ? ($editingMemberId
                        ? __('Editing a roster member for :team.', ['team' => $selectedTeam->name])
                        : __('Adding players to :team.', ['team' => $selectedTeam->name]))
                    : __('Choose a team first to manage its roster.') }}
            </p>
            @if ($selectedTeam)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Deleting a team also removes its roster and uploaded logo.') }}
                    </p>

                    @if ($teamDeleteLocked)
                        <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                            {{ __('Locked: has tournament records') }}
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="deleteTeam({{ $selectedTeam->id }})"
                            onclick="return confirm('{{ __('Delete this team and its roster?') }}')"
                            class="rounded-full border border-red-200 px-3 py-1 text-[11px] font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:text-red-300 dark:hover:bg-red-950/40"
                        >
                            {{ __('Delete Team') }}
                        </button>
                    @endif
                </div>
            @endif
        </div>

        @if ($selectedTeam)
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)]">
                <form wire:submit="{{ $editingMemberId ? 'updateMember' : 'addMember' }}" class="space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                            {{ $editingMemberId ? __('Edit Team Member') : __('Add Team Member') }}
                        </h3>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ __('Captain, spirit captain, and standard member roles can all be updated here.') }}
                        </p>
                    </div>

                    <flux:input wire:model="member_name" :label="__('Name')" type="text" required />
                    <flux:input wire:model="member_nickname" :label="__('Nickname')" type="text" required />

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Gender') }}
                                <select
                                    wire:model="member_gender"
                                    required
                                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                                >
                                    <option value="">{{ __('Select gender') }}</option>
                                    @foreach (['Male', 'Female', 'Non-binary', 'Prefer not to say'] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('member_gender')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </label>

                            <flux:input wire:model="member_age" :label="__('Age')" type="number" min="1" max="99" required />
                        </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Province') }}
                            <select
                                wire:model.live="member_province_code"
                                required
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            >
                                <option value="">{{ __('Select province') }}</option>
                                @foreach ($provinceOptions as $province)
                                    <option value="{{ $province['code'] }}">{{ $province['name'] }}</option>
                                @endforeach
                            </select>
                            @error('member_province_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Municipality / City') }}
                            <select
                                wire:model.live="member_city_code"
                                required
                                @disabled($memberCityOptions === [])
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:disabled:bg-zinc-900 dark:disabled:text-zinc-500"
                            >
                                <option value="">{{ __('Select municipality or city') }}</option>
                                @foreach ($memberCityOptions as $city)
                                    <option value="{{ $city['code'] }}">{{ $city['name'] }}</option>
                                @endforeach
                            </select>
                            @error('member_city_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Barangay') }}
                        <select
                            wire:model.live="member_barangay_code"
                            required
                            @disabled($memberBarangayOptions === [])
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:disabled:bg-zinc-900 dark:disabled:text-zinc-500"
                        >
                            <option value="">{{ __('Select barangay') }}</option>
                            @foreach ($memberBarangayOptions as $barangay)
                                <option value="{{ $barangay['code'] }}">{{ $barangay['name'] }}</option>
                            @endforeach
                        </select>
                        @error('member_barangay_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Role') }}
                            <select
                                wire:model="member_role"
                                required
                                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            >
                                <option value="captain">{{ __('Captain') }}</option>
                                <option value="spirit_captain">{{ __('Spirit Captain') }}</option>
                                <option value="member">{{ __('Member') }}</option>
                            </select>
                            @error('member_role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                    <div class="flex flex-wrap gap-3">
                        <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled" wire:target="addMember,updateMember">
                            {{ $editingMemberId ? __('Save Member Changes') : __('Add Team Member') }}
                        </flux:button>

                        @if ($editingMemberId)
                            <button
                                type="button"
                                wire:click="cancelMemberEdit"
                                class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-50 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-950"
                            >
                                {{ __('Cancel') }}
                            </button>
                        @endif
                    </div>
                </form>

                <div class="space-y-3">
                    @forelse ($selectedTeam->members as $member)
                        <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950" wire:key="team-member-{{ $member->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $member->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $member->nickname }} &middot; {{ str($member->role)->replace('_', ' ')->headline() }}
                                    </div>
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $member->gender }} &middot; {{ __('Age :age', ['age' => $member->age]) }}
                                </div>
                            </div>

                            <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $member->address }}
                            </div>

                            <div class="mt-4 flex justify-end gap-2">
                                <button
                                    type="button"
                                    wire:click="editMember({{ $member->id }})"
                                    class="rounded-full border px-3 py-1 text-[11px] font-medium transition {{ $editingMemberId === $member->id
                                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                                        : 'border-neutral-300 text-zinc-700 hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-zinc-900' }}"
                                >
                                    {{ $editingMemberId === $member->id ? __('Editing') : __('Edit') }}
                                </button>

                                @if ($member->match_stats_count > 0)
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                                        {{ __('Locked: has match stats') }}
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="deleteMember({{ $member->id }})"
                                        onclick="return confirm('{{ __('Delete this team member?') }}')"
                                        class="rounded-full border border-red-200 px-3 py-1 text-[11px] font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:text-red-300 dark:hover:bg-red-950/40"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                            {{ __('No roster members yet for this team.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-zinc-600 dark:border-neutral-700 dark:text-zinc-300">
                {{ __('Register a team first, then come back here to add roster members.') }}
            </div>
        @endif
    </section>
</div>
