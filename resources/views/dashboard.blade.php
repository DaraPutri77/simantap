<x-app-layout>

<div class="py-12 bg-slate-100 min-h-screen">

    <div class="max-w-7xl mx-auto px-6">

        <h1 class="text-3xl font-bold text-slate-800 mb-8">
            Dashboard SIMANTAP
        </h1>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <div class="bg-white rounded-xl shadow p-6">

                <p class="text-gray-500">
                    Total User
                </p>

                <h2 class="text-4xl font-bold text-blue-600 mt-3">
                    {{ $totalUser }}
                </h2>

            </div>



            <div class="bg-white rounded-xl shadow p-6">

                <p class="text-gray-500">
                    Total Role
                </p>

                <h2 class="text-4xl font-bold text-blue-600 mt-3">
                    {{ $totalRole }}
                </h2>

            </div>



            <div class="bg-white rounded-xl shadow p-6">

                <p class="text-gray-500">
                    Total Profile
                </p>

                <h2 class="text-4xl font-bold text-blue-600 mt-3">
                    {{ $totalProfile }}
                </h2>

            </div>


        </div>



        <div class="mt-10 bg-white rounded-xl shadow p-8">

            <h2 class="text-xl font-bold text-slate-800">
                Aktivitas Sistem
            </h2>


            <p class="text-gray-500 mt-3">
                Sistem SIMANTAP siap digunakan.
            </p>


        </div>


    </div>

</div>

</x-app-layout>