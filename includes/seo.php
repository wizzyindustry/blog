<?php
/**
 * <head> helpers: meta description, canonical URL, social sharing (Open Graph / Twitter),
 * favicons + web app manifest, and fonts loaded according to the visitor's cookie choice.
 */

const SITE_NAME = 'UDOM Student Materials';
const SITE_DESCRIPTION = 'Free study materials, lecture notes, past papers and campus news for University of Dodoma students, plus files shared by students themselves.';
const LEGAL_VERSION = '2026-11-12'; // Bump when the Terms or Privacy Policy change materially
const CONSENT_COOKIE = 'udom_consent';

/** The visitor's cookie choice: 'all', 'essential', or '' (not chosen yet). */
function cookie_consent(): string
{
    $v = $_COOKIE[CONSENT_COOKIE] ?? '';
    return in_array($v, ['all', 'essential'], true) ? $v : '';
}

/** Shorten text for a meta description (max ~160 characters, whole words). */
function meta_excerpt(?string $text, int $max = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8'))));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space > 80 ? mb_substr($cut, 0, $space) : $cut, " ,.;:-") . '…';
}

/**
 * All the <meta>/<link> tags for a page.
 * $m: description, canonical (path like 'post.php?id=3'), image (path), type ('website'|'article'),
 *     noindex (bool), title (for social cards). $prefix = '../' from sub-folders.
 */
function render_head_meta(array $m = [], string $prefix = ''): string
{
    $m = array_filter($m, fn($v) => $v !== null);
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $description = meta_excerpt($m['description'] ?? SITE_DESCRIPTION);
    $title = $m['title'] ?? SITE_NAME;
    $html = '<meta name="description" content="' . $e($description) . '">' . "\n";

    if (!empty($m['noindex'])) {
        $html .= '<meta name="robots" content="noindex, nofollow">' . "\n";
    } else {
        $canonical = site_url($m['canonical'] ?? ltrim(basename($_SERVER['PHP_SELF'] ?? ''), '/'));
        $image = site_url($m['image'] ?? 'assets/img/icon-512.png');
        $html .= '<link rel="canonical" href="' . $e($canonical) . '">' . "\n"
            . '<meta property="og:site_name" content="' . SITE_NAME . '">' . "\n"
            . '<meta property="og:type" content="' . $e($m['type'] ?? 'website') . '">' . "\n"
            . '<meta property="og:title" content="' . $e($title) . '">' . "\n"
            . '<meta property="og:description" content="' . $e($description) . '">' . "\n"
            . '<meta property="og:url" content="' . $e($canonical) . '">' . "\n"
            . '<meta property="og:image" content="' . $e($image) . '">' . "\n"
            . '<meta property="og:locale" content="en_TZ">' . "\n"
            . '<meta name="twitter:card" content="' . (isset($m['image']) ? 'summary_large_image' : 'summary') . '">' . "\n";
    }

    $html .= '<meta name="theme-color" content="#0A1E3C">' . "\n"
        . '<link rel="icon" href="' . $prefix . 'favicon.ico" sizes="32x32">' . "\n"
        . '<link rel="icon" href="' . $prefix . 'assets/img/favicon.svg" type="image/svg+xml">' . "\n"
        . '<link rel="apple-touch-icon" href="' . $prefix . 'assets/img/apple-touch-icon.png">' . "\n"
        . '<link rel="manifest" href="' . $prefix . 'site.webmanifest">' . "\n";

    return $html . render_font_links();
}

/**
 * Google Fonts (Inter) is a third-party request, so it is only loaded when the visitor
 * accepted "all" cookies / third-party content. Otherwise the system font is used.
 */
function render_font_links(): string
{
    if (cookie_consent() !== 'all') {
        return '';
    }
    return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n";
}
