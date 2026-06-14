<?php
// core/class/VeoliaNouveauParser.php
// Parseur du CSV Veolia (gère séparateur ; ou , et décimales avec virgule).

class VeoliaNouveauParser
{
    private static array $months = [
        'janv' => 1, 'janv.' => 1, 'févr' => 2, 'févr.' => 2, 'fevr' => 2, 'fevr.' => 2,
        'mars' => 3, 'avr' => 4, 'avr.' => 4, 'mai' => 5, 'juin' => 6, 'juil' => 7, 'août' => 8,
        'sept' => 9, 'oct' => 10, 'nov' => 11, 'déc' => 12, 'dec' => 12, 'déc.' => 12
    ];

    public static function parseCsv(string $csvText): array
    {
        $csvText = trim($csvText);
        if ($csvText === '') return [];

        $lines = preg_split("/\r\n|\n|\r/", $csvText);
        $sep = (strpos($lines[0], ';') !== false) ? ';' : ',';

        $header = str_getcsv(array_shift($lines), $sep);
        $header = array_map('trim', $header);

        $out = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $cols = str_getcsv($line, $sep);
            if (count($cols) < 2) continue;
            $row = array_combine($header, $cols);

            $dateRaw = trim($row[$header[0]] ?? '');
            $litresRaw = trim($row[$header[1]] ?? '0');

            $litres = floatval(str_replace(',', '.', str_replace(' ', '', $litresRaw)));
            $m3 = $litres / 1000.0;

            $date = self::normalizeMonthStringToDate($dateRaw);

            $out[] = [
                'date_raw' => $dateRaw,
                'date' => $date?->format('Y-m-d') ?? null,
                'litres' => $litres,
                'm3' => round($m3, 6),
                'raw_row' => $row
            ];
        }
        return $out;
    }

    private static function normalizeMonthStringToDate(string $s): ?DateTime
    {
        $s = trim(strtolower($s));
        $s = str_replace(['.', '‑', '-'], '-', $s);
        if (!str_contains($s, '-')) return null;
        [$monthPart, $yy] = explode('-', $s, 2);
        $monthPart = trim($monthPart);
        $yy = preg_replace('/[^0-9]/', '', $yy);
        if ($yy === '') return null;
        $year = intval($yy) + ($yy < 50 ? 2000 : 1900);
        $monthKey = str_replace(['é','è','ê','û','ô','à','ï','ç'], ['e','e','e','u','o','a','i','c'], $monthPart);
        $monthKey = rtrim($monthKey, '.');
        $monthKey = substr($monthKey, 0, 4);
        foreach (self::$months as $k => $v) {
            if (strpos($k, $monthKey) === 0 || strpos($monthKey, $k) === 0) {
                return DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $v));
            }
        }
        return null;
    }
}
