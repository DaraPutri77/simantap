<x-app-layout>

<div style="padding:40px">


<h1 style="
font-size:32px;
font-weight:bold;
margin-bottom:30px;
">
User Management
</h1>


<a href="{{ route('users.create') }}"
style="
background:#2563eb;
color:white;
padding:12px 20px;
border-radius:10px;
display:inline-block;
margin-bottom:25px;
">
+ Tambah User
</a>



<div style="
background:white;
padding:25px;
border-radius:18px;
">


<table width="100%"
cellpadding="15">


<tr style="border-bottom:1px solid #ddd">

<th>No</th>
<th>Nama</th>
<th>Email</th>
<th>Role</th>
<th>Aksi</th>

</tr>



@foreach($users as $user)

<tr>


<td>
{{ $loop->iteration }}
</td>


<td>
{{ $user->name }}
</td>


<td>
{{ $user->email }}
</td>


<td>

@foreach($user->roles as $role)

<span style="
background:#2563eb;
color:white;
padding:5px 12px;
border-radius:20px;
">

{{ $role->nama_role }}

</span>

@endforeach


</td>



<td>


<a href="{{ route('users.edit',$user->id) }}"
style="
color:#2563eb;
">
Edit
</a>



<form action="{{ route('users.destroy',$user->id) }}"
method="POST"
style="display:inline">


@csrf

@method('DELETE')


<button
style="
color:red;
margin-left:15px;
"
onclick="return confirm('Hapus user?')">

Hapus

</button>


</form>


</td>



</tr>


@endforeach


</table>


</div>


</div>


</x-app-layout>