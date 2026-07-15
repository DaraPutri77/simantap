<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SIMANTAP</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>


<body class="bg-slate-100">


<div class="flex min-h-screen">


    {{-- SIDEBAR --}}
    @include('layouts.sidebar')


    {{-- CONTENT --}}
    <div class="flex-1 ml-64">


        {{-- NAVBAR --}}
        @include('layouts.navbar')


        <main class="p-8">

            {{ $slot }}

        </main>


    </div>


</div>


</body>

</html>