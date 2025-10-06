<?php

namespace App\Services;

use FPDF;

class InvoicePDF extends FPDF
{
    private $reportDate;
    private $sectionColor;
    private $logoPath;

    public function __construct($reportDate)
    {
        parent::__construct();
        $this->reportDate = $reportDate;

        // Set logo path
        $this->logoPath = storage_path('app/logos/_RL_Primary_Red.png');

        // Extract red color from logo if it exists, otherwise use default
        if (file_exists($this->logoPath)) {
            $this->sectionColor = $this->extractRedFromLogo($this->logoPath);
        } else {
            $this->sectionColor = [200, 16, 46]; // Default red color
        }

        $this->AliasNbPages();
        $this->SetAutoPageBreak(true, 15);
    }

    private function extractRedFromLogo($logoPath)
    {
        try {
            // Load image
            $img = imagecreatefrompng($logoPath);
            if (!$img) {
                return [200, 16, 46]; // Default if can't load
            }

            $width = imagesx($img);
            $height = imagesy($img);

            $colorCounts = [];

            // Sample pixels (checking every pixel can be slow, so we sample)
            for ($x = 0; $x < $width; $x += 2) {
                for ($y = 0; $y < $height; $y += 2) {
                    $rgb = imagecolorat($img, $x, $y);
                    $colors = imagecolorsforindex($img, $rgb);

                    $r = $colors['red'];
                    $g = $colors['green'];
                    $b = $colors['blue'];
                    $alpha = $colors['alpha'];

                    // Skip transparent pixels and white pixels
                    if ($alpha < 127 && !($r == 255 && $g == 255 && $b == 255)) {
                        $colorKey = "$r,$g,$b";

                        if (!isset($colorCounts[$colorKey])) {
                            $colorCounts[$colorKey] = 0;
                        }
                        $colorCounts[$colorKey]++;
                    }
                }
            }

            imagedestroy($img);

            // Find most common color
            if (empty($colorCounts)) {
                return [200, 16, 46]; // Default
            }

            arsort($colorCounts);
            $mostCommon = array_key_first($colorCounts);
            $rgb = explode(',', $mostCommon);

            return [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]];

        } catch (\Exception $e) {
            return [200, 16, 46]; // Default on error
        }
    }

    function Header()
    {
        // Add logo if it exists
        if (file_exists($this->logoPath)) {
            $this->Image($this->logoPath, 10, 8, 40);
        }

        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->SetY(15);
        $this->Cell(0, 10, 'LBS Invoice Report - ' . $this->reportDate, 0, 1, 'R');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }

    public function generate($sections, $outputName)
    {
        $this->AddPage();

        foreach ($sections as $section) {
            $this->addSection($section);
        }

        // Save to storage
        $outputPath = storage_path('app/generated/' . $outputName);

        // Create directory if it doesn't exist
        if (!file_exists(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $this->Output('F', $outputPath);

        return $outputPath;
    }

    private function addSection($section)
    {
        // Section header in red
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($this->sectionColor[0], $this->sectionColor[1], $this->sectionColor[2]);
        $this->Cell(0, 10, $section['header'], 0, 1);

        // Section note if exists
        if (!empty($section['note'])) {
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(0, 0, 0);
            $this->MultiCell(0, 6, $section['note']);
            $this->Ln(2);
        }

        // Projects
        foreach ($section['projects'] as $project) {
            // Check if we need a new page
            if ($this->GetY() > 250) {
                $this->AddPage();
            }

            // Project name in bold
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 8, $project['name'], 0, 1);

            // Project lines
            $this->SetFont('Arial', '', 10);
            foreach ($project['lines'] as $line) {
                $this->Cell(0, 6, $line, 0, 1);
            }
            $this->Ln(1);
        }

        // Section total
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 10, $section['header'] . ' Total: $' . number_format($section['subtotal'], 2), 0, 1, 'R');
        $this->Ln(5);
    }
}
