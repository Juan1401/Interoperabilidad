<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'iops_api') }}</title>

    <!-- Fonts (Local) -->
    <link href="{{ asset('css/nunito.css') }}" rel="stylesheet">

    <!-- Styles -->
    <style>
        html,
        body {
            background-color: transparent;
            color: #636b6f;
            font-family: 'Nunito', sans-serif;
            font-weight: 200;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            /* Evitar scrollbars si el canvas se pasa un poco */
        }

        .full-height {
            height: 100vh;
        }

        .flex-center {
            align-items: center;
            display: flex;
            justify-content: center;
        }

        .position-ref {
            position: relative;
        }

        .top-right {
            position: absolute;
            right: 10px;
            top: 18px;
        }

        /* Improved Visibility - Glassmorphism Effect */
        .content-wrapper {
            background: rgba(255, 255, 255, 0.85);
            /* White with 85% opacity */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            text-align: center;
            max-width: 800px;
            width: 90%;
        }

        .title {
            font-size: 84px;
            color: #2c3e50;
            /* Darker color for better contrast */
            font-weight: 600;
            /* Bolder text */
            text-shadow: 2px 2px 4px rgba(255, 255, 255, 0.5);
        }

        .links>a {
            color: #2c3e50;
            /* Darker color */
            padding: 0 25px;
            font-size: 13px;
            font-weight: 800;
            /* Extra bold */
            letter-spacing: .1rem;
            text-decoration: none;
            text-transform: uppercase;
        }

        .links>a:hover {
            color: #3490dc;
            /* Laravel Blue on Hover */
            text-decoration: underline;
        }

        .m-b-md {
            margin-bottom: 30px;
        }

        /* Auth Links visibility */
        .top-right.links>a {
            color: #2c3e50;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.7);
            padding: 8px 15px;
            border-radius: 5px;
            margin-left: 5px;
        }
    </style>
</head>

<body>
    <div id="vanta-bg" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;"></div>

    <div class="flex-center position-ref full-height" style="z-index: 1; position: relative;">
        @if (Route::has('login'))
        <div class="top-right links">
            @auth
            <a href="{{ url('/home') }}">Home</a>
            @else
            <a href="{{ route('login') }}">Login</a>

            {{-- @if (Route::has('register'))
                            <a href="{{ route('register') }}">Register</a>
            @endif --}}
            @endauth
        </div>
        @endif

        <div class="content-wrapper">
            <div class="title m-b-md">
                {{ config('app.name', 'iops_api') }}
            </div>

            <div class="links">
                <a href="https://laravel.com/docs">Docs</a>
                <a href="https://laracasts.com">Laracasts</a>
                <a href="https://laravel-news.com">News</a>
                <a href="https://blog.laravel.com">Blog</a>
                <a href="https://nova.laravel.com">Nova</a>
                <a href="https://forge.laravel.com">Forge</a>
                <a href="https://vapor.laravel.com">Vapor</a>
                <a href="https://github.com/laravel/laravel">GitHub</a>
            </div>
        </div>
    </div>

    <!-- Vanta.js Scripts (Local) -->
    <script src="{{ asset('js/three.min.js') }}"></script>
    <script src="{{ asset('js/vanta.net.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            VANTA.NET({
                el: "#vanta-bg",
                mouseControls: true,
                touchControls: true,
                gyroControls: false,
                minHeight: 200.00,
                minWidth: 200.00,
                scale: 1.00,
                scaleMobile: 1.00,
                color: 0x3490dc,
                backgroundColor: 0xffffff,
                points: 12.00,
                maxDistance: 22.00,
                spacing: 18.00
            })
        });
    </script>
</body>

</html>