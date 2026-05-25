<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Instalador - MiAsoVec</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="max-w-2xl w-full px-6">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="px-6 py-5 border-b bg-zinc-800">
                <h1 class="text-white text-center text-lg font-semibold">Instalador de MiAsoVec</h1>
            </div>

            <div class="p-6">
                @if($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 p-3 rounded">
                        <ul class="list-disc pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-6">
                    <nav class="flex items-center gap-4">
                        <div id="step-indicator-1" class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-semibold">1</span>
                            <span class="text-sm font-medium">Base de datos</span>
                        </div>
                        <div class="flex-1 border-t border-gray-200"></div>
                        <div id="step-indicator-2" class="flex items-center gap-2 opacity-50">
                            <span class="w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-semibold">2</span>
                            <span class="text-sm font-medium text-gray-600">Administrador</span>
                        </div>
                    </nav>
                </div>

                <form method="POST" action="{{ route('install.store') }}" id="installer-form">
                    @csrf

                    <div id="step-1" class="space-y-4">
                        <h2 class="text-lg font-semibold">1. Configuracion de la base de datos</h2>

                        <div class="grid grid-cols-1 gap-3">
                            <input name="DB_CONNECTION" value="{{ old('DB_CONNECTION', 'mysql') }}" placeholder="Tipo de conexion (mysql)" class="block w-full rounded border-gray-200 shadow-sm focus:ring-2 focus:ring-emerald-300 p-2" required>
                            <div class="grid grid-cols-2 gap-3">
                                <input name="DB_HOST" value="{{ old('DB_HOST', '127.0.0.1') }}" placeholder="Host de la base de datos" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-emerald-300" required>
                                <input name="DB_PORT" value="{{ old('DB_PORT', '3306') }}" placeholder="Puerto de la base de datos" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-emerald-300" required>
                            </div>

                            <input name="DB_DATABASE" value="{{ old('DB_DATABASE') }}" placeholder="Nombre de la base de datos" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-emerald-300" required>
                            <div class="grid grid-cols-2 gap-3">
                                <input name="DB_USERNAME" value="{{ old('DB_USERNAME') }}" placeholder="Usuario de la base de datos" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-emerald-300" required>
                                <input name="DB_PASSWORD" type="password" placeholder="Contrasena de la base de datos" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-emerald-300">
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button type="button" id="to-step-2" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700 transition">Siguiente</button>
                        </div>
                    </div>

                    <div id="step-2" class="hidden space-y-4">
                        <h2 class="text-lg font-semibold">2. Administrador inicial</h2>

                        <div class="grid grid-cols-1 gap-3">
                            <input name="admin_name" value="{{ old('admin_name') }}" placeholder="Nombre" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-amber-300" required>
                            <input name="admin_email" type="email" value="{{ old('admin_email') }}" placeholder="Email" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-amber-300" required>
                            <div class="grid grid-cols-2 gap-3">
                                <input name="admin_password" type="password" placeholder="Contrasena" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-amber-300" required>
                                <input name="admin_password_confirmation" type="password" placeholder="Confirmar contrasena" class="rounded border-gray-200 shadow-sm p-2 focus:ring-2 focus:ring-amber-300" required>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-between items-center">
                            <button type="button" id="back-step-1" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Atras</button>
                            <button type="submit" id="install-submit" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600 transition">
                                <svg id="install-spinner" class="hidden animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                Instalar y crear usuario
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const toStep2 = document.getElementById('to-step-2');
            const backStep1 = document.getElementById('back-step-1');
            const indicator1 = document.getElementById('step-indicator-1');
            const indicator2 = document.getElementById('step-indicator-2');
            const form = document.getElementById('installer-form');
            const submitBtn = document.getElementById('install-submit');
            const spinner = document.getElementById('install-spinner');

            toStep2.addEventListener('click', function () {
                const required = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'];

                for (const name of required) {
                    const field = document.querySelector('[name="' + name + '"]');

                    if (!field || !field.value.trim()) {
                        if (field) {
                            field.focus();
                        }

                        alert('Rellena todos los campos de conexion a la base de datos.');

                        return;
                    }
                }

                step1.classList.add('hidden');
                step2.classList.remove('hidden');

                indicator1.querySelector('span').classList.remove('bg-emerald-500');
                indicator1.querySelector('span').classList.add('bg-emerald-600');
                indicator2.classList.remove('opacity-50');
                indicator2.querySelector('span').classList.remove('bg-gray-200');
                indicator2.querySelector('span').classList.add('bg-amber-400', 'text-white');
            });

            backStep1.addEventListener('click', function () {
                step2.classList.add('hidden');
                step1.classList.remove('hidden');

                indicator2.classList.add('opacity-50');
                indicator2.querySelector('span').classList.remove('bg-amber-400', 'text-white');
                indicator2.querySelector('span').classList.add('bg-gray-200');
            });

            form.addEventListener('submit', function () {
                submitBtn.setAttribute('disabled', 'disabled');
                spinner.classList.remove('hidden');
            });
        })();
    </script>
</body>
</html>
