<?php

namespace App\Console\Commands;

use App\Domain\Notifications\NotificationReminderGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateNotificationReminders extends Command
{
    protected $signature = 'notifications:generate
                            {--company= : Optional company code}';

    protected $description = 'Generate idempotent operational notifications and reminders';

    public function handle(NotificationReminderGenerator $generator): int
    {
        $companyCode = trim((string) $this->option('company'));
        $companyIds = DB::table('companies')
            ->where('is_active', true)
            ->when($companyCode !== '', fn ($query) => $query->where('code', strtoupper($companyCode)))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($companyIds === []) {
            $this->components->warn('Tidak ada perusahaan aktif yang sesuai.');

            return self::SUCCESS;
        }

        $total = ['rules' => 0, 'sources' => 0, 'created' => 0, 'repeated' => 0, 'resolved' => 0];

        foreach ($companyIds as $companyId) {
            $result = $generator->generateForCompany($companyId);

            $total['rules'] += $result['rules'];
            $total['sources'] += $result['sources'];
            $total['created'] += $result['created'];
            $total['repeated'] += $result['repeated'];
            $total['resolved'] += $result['resolved'];
        }

        $this->components->info(sprintf(
            'Reminder scan selesai: %d rules, %d sumber, %d baru, %d berulang, %d terselesaikan.',
            $total['rules'],
            $total['sources'],
            $total['created'],
            $total['repeated'],
            $total['resolved'],
        ));

        return self::SUCCESS;
    }
}
