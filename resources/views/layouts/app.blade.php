<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
SIMANTAP
</title>


@vite([
'resources/css/app.css',
'resources/js/app.js'
])


</head>


<body class="bg-slate-100">


<div class="flex min-h-screen">


    <!-- SIDEBAR -->

    <aside class="w-80 bg-[#111827] fixed left-0 top-0 bottom-0">

        @include('layouts.sidebar')

    </aside>



    <!-- MAIN -->

    <div class="flex-1 ml-80">


        <!-- HEADER -->

        <header class="h-24 bg-white shadow-sm flex items-center justify-between px-10">


            <h2 class="text-2xl font-bold text-slate-800">

                Dashboard

            </h2>



            <div class="flex items-center gap-8">


                <span class="text-xl text-slate-600">

                    {{ auth()->user()->name }}

                </span>



                <form method="POST" action="{{ route('logout') }}">

                    @csrf


                    <button
                    class="bg-red-500 hover:bg-red-600 text-white px-8 py-3 rounded-xl font-semibold">

                    Logout

                    </button>


                </form>


            </div>


        </header>



        <!-- CONTENT -->

        <main class="p-10">


            {{ $slot }}


        </main>



    </div>



</div>


</body>

</html>