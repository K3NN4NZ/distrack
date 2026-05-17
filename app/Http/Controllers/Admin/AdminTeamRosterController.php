<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMember;
use App\Support\Utf8Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTeamRosterController extends Controller
{
    public function index(Team $team): View
    {
        $team->load(['members' => fn ($q) => $q->orderBy('name')]);

        return view('admin.teams.roster.index', [
            'team' => $team,
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate($this->memberRules());

        DB::transaction(function () use ($team, $validated): void {
            $role = $this->applyLeadershipFlags($team, $validated);
            TeamMember::query()->create([
                'team_id' => $team->id,
                'user_id' => null,
                'name' => $validated['name'],
                'nickname' => $this->nullableString($validated['nickname'] ?? null),
                'gender' => $this->normalizeGender($validated['gender']),
                'jersey_number' => $validated['jersey_number'] ?? null,
                'email' => $this->nullableString($validated['email'] ?? null),
                'contact' => $this->nullableString($validated['contact'] ?? null),
                'age' => $validated['age'] ?? null,
                'address' => $this->nullableString($validated['address'] ?? null),
                'role' => $role,
            ]);
        });

        return redirect()
            ->route('admin.teams.roster.index', $team)
            ->with('status', 'roster-member-created');
    }

    public function update(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        abort_unless($teamMember->team_id === $team->id, 404);

        $validated = $request->validate($this->memberRules());

        DB::transaction(function () use ($team, $teamMember, $validated): void {
            $role = $this->applyLeadershipFlags($team, $validated, $teamMember->id);
            $teamMember->update([
                'name' => $validated['name'],
                'nickname' => $this->nullableString($validated['nickname'] ?? null),
                'gender' => $this->normalizeGender($validated['gender']),
                'jersey_number' => $validated['jersey_number'] ?? null,
                'email' => $this->nullableString($validated['email'] ?? null),
                'contact' => $this->nullableString($validated['contact'] ?? null),
                'age' => $validated['age'] ?? null,
                'address' => $this->nullableString($validated['address'] ?? null),
                'role' => $role,
            ]);
        });

        return redirect()
            ->route('admin.teams.roster.index', $team)
            ->with('status', 'roster-member-updated');
    }

    public function destroy(Team $team, TeamMember $teamMember): RedirectResponse
    {
        abort_unless($teamMember->team_id === $team->id, 404);

        if ($teamMember->isLinkedToMatchRecords()) {
            return redirect()
                ->route('admin.teams.roster.index', $team)
                ->with('status', 'roster-member-delete-blocked');
        }

        $teamMember->delete();

        return redirect()
            ->route('admin.teams.roster.index', $team)
            ->with('status', 'roster-member-deleted');
    }

    public function upload(Request $request, Team $team): RedirectResponse
    {
        $request->validate([
            'roster_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('roster_file')->getRealPath();
        if ($path === false) {
            return redirect()->route('admin.teams.roster.index', $team)->with('status', 'roster-upload-invalid');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return redirect()->route('admin.teams.roster.index', $team)->with('status', 'roster-upload-invalid');
        }

        $contents = Utf8Text::normalizeUtf8($contents) ?? '';
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            return redirect()->route('admin.teams.roster.index', $team)->with('status', 'roster-upload-invalid');
        }

        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return redirect()->route('admin.teams.roster.index', $team)->with('status', 'roster-upload-empty');
        }

        if ($header !== false && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $indexes = array_flip($header);

        $required = ['name', 'gender'];
        foreach ($required as $col) {
            if (! array_key_exists($col, $indexes)) {
                fclose($handle);

                return redirect()
                    ->route('admin.teams.roster.index', $team)
                    ->with('status', 'roster-upload-missing-columns')
                    ->with('roster_upload_error', "Missing required column: {$col}");
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->csvRowIsBlank($row)) {
                continue;
            }

            $name = Utf8Text::prepareForStorage((string) ($row[$indexes['name']] ?? '')) ?? '';
            if ($name === '') {
                $skipped++;
                $errors[] = __('Row :row: empty name', ['row' => $rowNum]);

                continue;
            }

            $genderRaw = strtolower(trim((string) ($row[$indexes['gender']] ?? '')));
            $gender = $this->normalizeGenderFromImport($genderRaw);
            if ($gender === null) {
                $skipped++;
                $errors[] = __('Row :row: invalid gender', ['row' => $rowNum]);

                continue;
            }

            $jersey = isset($indexes['jersey_number']) ? trim((string) ($row[$indexes['jersey_number']] ?? '')) : '';
            $jerseyNumber = $jersey === '' ? null : (int) $jersey;
            if ($jerseyNumber !== null && ($jerseyNumber < 0 || $jerseyNumber > 9999)) {
                $skipped++;
                $errors[] = __('Row :row: invalid jersey number', ['row' => $rowNum]);

                continue;
            }

            $email = isset($indexes['email']) ? $this->nullableString($row[$indexes['email']] ?? null) : null;
            $roleRaw = isset($indexes['role']) ? strtolower(trim((string) ($row[$indexes['role']] ?? ''))) : '';
            $role = $this->normalizeRoleFromImport($roleRaw);

            $isCaptain = $this->csvBool($row, $indexes, 'is_captain');
            $isSpirit = $this->csvBool($row, $indexes, 'is_spirit_captain');

            if ($isCaptain) {
                $role = 'captain';
            }
            if ($isSpirit) {
                $role = 'spirit_captain';
            }

            $attributes = [
                'name' => $name,
                'gender' => $gender,
                'jersey_number' => $jerseyNumber,
                'email' => $email,
                'role' => $role,
            ];

            $member = TeamMember::query()
                ->where('team_id', $team->id)
                ->where('name', $name)
                ->where('gender', $gender)
                ->first();

            try {
                DB::transaction(function () use ($team, $member, $attributes, &$created, &$updated): void {
                    if ($attributes['role'] === 'captain') {
                        $team->members()->where('role', 'captain')->update(['role' => 'member']);
                    }
                    if ($attributes['role'] === 'spirit_captain') {
                        $team->members()->where('role', 'spirit_captain')->update(['role' => 'member']);
                    }

                    if ($member) {
                        $member->update($attributes);
                        $updated++;
                    } else {
                        TeamMember::query()->create([
                            'team_id' => $team->id,
                            'user_id' => null,
                            ...$attributes,
                            'nickname' => null,
                            'contact' => null,
                            'age' => null,
                            'address' => null,
                        ]);
                        $created++;
                    }
                });
            } catch (\Throwable) {
                $skipped++;
                $errors[] = __('Row :row: could not save', ['row' => $rowNum]);
            }
        }

        fclose($handle);

        return redirect()
            ->route('admin.teams.roster.index', $team)
            ->with('status', 'roster-upload-complete')
            ->with('roster_import_summary', [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 25),
            ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function memberRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'jersey_number' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'role' => ['nullable', Rule::in(['member', 'captain', 'spirit_captain'])],
            'is_captain' => ['sometimes', 'boolean'],
            'is_spirit_captain' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function applyLeadershipFlags(Team $team, array $validated, ?int $exceptMemberId = null): string
    {
        $role = $this->resolveRosterRole($validated);

        if ($role === 'captain') {
            $q = $team->members()->where('role', 'captain');
            if ($exceptMemberId) {
                $q->where('id', '!=', $exceptMemberId);
            }
            $q->update(['role' => 'member']);
        }

        if ($role === 'spirit_captain') {
            $q = $team->members()->where('role', 'spirit_captain');
            if ($exceptMemberId) {
                $q->where('id', '!=', $exceptMemberId);
            }
            $q->update(['role' => 'member']);
        }

        return $role;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function resolveRosterRole(array $validated): string
    {
        if (! empty($validated['is_captain'])) {
            return 'captain';
        }
        if (! empty($validated['is_spirit_captain'])) {
            return 'spirit_captain';
        }

        return $validated['role'] ?? 'member';
    }

    protected function normalizeGender(string $gender): string
    {
        $g = strtolower(trim($gender));

        return match (true) {
            in_array($g, ['male', 'm', 'men', 'man'], true) => 'male',
            in_array($g, ['female', 'f', 'women', 'woman'], true) => 'female',
            default => 'other',
        };
    }

    protected function normalizeGenderFromImport(string $raw): ?string
    {
        $g = strtolower(trim($raw));
        if (in_array($g, ['male', 'm', 'men', 'man'], true)) {
            return 'male';
        }
        if (in_array($g, ['female', 'f', 'women', 'woman'], true)) {
            return 'female';
        }
        if ($g === 'other' || $g === 'o') {
            return 'other';
        }

        return null;
    }

    protected function normalizeRoleFromImport(string $raw): string
    {
        return match ($raw) {
            'captain', 'cap' => 'captain',
            'spirit captain', 'spirit_captain', 'spirit' => 'spirit_captain',
            default => 'member',
        };
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $indexes
     */
    protected function csvBool(array $row, array $indexes, string $key): bool
    {
        if (! array_key_exists($key, $indexes)) {
            return false;
        }

        $v = strtolower(trim((string) ($row[$indexes[$key]] ?? '')));

        return in_array($v, ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * @param  array<int, string|null>  $row
     */
    protected function csvRowIsBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : Utf8Text::prepareForStorage($s);
    }
}
