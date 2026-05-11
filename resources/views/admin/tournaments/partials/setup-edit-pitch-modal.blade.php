@php
    $pitchModalRedirectTab = $redirectTab ?? 'crossover';
    $editPitchOldId = old('edit_pitch_id');
    $isThisPitchInOld = $editPitchOldId !== null && (int) $editPitchOldId === (int) $pitch->id;
    $pitchAssignmentScorekeepers = collect($pitchAssignmentScorekeepers ?? []);
@endphp
<flux:modal
    name="setup-edit-pitch-modal-{{ $pitch->id }}"
    :show="$isThisPitchInOld"
    class="max-w-2xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Edit playing field') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Update this surface for :tournament.', ['tournament' => $tournament->name]) }}
                </flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost">
                    {{ __('Close') }}
                </flux:button>
            </flux:modal.close>
        </div>

        <form method="POST" action="{{ route('admin.tournaments.pitches.update', ['pitch' => $pitch->id]) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_pitch_id" value="{{ $pitch->id }}">
            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
            <input type="hidden" name="redirect_tab" value="{{ $pitchModalRedirectTab }}">

            <flux:input
                name="name"
                :label="__('Field name')"
                :value="$isThisPitchInOld ? old('name', $pitch->name) : $pitch->name"
                type="text"
                required
            />
            <flux:input
                name="location"
                :label="__('Location')"
                :value="$isThisPitchInOld ? old('location', $pitch->location) : $pitch->location"
                type="text"
            />
            <flux:input
                name="sort_order"
                :label="__('Sort Order')"
                :value="$isThisPitchInOld ? old('sort_order', $pitch->sort_order) : $pitch->sort_order"
                type="number"
                min="1"
                required
            />

            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Assigned scorekeeper') }}
                <select
                    name="scorekeeper_user_id"
                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                >
                    <option value="">{{ __('None — assign later') }}</option>
                    @foreach ($pitchAssignmentScorekeepers as $skUser)
                        <option
                            value="{{ $skUser->id }}"
                            @selected(
                                $isThisPitchInOld
                                    ? (string) old('scorekeeper_user_id', (string) ($pitch->scorekeeper_user_id ?? '')) === (string) $skUser->id
                                    : (int) ($pitch->scorekeeper_user_id ?? 0) === (int) $skUser->id
                            )
                        >
                            {{ $skUser->name }}
                        </option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('If set, only this scorekeeper sees games on this field. If empty, any scorekeeper can score games here until someone is assigned.') }}
                </span>
            </label>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Save Changes') }}
            </flux:button>
        </form>
    </div>
</flux:modal>
