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

    public function processUpload(Request $request)
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

            // Clean up temp file
            unlink($fullPath);

            // Store processed data in session for comment form
            session([
                'invoice_data' => [
                    'sections' => $data['sections'],
                    'report_date' => $data['report_date'],
                    'output_name' => $data['output_name']
                ]
            ]);

            // Redirect to comment form
            return redirect()->route('invoice.comments');

        } catch (\Exception $e) {
            return back()->with('error', 'Error processing file: ' . $e->getMessage());
        }
    }

    public function showCommentForm()
    {
        // Retrieve data from session
        $invoiceData = session('invoice_data');

        // Validate session data exists
        if (!$invoiceData) {
            return redirect()->route('invoice.index')
                ->with('error', 'Session expired. Please upload the file again.');
        }

        // Pass data to comment form view
        return view('comment-form', [
            'sections' => $invoiceData['sections'],
            'reportDate' => $invoiceData['report_date']
        ]);
    }

    public function generatePdf(Request $request)
    {
        // Retrieve data from session
        $invoiceData = session('invoice_data');

        // Validate session data exists
        if (!$invoiceData) {
            return redirect()->route('invoice.index')
                ->with('error', 'Session expired. Please upload the file again.');
        }

        // Get optional custom comments from form submission
        // Comments will be in format: ['segment_project_rate_id' => 'comment text']
        $customComments = $request->input('comments', []);

        try {
            // Generate PDF with session data
            $pdfGenerator = new InvoicePDF($invoiceData['report_date']);

            // Pass custom comments to PDF generator
            $pdfPath = $pdfGenerator->generate(
                $invoiceData['sections'],
                $invoiceData['output_name'],
                $customComments
            );

            // Store PDF path in session for download
            session(['pdf_download' => $pdfPath]);

            // Clear invoice data from session
            session()->forget('invoice_data');

            // Redirect to upload page with success message
            return redirect()->route('invoice.index')
                ->with('success', 'Invoice report generated successfully!');

        } catch (\Exception $e) {
            // Keep session data on error so user can try again
            return back()->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    public function downloadPdf()
    {
        // Get PDF path from session
        $pdfPath = session('pdf_download');

        // Check if PDF exists
        if (!$pdfPath || !file_exists($pdfPath)) {
            return redirect()->route('invoice.index')
                ->with('error', 'PDF file not found or has expired.');
        }

        // Clear the session
        session()->forget('pdf_download');

        // Download the PDF
        return response()->download($pdfPath)->deleteFileAfterSend(true);
    }
}
