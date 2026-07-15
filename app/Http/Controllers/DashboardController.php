<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Profile;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'totalUser' => User::count(),
            'totalRole' => Role::count(),
            'totalProfile' => Profile::count(),
        ];

        return view('dashboard', $data);
    }
}