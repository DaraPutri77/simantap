<div class="w-full h-full bg-[#111827] text-white">

    <div class="px-8 py-10">

        <h1 class="text-3xl font-bold tracking-wide">
            SIMANTAP
        </h1>

    </div>


    <div class="px-5 mt-5">


        <a href="{{ route('dashboard') }}"
           class="flex items-center px-6 py-4 rounded-xl mb-3
           text-white font-medium text-lg
           hover:bg-[#1f2937] transition">

            Dashboard

        </a>



        @if(auth()->user()->hasRole('Admin'))

        <a href="{{ route('users.index') }}"
           class="flex items-center px-6 py-4 rounded-xl mb-3
           text-white font-medium text-lg
           hover:bg-[#1f2937] transition">

            User Management

        </a>

        @endif



        <a href="{{ route('profile.edit') }}"
           class="flex items-center px-6 py-4 rounded-xl mb-3
           text-white font-medium text-lg
           hover:bg-[#1f2937] transition">

            Profile

        </a>


    </div>


</div>