<header class="h-20 bg-white border-b flex items-center justify-between px-8">


    <h2 class="text-xl font-bold text-slate-800">
        Dashboard
    </h2>



    <div class="flex items-center gap-6">


        <span class="text-slate-600 text-lg">

            {{ Auth::user()->name }}

        </span>



        <form method="POST" action="{{ route('logout') }}">

            @csrf


            <button
                class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-xl">

                Logout

            </button>


        </form>


    </div>


</header>