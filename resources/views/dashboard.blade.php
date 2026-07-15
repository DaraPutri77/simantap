<x-app-layout>


<h1 style="
font-size:32px;
font-weight:bold;
margin-bottom:35px;
">

Dashboard SIMANTAP

</h1>



<div style="
display:grid;
grid-template-columns:repeat(3,1fr);
gap:25px;
">



<div style="
background:white;
padding:30px;
border-radius:18px;
">

<p style="color:#64748b;">
Total User
</p>

<h2 style="
font-size:40px;
color:#2563eb;
">

{{ $totalUser }}

</h2>

</div>




<div style="
background:white;
padding:30px;
border-radius:18px;
">

<p style="color:#64748b;">
Total Role
</p>

<h2 style="
font-size:40px;
color:#2563eb;
">

{{ $totalRole }}

</h2>

</div>





<div style="
background:white;
padding:30px;
border-radius:18px;
">

<p style="color:#64748b;">
Total Profile
</p>

<h2 style="
font-size:40px;
color:#2563eb;
">

{{ $totalProfile }}

</h2>

</div>



</div>





<div style="
background:white;
margin-top:35px;
padding:35px;
border-radius:18px;
">


<h2 style="
font-size:20px;
font-weight:bold;
">

Aktivitas Sistem

</h2>


<p style="
color:#64748b;
margin-top:10px;
">

Sistem SIMANTAP siap digunakan.

</p>


</div>


</x-app-layout>