<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\Http;

class ProductController extends Controller
{
    public function index() {
        try {
            $products = Http::get('https://induccion.fixlabsdev.com/api/products');
            if ($products->failed()) {
                throw new Exception('Error en la consulta');
            }
            $data = [
                "products" => $products,
                "status" => 200
            ];
            return response()->json($data, 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 404);
        }
    }
    private function createProduct(array $data) {
        try {
            $response = Http::withBasicAuth(
                env('API_LOGIN'),
                env('API_AUTH_TOKEN')
            )->
            post('https://api.jumpseller.com/v1/products.json',
            [
                'product' => [
                    'name' => $data['name'],
                    'price' => $data['price'],
                    'description' => $data['description']
                ]
            ]);
            if ($response->failed()) {
                throw new Exception('Hubo un error al procesar la solicitud');
            }
            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    private function createVariant(array $data, int $productid) {
        try {
            $result = [];
            $products = Http::get('https://induccion.fixlabsdev.com/api/products');
            if (!$products) {
                throw new Exception('Error al obtener los productos');
            }
            $productSKU = $products['product']['sku'];
            var_dump($productSKU);
            foreach ($data['variants'] as $size) {
                $response = Http::withBasicAuth(
                    env('API_LOGIN'),
                    env('API_AUTH_TOKEN')
                )->
                post("https://api.jumpseller.com/v1/products/{$productid}/variants.json",
                [
                    'variant' => [
                        'sku' => $productSKU.'-'.$size,
                        'options' => [
                            'name' => 'talla',
                            'option_type' => 'option',
                            'value' => 'string'
                        ]
                    ]
                ]);
                if ($response->failed()) {
                    throw new Exception('Hubo un error al procesar la solicitud');
                    
                }
                $result[] = $response->json();
            }
            return $result;
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }
    }
    public function store() {
        try {
            $result = [];
            $products = Http::get('https://induccion.fixlabsdev.com/api/products');
            if (!$products) {
                throw new Exception('no pudo obtener productos');
            }
            foreach ($products->json() as $product) {
                $product_jumpseller = $this->createProduct($product);
                $productId = $product_jumpseller['product']['id'];
                $this->createVariant($product, $productId);
                $result[] = $productId;
            }

            $data = [
                "products" => $result,
                "status" => 201
            ];
            return response()->json($data, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
