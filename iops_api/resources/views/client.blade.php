@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-3">
        <a href="{{ url('/home') }}" class="btn btn-outline-secondary">
            &larr; {{ __('Back to Home') }}
        </a>
    </div>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Clientes app') }}</div>

                <div class="card-body">
                    @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                    @endif


                    <form method="POST" action="{{ url('/client') }}">
                        @csrf

                        <div class="form-group row">
                            <label for="name" class="col-md-4 col-form-label text-md-right">{{ __('Nombre Cliente') }}</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="redirect" class="col-md-4 col-form-label text-md-right">{{ __('Redirect') }}</label>

                            <div class="col-md-6">
                                <input id="redirect" type="text" class="form-control @error('redirect') is-invalid @enderror" name="redirect" value="{{ old('redirect') }}" required autocomplete="redirect">

                                @error('redirect')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Register') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <br>
    <br>
    <div class="card">
        <div class="card-body">
            <div class="card-header">
                <h5 class="card-title">
                    {{ __('Tabla de Clientes') }}
                </h5>
            </div>
            <table class="table table-sm table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NAME</th>
                        <th>TIPO CLIENTE</th>
                        <th>REDIRECT</th>
                        <th>SECRET</th>
                        <th>FECHA EXPIRACIÓN</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                    <tr>
                        <td>{{ $client->id }}</td>
                        <td>{{ $client->name }}</td>
                        <td>
                            @if($client->personal_access_client)
                            <span class="badge badge-info">Personal Access</span>
                            @elseif($client->password_client)
                            <span class="badge badge-warning">Password Grant</span>
                            @else
                            <span class="badge badge-secondary">Standard</span>
                            @endif
                        </td>
                        <td>{{ $client->redirect }}</td>
                        <td>{{ $client->secret }}</td>
                        <td>{{ \Carbon\Carbon::parse($client->created_at)->addYear()->format('Y-m-d H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <br>
    <br>
    <div class="card">
        <div class="card-header">
            <h5>Personal Access Tokens</h5>
        </div>
        <div class="card-body">
            @if (session('pat_status'))
            <div class="alert alert-success" role="alert">
                {{ session('pat_status') }}
            </div>
            @endif

            @if (session('personal_token'))
            <div class="alert alert-warning" role="alert">
                <strong>{{ __('Nuevo Token Personal - Copia esto ahora, no se volverá a mostrar:') }}</strong>
                <div class="mt-2 text-break">
                    <code style="word-break: break-all; font-size: 0.9em;">{{ session('personal_token') }}</code>
                </div>
            </div>
            @endif

            <form action="{{ url('/personal-access-token') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="name">Nombre:</label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="Nombre" required>
                </div>
                <button type="submit" class="btn btn-primary">Crear</button>
            </form>

            <hr>

            <h5>Mis Tokens</h5>
            <table class="table table-sm table-hover table-bordered">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Creado</th>
                        <th>Expira</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tokens as $token)
                    <tr>
                        <td>{{ $token->name }}</td>
                        <td>{{ \Carbon\Carbon::parse($token->created_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ $token->expires_at ? \Carbon\Carbon::parse($token->expires_at)->format('Y-m-d H:i') : 'Nunca' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">No tienes tokens personales creados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <br>
    <br>

</div>
@endsection