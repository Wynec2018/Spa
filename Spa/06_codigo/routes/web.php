<?php

use App\Http\Controllers\Web\DashboardPageController;
use App\Http\Controllers\Web\LoginPageController;
use App\Http\Controllers\Web\ReservationPageController;
use App\Http\Controllers\Web\ServicePageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('/login', [LoginPageController::class, 'show'])->name('login');
Route::post('/login', [LoginPageController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginPageController::class, 'destroy'])->name('logout');

Route::get('/dashboard', [DashboardPageController::class, 'index'])->name('dashboard');
Route::get('/reservas', [ReservationPageController::class, 'index'])->name('reservations.index');
Route::get('/reservas/nueva', [ReservationPageController::class, 'create'])->name('reservations.create');
Route::post('/reservas', [ReservationPageController::class, 'store'])->name('reservations.store');
Route::post('/reservas/{id}/cancelar', [ReservationPageController::class, 'cancel'])->name('reservations.cancel');
Route::get('/servicios', [ServicePageController::class, 'index'])->name('services.index');
Route::get('/servicios/nuevo', [ServicePageController::class, 'create'])->name('services.create');
Route::post('/servicios', [ServicePageController::class, 'store'])->name('services.store');
Route::get('/servicios/{id}/editar', [ServicePageController::class, 'edit'])->name('services.edit');
Route::put('/servicios/{id}', [ServicePageController::class, 'update'])->name('services.update');
Route::post('/servicios/{id}/inactivar', [ServicePageController::class, 'deactivate'])->name('services.deactivate');
