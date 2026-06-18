<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra la lista de clientes OAuth y Personal Access Tokens.
     */
    public function index()
    {
        // Obtenemos los clientes de la tabla de Passport
        $clients = \Illuminate\Support\Facades\DB::table('oauth_clients')->get();

        // Obtenemos los Personal Access Tokens del usuario autenticado
        $tokens = \Illuminate\Support\Facades\Auth::user()->tokens;

        return view('client', [
            'clients' => $clients,
            'tokens' => $tokens
        ]);
    }

    /**
     * Almacena un nuevo cliente OAuth manualmente.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'redirect' => 'required|url',
        ]);

        // Verificamos si es personal_access_client o password_client basándonos en el nombre opcionalmente
        // Por defecto, lo creamos como cliente estándar para simplificar, 
        // pero permitimos que el usuario vea la distinción.

        \Illuminate\Support\Facades\DB::table('oauth_clients')->insert([
            'name' => $request->name,
            'secret' => \Illuminate\Support\Str::random(40),
            'redirect' => $request->redirect,
            'personal_access_client' => 0,
            'password_client' => 0,
            'revoked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect('/client')->with('status', 'Cliente creado exitosamente.');
    }

    /**
     * Crea un nuevo Personal Access Token para el usuario.
     */
    public function storeToken(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Creamos el token usando Passport
        $tokenResult = $request->user()->createToken($request->name);

        // Guardamos el token en texto plano en la sesión para mostrarlo una sola vez
        return redirect('/client')->with([
            'pat_status' => 'Token personal creado exitosamente.',
            'personal_token' => $tokenResult->accessToken
        ]);
    }

    /**
     * Método de ejemplo solicitado en rutas.
     */
    public function clientExample()
    {
        return view('client', [
            'clients' => \Illuminate\Support\Facades\DB::table('oauth_clients')->get()
        ]);
    }
}
