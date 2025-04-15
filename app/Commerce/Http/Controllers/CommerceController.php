<?php

namespace App\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;

class CommerceController extends Controller
{
    public function index()
    {
        return inertia('Commerce/Index');
    }
}
