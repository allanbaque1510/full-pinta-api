<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EjecutaServicio;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests, EjecutaServicio;
}
