<?php
/**
 * Document Attachments (PDF, Word, PowerPoint)
 * UDOM Student Materials Blog
 *
 * - Upload validation (checks the real file contents, not just the extension)
 * - Storage in uploads/documents/ (direct web access is blocked; files are
 *   delivered by download.php)
 * - In-browser previews for view.php:
 *     PDF  -> the browser's built-in PDF viewer
 *     DOCX -> text, headings, lists, tables and images
 *     PPTX -> slide by slide text and images
 *     DOC / PPT (old binary formats) -> download only
 *
 * DOCX/PPTX files are ZIP archives of XML. They are read with a small
 * pure-PHP ZIP reader (zlib's gzinflate), so no extra PHP extension
 * (ZipArchive) or Microsoft Office / LibreOffice is needed on the server.
 */

/* --------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------ */

// Allowed document types: extension => [label, MIME type, Font Awesome icon, can preview]
const DOCUMENT_TYPES = [
    'pdf' => ['PDF', 'application/pdf', 'fa-file-pdf', true],
    'docx' => ['Word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'fa-file-word', true],
    'doc' => ['Word (old format)', 'application/msword', 'fa-file-word', false],
    'pptx' => ['PowerPoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'fa-file-powerpoint', true],
    'ppt' => ['PowerPoint (old format)', 'application/vnd.ms-powerpoint', 'fa-file-powerpoint', false],
];

const MAX_DOCUMENT_BYTES = 20 * 1024 * 1024; // 20 MB per document
const MAX_ATTACHMENTS_PER_POST = 10;

// Limits that protect the server while building previews
const PREVIEW_MAX_XML_BYTES = 30 * 1024 * 1024;  // Largest XML part we will unpack
const PREVIEW_MAX_IMAGE_BYTES = 3 * 1024 * 1024; // Largest single embedded image
const PREVIEW_IMAGE_BUDGET = 12 * 1024 * 1024;   // Total images per preview
const PREVIEW_MAX_SLIDES = 200;

/* --------------------------------------------------------------
 * Small helpers
 * ------------------------------------------------------------ */

/** Absolute path of the documents folder (with trailing slash). */
function documents_dir(): string
{
    return __DIR__ . '/../uploads/documents/';
}

/** Human-readable file size, e.g. "2.4 MB". */
function format_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 1) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}

/** Convert a php.ini size such as "40M" into bytes. */
function ini_bytes(string $key): int
{
    $value = trim((string) ini_get($key));
    $number = (int) $value;
    switch (strtolower(substr($value, -1))) {
        case 'g':
            return $number * 1024 * 1024 * 1024;
        case 'm':
            return $number * 1024 * 1024;
        case 'k':
            return $number * 1024;
    }
    return $number;
}

/**
 * True when a form was submitted but PHP discarded it because the upload
 * was larger than post_max_size (PHP then leaves $_POST and $_FILES empty).
 */
function post_too_large(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && empty($_POST) && empty($_FILES)
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

/** Font Awesome icon for a document type. */
function document_icon(string $type): string
{
    return DOCUMENT_TYPES[$type][2] ?? 'fa-file';
}

/** Display label for a document type. */
function document_label(string $type): string
{
    return DOCUMENT_TYPES[$type][0] ?? strtoupper($type);
}

/** Whether view.php can show this type in the browser. */
function document_previewable(string $type): bool
{
    return DOCUMENT_TYPES[$type][3] ?? false;
}

/** All attachments of a post, oldest first. */
function get_post_attachments(PDO $pdo, int $post_id): array
{
    $stmt = $pdo->prepare('SELECT * FROM attachments WHERE post_id = :id ORDER BY id ASC');
    $stmt->execute([':id' => $post_id]);
    return $stmt->fetchAll();
}

/** Remove a stored document file from disk (only files we created). */
function delete_document_file(?string $file_name): void
{
    $file_name = basename((string) $file_name);
    if ($file_name === '' || strpos($file_name, 'doc_') !== 0) {
        return;
    }
    $path = documents_dir() . $file_name;
    if (is_file($path)) {
        @unlink($path);
    }
}

/* --------------------------------------------------------------
 * Upload validation & storage
 * ------------------------------------------------------------ */

/**
 * Check that a file really is the type its extension claims.
 * Returns an error message, or null when the file is fine.
 */
function document_content_error(string $path, string $ext): ?string
{
    $head = (string) file_get_contents($path, false, null, 0, 1024);

    switch ($ext) {
        case 'pdf':
            return strpos($head, '%PDF-') !== false ? null : 'is not a valid PDF file';

        case 'doc':
        case 'ppt':
            // Old Office files use the OLE compound file format
            return strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0
                ? null : 'is not a valid Office ' . strtoupper($ext) . ' file';

        case 'docx':
        case 'pptx':
            $zip = zip_open_index($path);
            if (!$zip) {
                return 'is not a valid ' . strtoupper($ext) . ' file';
            }
            $main = $ext === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
            $macro = $ext === 'docx' ? 'word/vbaProject.bin' : 'ppt/vbaProject.bin';
            $ok = isset($zip['entries'][$main]);
            $has_macros = isset($zip['entries'][$macro]);
            fclose($zip['fh']);
            if (!$ok) {
                return 'is not a valid ' . strtoupper($ext) . ' file';
            }
            if ($has_macros) {
                return 'contains macros, which are not allowed';
            }
            return null;
    }
    return 'has a file type that is not allowed';
}

/**
 * Validate and store the documents chosen in a multiple file input.
 *
 * $slots   How many more attachments this post may receive.
 * $errors  Receives one message per rejected file.
 * Returns the saved files: [original_name, file_name, file_type, file_size].
 * If $errors is not empty the caller should discard the saved files.
 */
function handle_document_uploads(string $field, int $slots, array &$errors): array
{
    $saved = [];
    if (empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
        return $saved;
    }

    $f = $_FILES[$field];
    $count = count($f['name']);

    for ($i = 0; $i < $count; $i++) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        // Clean display name: no folders, no control characters, max 200 chars
        $original = basename(str_replace('\\', '/', (string) $f['name'][$i]));
        $original = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $original)), 0, 200);
        $label = $original !== '' ? '"' . $original . '"' : 'A file';
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (count($saved) >= $slots) {
            $errors[] = 'You can attach at most ' . MAX_ATTACHMENTS_PER_POST . ' documents to one post.';
            break;
        }
        if (in_array($f['error'][$i], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || $f['size'][$i] > MAX_DOCUMENT_BYTES) {
            $errors[] = $label . ' is too large. Maximum size is ' . format_bytes(MAX_DOCUMENT_BYTES) . '.';
            continue;
        }
        if ($f['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $label . ' could not be uploaded (error code ' . (int) $f['error'][$i] . ').';
            continue;
        }
        if (!isset(DOCUMENT_TYPES[$ext])) {
            $errors[] = $label . ' is not allowed. Use PDF, DOC, DOCX, PPT or PPTX.';
            continue;
        }
        if ($f['size'][$i] === 0) {
            $errors[] = $label . ' is empty.';
            continue;
        }
        $problem = document_content_error($f['tmp_name'][$i], $ext);
        if ($problem !== null) {
            $errors[] = $label . ' ' . $problem . '.';
            continue;
        }

        $dir = documents_dir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $errors[] = 'The documents folder is missing and could not be created.';
            break;
        }

        $stored = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'][$i], $dir . $stored)) {
            $errors[] = $label . ' could not be saved on the server.';
            continue;
        }

        $saved[] = [
            'original_name' => $original,
            'file_name' => $stored,
            'file_type' => $ext,
            'file_size' => (int) $f['size'][$i],
        ];
    }

    return $saved;
}

/**
 * Insert attachment rows for a post (call inside the post's transaction).
 */
function save_attachments(PDO $pdo, int $post_id, array $files): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO attachments (post_id, original_name, file_name, file_type, file_size)
         VALUES (:post_id, :original_name, :file_name, :file_type, :file_size)'
    );
    foreach ($files as $file) {
        $stmt->execute([
            ':post_id' => $post_id,
            ':original_name' => $file['original_name'],
            ':file_name' => $file['file_name'],
            ':file_type' => $file['file_type'],
            ':file_size' => $file['file_size'],
        ]);
    }
}

/* --------------------------------------------------------------
 * Minimal ZIP reader (DOCX and PPTX are ZIP archives)
 * ------------------------------------------------------------ */

/**
 * Read a ZIP file's central directory.
 * Returns ['fh' => file handle, 'entries' => [name => info]] or null.
 * Close $zip['fh'] when finished.
 */
function zip_open_index(string $path): ?array
{
    $size = @filesize($path);
    $fh = @fopen($path, 'rb');
    if (!$fh || !$size || $size < 22) {
        return null;
    }

    // The "end of central directory" record is in the last 64 KB
    $tail_len = min($size, 65557);
    fseek($fh, $size - $tail_len);
    $tail = fread($fh, $tail_len);
    $pos = strrpos($tail, "PK\x05\x06");
    if ($pos === false || strlen($tail) < $pos + 22) {
        fclose($fh);
        return null;
    }
    $eocd = unpack('vdisk/vcd_disk/vdisk_entries/ventries/Vcd_size/Vcd_offset', substr($tail, $pos + 4, 16));
    if ($eocd['cd_offset'] + $eocd['cd_size'] > $size || $eocd['entries'] > 20000) {
        fclose($fh);
        return null;
    }

    fseek($fh, $eocd['cd_offset']);
    $cd = fread($fh, $eocd['cd_size']);
    $entries = [];
    $p = 0;
    $cd_len = strlen($cd);

    while ($p + 46 <= $cd_len && substr($cd, $p, 4) === "PK\x01\x02") {
        $e = unpack(
            'vmade_by/vneeded/vflags/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vusize/vname_len/vextra_len/vcomment_len/vdisk_start/vint_attr/Vext_attr/Vlocal_offset',
            substr($cd, $p + 4, 42)
        );
        $name = substr($cd, $p + 46, $e['name_len']);
        $entries[$name] = [
            'method' => $e['method'],
            'csize' => $e['csize'],
            'usize' => $e['usize'],
            'offset' => $e['local_offset'],
        ];
        $p += 46 + $e['name_len'] + $e['extra_len'] + $e['comment_len'];
    }

    if (!$entries) {
        fclose($fh);
        return null;
    }
    return ['fh' => $fh, 'entries' => $entries];
}

/**
 * Get one file out of the ZIP, or null if missing, too big or unreadable.
 */
function zip_entry(array $zip, string $name, int $max_bytes): ?string
{
    if (!isset($zip['entries'][$name])) {
        return null;
    }
    $e = $zip['entries'][$name];
    if ($e['usize'] > $max_bytes || $e['csize'] > $max_bytes) {
        return null;
    }

    // Local header: 30 bytes + file name + extra field, then the data
    fseek($zip['fh'], $e['offset']);
    $lh = fread($zip['fh'], 30);
    if (strlen($lh) < 30 || substr($lh, 0, 4) !== "PK\x03\x04") {
        return null;
    }
    $l = unpack('vname_len/vextra_len', substr($lh, 26, 4));
    fseek($zip['fh'], $e['offset'] + 30 + $l['name_len'] + $l['extra_len']);
    $data = $e['csize'] > 0 ? fread($zip['fh'], $e['csize']) : '';

    if ($e['method'] === 0) {
        return $data;          // Stored (no compression)
    }
    if ($e['method'] === 8) {   // Deflate
        $out = @gzinflate($data, $max_bytes);
        return $out === false ? null : $out;
    }
    return null;
}

/**
 * Resolve a relationship target ("../media/image1.png") against a folder ("ppt/slides").
 */
function zip_resolve_path(string $base_dir, string $target): string
{
    if (strpos($target, '/') === 0) {
        return ltrim($target, '/');
    }
    $parts = $base_dir === '' ? [] : explode('/', $base_dir);
    foreach (explode('/', $target) as $segment) {
        if ($segment === '..') {
            array_pop($parts);
        } elseif ($segment !== '.' && $segment !== '') {
            $parts[] = $segment;
        }
    }
    return implode('/', $parts);
}

/**
 * Load an XML part safely (no network access, no entity expansion).
 */
function load_xml_part(array $zip, string $name): ?DOMDocument
{
    $xml = zip_entry($zip, $name, PREVIEW_MAX_XML_BYTES);
    if ($xml === null || $xml === '') {
        return null;
    }
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $ok ? $dom : null;
}

/**
 * Relationship ids => targets from a .rels file.
 */
function load_relationships(array $zip, string $rels_path): array
{
    $rels = [];
    $dom = load_xml_part($zip, $rels_path);
    if ($dom) {
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            $rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
    }
    return $rels;
}

/**
 * Embedded image as a safe <img> tag (data URI), within the size budget.
 * Only real PNG / JPEG / GIF / WEBP images are shown.
 */
function preview_image_tag(array $zip, string $path, int &$budget, string $class = 'doc-preview-image'): string
{
    $data = zip_entry($zip, $path, PREVIEW_MAX_IMAGE_BYTES);
    if ($data === null || strlen($data) > $budget) {
        return '<span class="doc-preview-noimage"><i class="fa-regular fa-image"></i> Image not shown in preview</span>';
    }
    $info = @getimagesizefromstring($data);
    if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
        return '';
    }
    $budget -= strlen($data);
    return '<img class="' . $class . '" src="data:' . $info['mime'] . ';base64,' . base64_encode($data)
        . '" alt="" loading="lazy">';
}

/* --------------------------------------------------------------
 * DOCX preview
 * ------------------------------------------------------------ */

const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
const NS_P = 'http://schemas.openxmlformats.org/presentationml/2006/main';

/**
 * Turn a Word document into simple, safe HTML (all text is escaped).
 * Returns null if the file cannot be read.
 */
function render_docx_preview(string $path): ?string
{
    $zip = zip_open_index($path);
    if (!$zip) {
        return null;
    }
    $dom = load_xml_part($zip, 'word/document.xml');
    if (!$dom) {
        fclose($zip['fh']);
        return null;
    }

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('w', NS_W);
    $xp->registerNamespace('a', NS_A);
    $xp->registerNamespace('r', NS_R);

    $rels = load_relationships($zip, 'word/_rels/document.xml.rels');
    $budget = PREVIEW_IMAGE_BUDGET;
    $html = '';
    $list_open = false;

    $body = $xp->query('/w:document/w:body')->item(0);
    if (!$body) {
        fclose($zip['fh']);
        return null;
    }

    foreach ($body->childNodes as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        if ($node->localName === 'p') {
            [$inner, $tag, $is_list] = docx_paragraph($xp, $node, $zip, $rels, $budget);
            if ($is_list && trim(strip_tags($inner, '<img>')) !== '') {
                if (!$list_open) {
                    $html .= '<ul>';
                    $list_open = true;
                }
                $html .= '<li>' . $inner . '</li>';
                continue;
            }
            if ($list_open) {
                $html .= '</ul>';
                $list_open = false;
            }
            if (trim(strip_tags($inner, '<img>')) !== '') {
                $html .= '<' . $tag . '>' . $inner . '</' . $tag . '>';
            }
        } elseif ($node->localName === 'tbl') {
            if ($list_open) {
                $html .= '</ul>';
                $list_open = false;
            }
            $html .= '<div class="table-responsive"><table class="doc-preview-table">';
            foreach ($xp->query('w:tr', $node) as $row) {
                $html .= '<tr>';
                foreach ($xp->query('w:tc', $row) as $cell) {
                    $parts = [];
                    foreach ($xp->query('w:p', $cell) as $p) {
                        [$inner] = docx_paragraph($xp, $p, $zip, $rels, $budget);
                        if (trim(strip_tags($inner, '<img>')) !== '') {
                            $parts[] = $inner;
                        }
                    }
                    $html .= '<td>' . implode('<br>', $parts) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table></div>';
        }
    }
    if ($list_open) {
        $html .= '</ul>';
    }

    fclose($zip['fh']);
    return $html;
}

/**
 * One Word paragraph => [inner HTML, tag name, is list item].
 */
function docx_paragraph(DOMXPath $xp, DOMElement $p, array $zip, array $rels, int &$budget): array
{
    $style = $xp->evaluate('string(w:pPr/w:pStyle/@w:val)', $p);
    $tag = 'p';
    if (preg_match('/^(Title|Heading1)$/i', $style)) {
        $tag = 'h2';
    } elseif (preg_match('/^Heading2$/i', $style)) {
        $tag = 'h3';
    } elseif (preg_match('/^Heading[3-9]$/i', $style)) {
        $tag = 'h4';
    }
    $is_list = $xp->query('w:pPr/w:numPr', $p)->length > 0 || stripos($style, 'List') !== false;

    $inner = '';
    foreach ($xp->query('w:r | w:hyperlink/w:r | w:ins/w:r | w:smartTag/w:r', $p) as $run) {
        $on = function (string $prop) use ($xp, $run): bool {
            $el = $xp->query('w:rPr/w:' . $prop, $run)->item(0);
            if (!$el) {
                return false;
            }
            $val = $el->getAttributeNS(NS_W, 'val');
            return !in_array($val, ['0', 'false', 'none'], true);
        };

        $text = '';
        foreach ($run->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            switch ($child->localName) {
                case 't':
                    $text .= htmlspecialchars($child->textContent, ENT_QUOTES, 'UTF-8');
                    break;
                case 'tab':
                    $text .= ' ';
                    break;
                case 'br':
                case 'cr':
                    $text .= '<br>';
                    break;
                case 'drawing':
                case 'pict':
                    foreach ($xp->query('.//a:blip/@r:embed', $child) as $embed) {
                        $target = $rels[$embed->value] ?? '';
                        if ($target !== '') {
                            $text .= preview_image_tag($zip, zip_resolve_path('word', $target), $budget);
                        }
                    }
                    break;
            }
        }
        if ($text === '') {
            continue;
        }
        if ($on('b')) {
            $text = '<strong>' . $text . '</strong>';
        }
        if ($on('i')) {
            $text = '<em>' . $text . '</em>';
        }
        if ($on('u')) {
            $text = '<u>' . $text . '</u>';
        }
        $inner .= $text;
    }

    return [$inner, $tag, $is_list];
}

/* --------------------------------------------------------------
 * PPTX preview
 * ------------------------------------------------------------ */

/**
 * Turn a PowerPoint file into slide cards (title, text, images).
 * Returns null if the file cannot be read.
 */
function render_pptx_preview(string $path): ?string
{
    $zip = zip_open_index($path);
    if (!$zip) {
        return null;
    }

    // Slide order comes from presentation.xml; fall back to file numbering
    $slides = [];
    $pres = load_xml_part($zip, 'ppt/presentation.xml');
    if ($pres) {
        $pres_rels = load_relationships($zip, 'ppt/_rels/presentation.xml.rels');
        $xp = new DOMXPath($pres);
        $xp->registerNamespace('p', NS_P);
        $xp->registerNamespace('r', NS_R);
        foreach ($xp->query('//p:sldIdLst/p:sldId') as $sld) {
            $target = $pres_rels[$sld->getAttributeNS(NS_R, 'id')] ?? '';
            if ($target !== '') {
                $slides[] = zip_resolve_path('ppt', $target);
            }
        }
    }
    if (!$slides) {
        foreach (array_keys($zip['entries']) as $name) {
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                $slides[(int) $m[1]] = $name;
            }
        }
        ksort($slides);
        $slides = array_values($slides);
    }
    if (!$slides) {
        fclose($zip['fh']);
        return null;
    }

    $budget = PREVIEW_IMAGE_BUDGET;
    $total = count($slides);
    $html = '';

    foreach (array_slice($slides, 0, PREVIEW_MAX_SLIDES) as $index => $slide_path) {
        $dom = load_xml_part($zip, $slide_path);
        if (!$dom) {
            continue;
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('p', NS_P);
        $xp->registerNamespace('a', NS_A);
        $xp->registerNamespace('r', NS_R);

        $slide_dir = dirname($slide_path);
        $rels = load_relationships($zip, $slide_dir . '/_rels/' . basename($slide_path) . '.rels');

        $title = '';
        $lines = [];
        // Text boxes and tables, in the order they appear on the slide
        foreach ($xp->query('//p:sp | //p:graphicFrame') as $shape) {
            $ph = $xp->evaluate('string(p:nvSpPr/p:nvPr/p:ph/@type)', $shape);
            foreach ($xp->query('.//a:p', $shape) as $para) {
                $text = '';
                foreach ($xp->query('.//a:t', $para) as $t) {
                    $text .= $t->textContent;
                }
                $text = trim(preg_replace('/\s+/u', ' ', $text));
                if ($text === '') {
                    continue;
                }
                if ($title === '' && in_array($ph, ['title', 'ctrTitle'], true)) {
                    $title = $text;
                } else {
                    $lines[] = $text;
                }
            }
        }

        $images = '';
        foreach ($xp->query('//p:pic//a:blip/@r:embed') as $embed) {
            $target = $rels[$embed->value] ?? '';
            if ($target !== '') {
                $images .= preview_image_tag($zip, zip_resolve_path($slide_dir, $target), $budget, 'slide-preview-image');
            }
        }

        $html .= '<section class="slide-preview" aria-label="Slide ' . ($index + 1) . '">';
        $html .= '<div class="slide-preview-number">Slide ' . ($index + 1) . ' of ' . $total . '</div>';
        if ($title !== '') {
            $html .= '<h3 class="slide-preview-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
        }
        if ($lines) {
            $html .= '<ul class="slide-preview-text">';
            foreach ($lines as $line) {
                $html .= '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul>';
        }
        if ($images !== '') {
            $html .= '<div class="slide-preview-images">' . $images . '</div>';
        }
        if ($title === '' && !$lines && $images === '') {
            $html .= '<p class="text-muted">This slide has no text or pictures to preview.</p>';
        }
        $html .= '</section>';
    }

    if ($total > PREVIEW_MAX_SLIDES) {
        $html .= '<p class="text-muted">Only the first ' . PREVIEW_MAX_SLIDES . ' slides are shown. Download the file to see all ' . $total . '.</p>';
    }

    fclose($zip['fh']);
    return $html;
}
