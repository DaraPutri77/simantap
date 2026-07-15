<x-app-layout>

<div>

<h1 style="
font-size:32px;
font-weight:bold;
margin-bottom:30px;
color:#0f172a;
">
User Management
</h1>


<div style="
background:white;
border-radius:18px;
padding:25px;
overflow-x:auto;
box-shadow:0 10px 25px rgba(0,0,0,0.05);
">


<table width="100%" style="
border-collapse:collapse;
">

<thead>

<tr style="
background:#f8fafc;
text-align:left;
border-bottom:1px solid #e5e7eb;
">


<th style="padding:15px;">
No
</th>


<th style="padding:15px;">
Nama
</th>


<th style="padding:15px;">
Email
</th>


<th style="padding:15px;">
Role
</th>


<th style="padding:15px;">
Profile
</th>


</tr>

</thead>


<tbody>


@foreach($users as $user)

<tr style="
border-bottom:1px solid #e5e7eb;
">


<td style="padding:15px;">
{{ $loop->iteration }}
</td>


<td style="padding:15px;font-weight:600;">
{{ $user->name }}
</td>


<td style="padding:15px;color:#64748b;">
{{ $user->email }}
</td>


<td style="padding:15px;">

@forelse($user->roles as $role)

<span style="
background:#2563eb;
color:white;
padding:6px 14px;
border-radius:20px;
font-size:13px;
">

{{ $role->nama_role }}

</span>

@empty

<span style="color:#64748b;">
-
</span>

@endforelse


</td>


<td style="padding:15px;">

@if($user->profile)

{{ $user->profile->nama_lengkap }}

@else

-

@endif


</td>


</tr>


@endforeach


</tbody>


</table>


</div>


</div>


</x-app-layout>