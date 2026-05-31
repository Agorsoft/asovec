<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class UpdateStatusController extends Controller
{
    public function show(Request $request, string $runId)
    {
        // Solo administradores pueden consultar los logs de actualización
        if (! Auth::check() || ! (Auth::user()->is_admin ?? false)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $logPath = storage_path('app/updates/runs/run_'.$runId.'/log.txt');
        if (! file_exists($logPath)) {
            return response()->json(['status' => 'idle', 'logs' => []]);
        }

        $content = @file_get_contents($logPath);
        $lines = $content === false ? [] : array_filter(explode("\n", trim($content)));
        $lc = strtolower($content ?: '');

        $status = 'running';
        if (str_contains($lc, 'actualización aplicada con éxito')) {
            $status = 'completed';
        } elseif (str_contains($lc, 'error durante la actualización') || str_contains($lc, 'comando falló') || str_contains($lc, 'falló con código')) {
            $status = 'failed';
        }

        return response()->json([
            'status' => $status,
            'logs' => array_values($lines),
        ]);
    }
}
