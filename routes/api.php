<?php

use App\Http\Controllers\StringsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/strings', [StringsController::class, 'analyseString']);
Route::get('/strings/filter-by-natural-language', [StringsController::class, 'filterByNaturalLanguage']);
Route::get('/strings', [StringsController::class, 'filterStrings']);
Route::get('/strings/{string_value}', [StringsController::class, 'checkString']);
Route::delete('/strings/{string_value}', [StringsController::class, 'deleteString']);

