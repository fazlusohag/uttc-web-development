<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/document-extract.php';

function assert_same(string $expected, string $actual, string $message): void {
    if ($expected !== $actual) { fwrite(STDERR, "FAIL: {$message}; got: {$actual}\n"); exit(1); }
}
function make_pdf(string $path): void {
    $stream = "BT /F1 12 Tf 20 100 Td (PDF extraction smoke test) Tj ET\n";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream"
    ];
    $pdf = "%PDF-1.4\n"; $offsets = [0];
    foreach ($objects as $index=>$object) { $offsets[] = strlen($pdf); $pdf .= ($index+1)." 0 obj\n".$object."\nendobj\n"; }
    $xref = strlen($pdf); $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i=1;$i<=5;$i++) $pdf .= sprintf("%010d 00000 n \n",$offsets[$i]);
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    file_put_contents($path,$pdf);
}

$dir = sys_get_temp_dir().'/uttc-doc-test-'.bin2hex(random_bytes(4));
mkdir($dir,0700,true);
try {
    $txt = $dir.'/sample.txt'; file_put_contents($txt,"UTTC plain text extraction smoke test");
    assert_same('UTTC plain text extraction smoke test',uttc_extract_document($txt,'txt'),'TXT extraction');

    if (class_exists('ZipArchive') && class_exists('DOMDocument')) {
        $docx = $dir.'/sample.docx'; $zip = new ZipArchive();
        if ($zip->open($docx,ZipArchive::CREATE)!==true) throw new RuntimeException('Could not create DOCX fixture');
        $xml='<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>DOCX extraction smoke test</w:t></w:r></w:p></w:body></w:document>';
        $zip->addFromString('word/document.xml',$xml); $zip->close();
        assert_same('DOCX extraction smoke test',uttc_extract_document($docx,'docx'),'DOCX extraction');
    } else {
        try { uttc_extract_document($txt,'docx'); throw new RuntimeException('Missing DOCX dependencies were not reported'); }
        catch (RuntimeException $e) { if (!str_contains($e->getMessage(),'ZipArchive')) throw $e; }
    }

    $pdfTool = is_executable('/usr/bin/pdftotext') || is_executable('/usr/local/bin/pdftotext');
    if ($pdfTool) {
        $pdf=$dir.'/sample.pdf'; make_pdf($pdf);
        $pdfText=uttc_extract_document($pdf,'pdf');
        if (!str_contains($pdfText,'PDF extraction smoke test')) throw new RuntimeException('PDF extracted text did not contain the fixture sentence');
    } else {
        try { uttc_extract_document($txt,'pdf'); throw new RuntimeException('Missing PDF dependency was not reported'); }
        catch (RuntimeException $e) { if (!str_contains($e->getMessage(),'pdftotext')) throw $e; }
    }
    echo "OK: TXT/DOCX/PDF extraction smoke tests passed for available dependencies.\n";
} finally {
    foreach (glob($dir.'/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
}
