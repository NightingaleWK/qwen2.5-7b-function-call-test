<?php

use App\Http\Controllers\OpenAILaravelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/openAiLaravel', [OpenAILaravelController::class, 'index'])->name('chat.openAiLaravel');
Route::post('/openAiLaravel/chat', [OpenAILaravelController::class, 'chat'])->name('chat.openAiLaravel.send');
