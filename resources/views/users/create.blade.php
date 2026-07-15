<x-app-layout>

<div>

<h1 style="
font-size:32px;
font-weight:bold;
margin-bottom:30px;
">
Tambah User
</h1>


<div style="
background:white;
padding:30px;
border-radius:18px;
max-width:800px;
">


<form method="POST" action="{{ route('users.store') }}">

@csrf



<div style="margin-bottom:20px;">

<label>
Nama
</label>

<input
type="text"
name="name"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
"
>

</div>



<div style="margin-bottom:20px;">

<label>
Email
</label>

<input
type="email"
name="email"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
"
>

</div>




<div style="margin-bottom:20px;">

<label>
Password
</label>

<input
type="password"
name="password"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
"
>

</div>




<div style="margin-bottom:20px;">

<label>
Role
</label>


<select
name="role_id"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
">


<option value="">
-- Pilih Role --
</option>


@foreach($roles as $role)

<option value="{{ $role->id }}">

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
border-radius:10px;
border:none;
font-weight:bold;
">

Simpan User

</button>



</form>


</div>


</div>


</x-app-layout>