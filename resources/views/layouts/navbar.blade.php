<div style="
height:70px;
background:white;
display:flex;
align-items:center;
justify-content:space-between;
padding:0 30px;
border-bottom:1px solid #e5e7eb;
">


<h3 style="
margin:0;
font-size:20px;
font-weight:bold;
">
Dashboard
</h3>


<div style="
display:flex;
align-items:center;
gap:20px;
">


<span style="
font-size:16px;
color:#64748b;
">
{{ Auth::user()->name }}
</span>


<form method="POST" action="{{ route('logout') }}">

@csrf


<button style="
background:#ef4444;
color:white;
border:none;
padding:10px 20px;
border-radius:8px;
cursor:pointer;
font-size:14px;
">

Logout

</button>


</form>


</div>


</div>