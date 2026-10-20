<?php
namespace InvoiceTool;

/** Shared display formatting for the web UI, PDF and Excel output. */
class Format
{
    /** Exact decimal hours (no rounding). */
    public static function hours(int $seconds): float
    {
        return $seconds / 3600;
    }

    /** Money for a duration at an hourly rate; only rounded to cents for display. */
    public static function amount(int $seconds, float $rate): float
    {
        return round($seconds / 3600 * $rate, 2);
    }

    public static function money(float $value): string
    {
        return number_format($value, 2);
    }

    /** [hours, minutes] split for the PDF's Hrs / Min columns. */
    public static function hrsMin(int $seconds): array
    {
        return [intdiv($seconds, 3600), intdiv($seconds % 3600, 60)];
    }

    /** 305820 → "84:57" */
    public static function hhmm(int $seconds): string
    {
        [$h, $m] = self::hrsMin($seconds);
        return $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }

    /** 3900 → "1h 05m", 1740 → "29m" (PDF description suffix). */
    public static function duration(int $seconds): string
    {
        [$h, $m] = self::hrsMin($seconds);
        return $h > 0 ? $h . 'h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'm' : $m . 'm';
    }

    /** "2026-09-16" → "Sep 16" */
    public static function monthDay(string $date): string
    {
        return date('M j', strtotime($date));
    }

    /** "2026-06-01" → "01 June" */
    public static function dayMonth(string $date): string
    {
        return date('d F', strtotime($date));
    }

    /** "June 1st-15th, 2026" or "September 28th - October 3rd, 2026" */
    public static function period(string $start, string $end): string
    {
        $s = strtotime($start);
        $e = strtotime($end);
        if (date('Y-m', $s) === date('Y-m', $e)) {
            return date('F jS', $s) . '-' . date('jS, Y', $e);
        }
        if (date('Y', $s) === date('Y', $e)) {
            return date('F jS', $s) . ' - ' . date('F jS, Y', $e);
        }
        return date('F jS, Y', $s) . ' - ' . date('F jS, Y', $e);
    }

    /** "WR-11692" → "WR" (the PDF's Project column). */
    public static function prefix(string $key): string
    {
        return $key === '' ? '' : strstr($key, '-', true);
    }

    /** "002560" → "002561" (keeps zero padding). */
    public static function nextNumber(string $current): string
    {
        $digits = preg_replace('/\D/', '', $current);
        return str_pad((string) ((int) $digits + 1), strlen($digits), '0', STR_PAD_LEFT);
    }

    /** Safe file-name fragment. */
    public static function slug(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9]+/', '_', $value), '_');
    }
}
