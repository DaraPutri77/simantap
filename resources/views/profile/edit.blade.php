<x-app-layout>


<div>


<h1 style="
font-size:32px;
font-weight:bold;
margin-bottom:30px;
color:#0f172a;
">
Profile Saya
</h1>



<div style="
background:white;
border-radius:18px;
padding:30px;
box-shadow:0 10px 25px rgba(0,0,0,0.05);
max-width:900px;
">



<form method="POST" action="{{ route('profile.update') }}">

@csrf

@method('PATCH')



<div style="margin-bottom:25px;">


<label style="
display:block;
font-weight:bold;
margin-bottom:8px;
">
Nama User
</label>


<input 
type="text"
name="name"
value="{{ old('name',$user->name) }}"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
">


</div>





<div style="margin-bottom:25px;">


<label style="
display:block;
font-weight:bold;
margin-bottom:8px;
">
Email
</label>


<input 
type="email"
name="email"
value="{{ old('email',$user->email) }}"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
">


</div>





<div style="margin-bottom:25px;">


<label style="
display:block;
font-weight:bold;
margin-bottom:8px;
">
Nama Lengkap
</label>


<input 
type="text"
name="nama_lengkap"
value="{{ old('nama_lengkap',$user->profile->nama_lengkap ?? '') }}"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
">


</div>





<div style="margin-bottom:25px;">


<label style="
display:block;
font-weight:bold;
margin-bottom:8px;
">
Alamat
</label>


<textarea
name="alamat"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
height:120px;
">{{ old('alamat',$user->profile->alamat ?? '') }}</textarea>


</div>





<div style="margin-bottom:25px;">


<label style="
display:block;
font-weight:bold;
margin-bottom:8px;
">
Nomor HP
</label>


<input 
type="text"
name="no_hp"
value="{{ old('no_hp',$user->profile->no_hp ?? '') }}"
style="
width:100%;
padding:12px;
border:1px solid #ddd;
border-radius:10px;
">


</div>





<button
type="submit"
style="
background:#2563eb;
color:white;
padding:12px 30px;
border:none;
border-radius:10px;
font-weight:bold;
cursor:pointer;
">

Simpan Perubahan

</button>



</form>



</div>


</div>


</x-app-layout>