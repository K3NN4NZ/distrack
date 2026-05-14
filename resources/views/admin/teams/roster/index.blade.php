<x-layouts::app :title="__('Roster: :team', ['team' => $team->name])">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.teams.show', $team) }}" wire:navigate class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                ← {{ __('Back to team') }}
            </a>
            <a href="{{ route('admin.teams.edit', $team) }}" wire:navigate class="text-sm font-medium text-[#2f55b7] hover:underline dark:text-sky-300">
                {{ __('Edit team info') }}
            </a>
        </div>

        @if (session('status'))
            @php $st = session('status'); @endphp
            <div @class([
                'rounded-xl border px-4 py-3 text-sm',
                'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200' => $st === 'roster-member-delete-blocked',
                'border-red-200 bg-red-50 text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200' => in_array($st, ['roster-upload-missing-columns', 'roster-upload-invalid', 'roster-upload-empty'], true),
                'border-green-200 bg-green-50 text-green-800 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-200' => ! in_array($st, ['roster-member-delete-blocked', 'roster-upload-missing-columns', 'roster-upload-invalid', 'roster-upload-empty'], true),
            ])>
                @switch(session('status'))
                    @case('roster-member-created') {{ __('Player added.') }} @break
                    @case('roster-member-updated') {{ __('Player updated.') }} @break
                    @case('roster-member-deleted') {{ __('Player removed.') }} @break
                    @case('roster-member-delete-blocked') {{ __('Cannot delete this player while match or spirit records reference them.') }} @break
                    @case('roster-upload-complete') {{ __('Roster file processed.') }} @break
                    @case('roster-upload-missing-columns') {{ __('CSV is missing required columns.') }} {{ session('roster_upload_error') }} @break
                    @case('roster-upload-empty') {{ __('The uploaded file was empty.') }} @break
                    @case('roster-upload-invalid') {{ __('Could not read the uploaded file.') }} @break
                    @default {{ __('Saved.') }}
                @endswitch
            </div>
        @endif

        @if (session('roster_import_summary'))
            @php $s = session('roster_import_summary'); @endphp
            <section class="rounded-xl border border-neutral-200 bg-zinc-50 p-4 text-sm dark:border-neutral-700 dark:bg-zinc-900">
                <div class="font-semibold text-zinc-900 dark:text-white">{{ __('Import summary') }}</div>
                <ul class="mt-2 list-inside list-disc text-zinc-700 dark:text-zinc-300">
                    <li>{{ __('Created: :n', ['n' => $s['created'] ?? 0]) }}</li>
                    <li>{{ __('Updated: :n', ['n' => $s['updated'] ?? 0]) }}</li>
                    <li>{{ __('Skipped: :n', ['n' => $s['skipped'] ?? 0]) }}</li>
                </ul>
                @if (! empty($s['errors']))
                    <div class="mt-2 text-xs text-red-700 dark:text-red-300">
                        @foreach ($s['errors'] as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Upload roster (CSV)') }}</h2>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Columns: name, gender, jersey_number, email, role, is_captain, is_spirit_captain') }}</p>
                <form method="POST" action="{{ route('admin.teams.roster.upload', $team) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <input type="file" name="roster_file" accept=".csv,.txt" required class="block w-full text-sm" />
                    @error('roster_file')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <flux:button type="submit" variant="primary" size="sm">{{ __('Upload & import') }}</flux:button>
                </form>
            </section>

            <section class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Add player') }}</h2>
                <form method="POST" action="{{ route('admin.teams.roster.store', $team) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Name') }}</label>
                        <input name="name" required value="{{ old('name') }}" class="mt-1 w-full rounded border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-600 dark:bg-zinc-950" />
                        @error('name')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-zinc-600">{{ __('Gender') }}</label>
                        <select name="gender" required class="mt-1 w-full rounded border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-600 dark:bg-zinc-950">
                            <option value="male">{{ __('Male') }}</option>
                            <option value="female">{{ __('Female') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-zinc-600">{{ __('Jersey #') }}</label>
                        <input name="jersey_number" type="number" min="0" max="9999" value="{{ old('jersey_number') }}" class="mt-1 w-full rounded border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-600 dark:bg-zinc-950" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-zinc-600">{{ __('Email') }}</label>
                        <input name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-600 dark:bg-zinc-950" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-zinc-600">{{ __('Contact') }}</label>
                        <input name="contact" value="{{ old('contact') }}" class="mt-1 w-full rounded border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-600 dark:bg-zinc-950" />
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap gap-4 text-xs">
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_captain" value="1" class="rounded" @checked(old('is_captain')) /> {{ __('Captain') }}</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_spirit_captain" value="1" class="rounded" @checked(old('is_spirit_captain')) /> {{ __('Spirit captain') }}</label>
                    </div>
                    <div class="sm:col-span-2">
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Add player') }}</flux:button>
                    </div>
                </form>
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="border-b border-neutral-200 px-5 py-3 dark:border-neutral-700">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Roster') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-xs dark:divide-neutral-700">
                    <thead class="bg-zinc-50 text-left text-zinc-600 dark:bg-zinc-800/60 dark:text-zinc-300">
                        <tr>
                            <th class="px-3 py-2">{{ __('Player') }}</th>
                            <th class="px-3 py-2 w-24">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($team->members as $member)
                            <tr id="member-{{ $member->id }}" class="text-zinc-800 dark:text-zinc-100">
                                <td class="px-2 py-3" colspan="2">
                                    <form method="POST" action="{{ route('admin.teams.roster.update', [$team, $member]) }}" class="flex flex-wrap items-end gap-3">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <label class="block text-[10px] font-medium uppercase text-zinc-500">{{ __('Name') }}</label>
                                            <input name="name" value="{{ $member->name }}" required class="mt-0.5 w-40 rounded border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-600 dark:bg-zinc-950" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-medium uppercase text-zinc-500">{{ __('Gender') }}</label>
                                            <select name="gender" class="mt-0.5 rounded border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-600 dark:bg-zinc-950">
                                                @foreach (['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')] as $val => $lab)
                                                    <option value="{{ $val }}" @selected(strtolower((string) $member->gender) === $val)>{{ $lab }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-medium uppercase text-zinc-500">{{ __('Jersey') }}</label>
                                            <input name="jersey_number" type="number" min="0" max="9999" value="{{ $member->jersey_number }}" class="mt-0.5 w-16 rounded border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-600 dark:bg-zinc-950" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-medium uppercase text-zinc-500">{{ __('Email') }}</label>
                                            <input name="email" type="email" value="{{ $member->email }}" class="mt-0.5 w-44 rounded border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-600 dark:bg-zinc-950" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-medium uppercase text-zinc-500">{{ __('Contact') }}</label>
                                            <input name="contact" value="{{ $member->contact }}" class="mt-0.5 w-36 rounded border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-600 dark:bg-zinc-950" />
                                        </div>
                                        <div class="flex flex-col gap-1 pb-0.5 text-[10px]">
                                            <label class="flex items-center gap-1"><input type="checkbox" name="is_captain" value="1" @checked($member->role === 'captain') /> {{ __('Captain') }}</label>
                                            <label class="flex items-center gap-1"><input type="checkbox" name="is_spirit_captain" value="1" @checked($member->role === 'spirit_captain') /> {{ __('Spirit') }}</label>
                                        </div>
                                        <flux:button type="submit" variant="ghost" size="sm">{{ __('Save') }}</flux:button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.teams.roster.destroy', [$team, $member]) }}" class="mt-2 inline" onsubmit="return confirm('{{ __('Remove this player from the roster?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline">{{ __('Delete player') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($team->members->isEmpty())
                <div class="p-6 text-sm text-zinc-600 dark:text-zinc-300">{{ __('No players yet.') }}</div>
            @endif
        </section>
    </div>
</x-layouts::app>
