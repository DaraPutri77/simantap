<aside class="fixed left-0 top-0 w-64 h-screen bg-[#0f172a] text-white">


    <div class="px-8 py-8">

        <h1 class="text-3xl font-bold tracking-wide">
            SIMANTAP
        </h1>

    </div>



    <nav class="mt-5">


        <a href="{{ route('dashboard') }}"
           class="flex items-center px-8 py-4 text-gray-200 hover:bg-slate-800 transition">

            Dashboard

        </a>



        <a href="{{ route('users.index') }}"
           class="flex items-center px-8 py-4 text-gray-200 hover:bg-slate-800 transition">

            User Management

        </a>



        <a href="#"
           class="flex items-center px-8 py-4 text-gray-200 hover:bg-slate-800 transition">

            Laporan

        </a>


    </nav>


</aside>