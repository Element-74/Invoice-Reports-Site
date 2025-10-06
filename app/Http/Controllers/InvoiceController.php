<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ExcelProcessor;
use App\Services\InvoicePDF;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('invoice-upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'invoice_file' => 'required|file|max:10240'
        ]);

        try {
            // Store uploaded file temporarily
            $file = $request->file('invoice_file');

            // Check file extension
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, ['xlsx', 'xls'])) {
                return back()->with('error', 'File must be an Excel file (.xlsx or .xls). You uploaded: ' . $extension);
            }

            $path = $file->store('temp');
            $fullPath = storage_path('app/' . $path);

            // Process the Excel file
            $processor = new ExcelProcessor();
            $data = $processor->process($fullPath);

            // Generate PDF
            $pdfGenerator = new InvoicePDF($data['report_date']);
            $pdfPath = $pdfGenerator->generate($data['sections'], $data['output_name']);

            // Clean up temp file
            unlink($fullPath);

            // Download the PDF
            return response()->download($pdfPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return back()->with('error', 'Error processing file: ' . $e->getMessage());
        }
    }
}
