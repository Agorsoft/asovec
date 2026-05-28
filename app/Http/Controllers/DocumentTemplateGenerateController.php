<?php

namespace App\Http\Controllers;

use App\Models\DocumentGeneration;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\Quota;
use App\Services\Documents\DocxTemplateRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateGenerateController extends Controller
{
    public function __construct(private readonly DocxTemplateRenderer $renderer) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate([
            'template_id' => ['required', 'integer', 'exists:document_templates,id'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'quota_id' => ['nullable', 'integer', 'exists:quotas,id'],
        ]);

        $template = DocumentTemplate::findOrFail($request->integer('template_id'));
        $member = Member::findOrFail($request->integer('member_id'));

        $context = ['member' => $member];

        if ($request->filled('quota_id')) {
            $quota = Quota::with('quotaType')->findOrFail($request->integer('quota_id'));
            $context['quota'] = $quota;
            $context['quota_type'] = $quota->quotaType;
        }

        $generation = DocumentGeneration::create([
            'template_id' => $template->id,
            'generated_by' => Auth::id(),
            'member_id' => $member->id,
            'status' => 'completed',
        ]);

        try {
            $bytes = $this->renderer->render($template, $context);

            $filename = Str::slug($template->name)
                .'_'.Str::slug($member->last_name.'_'.$member->first_name)
                .'_'.Carbon::now()->format('Ymd_His')
                .'.docx';

            return response()->streamDownload(
                fn () => print ($bytes),
                $filename,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            );
        } catch (\Throwable $e) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            abort(500, $e->getMessage());
        }
    }
}
