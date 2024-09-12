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
            $SKU = $data['sku'];
            foreach ($data['variants'] as $size) {
                $response = Http::withBasicAuth(
                    env('API_LOGIN'),
                    env('API_AUTH_TOKEN')
                )->
                post("https://api.jumpseller.com/v1/products/{$productid}/variants.json",
                [
                    'variant' => [
                        'sku' => "$SKU-$size",
                        'stock' => $this->getStock($SKU,$size),
                        'options' => [
                            [
                                'name' => 'talla',
                                'option_type' => 'option',
                                'value' => "$size"
                            ]
                        ]
                    ]
                ]);
                if ($response->failed()) {
                    $error = $response->json();
                    $errorMessage = $error['message'];
                    $message = "producto $productid, $size no encontrado, error: $errorMessage";
                    $result[] = $message;
                }else {
                    $result[] = $response->json();
                }
                
            }
            return $result;
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function getStock($sku="8734-768-23580-34576", $size="34") {
        try {
            $totalStock = 0;
            $stocks = Http::get('https://induccion.fixlabsdev.com/api/products/stock');
            if ($stocks->failed()) {
                throw new Exception('Error en la consulta');
            }
            $stockData = $stocks->json();
            foreach ($stockData as $product) {
                if (isset($product[$sku])) {
                   foreach ($product as $warehouses) {
                       foreach ($warehouses as $warehouse) {
                           foreach ($warehouse['variants'] as $variants) {
                               $result = "$sku-$size";
                                if ($variants['sku'] === $result) {
                                $totalStock += $variants['stock'];
                                }
                           }
                        }
                    }
                }
            }
            return $totalStock;
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 404);
        }
    }

    public function store() {
        try {
            $result = [];
            $variants = [];
            $products = Http::get('https://induccion.fixlabsdev.com/api/products');
            if (!$products) {
                throw new Exception('no pudo obtener productos');
            }
            foreach ($products->json() as $product) {
                $product_jumpseller = $this->createProduct($product);
                $productId = $product_jumpseller['product']['id'];
                $variants[] = $this->createVariant($product, $productId);
                $result[] = $productId;
            }

            $data = [
                'products' => $result,
                'variants' => $variants,
                'status' => 201
            ];
            return response()->json($data, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
