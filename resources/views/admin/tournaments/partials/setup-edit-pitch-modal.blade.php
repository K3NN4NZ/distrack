@php
    $pitchModalRedirectTab = $redirectTab ?? 'pitches';
    $editPitchOldId = old('edit_pitch_id');
    $isThisPitchInOld = $editPitchOldId !== null && (int) $editPitchOldId === (int) $pitch->id;
@endphp
<flux:modal
    name="setup-edit-pitch-modal-{{ $pitch->id }}"
    :show="$isThisPitchInOld"
    class="max-w-2xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Edit Pitch') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Update the pitch details for :tournament.', ['tournament' => $tournament->name]) }}
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
                :label="__('Pitch Name')"
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

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Save Changes') }}
            </flux:button>
        </form>
    </div>
</flux:modal>
