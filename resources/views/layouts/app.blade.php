<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        SIMANTAP
    </title>

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>


<body class="bg-slate-100">

<div class="min-h-screen flex">


    @include('layouts.sidebar')


    <div class="flex-1">


        @include('layouts.navbar')


        <main class="p-6">

            {{ $slot ?? '' }}

            @yield('content')

        </main>


    </div>


</div>


</body>

</html>