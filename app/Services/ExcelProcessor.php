<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExcelProcessor
{
    public function process($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheet(0);
        $data = $sheet->toArray(null, true, true, true);

        // DEBUG: Save first 10 rows to a file so we can see the structure
        $debugFile = storage_path('app/excel_debug.txt');
        $debugContent = "Excel File Debug Output\n";
        $debugContent .= "=====================\n\n";

        $rowCount = 0;
        foreach ($data as $rowIndex => $row) {
            if ($rowCount >= 10) break;
            $debugContent .= "Row $rowIndex:\n";
            $debugContent .= "  A: " . ($row['A'] ?? 'NULL') . "\n";
            $debugContent .= "  B: " . ($row['B'] ?? 'NULL') . "\n";
            $debugContent .= "  C: " . ($row['C'] ?? 'NULL') . "\n";
            $debugContent .= "  D: " . ($row['D'] ?? 'NULL') . "\n";
            $debugContent .= "  E: " . ($row['E'] ?? 'NULL') . "\n";
            $debugContent .= "  F: " . ($row['F'] ?? 'NULL') . "\n";
            $debugContent .= "  G: " . ($row['G'] ?? 'NULL') . "\n";
            $debugContent .= "  H: " . ($row['H'] ?? 'NULL') . "\n";
            $debugContent .= "  I: " . ($row['I'] ?? 'NULL') . "\n";
            $debugContent .= "\n";
            $rowCount++;
        }

        file_put_contents($debugFile, $debugContent);

        // Now continue with the normal processing
        // Remove first row (row 1)
        unset($data[1]);

        // Row 2 should be headers
        $headers = $data[2] ?? [];
        $debugContent .= "\nHeaders (Row 2):\n";
        $debugContent .= json_encode($headers, JSON_PRETTY_PRINT) . "\n";
        file_put_contents($debugFile, $debugContent, FILE_APPEND);

        unset($data[2]);

        // Parse data rows
        $rows = [];
        foreach ($data as $rowIndex => $row) {
            if ($rowIndex <= 2) continue; // Skip first two rows
            if (empty($row['A'])) continue; // Skip empty rows

            $rows[] = [
                'Date' => $this->parseDate($row['A']),
                'Campaign Segment' => trim($row['B'] ?? ''),
                'Project Name' => trim($row['C'] ?? ''),
                'Description' => trim($row['D'] ?? ''),
                'Type' => trim($row['E'] ?? ''),
                'Comments' => trim($row['F'] ?? ''),
                'Quantity' => floatval($row['G'] ?? 0),
                'Unit Cost' => floatval($row['H'] ?? 0),
                'Billable Amount' => floatval($row['I'] ?? 0),
            ];
        }

        // Save first 5 processed rows for debugging
        $debugContent = "\n\nFirst 5 Processed Rows:\n";
        $debugContent .= json_encode(array_slice($rows, 0, 5), JSON_PRETTY_PRINT) . "\n";
        file_put_contents($debugFile, $debugContent, FILE_APPEND);

        // Get report month/year from first date
        $reportDate = !empty($rows) ? date('F Y', strtotime($rows[0]['Date'])) : date('F Y');
        $outputName = "LBS_Invoice_Report_{$reportDate}.pdf";

        // Group by Campaign Segment > Project Name
        $sections = $this->groupData($rows);

        return [
            'sections' => $sections,
            'report_date' => $reportDate,
            'output_name' => $outputName
        ];
    }

    private function parseDate($value)
    {
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        return date('Y-m-d', strtotime($value));
    }

    private function groupData($rows)
    {
        $grouped = [];

        foreach ($rows as $row) {
            $segment = $row['Campaign Segment'];
            $project = $row['Project Name'];

            if (!isset($grouped[$segment])) {
                $grouped[$segment] = [
                    'header' => $segment,
                    'note' => in_array($segment, ['Graphic Design', 'Graphic Production'])
                        ? 'Includes graphic design, proofing and project management.'
                        : '',
                    'projects' => [],
                    'subtotal' => 0.0
                ];
            }

            if (!isset($grouped[$segment]['projects'][$project])) {
                $grouped[$segment]['projects'][$project] = [
                    'name' => $project,
                    'entries' => [],
                    'lines' => [],
                    'subtotal' => 0.0
                ];
            }

            $grouped[$segment]['projects'][$project]['entries'][] = $row;
        }

        // Process each project to create formatted lines
        foreach ($grouped as $segmentName => &$segment) {
            foreach ($segment['projects'] as $projectName => &$project) {
                $this->processProjectLines($project);
                $segment['subtotal'] += $project['subtotal'];
            }
            $segment['projects'] = array_values($segment['projects']); // Re-index
        }

        return array_values($grouped);
    }

    private function processProjectLines(&$project)
    {
        $lines = [];
        $subtotal = 0.0;
        $unitCostGroups = [];

        $firstLine = null;
        $lastLine = null;

        foreach ($project['entries'] as $entry) {
            $desc = $entry['Description'];
            $type = $entry['Type'];
            $comments = $entry['Comments'];
            $qty = $entry['Quantity'];
            $rate = $entry['Unit Cost'];
            $amt = $entry['Billable Amount'];

            // Check for special "Rooted Web" comment
            if ($desc === 'Rooted Web' && !empty($comments)) {
                $firstLine = $comments;
            }
            // Check for Expense type
            elseif ($type === 'Expense' && !empty($comments)) {
                $lastLine = $comments . ' - $' . number_format($amt, 2);
                $subtotal += $amt;
            }
            // Regular hours entry
            else {
                if (!isset($unitCostGroups[$rate])) {
                    $unitCostGroups[$rate] = 0.0;
                }
                $unitCostGroups[$rate] += $qty;
                $subtotal += $amt;
            }
        }

        // Build formatted lines
        if ($firstLine) {
            $lines[] = $firstLine;
        }

        foreach ($unitCostGroups as $rate => $qty) {
            $label = $qty <= 1 ? 'hour' : 'hours';
            $lines[] = '- ' . number_format($qty, 2) . ' ' . $label . ' @ $' . number_format($rate, 2) . '/hr';
        }

        if ($lastLine) {
            $lines[] = $lastLine;
        }

        $project['lines'] = $lines;
        $project['subtotal'] = $subtotal;
    }
}
