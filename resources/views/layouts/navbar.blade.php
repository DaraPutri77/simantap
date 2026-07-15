<header 
class="
bg-white
shadow-sm
h-16
flex
items-center
justify-between
px-6
">


<div>

<h1 class="font-semibold text-xl">

Dashboard

</h1>

</div>



<div class="flex items-center gap-4">


<span class="text-gray-600">

{{ Auth::user()->name }}

</span>



<form method="POST" action="/logout">

@csrf


<button
class="
bg-red-500
text-white
px-4
py-2
rounded-lg
">

Logout

</button>


</form>


</div>



</header>