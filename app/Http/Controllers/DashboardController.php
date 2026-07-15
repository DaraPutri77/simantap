<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Profile;


class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'totalUser' => User::count(),
            'totalRole' => Role::count(),
            'totalProfile' => Profile::count(),
        ]);
    }
}