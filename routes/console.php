<?php

use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\QuotaStatus;
use App\Models\Association;
use App\Models\Member;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('members:sync-last-number', function () {
    $this->comment('Sincronizando last_member_number con el máximo member_number existente...');

    $max = (int) DB::table('members')
        ->whereNotNull('member_number')
        ->selectRaw('MAX(CAST(member_number AS UNSIGNED)) as max')
        ->value('max');

    $max = $max ?: 0;

    $this->info("Máximo encontrado: {$max}");

    $association = Association::first();

    if (! $association) {
        Association::create(['last_member_number' => $max]);
        $this->info("Se creó fila de Association con last_member_number={$max}");

        return;
    }

    if ($max > $association->last_member_number) {
        $association->update(['last_member_number' => $max]);
        $this->info("Association.last_member_number actualizado a {$max}");

        return;
    }

    $this->info("No hay cambios necesarios. Association.last_member_number={$association->last_member_number}");
})->purpose('Sincroniza last_member_number con el máximo member_number existente');

Artisan::command('members:apply-overdue-baja', function () {
    $today = now()->toDateString();

    $members = Member::query()
        ->where('member_type', MemberType::Socio->value)
        ->where('status', MemberStatus::Pendiente->value)
        ->whereHas('quotas', function ($query) use ($today): void {
            $query
                ->whereNotIn('status', [QuotaStatus::Pagada->value, QuotaStatus::Cancelada->value])
                ->whereDate('due_date', '<', $today);
        })
        ->get();

    $count = 0;

    foreach ($members as $member) {
        $member->update(['status' => MemberStatus::Baja->value]);
        $count++;
    }

    $this->info("Dados de baja por cuota vencida: {$count} socio(s).");
})->purpose('Da de baja a los socios Pendientes que han superado la fecha límite de su cuota');

Schedule::command('members:apply-overdue-baja')->dailyAt('02:00');
Schedule::command('events:update-statuses')->dailyAt('01:00');
