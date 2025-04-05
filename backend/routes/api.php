<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\ExternalApiController;


Route::match(['GET', 'POST'], '/external-api', [ExternalApiController::class, 'logApiCall']);
