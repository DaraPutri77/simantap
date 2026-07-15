<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{

    public function edit(Request $request): View
    {
        $user = $request->user();

        $user->load('profile');

        return view('profile.edit', [
            'user' => $user,
        ]);
    }



    public function update(Request $request)
    {
        $user = Auth::user();


        $request->validate([

            'name' => [
                'required',
                'string',
                'max:255'
            ],


            'email' => [
                'required',
                'email',
                'max:255'
            ],


            'nama_lengkap' => [
                'required',
                'string',
                'max:255'
            ],


            'alamat' => [
                'nullable',
                'string'
            ],


            'no_hp' => [
                'nullable',
                'string',
                'max:20'
            ],


        ]);



        $user->update([

            'name' => $request->name,

            'email' => $request->email,

        ]);



        $user->profile()->updateOrCreate(

            [
                'user_id' => $user->id
            ],


            [

                'nama_lengkap' => $request->nama_lengkap,

                'alamat' => $request->alamat,

                'no_hp' => $request->no_hp,

            ]

        );



        return Redirect::route('profile.edit')
            ->with('status','profile-updated');

    }




    public function destroy(Request $request)
    {
        $request->validate([

            'password' => [
                'required',
                'current_password'
            ],

        ]);



        $user = $request->user();


        Auth::logout();


        $user->delete();


        $request->session()->invalidate();

        $request->session()->regenerateToken();


        return Redirect::to('/');

    }

}