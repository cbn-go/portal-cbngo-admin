<?php

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::get('/', function (): View|RedirectResponse {
    /** @var User|null $user */
    $user = Auth::user();

    if ($user !== null) {
        return redirect()->to($user->getDefaultPanelUrl());
    }

    return view('welcome');
});

Route::get('/login', function (): RedirectResponse {
    /** @var User|null $user */
    $user = Auth::user();

    if ($user !== null) {
        return redirect()->to($user->getDefaultPanelUrl());
    }

    return redirect()->to('/admin/login');
})->name('login');
