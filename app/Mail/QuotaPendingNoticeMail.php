<?php

namespace App\Mail;

use App\Enums\EmailTemplateType;
use App\Models\Association;
use App\Models\Quota;
use App\Services\Emails\EmailTemplateRenderer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class QuotaPendingNoticeMail extends Mailable
{
    /**
     * @param  string  $pdfBytes  Raw PDF bytes to attach
     */
    public function __construct(
        public readonly Quota $quota,
        public readonly string $pdfBytes,
        public readonly string $noticeDate,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->resolvedSubject());
    }

    public function content(): Content
    {
        $quota = $this->quota;
        $rendered = $this->renderTemplate();

        if ($rendered !== null) {
            return new Content(
                view: 'emails.template-body',
                with: ['bodyHtml' => $rendered['body_html']],
            );
        }

        return new Content(
            view: 'emails.quota-pending-notice',
            with: [
                'memberName' => mb_convert_case($quota->member?->full_name ?? '-', MB_CASE_TITLE, 'UTF-8'),
                'quotaTypeName' => $quota->quotaType?->name ?? 'Cuota de socio',
                'periodStart' => $quota->period_start?->format('d/m/Y') ?? '-',
                'periodEnd' => $quota->period_end?->format('d/m/Y') ?? '-',
                'amount' => number_format((float) ($quota->amount ?? 0), 2, ',', '.'),
                'dueDate' => $quota->due_date?->format('d/m/Y'),
                'associationName' => Association::query()->value('name') ?? '',
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $quota = $this->quota;
        $filename = sprintf('aviso_cuota_%s_%s.pdf', $quota->id, $this->noticeDate);

        return [
            Attachment::fromData(fn (): string => $this->pdfBytes, $filename)
                ->withMime('application/pdf'),
        ];
    }

    /** @return array{subject: string, body_html: string}|null */
    private function renderTemplate(): ?array
    {
        return app(EmailTemplateRenderer::class)->renderActive(
            EmailTemplateType::QuotaPending,
            ['member' => $this->quota->member, 'quota' => $this->quota]
        );
    }

    private function resolvedSubject(): string
    {
        return $this->renderTemplate()['subject'] ?? 'Aviso de cuota pendiente';
    }
}
