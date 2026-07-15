<x-app-layout>


<div>


<h1 style="
font-size:32px;
font-weight:bold;
margin-bottom:30px;
">
Edit User
</h1>




<div style="
background:white;
padding:30px;
border-radius:18px;
max-width:800px;
">



<form method="POST" action="{{ route('users.update',$user->id) }}">

@csrf

@method('PUT')





<div style="
margin-bottom:20px;
">

<label style="
font-weight:bold;
">
Nama
</label>


<input
type="text"
name="name"
value="{{ $user->name }}"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
"
>


</div>







<div style="
margin-bottom:20px;
">

<label style="
font-weight:bold;
">
Email
</label>


<input
type="email"
name="email"
value="{{ $user->email }}"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
"
>


</div>







<div style="
margin-bottom:20px;
">

<label style="
font-weight:bold;
">
Password Baru
</label>


<input
type="password"
name="password"
placeholder="Kosongkan jika tidak ingin mengganti password"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
"
>


</div>








<div style="
margin-bottom:20px;
">

<label style="
font-weight:bold;
">
Role
</label>



<select

name="role_id"

style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
"

>



@foreach($roles as $role)


<option

value="{{ $role->id }}"

@if($user->roles->first()?->id == $role->id)

selected

@endif

>

{{ $role->nama_role }}

</option>



@endforeach



</select>


</div>







<button

style="
background:#2563eb;
color:white;
padding:12px 30px;
border:none;
border-radius:10px;
font-weight:bold;
cursor:pointer;
"

>

Update User

</button>





</form>



</div>


</div>


</x-app-layout>