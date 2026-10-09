<?php

if (!defined('ABSPATH') && !defined('PHPUNIT_COMPOSER_INSTALL')) {
    // This class is also exercised through the isolated Composer PHPUnit bootstrap.
    // No direct web execution is allowed.
    if (PHP_SAPI !== 'cli') {
        exit;
    }
}

/**
 * Local, pure QR rendering adapter. Invitation credentials remain owned by WEM-33.
 */
final class WEM_Local_QR_Encoder
{
    /**
     * Render a self-contained SVG. The caller must authorize the request and
     * pass the freshly composed public invitation URL; no secrets are stored.
     */
    public function render_svg(string $url): string
    {
        if ($url === '') {
            throw new InvalidArgumentException('Invitation URL cannot be empty.');
        }

        $options = new \chillerlan\QRCode\QROptions();
        $options->outputInterface = \chillerlan\QRCode\Output\QRMarkupSVG::class;
        $options->outputBase64 = false;
        $options->svgAddXmlHeader = false;

        return (new \chillerlan\QRCode\QRCode($options))->render($url);
    }
}
