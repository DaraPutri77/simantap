@extends('layouts.app')


@section('content')


<div>


<h1 class="
text-3xl
font-bold
text-slate-800
mb-8
">

Dashboard SIMANTAP

</h1>



<div class="
grid
grid-cols-1
md:grid-cols-3
gap-6
">


<x-stat-card
title="Total User"
value="{{ $totalUser }}"
/>



<x-stat-card
title="Total Role"
value="{{ $totalRole }}"
/>



<x-stat-card
title="Total Profile"
value="{{ $totalProfile }}"
/>



</div>




<div 
class="
mt-8
bg-white
rounded-2xl
shadow-sm
p-8
">


<h2 class="
text-xl
font-semibold
mb-3
">

Aktivitas Sistem

</h2>


<p class="text-gray-500">

Sistem SIMANTAP siap digunakan.

</p>


</div>



</div>


@endsection