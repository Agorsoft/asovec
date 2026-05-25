<?php

namespace App\Http\Controllers;

use App\Services\MemberImportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportDownloadController extends Controller
{
    public function download(): StreamedResponse
    {
        $csv = MemberImportService::generateExampleCsv();

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'socios_ejemplo.csv', [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="socios_ejemplo.csv"',
        ]);
    }
}
