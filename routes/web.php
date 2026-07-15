<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;


Route::get('/', function () {

    return redirect()->route('login');

});



Route::middleware('auth')->group(function () {


    Route::get('/dashboard',
        [DashboardController::class,'index']
    )->name('dashboard');



    Route::resource('users', UserController::class)
        ->except(['show']);



    Route::get('/profile',
        [ProfileController::class,'edit']
    )->name('profile.edit');



    Route::patch('/profile',
        [ProfileController::class,'update']
    )->name('profile.update');



    Route::delete('/profile',
        [ProfileController::class,'destroy']
    )->name('profile.destroy');


});



require __DIR__.'/auth.php';