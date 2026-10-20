<?php

/**
 * WEM-73: spreadsheet-safe CSV serialization only.
 * Persisting or releasing this output requires separately authorized,
 * reconciled, private storage.
 */
final class WEM_Invitation_Private_Csv
{
    public function render(array $records): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Unable to prepare private CSV.');
        }
        try {
            $this->write_row($stream, array('guest_id', 'guest_name', 'event_id', 'invitation_url'));
            foreach ($records as $record) {
                if (!is_array($record)
                    || !isset($record['guest_id'], $record['guest_name'], $record['event_id'], $record['invitation_url'])
                    || !is_int($record['guest_id']) || $record['guest_id'] <= 0
                    || !is_int($record['event_id']) || $record['event_id'] <= 0
                    || !is_string($record['guest_name']) || !is_string($record['invitation_url'])
                    || preg_match('/[\r\n]/', $record['invitation_url'])) {
                    throw new InvalidArgumentException('Invalid private CSV record.');
                }
                $this->write_row($stream, array(
                    (string) $record['guest_id'],
                    $this->escape_formula($record['guest_name']),
                    (string) $record['event_id'],
                    $this->escape_formula($record['invitation_url']),
                ));
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            if ($csv === false) {
                throw new RuntimeException('Unable to read private CSV.');
            }
            return $csv;
        } finally {
            fclose($stream);
        }
    }

    private function escape_formula(string $value): string
    {
        // Leading whitespace/control characters may be ignored by CSV clients.
        if (preg_match('/^[\x00-\x20]*[=+@\-]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    private function write_row($stream, array $values): void
    {
        if (fputcsv($stream, $values, ',', '"', '') === false) {
            throw new RuntimeException('Unable to serialize private CSV.');
        }
    }
}
