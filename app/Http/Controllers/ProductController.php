<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProductController extends Controller
{
    public function index() {

        $response = Http::get('https://induccion.fixlabsdev.com/api/products');

        return $response->json();
    }
}
