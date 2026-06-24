@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                    @endif

                    <div class="text-center">
                        <h4 class="mb-4">{{ __('Welcome!') }}</h4>
                        <p>{{ __('You are logged in!') }}</p>

                        <hr>

                        <div class="d-flex justify-content-center">
                            @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-primary mx-2">
                                {{ __('Register User') }}
                            </a>
                            @endif

                            <a href="{{ url('/client') }}" class="btn btn-secondary mx-2">
                                {{ __('Manage Clients') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection