<?php

namespace App\Http\Controllers;

use App\Models\product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\borrow;
class productController extends Controller
{
    public function insert(Request $request)
    {
        try {
            $validated_data = $request->validate([
                'name' => 'required|max:50',
                'description' => 'required',
                'category' => 'required',
                'add_stock' => 'required'
            ]);
            $now = now();
            $product_data = array_merge($validated_data, [
                'full_stock' => $validated_data['add_stock'],
                'in_stock' => $validated_data['add_stock'],
                'created_at' => $now,
                'updated_at' => $now
            ]);
            unset($product_data['add_stock']);
            DB::table('products')->insert($product_data);
            // Product::create($request->all());//CamelUpper model ใส่ stock แค่ field เดียว, สถานะ = enum ยืมได้ รอซ่อม ของหมด ยังไม่เปิดให้ยืม(ถ้าสถานะ = ของหมด instock=0)
            return response()->json(['success' => true, 'message' => 'Product insert successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function delete($id)
    {
        try {
            // $product = Product::findOrFail($id); //กรณีมีคนยืม แต่โดนลบ product DB จะพัง ใช้ where เท่านั้น
            // $product->delete(); //if-else สำหรับเช็คว่าลบได้มั้ย
            // return response()->json(['message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
            $product = Product::withTrashed()->where('id', $id)->first();
            if ($product) {
                if (Borrow::where('product_id', $id)->where('borrow_status', '!=', 'คืนแล้ว')->exists()) {
                    return response()->json(['success' => false, 'message' => 'User must return this product before deletion'], 400);
                }
                $product->delete();
                return response()->json(['success' => true, 'message' => 'Product deleted successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Product not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated_data = $request->validate([
                'name' => 'sometimes|required|max:50',
                'description' => 'sometimes|required',
                'category' => 'sometimes|required',
                'status' => 'sometimes|required',
                'full_stock' => 'sometimes|required',
                'in_stock' => 'sometimes|required'
            ]);
            $product = Product::where('id', $id)->first();
            if ($product) {
                $product->update($validated_data);
                return response()->json(['success' => true, 'message' => 'Product updated successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Product not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function getList(Request $request)
    {
        try {
            //category มีแยกได้ และมี category_id ช่วยให้เปนระเบียบได้
            //สำหรับข้อมูลเป๊ะเท่านั้น อาจมี filter แบบซับซ้อน
            $query = Product::query();
            $filters = $request->only(['name', 'category', 'status', 'full_stock', 'in_stock', 'description']);
            foreach ($filters as $key => $value) {
                $query->where($key, 'like', "%$value%");
            }
            $product_data = $query->get();
            $records = [];
            $records = $product_data->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'in_stock' => $product->in_stock,
                    'status' => $product->status
                ];
            });
            return response()->json(['success' => true, 'List of Products' => $records], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getDetail($id)
    {
        try {
            $product = Product::find($id); //
            $record = [ //
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'category' => $product->category,
                'in_stock' => $product->in_stock,
                'full_stock' => $product->full_stock,
                'status' => $product->status
            ];
            return response()->json(['success' => true, 'Detail of Product' => $record], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
