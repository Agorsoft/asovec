<?php

namespace App\Mail;

use App\Models\Association;
use App\Models\Quota;
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
        return new Envelope(
            subject: 'Aviso de cuota pendiente',
        );
    }

    public function content(): Content
    {
        $quota = $this->quota;

        return new Content(
            view: 'emails.quota-pending-notice',
            with: [
                'memberName' => $quota->member?->full_name ?? '-',
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
}
