<?php

namespace App\Http\Controllers;

use App\Enums\PdfEngine;
use App\Models\Association;
use App\Models\Quota;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class QuotaReceiptController extends Controller
{
    public function __construct(private readonly PdfService $pdfService) {}

    public function show(Request $request, Quota $quota): Response
    {
        $association = Association::firstOrFail();

        $receiptType = (string) $request->query('type', 'full');

        if (! in_array($receiptType, ['full', 'association', 'member', 'refund', 'refundA5', 'refund_association', 'refund_member'], true)) {
            $receiptType = 'full';
        }

        $isRefund = str_starts_with($receiptType, 'refund');

        if ($isRefund) {
            $refundA4View = 'documents.quotas.QuotaRefundReceiptA4';
            $refundA5View = 'documents.quotas.QuotaRefundReceiptA5';

            $view = ($receiptType === 'refund' && View::exists($refundA4View))
                ? $refundA4View
                : $refundA5View;

            $receiptLegendType = match ($receiptType) {
                'refund_member' => 'member',
                default => 'association',
            };
        } else {
            $view = match ($receiptType) {
                'full' => 'documents.quotas.QuotaReceiptA4',
                default => 'documents.quotas.QuotaReceiptA5',
            };

            $receiptLegendType = $receiptType;
        }

        $filename = sprintf(
            'recibo_cuota_%s_%s_%s.pdf',
            $quota->receipt_number ?: $quota->id,
            $receiptType,
            now()->format('Y-m-d_His')
        );

        $rawPdf = $this->pdfService->renderView($view, [
            'quota' => $quota,
            'association' => $association,
            'receiptType' => $receiptLegendType,
            'pdf_meta' => [
                'creator' => 'AsoVec',
                'author' => 'AsoVec',
                'title' => 'Recibo de cuota',
                'keywords' => 'AsoVec, recibo, cuota',
                'filename' => $filename,
            ],
        ], PdfEngine::Dompdf);

        return $this->pdfService->inlineResponse($rawPdf, $filename);
    }
}
