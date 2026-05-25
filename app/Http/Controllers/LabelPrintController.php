<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\Labels\PrintJobConfig;
use App\Models\LabelTemplate;
use App\Models\Member;
use App\Services\Labels\LabelPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LabelPrintController extends Controller
{
    public function __construct(private readonly LabelPdfGenerator $generator) {}

    public function generate(Request $request): Response
    {
        $request->validate([
            'template_id' => ['required', 'integer', 'exists:label_templates,id'],
            'fields' => ['required', 'string'],
            'start' => ['nullable', 'integer', 'min:1'],
            'test' => ['nullable', 'boolean'],
            'members' => ['nullable', 'string'],
            'preview' => ['nullable', 'boolean'],
        ]);

        $template = LabelTemplate::findOrFail($request->integer('template_id'));

        $fields = array_filter(explode(',', $request->string('fields')));

        $memberQuery = Member::orderBy('last_name')->orderBy('first_name');

        if ($request->filled('members')) {
            $memberIds = array_filter(
                array_map('intval', explode(',', $request->string('members')))
            );
            $memberQuery->whereIn('id', $memberIds);
        }

        $members = $memberQuery->get();

        $config = new PrintJobConfig(
            template: $template,
            members: $members,
            fields: $fields,
            startPosition: max(1, $request->integer('start', 1)),
            testSheet: (bool) $request->integer('test', 0),
        );

        $rawPdf = $this->generator->generate($config);

        $disposition = $request->integer('preview', 0) ? 'inline' : 'attachment';
        $filename = $config->testSheet ? 'etiquetas-prueba.pdf' : 'etiquetas-socios.pdf';

        return response($rawPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        ]);
    }
}
