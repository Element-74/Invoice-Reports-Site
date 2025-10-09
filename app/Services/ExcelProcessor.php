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

        // Remove first row (title row)
        unset($data[1]);

        // Row 2 has headers
        unset($data[2]);

        // Parse data rows with CORRECT column mapping
        $rows = [];
        foreach ($data as $rowIndex => $row) {
            if ($rowIndex <= 2) continue; // Skip first two rows
            if (empty($row['A'])) continue; // Skip empty rows

            $rows[] = [
                'Date' => $this->parseDate($row['A']),
                'Service' => trim($row['B'] ?? ''),
                'Quantity' => $this->parseNumber($row['C'] ?? 0),
                'Unit Cost' => $this->parseNumber($row['D'] ?? 0),
                'Billable Amount' => $this->parseNumber($row['E'] ?? 0),
                'Campaign Segment' => trim($row['F'] ?? ''),
                'Project Name' => trim($row['G'] ?? ''),
                'Comments' => trim($row['H'] ?? ''),
                'Type' => trim($row['I'] ?? ''),
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

    private function parseNumber($value)
    {
        // Remove currency symbols, commas, and spaces
        $cleaned = preg_replace('/[$,\s]/', '', $value);
        return floatval($cleaned);
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
                $this->processProjectLines($project, $segmentName);
                $segment['subtotal'] += $project['subtotal'];
            }
            // Keep project names as keys - don't re-index to numeric
        }

        return $grouped;
    }

    private function processProjectLines(&$project, $segmentName)
    {
        $lines = [];
        $subtotal = 0.0;
        $unitCostGroups = [];

        $firstLine = null;
        $expenses = []; // Handle multiple expenses

        foreach ($project['entries'] as $entry) {
            $service = $entry['Service'];
            $type = $entry['Type'];
            $comments = $entry['Comments'];
            $qty = $entry['Quantity'];
            $rate = $entry['Unit Cost'];
            $amt = $entry['Billable Amount'];

            // Check for special "Rooted Web" comment
            if ($service === 'Rooted Web' && !empty($comments)) {
                $firstLine = $comments;
            }
            // Check for Expense type
            elseif ($type === 'Expense' && !empty($comments)) {
                // Format like individual entries: amount first, then comment
                $expenses[] = [
                    'amount' => $amt,
                    'comment' => $comments
                ];
                $subtotal += $amt;
            }
            // Regular labor entry
            else {
                // Aggregate ALL sections by rate (including Web Development)
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

        // Display aggregated format for ALL sections
        foreach ($unitCostGroups as $rate => $qty) {
            $label = $qty <= 1 ? 'hour' : 'hours';
            $lines[] = '- ' . number_format($qty, 2) . ' ' . $label . ' @ $' . number_format($rate, 2) . '/hr';
        }

        // Add all expenses with proper formatting
foreach ($expenses as $expense) {
    $lines[] = '- $' . number_format($expense['amount'], 2);
    if (!empty($expense['comment'])) {
        $lines[] = '       ' . chr(149) . '  ' . $expense['comment'];
    }
}

        $project['lines'] = $lines;
        $project['subtotal'] = $subtotal;
    }
}
