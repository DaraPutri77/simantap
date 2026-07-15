<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SIMANTAP</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
        }

        .main-content {
            padding: 30px;
        }
    </style>
</head>


<body>


<div class="wrapper">


    @include('layouts.sidebar')


    <div class="content">


        @include('layouts.navbar')


        <main class="main-content">

            {{ $slot }}

        </main>


    </div>


</div>


</body>

</html>