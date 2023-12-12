<?php

namespace App\Http\Controllers;
use App\Models\product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class productController extends Controller
{
    public function insert(Request $request) {
        try {
            product::create($request->all());
            return response()->json(['message' => 'เพิ่มข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete($id) {
        try {
            $product = product::findOrFail($id);
            $product->delete();

            return response()->json(['message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id) {
        try {
            $product = product::findOrFail($id);
            $product->update($request->all());

            return response()->json(['message' => 'อัพเดตข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getList(Request $request) {
        try {
            
            $query = product::query();
            $filters = $request->only(['category', 'status']);
            foreach ($filters as $key => $value) {
                if ($value) {

                    $query->where($key, $value);
                }
            }
            $product_data = $query->get();
            $records = $product_data->map(function ($product){
                return [
                    'id' => $product->id,
                    'ชื่อสิ่งของ' => $product->name,
                    'คำอธิบาย' => $product->description,
                    'จำนวนคงเหลือ' => $product->in_stock,
                    'สถานะ' => $product->status
                ];
            });
            return response()->json(['รายการสิ่งของ' => $records]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getDetail($id) {
        try {
            $product = product::findOrFail($id);
            $record = [
                'id' => $product->id,
                'ชื่อสิ่งของ' => $product->name,
                'คำอธิบาย' => $product->description,
                'ประเภท' => $product->category,
                'จำนวนคงเหลือ' => $product->in_stock,
                'จำนวนทั้งหมด' => $product->full_stock,
                'สถานะ' => $product->status
            ];
            return response()->json(['รายละเอียดสิ่งของ' => $record]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
