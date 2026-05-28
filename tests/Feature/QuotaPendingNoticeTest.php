<?php

use App\Enums\PaymentPeriod;
use App\Enums\QuotaStatus;
use App\Mail\QuotaMemberReceiptMail;
use App\Mail\QuotaPendingNoticeMail;
use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('la ruta de aviso de cuota pendiente devuelve un PDF', function (): void {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    Association::factory()->create();

    $member = Member::query()->create([
        'member_number' => '9001',
        'first_name' => 'Juan',
        'last_name' => 'García',
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Cuota anual',
        'period_type' => PaymentPeriod::Anual->value,
        'default_amount' => 60,
        'grace_days' => 10,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'status' => QuotaStatus::Pendiente,
        'period_start' => now()->startOfYear(),
        'period_end' => now()->endOfYear(),
        'due_date' => now()->addMonth(),
        'amount' => 60,
    ]);

    $response = $this->get(route('quotas.receipt.show', ['quota' => $quota->id, 'type' => 'pending_notice']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('QuotaPendingNoticeMail tiene asunto correcto y muestra nombre del socio', function (): void {
    $member = Member::query()->create([
        'member_number' => '9002',
        'first_name' => 'Juan',
        'last_name' => 'García',
        'email' => 'socio@example.com',
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Cuota anual',
        'period_type' => PaymentPeriod::Anual->value,
        'default_amount' => 60,
        'grace_days' => 10,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'status' => QuotaStatus::Pendiente,
        'period_start' => now()->startOfYear(),
        'period_end' => now()->endOfYear(),
        'amount' => 60,
    ]);

    $mailable = new QuotaPendingNoticeMail($quota, '%PDF-fake', now()->format('d/m/Y'));

    $mailable->assertHasSubject('Aviso de cuota pendiente');
    $mailable->assertSeeInHtml('Juan');
});

test('QuotaMemberReceiptMail tiene asunto correcto y muestra nombre del socio', function (): void {
    $member = Member::query()->create([
        'member_number' => '9003',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'email' => 'ana@example.com',
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Cuota anual',
        'period_type' => PaymentPeriod::Anual->value,
        'default_amount' => 60,
        'grace_days' => 10,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'status' => QuotaStatus::Pagada,
        'period_start' => now()->startOfYear(),
        'period_end' => now()->endOfYear(),
        'amount' => 60,
    ]);

    $mailable = new QuotaMemberReceiptMail($quota, '%PDF-fake');

    $mailable->assertHasSubject('Recibo de cuota de socio');
    $mailable->assertSeeInHtml('Ana');
});
