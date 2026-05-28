<?php

namespace App\Mail;

use App\Models\Association;
use App\Models\Quota;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class QuotaMemberReceiptMail extends Mailable
{
    /**
     * @param  string  $pdfBytes  Raw PDF bytes to attach
     */
    public function __construct(
        public readonly Quota $quota,
        public readonly string $pdfBytes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibo de cuota de socio',
        );
    }

    public function content(): Content
    {
        $quota = $this->quota;

        return new Content(
            view: 'emails.quota-receipt-member',
            with: [
                'memberName' => $quota->member?->full_name ?? '-',
                'quotaTypeName' => $quota->quotaType?->name ?? 'Cuota de socio',
                'periodStart' => $quota->period_start?->format('d/m/Y') ?? '-',
                'periodEnd' => $quota->period_end?->format('d/m/Y') ?? '-',
                'amount' => number_format((float) ($quota->amount ?? 0), 2, ',', '.'),
                'paidAt' => $quota->paid_at?->format('d/m/Y'),
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
        $filename = sprintf(
            'recibo_cuota_%s_%s.pdf',
            $quota->receipt_number ?: $quota->id,
            now()->format('Y-m-d')
        );

        return [
            Attachment::fromData(fn (): string => $this->pdfBytes, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
