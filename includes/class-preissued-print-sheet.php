<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders an in-memory private HTML print sheet. Each QR is a bearer:
 * callers must authorize output and manage private custody themselves.
 */
final class WEM_Preissued_Print_Sheet
{
    public function render(array $records): string
    {
        if ($records === []) {
            throw new InvalidArgumentException('Print sheet cannot be empty.');
        }

        $slots = [];
        $urls = [];
        $cards = [];
        $encoder = new WEM_Local_QR_Encoder();

        foreach ($records as $record) {
            if (!is_array($record) || !isset($record['slot'], $record['url'])
                || !is_string($record['slot']) || !is_string($record['url'])) {
                throw new InvalidArgumentException('Invalid private print record.');
            }
            $slot = $record['slot'];
            $url = $record['url'];
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,39}$/D', $slot)
                || isset($slots[$slot]) || isset($urls[$url])) {
                throw new InvalidArgumentException('Duplicate or invalid print slot/URL.');
            }
            $parts = wp_parse_url($url);
            if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https'], true)
                || empty($parts['host']) || !isset($parts['query'])
                || !preg_match('/^wem_preissued=[A-Za-z0-9_-]{43}$/D', $parts['query'])
                || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
                throw new InvalidArgumentException('Non-canonical invitation URL.');
            }
            $slots[$slot] = true;
            $urls[$url] = true;
            // WEM_Local_QR_Encoder produces XML/SVG locally without external requests.
            $svg = $encoder->render_svg($url);
            if (!str_contains($svg, '<svg') || !str_contains($svg, '</svg>')) {
                throw new RuntimeException('Local QR rendering failed.');
            }
            $cards[] = '<article class="wem-slot"><div class="wem-qr">'
                . $svg . '</div><p class="wem-label">' . esc_html($slot) . '</p></article>';
        }

        return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="robots" content="noindex,nofollow,noarchive">'
            . '<title>Invitaciones privadas</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:12mm}'
            . '.wem-sheet{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7mm}'
            . '.wem-slot{break-inside:avoid;text-align:center;border:1px solid #bbb;padding:5mm}'
            . '.wem-qr svg{width:100%;max-width:45mm;height:auto}'
            . '.wem-label{font-size:14pt;font-weight:bold}'
            . '@media print{@page{size:A4;margin:10mm}body{margin:0}'
            . '.wem-slot{break-inside:avoid}}</style></head><body>'
            . '<main class="wem-sheet">' . implode('', $cards) . '</main></body></html>';
    }
}
