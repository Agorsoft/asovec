<?php

use App\Services\Documents\DocxTemplateParser;
use App\Services\Documents\DocumentVariableRegistry;

covers(DocxTemplateParser::class);

/**
 * Crea un DOCX mínimo (ZIP con word/document.xml) en memoria y lo vuelca a un temporal.
 *
 * @param  string  $bodyText  Texto XML que irá dentro de <w:body>
 */
function makeMinimalDocx(string $bodyText): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body><w:p><w:r><w:t>'
        . $bodyText
        . '</w:t></w:r></w:p></w:body></w:document>';

    $tmp = tempnam(sys_get_temp_dir(), 'docx_test_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    return $tmp;
}

test('detecta variables válidas del catálogo', function () {
    $path = makeMinimalDocx('Hola ${member.first_name} ${member.last_name}');

    $registry = new DocumentVariableRegistry();
    $parser = new DocxTemplateParser();

    $result = $parser->parse($path, $registry);

    expect($result['valid'])->toContain('member.first_name', 'member.last_name')
        ->and($result['invalid'])->toBeEmpty();

    @unlink($path);
});

test('clasifica correctamente variables no reconocidas', function () {
    $path = makeMinimalDocx('${member.first_name} ${socio.nombre_desconocido}');

    $registry = new DocumentVariableRegistry();
    $parser = new DocxTemplateParser();

    $result = $parser->parse($path, $registry);

    expect($result['valid'])->toContain('member.first_name')
        ->and($result['invalid'])->toContain('socio.nombre_desconocido');

    @unlink($path);
});

test('devuelve arrays vacíos si no hay variables', function () {
    $path = makeMinimalDocx('Sin variables aquí.');

    $registry = new DocumentVariableRegistry();
    $parser = new DocxTemplateParser();

    $result = $parser->parse($path, $registry);

    expect($result['valid'])->toBeEmpty()
        ->and($result['invalid'])->toBeEmpty()
        ->and($result['all'])->toBeEmpty();

    @unlink($path);
});

test('deduplica variables repetidas', function () {
    $path = makeMinimalDocx('${member.first_name} y también ${member.first_name}');

    $registry = new DocumentVariableRegistry();
    $parser = new DocxTemplateParser();

    $result = $parser->parse($path, $registry);

    expect($result['all'])->toHaveCount(1);

    @unlink($path);
});

test('lanza excepción si el archivo no es un DOCX válido', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'invalid_');
    file_put_contents($tmp, 'esto no es un zip');

    $registry = new DocumentVariableRegistry();
    $parser = new DocxTemplateParser();

    expect(fn () => $parser->parse($tmp, $registry))->toThrow(RuntimeException::class);

    @unlink($tmp);
});
