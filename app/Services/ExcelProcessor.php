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

        // Remove first row (it's at index 1, row 2 has actual headers)
        unset($data[1]);
        $headers = $data[2]; // Row 2 is the header
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
