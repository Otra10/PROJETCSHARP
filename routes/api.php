<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatimentController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\EtudiantController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\PaiementController;
use App\Models\Batiment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/etudiant', [EtudiantController::class, 'getAuthenticatedEtudiant']);
Route::middleware('auth:sanctum')->apiResource('grades', GradeController::class);
Route::middleware('auth:sanctum')->apiResource('batiments', BatimentController::class);
Route::middleware('auth:sanctum')->apiResource('classes', ClasseController::class);
Route::middleware('auth:sanctum')->apiResource('etudiants', EtudiantController::class);
Route::middleware('auth:sanctum')->apiResource('paiements', PaiementController::class);
Route::apiResource('paiementss', BatimentController::class);
Route::middleware('auth:sanctum')->get('etudiant/statut', [EtudiantController::class, 'statut']);



// Route::post('/payment/create', [PaiementController::class, 'createInvoice'])->middleware('auth:sanctum');
// Route::post('/payment/callback', [PaiementController::class, 'paymentCallback'])->middleware('auth:sanctum');
// Route::get('/payment/amount', [PaiementController::class, 'paymentAmount'])->middleware('auth:sanctum');
// Route::get('/paydunya/return', [PaiementController::class, 'returnUrl'])->name('paydunya.return');
// Route::get('/paydunya/cancel', [PaiementController::class, 'cancelUrl'])->name('paydunya.cancel');
// Route::post('/paydunya/callback', [PaiementController::class, 'callback'])->name('paydunya.callback');