<?php

use App\Models\JubanReportInquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/', function (): RedirectResponse {
    /** @var User|null $user */
    $user = Auth::user();

    if ($user !== null) {
        return redirect()->to($user->getDefaultPanelUrl());
    }

    return redirect()->to('/admin/login');
});

Route::get('/login', function (): RedirectResponse {
    /** @var User|null $user */
    $user = Auth::user();

    if ($user !== null) {
        return redirect()->to($user->getDefaultPanelUrl());
    }

    return redirect()->to('/admin/login');
})->name('login');

Route::middleware('auth')->get('/juban/inquiries/{inquiry}/attachment', function (JubanReportInquiry $inquiry): StreamedResponse {
    Gate::authorize('downloadAttachment', $inquiry);

    if (! $inquiry->attachment_path || ! Storage::disk('local')->exists($inquiry->attachment_path)) {
        abort(404, 'Comprovante não encontrado.');
    }

    $filename = basename($inquiry->attachment_path);

    return Storage::disk('local')->download($inquiry->attachment_path, $filename);
})->name('juban.inquiries.download-attachment');
