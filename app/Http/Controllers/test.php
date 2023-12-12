<?php

namespace App\Http\Controllers;

use App\Models\Borrow;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class BorrowController extends Controller
{
    public function insert(Request $request)
    {
        try {
            $borrowing = [
                'user_id' => $request->input('user_id'),
                'product_id' => $request->input('product_id'),
                'borrow_days' => $request->input('borrow_days'),
                'borrow_product_number' => $request->input('borrow_product_number')
            ];

            Borrow::create($borrowing);

            // Update times_of_borrow in the User table
            User::where('id', $request->input('user_id'))
                ->increment('times_of_borrow');

            // Update in_stock in the Product table
            $product = Product::find($request->input('product_id'));
            if ($product) {
                $product->decrement('in_stock', $request->input('borrow_product_number'));

                if ($product->in_stock === 0) {
                    return response()->json(['message' => 'Data inserted successfully, product is now out of stock']);
                }

                return response()->json(['message' => 'Data inserted successfully']);
            }

            return response()->json(['error' => 'Product not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
