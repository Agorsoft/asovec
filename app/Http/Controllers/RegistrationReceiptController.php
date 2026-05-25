<?php

namespace App\Http\Controllers;

use App\Enums\PdfEngine;
use App\Models\Association;
use App\Models\Registration;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class RegistrationReceiptController extends Controller
{
    public function __construct(private readonly PdfService $pdfService) {}

    public function show(Request $request, Registration $registration): Response
    {
        $association = Association::firstOrFail();

        $receiptType = (string) $request->query('type', 'full');

        if (! in_array($receiptType, ['full', 'association', 'member', 'refund', 'refundA5', 'refund_association', 'refund_member'], true)) {
            $receiptType = 'full';
        }

        $isRefund = str_starts_with($receiptType, 'refund');

        if ($isRefund) {
            $refundA4View = 'documents.registrations.RegistrationRefundReceiptA4';
            $refundA5View = 'documents.registrations.RegistrationRefundReceiptA5';

            $view = ($receiptType === 'refund' && View::exists($refundA4View))
                ? $refundA4View
                : $refundA5View;

            $receiptLegendType = match ($receiptType) {
                'refund_member' => 'member',
                default => 'association',
            };
        } else {
            $view = match ($receiptType) {
                'full' => 'documents.registrations.RegistrationReceiptA4',
                default => 'documents.registrations.RegistrationReceiptA5',
            };

            $receiptLegendType = $receiptType;
        }

        $filename = sprintf(
            'recibo_inscripcion_%s_%s_%s.pdf',
            $registration->registration_number ?: $registration->id,
            $receiptType,
            now()->format('Y-m-d_His')
        );

        $rawPdf = $this->pdfService->renderView($view, [
            'registration' => $registration,
            'association' => $association,
            'receiptType' => $receiptLegendType,
            'pdf_meta' => [
                'creator' => 'AsoVec',
                'author' => 'AsoVec',
                'title' => 'Recibo de inscripción',
                'keywords' => 'AsoVec, recibo, inscripción',
                'filename' => $filename,
            ],
        ], PdfEngine::Dompdf);

        return $this->pdfService->inlineResponse($rawPdf, $filename);
    }
}
