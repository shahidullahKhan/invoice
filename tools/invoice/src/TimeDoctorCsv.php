<?php
namespace InvoiceTool;

/**
 * Parses a Time Doctor "Projects & Tasks" daily CSV and combines all rows of the
 * same Jira ticket into one invoice item (summed time, last worked date).
 */
class TimeDoctorCsv
{
    private const REQUIRED = ['Project', 'Task', 'Date', 'Time Tracked (Digital)'];

    /**
     * @return array{items: array<int, array>, period_start: string, period_end: string}
     * Each item: ticket_key, project_name, title, work_date (Y-m-d), seconds.
     */
    public static function parse(string $path): array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        // Header row → column index map (strip a UTF-8 BOM if present)
        $header = fgetcsv($fh, 0, ',', '"', '');
        if (!$header) {
            throw new \RuntimeException('The CSV file is empty.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $col = array_flip(array_map('trim', $header));
        foreach (self::REQUIRED as $name) {
            if (!isset($col[$name])) {
                throw new \RuntimeException("This doesn't look like a Time Doctor export: missing column \"$name\".");
            }
        }

        $items = [];
        $dates = [];
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($row) < count($header)) {
                continue; // blank / malformed line
            }
            $date    = self::parseDate($row[$col['Date']]);
            $seconds = self::parseSeconds($row[$col['Time Tracked (Digital)']]);
            if ($date === null || $seconds === 0) {
                continue;
            }
            [$key, $title] = self::splitTask(trim($row[$col['Task']]));

            // Group by Jira key; tasks without a key are grouped by their full name
            $group = $key !== '' ? $key : 'task:' . $title;
            if (!isset($items[$group])) {
                $items[$group] = [
                    'ticket_key'   => $key,
                    'project_name' => preg_replace('/^Jira:\s*/i', '', trim($row[$col['Project']])),
                    'title'        => $title,
                    'work_date'    => $date,
                    'seconds'      => 0,
                ];
            }
            $items[$group]['seconds'] += $seconds;
            if ($date >= $items[$group]['work_date']) {
                $items[$group]['work_date'] = $date;
                $items[$group]['title']     = $title; // latest wording wins
            }
            $dates[] = $date;
        }
        fclose($fh);

        if (!$items) {
            throw new \RuntimeException('No time entries found in the CSV.');
        }

        $items = array_values($items);
        usort($items, function ($a, $b) {
            return [$a['work_date'], $a['ticket_key']] <=> [$b['work_date'], $b['ticket_key']];
        });

        return ['items' => $items, 'period_start' => min($dates), 'period_end' => max($dates)];
    }

    /** "Some title - WR-11692" → ['WR-11692', 'Some title'] */
    private static function splitTask(string $task): array
    {
        if (preg_match('/^(.*?)\s*-\s*([A-Z][A-Z0-9]+-\d+)$/', $task, $m)) {
            return [$m[2], $m[1]];
        }
        return ['', $task];
    }

    /** Time Doctor uses m/d/yy (e.g. 9/22/26). */
    private static function parseDate(string $value): ?string
    {
        $d = \DateTime::createFromFormat('!n/j/y', trim($value)) ?: \DateTime::createFromFormat('!n/j/Y', trim($value));
        return $d ? $d->format('Y-m-d') : null;
    }

    /** "01:07:08" → 4028 */
    private static function parseSeconds(string $value): int
    {
        $parts = array_map('intval', explode(':', trim($value)));
        return count($parts) === 3 ? $parts[0] * 3600 + $parts[1] * 60 + $parts[2] : 0;
    }
}
