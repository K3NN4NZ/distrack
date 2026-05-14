<x-layouts::app :title="__('Edit Team')">
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.teams.show', $team) }}" wire:navigate class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                ← {{ __('Back to team') }}
            </a>
            <a href="{{ route('admin.teams.roster.index', $team) }}" wire:navigate class="text-sm font-medium text-[#2f55b7] hover:underline dark:text-sky-300">
                {{ __('Manage roster') }}
            </a>
        </div>

        <section class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('Edit Team') }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $team->name }}</p>

            <form method="POST" action="{{ route('admin.teams.update', $team) }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="from_edit_page" value="1">

                <flux:input name="edit_name" :label="__('Team Name')" :value="old('edit_name', $team->name)" type="text" required />
                <flux:input name="edit_short_name" :label="__('Short name / code')" :value="old('edit_short_name', $team->short_name)" type="text" />

                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Description') }}
                    <textarea name="edit_description" rows="3" class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-neutral-700 dark:bg-zinc-950 dark:text-white">{{ old('edit_description', $team->description) }}</textarea>
                    @error('edit_description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Address') }}
                    <input name="edit_address" type="text" value="{{ old('edit_address', $team->address) }}" required class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white" />
                    @error('edit_address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('City') }}
                        <input name="edit_city" type="text" value="{{ old('edit_city', $team->city) }}" required class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white" />
                        @error('edit_city')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Province') }}
                        <input name="edit_province" type="text" value="{{ old('edit_province', $team->province) }}" required class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white" />
                        @error('edit_province')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Country') }}
                        <input name="edit_country_name" type="text" value="{{ old('edit_country_name', $team->country_name ?: 'Philippines') }}" class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white" />
                    </label>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Status') }}
                        <select name="edit_status" required class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-950 dark:text-white">
                            @foreach (['active', 'inactive', 'archived'] as $status)
                                <option value="{{ $status }}" @selected(old('edit_status', $team->status) === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @if ($team->logoUrl())
                    <div class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
                        <div class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Current logo') }}</div>
                        <img src="{{ $team->logoUrl() }}" alt="" class="h-24 w-24 rounded-xl border border-neutral-200 object-cover dark:border-neutral-700">
                    </div>
                @endif

                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Replace logo') }}
                    <input name="edit_logo" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 block w-full text-sm" />
                    <p class="mt-1 text-xs text-zinc-500">{{ __('JPG, PNG, or WEBP up to 2 MB.') }}</p>
                    @error('edit_logo')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                @if ($team->logo_path)
                    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <input type="checkbox" name="edit_remove_logo" value="1" @checked(old('edit_remove_logo')) class="rounded border-neutral-300" />
                        {{ __('Remove logo') }}
                    </label>
                @endif

                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
            </form>
        </section>
    </div>
</x-layouts::app>
