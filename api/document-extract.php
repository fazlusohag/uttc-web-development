<?php
declare(strict_types=1);

/** Extract text from administrator-uploaded, untrusted documents. */
function uttc_extract_document(string $path, string $ext): string {
    if ($ext === 'txt' || $ext === 'md') {
        $text = file_get_contents($path);
        if ($text === false) throw new RuntimeException('ফাইলটি পড়া যাচ্ছে না।');
        if (!mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        return trim($text);
    }
    if ($ext === 'docx') {
        if (!class_exists('ZipArchive')) throw new RuntimeException('এই সার্ভারে DOCX পড়ার জন্য PHP ZipArchive extension চালু নেই।');
        if (!class_exists('DOMDocument')) throw new RuntimeException('এই সার্ভারে DOCX পড়ার জন্য PHP DOM extension চালু নেই।');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('DOCX ফাইলটি পড়া যাচ্ছে না।');
        $stat = $zip->statName('word/document.xml');
        if (!is_array($stat) || (int)($stat['size'] ?? 0) > 10 * 1024 * 1024) {
            $zip->close();
            throw new RuntimeException('DOCX-এর মূল লেখার আকার ১০ MB-এর বেশি বা ফাইলটি অসম্পূর্ণ।');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) throw new RuntimeException('DOCX ফাইলে মূল লেখা পাওয়া যায়নি।');
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET)) throw new RuntimeException('DOCX-এর লেখা পার্স করা যায়নি।');
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $parts = [];
        foreach ($xp->query('//w:p') as $paragraph) {
            $line = '';
            foreach ($xp->query('.//w:t', $paragraph) as $node) $line .= $node->textContent;
            if ($line !== '') $parts[] = $line;
        }
        return trim(implode("\n", $parts));
    }
    if ($ext === 'pdf') {
        $bin = null;
        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'] as $candidate) if (is_executable($candidate)) { $bin = $candidate; break; }
        if (!$bin) throw new RuntimeException('এই সার্ভারে PDF থেকে লেখা পড়ার pdftotext সুবিধা পাওয়া যায়নি। TXT বা DOCX ফাইল দিন, অথবা সার্ভারে PDF parser সেটআপ করতে হবে।');
        $pipes = [];
        $proc = proc_open([$bin, '-layout', $path, '-'], [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        if (!is_resource($proc)) throw new RuntimeException('PDF parser চালু করা যায়নি।');
        $text = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0 || $text === false) throw new RuntimeException('PDF থেকে লেখা বের করা যায়নি।');
        return trim($text);
    }
    throw new RuntimeException('এই ফাইল ফরম্যাট সমর্থিত নয়।');
}
