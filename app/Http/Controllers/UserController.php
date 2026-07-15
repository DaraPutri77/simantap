<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{


    public function index()
    {
        $users = User::with(['roles','profile'])->get();

        return view('users.index', compact('users'));
    }




    public function create()
    {
        $roles = Role::all();

        return view('users.create', compact('roles'));
    }





    public function store(Request $request)
    {

        $request->validate([

            'name'=>'required',
            'email'=>'required|email|unique:users',
            'password'=>'required|min:6',
            'role_id'=>'required'

        ]);



        $user = User::create([

            'name'=>$request->name,

            'email'=>$request->email,

            'password'=>Hash::make($request->password),

        ]);



        $user->roles()->sync([

            $request->role_id

        ]);



        return redirect()
        ->route('users.index')
        ->with('success','User berhasil ditambahkan');

    }







    public function edit(User $user)
    {

        $roles = Role::all();


        return view('users.edit',compact(
            'user',
            'roles'
        ));

    }







    public function update(Request $request, User $user)
    {


        $request->validate([

            'name'=>'required',

            'email'=>'required|email',

            'role_id'=>'required'

        ]);



        $user->update([

            'name'=>$request->name,

            'email'=>$request->email,

        ]);




        if($request->password)
        {

            $user->update([

                'password'=>Hash::make(
                    $request->password
                )

            ]);

        }



        $user->roles()->sync([

            $request->role_id

        ]);



        return redirect()

        ->route('users.index')

        ->with('success','User berhasil diperbarui');


    }








    public function destroy(User $user)
    {


        if($user->email == 'admin@simantap.com')
        {

            return redirect()

            ->route('users.index')

            ->with('error',
            'Administrator tidak dapat dihapus'
            );

        }



        $user->delete();



        return redirect()

        ->route('users.index')

        ->with('success',
        'User berhasil dihapus'
        );


    }


}