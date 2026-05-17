<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCrew;
use App\Models\User;
use App\Support\Utf8Text;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class FixMojibakeNames extends Command
{
    protected $signature = 'names:fix-mojibake {--dry-run : Scan and report without writing changes}';

    protected $description = 'Repair mojibake in stored player, team, and tournament name fields';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be saved.');
        }

        $targets = [
            [TeamMember::class, ['name', 'nickname', 'address', 'contact', 'email']],
            [Team::class, ['name', 'short_name', 'description', 'address', 'city', 'province', 'country_name']],
            [Tournament::class, ['name', 'venue', 'description', 'city', 'province', 'country_name', 'barangay']],
            [User::class, ['name']],
            [TournamentCrew::class, ['name', 'title', 'category']],
        ];

        $updatedRows = 0;
        $updatedColumns = 0;

        foreach ($targets as [$modelClass, $columns]) {
            /** @var class-string<Model> $modelClass */
            $label = class_basename($modelClass);
            $this->line("Scanning {$label}…");

            $modelClass::query()
                ->where(function ($query) use ($columns): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%Ã%')
                            ->orWhere($column, 'like', '%Â%')
                            ->orWhere($column, 'like', '%â€™%')
                            ->orWhere($column, 'like', '%â€œ%')
                            ->orWhere($column, 'like', '%â€%');
                    }
                })
                ->chunkById(100, function ($rows) use ($columns, $dryRun, &$updatedRows, &$updatedColumns, $label): void {
                    foreach ($rows as $row) {
                        $dirty = false;

                        foreach ($columns as $column) {
                            $original = $row->getAttribute($column);

                            if (! is_string($original) || $original === '') {
                                continue;
                            }

                            if (! Utf8Text::looksLikeMojibake($original)) {
                                continue;
                            }

                            $fixed = Utf8Text::prepareForStorage($original);

                            if (! is_string($fixed) || $fixed === $original) {
                                continue;
                            }

                            $dirty = true;
                            $updatedColumns++;

                            $this->line(sprintf(
                                '  [%s #%d] %s: %s => %s',
                                $label,
                                $row->getKey(),
                                $column,
                                $original,
                                $fixed,
                            ));

                            if (! $dryRun) {
                                $row->setAttribute($column, $fixed);
                            }
                        }

                        if ($dirty) {
                            $updatedRows++;

                            if (! $dryRun) {
                                $row->save();
                            }
                        }
                    }
                });
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. %s %d row(s), %d column value(s).',
            $dryRun ? 'Would update' : 'Updated',
            $updatedRows,
            $updatedColumns,
        ));

        return self::SUCCESS;
    }
}
