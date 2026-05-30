<?php

namespace App\Jobs;

use App\Enums\PdfEngine;
use App\Enums\QuotaStatus;
use App\Mail\QuotaPendingNoticeMail;
use App\Models\Association;
use App\Models\Quota;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class SendQuotaNoticesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?array $quotaIds;

    public ?string $statusValue;

    public function __construct(?array $quotaIds = null, ?string $statusValue = null)
    {
        $this->quotaIds = $quotaIds;
        $this->statusValue = $statusValue;
    }

    public function handle(): void
    {
        $association = Association::firstOrFail();

        Config::set('mail.mailers.assoc_dynamic', [
            'transport' => $association->mail_mailer ?? 'smtp',
            'host' => $association->mail_host,
            'port' => $association->mail_port,
            'encryption' => $association->mail_encryption,
            'username' => $association->mail_username,
            'password' => $association->mail_password,
            'from' => [
                'address' => (string) ($association->mail_from_address ?? $association->email),
                'name' => (string) ($association->mail_from_name ?? $association->name),
            ],
        ]);
        Mail::purge('assoc_dynamic');

        if ($this->quotaIds === null && $this->statusValue !== null) {
            $quotas = Quota::where('status', $this->statusValue)
                ->whereHas('member', fn ($q) => $q->whereNotNull('email')->where('email_opt_in', true))
                ->with(['member', 'quotaType'])
                ->cursor();
        } else {
            $quotas = Quota::whereIn('id', $this->quotaIds ?? [])
                ->with(['member', 'quotaType'])
                ->cursor();
        }

        $pdfService = app(PdfService::class);
        $noticeDate = now()->format('d/m/Y');

        foreach ($quotas as $quota) {
            try {
                if (! filled($quota->member?->email) || ! (bool) $quota->member?->email_opt_in) {
                    continue;
                }

                // Only send pending/expired notices
                if (! in_array($quota->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida], true)) {
                    continue;
                }

                $pdfBytes = $pdfService->renderView('documents.quotas.QuotaPendingNoticeA5', [
                    'quota' => $quota,
                    'association' => $association,
                    'noticeDate' => $noticeDate,
                ], PdfEngine::Dompdf);

                Mail::mailer('assoc_dynamic')
                    ->to((string) $quota->member->email, $quota->member->full_name ?? '-')
                    ->send(new QuotaPendingNoticeMail($quota, $pdfBytes, $noticeDate));
            } catch (\Throwable $e) {
                // swallow to allow the job to continue; logging is optional
                logger()->error('SendQuotaNoticesJob error: '.$e->getMessage(), ['quota_id' => $quota->id ?? null]);
            }
        }
    }
}
