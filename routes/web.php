<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [InvoiceController::class, 'index'])->name('invoice.index');
Route::post('/upload', [InvoiceController::class, 'processUpload'])->name('invoice.upload');
Route::get('/comments', [InvoiceController::class, 'showCommentForm'])->name('invoice.comments');
Route::post('/generate-pdf', [InvoiceController::class, 'generatePdf'])->name('invoice.generate');
Route::get('/download-pdf', [InvoiceController::class, 'downloadPdf'])->name('invoice.download');

// Catch-all fallback route - redirect to upload page
Route::fallback(function () {
    return redirect()->route('invoice.index');
});
