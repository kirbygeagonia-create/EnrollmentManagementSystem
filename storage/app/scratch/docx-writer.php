<?php

/**
 * A small OOXML writer shared by the document generators in this repository.
 *
 * Two generators each carrying their own paragraph-and-table serializer is the two-answers-to-one-question defect
 * waiting to disagree (a heading that renders in one file and not the other), so the block vocabulary lives here:
 * h1, h2, h3, p, bullets, steps, table. Every generator hands over blocks and this writes the package.
 *
 * Tables are fixed-width across a printable page of 9360 twentieths of a point; text is escaped on the way in, so a
 * verdict carrying `enum('a','b')` or an arrow cannot corrupt the archive.
 */
if (! function_exists('docxBlocksToBody')) {
    /**
     * @param  array<int, array<int, mixed>>  $blocks
     */
    function docxBlocksToBody(array $blocks): string
    {
        $pageW = 9360;
        $esc = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8', false);
        $pPr = static fn (string $extra = ''): string => '<w:pPr>'.$extra.'</w:pPr>';
        $run = static function (string $text, array $props = []) use ($esc): string {
            $rPr = '';
            if (! empty($props['b'])) {
                $rPr .= '<w:b/>';
            }
            if (! empty($props['sz'])) {
                $rPr .= '<w:sz w:val="'.((int) $props['sz']).'"/><w:szCs w:val="'.((int) $props['sz']).'"/>';
            }
            if (! empty($props['color'])) {
                $rPr .= '<w:color w:val="'.$esc($props['color']).'"/>';
            }

            return ($rPr === '' ? '' : '<w:rPr>'.$rPr.'</w:rPr>').'<w:t xml:space="preserve">'.$esc($text).'</w:t>';
        };

        $body = '';
        foreach ($blocks as $b) {
            [$kind, $a, $c] = array_pad($b, 3, null);
            if ($kind === 'h1') {
                $body .= '<w:p>'.$pPr('<w:spacing w:before="240" w:after="160"/>').'<w:r>'.$run($a, ['b' => 1, 'sz' => 36]).'</w:r></w:p>';
            } elseif ($kind === 'h2') {
                $body .= '<w:p>'.$pPr('<w:spacing w:before="280" w:after="120"/>').'<w:r>'.$run($a, ['b' => 1, 'sz' => 28, 'color' => '1F3864']).'</w:r></w:p>';
            } elseif ($kind === 'h3') {
                $body .= '<w:p>'.$pPr('<w:spacing w:before="160" w:after="80"/>').'<w:r>'.$run($a, ['b' => 1, 'sz' => 24, 'color' => '2E5496']).'</w:r></w:p>';
            } elseif ($kind === 'p') {
                $body .= '<w:p>'.$pPr('<w:spacing w:after="120"/><w:jc w:val="both"/>').'<w:r>'.$run($a).'</w:r></w:p>';
            } elseif ($kind === 'bullets') {
                foreach ((array) $a as $item) {
                    $body .= '<w:p>'.$pPr('<w:spacing w:after="40"/><w:ind w:left="360" w:hanging="180"/>').'<w:r>'.$run("\u{2022}  ".(string) $item).'</w:r></w:p>';
                }
            } elseif ($kind === 'steps') {
                foreach ((array) $a as $i => $item) {
                    $body .= '<w:p>'.$pPr('<w:spacing w:after="60"/><w:ind w:left="420" w:hanging="420"/>').'<w:r>'.
                        $run((string) ($i + 1).'.  '.$item).'</w:r></w:p>';
                }
            } elseif ($kind === 'table') {
                $cols = count($a);
                if ($cols === 0) {
                    throw new InvalidArgumentException('a table block with no header cell cannot be judged for width');
                }
                $w = intdiv($pageW, $cols);
                $grid = str_repeat('<w:gridCol w:w="'.$w.'"/>', $cols);
                $tbl = '<w:tbl><w:tblPr><w:tblW w:w="'.$pageW.'" w:type="dxa"/><w:tblBorders>'
                    .'<w:top w:val="single" w:sz="4" w:color="BFBFBF"/><w:left w:val="single" w:sz="4" w:color="BFBFBF"/>'
                    .'<w:bottom w:val="single" w:sz="4" w:color="BFBFBF"/><w:right w:val="single" w:sz="4" w:color="BFBFBF"/>'
                    .'<w:insideH w:val="single" w:sz="2" w:color="D9D9D9"/><w:insideV w:val="single" w:sz="2" w:color="D9D9D9"/>'
                    .'</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>'.$grid.'</w:tblGrid>';
                $row = static function (array $cells, bool $header) use ($w, $pPr, $run): string {
                    $r = '<w:tr>';
                    foreach ($cells as $cell) {
                        $r .= '<w:tc><w:tcPr><w:tcW w:w="'.$w.'" w:type="dxa"/>'
                            .($header ? '<w:shd w:val="clear" w:color="auto" w:fill="EDEFF3"/>' : '').'</w:tcPr>'
                            .'<w:p>'.$pPr('<w:spacing w:after="20"/>').'<w:r>'.$run((string) $cell, $header ? ['b' => 1] : []).'</w:r></w:p></w:tc>';
                    }

                    return $r.'</w:tr>';
                };
                $tbl .= $row($a, true);
                foreach ((array) $c as $r) {
                    $tbl .= $row((array) $r, false);
                }
                $body .= $tbl.'</w:tbl><w:p>'.$pPr('<w:spacing w:after="120"/>').'</w:p>';
            } else {
                throw new InvalidArgumentException("unknown block kind '{$kind}'");
            }
        }

        return $body;
    }
}

if (! function_exists('docxPackage')) {
    /** Wraps a body of blocks in the five parts a .docx must hold, and refuses an empty document. */
    function docxPackage(string $out, string $body, bool $allowOverwrite = false): void
    {
        if (trim($body) === '') {
            throw new RuntimeException('the body is empty; an empty package is not a document.');
        }
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<w:body>'.$body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/>'
            .'</w:sectPr></w:body></w:document>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/></w:rPr></w:rPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'</w:styles>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';

        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';

        $relsDoc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';

        foreach (['document' => $document, 'styles' => $styles, 'content types' => $contentTypes, 'relationships' => $relsRoot, 'document relationships' => $relsDoc] as $name => $xml) {
            libxml_use_internal_errors(true);
            if (simplexml_load_string($xml) === false) {
                $err = libxml_get_errors();
                throw new RuntimeException("part {$name} does not parse: ".trim($err === false ? 'unknown' : $err[0]->message));
            }
        }

        if (is_file($out) && ! $allowOverwrite) {
            throw new RuntimeException("{$out} already exists and this run was not told to replace it.");
        }
        if (is_file($out)) {
            unlink($out);
        }
        $zip = new ZipArchive;
        if ($zip->open($out, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("could not create {$out}.");
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $relsRoot);
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/_rels/document.xml.rels', $relsDoc);
        $zip->addFromString('word/styles.xml', $styles);
        $zip->close();

        $check = new ZipArchive;
        if ($check->open($out) !== true) {
            throw new RuntimeException("the file written does not open as a package: {$out}");
        }
        foreach (['[Content_Types].xml', '_rels/.rels', 'word/document.xml', 'word/_rels/document.xml.rels', 'word/styles.xml'] as $part) {
            if ($check->getFromName($part) === false) {
                $check->close();

                throw new RuntimeException("the written package has no {$part}.");
            }
        }
        $check->close();
    }
}
