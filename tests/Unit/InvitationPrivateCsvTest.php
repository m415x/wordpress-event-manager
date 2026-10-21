<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InvitationPrivateCsvTest extends TestCase
{
    public function testEscapesSpreadsheetFormulaeAndPreservesIndividualUrls(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-private-csv.php';
        $writer = new \WEM_Invitation_Private_Csv();
        $url = 'https://example.test/?wem_invitation=' . str_repeat('A', 43);
        $csv = $writer->render([
            [
                'guest_id' => 91,
                'guest_name' => '=HYPERLINK("https://evil.test","click")',
                'event_id' => 7,
                'invitation_url' => $url,
            ],
            [
                'guest_id' => 92,
                'guest_name' => '+SUM(1,2)',
                'event_id' => 7,
                'invitation_url' => $url,
            ],
        ]);
        // Escaped formulas still contain their literal text; validate parsed cells below.
        self::assertStringContainsString($url, $csv);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        self::assertSame(['guest_id', 'guest_name', 'event_id', 'invitation_url'], fgetcsv($stream));
        $first = fgetcsv($stream);
        $second = fgetcsv($stream);
        fclose($stream);
        self::assertSame("'=HYPERLINK(\"https://evil.test\",\"click\")", $first[1]);
        self::assertSame("'+SUM(1,2)", $second[1]);
        self::assertSame($url, $first[3]);
    }
}
