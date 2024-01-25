<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\Borrow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request; //นึกถึงตอน review code สรุป 3 sprint **challenge sprint 2 ***อันนี้ต้องโชว์ ตามไทม์ไลน์
use App\Http\Middleware\Admin;
class borrowController extends Controller
{
    public function borrow(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'user must to login before borrowing'], 401);
            }
            $validated_data = $request->validate([
                'product_id' => 'required|exists:products,id',
                'borrow_days' => 'required|max:14',
                'borrow_product_number' => 'required'
            ]);
            $stock_update_result = $this->update_product_stock($validated_data['product_id'], $validated_data['borrow_product_number']);
            if ($stock_update_result['success']) {
                $this->update_user_borrow_count($user['user_id']);
                $now = now();
                $borrow_data = array_merge($validated_data, [
                    'user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
                DB::table('borrows')->insert($borrow_data);
                return response()->json([
                    'message' => $stock_update_result['message'],
                    'remaining_stock' => $stock_update_result['remaining_stock'],
                    'all_stock' => $stock_update_result['all_stock']
                ]);
            } else {
                return response()->json(['success' => false, 'error' => $stock_update_result['message']], 400);
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function return (Request $request, $id)//admin
    {
        try {
            $borrow = Borrow::find($id);
            $returnResult = $this->process_return($borrow);
            if ($returnResult['success']) {
                $borrow->update(['return_date' => now()]);
                return response()->json(['success' => true, 'message' => $returnResult['message']]);
            } else {
                return response()->json(['success' => false, 'error' => $returnResult['message']], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function delete($id)//admin
    {
        try {
            $this->middleware(Admin::class);

            $borrow = Borrow::withTrashed()->where('id', $id)->first();
            if ($borrow) {
                if ($borrow->borrow_status !== 'คืนแล้ว') {
                    return response()->json(['success' => false, 'message' => 'User must be returned before deletion'], 400);
                }
                $borrow->delete();
                return response()->json(['success' => true, 'message' => 'Borrow deleted successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Borrow_id not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)//admin
    {
        try {
            $this->middleware(Admin::class);

            $validated_data = $request->validate([
                'borrow_days' => 'required|max:14'
            ]);
            $borrow = Borrow::where('id', $id)->first();
            if ($borrow->borrow_status !== 'คืนแล้ว') {
                $borrow->update(['borrow_status' => 'กำลังยืม']);
                $borrow->update($validated_data);
                return response()->json(['success' => true, 'message' => 'Borrow_days changed successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Borrow_id not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getList(Request $request)//admin
    {
        try {
            $query = Borrow::withTrashed();
            $filters = $request->only(['borrow_status', 'user_id', 'product_id', 'category', 'user_name', 'product_name', 'create_at']);

            foreach ($filters as $key => $value) {
                if ($value && !in_array($key, ['product_name', 'category', 'user_name', 'create_at'])) {
                    $query->where($key, 'like', "%$value%");
                }
                if ($value && in_array($key, ['user_name', 'product_name', 'category'])) {
                    $relation = ($key === 'user_name') ? 'users' : 'products';
                    $query->whereHas($relation, function ($relationQuery) use ($key, $value) {
                        if ($key === 'category') {
                            $relationQuery->where($key, 'like', "%$value%");
                        } else {
                            $relationQuery->where('name', 'like', "%$value%");
                        }
                    });
                }
            }
            $borrow_data = $query->with([
                'users' => function ($query) {
                    $query->withTrashed();
                },
                'products' => function ($query) {
                    $query->withTrashed();
                }
            ])->get();
            $records = $borrow_data->map(function ($borrow) {
                return [
                    'id' => $borrow->id,
                    'user_name' => optional($borrow->users)->name,
                    'product_name' => optional($borrow->products)->name,
                    'category' => optional($borrow->products)->category,
                    'borrow_product_number' => $borrow->borrow_product_number,
                    'borrow_status' => $borrow->borrow_status,
                    'borrow_date' => Carbon::parse($borrow->created_at)->toDateString(),
                    'picture' => optional($borrow->products)->picture,
                ];
            });
            return response()->json(['success' => true, 'List of borrows' => $records], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getDetail($id)
    {
        try {
            $query = Borrow::withTrashed();
            $borrow = $query->with([
                'users' => function ($query) {
                    $query->withTrashed();
                },
                'products' => function ($query) {
                    $query->withTrashed();
                }
            ])->find($id);
            $note = '';
            $created_at_date = Carbon::parse($borrow->created_at)->toDateString();
            $return_date = Carbon::parse($borrow->return_date)->toDateString();
            $must_return_day = Carbon::parse($borrow->created_at)->addDays($borrow->borrow_days)->toDateString();
            $days_late = $this->is_return_late($must_return_day, $return_date); //ห้ามยุ่งกับ column updated_at ให้สร้างมาใหม่
            if ($days_late > 0) {
                $note = "เลยกำหนด {$days_late} วัน";
            }
            $this->is_borrow_late($must_return_day, $borrow); //สำหรับอัพเดตยืมเลยกำหนด

            $record = [
                'id' => $borrow->id,
                'user_name' => optional($borrow->users)->name,
                'product_name' => optional($borrow->products)->name,
                'borrow_product_number' => $borrow->borrow_product_number,
                'created_at_date' => $created_at_date,
                'must_return_day' => $must_return_day,
                'borrow_status' => $borrow->borrow_status, //is_borrow_late
                'return_date' => $borrow->return_date ? Carbon::parse($borrow->return_date)->toDateString() : 'ยังไม่คืน',
                'picture' => optional($borrow->products)->picture,
                'note' => $note
            ];
            return response()->json(['success' => true, 'Detail of Borrow' => $record], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getHistory(Request $request) //แยกเปน history ของ user/admin product
    {
        try {
            $filters = $request->only(['user_id', 'product_id']);
            $query = Borrow::withTrashed()
                ->with([
                    'users' => function ($query) {
                        $query->withTrashed();
                    },
                    'products' => function ($query) {
                        $query->withTrashed();
                    }
                ]);

            foreach ($filters as $key => $value) {
                if ($value) {
                    if ($key === 'user_id') {
                        $query->where('user_id', $value);
                    } elseif ($key === 'product_id') {
                        $query->where('product_id', $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
            }

            $query = $query->orderBy('created_at', 'asc')->get();

            if ($request->has('user_id')) {
                $records = $query->groupBy('product_id')->map(function ($group) {
                    $productBorrows = $group->map(function ($borrow) {
                        return [
                            'product_name' => optional($borrow->products)->name,
                            'borrow_days' => $borrow->borrow_days,
                            'borrow_product_number' => $borrow->borrow_product_number,
                            'borrow_status' => $borrow->borrow_status,
                            'borrow_date' => Carbon::parse($borrow->created_at)->toDateString(),
                            'return_date' => $borrow->return_date ? Carbon::parse($borrow->return_date)->toDateString() : 'ยังไม่คืน',
                            'picture' => optional($borrow->products)->picture
                        ];
                    });

                    return [
                        'user' => [
                            'id' => $group->first()->user_id,
                            'name' => optional($group->first()->users)->name,
                            'product_borrow' => $productBorrows->toArray(),
                        ],
                    ];
                });
            } elseif ($request->has('product_id')) {
                $records = $query->groupBy('user_id')->map(function ($group) {
                    $userBorrows = $group->map(function ($borrow) {
                        return [
                            'user_name' => optional($borrow->users)->name,
                            'borrow_days' => $borrow->borrow_days,
                            'borrow_product_number' => $borrow->borrow_product_number,
                            'borrow_status' => $borrow->borrow_status,
                            'borrow_date' => Carbon::parse($borrow->created_at)->toDateString(),
                            'return_date' => $borrow->return_date ? Carbon::parse($borrow->return_date)->toDateString() : 'ยังไม่คืน',
                            'picture' => optional($borrow->users)->picture,
                        ];
                    });

                    return [
                        'product' => [
                            'id' => $group->first()->product_id,
                            'name' => optional($group->first()->products)->name,
                            'user_borrow' => $userBorrows->toArray(),
                        ],
                    ];
                });
            }

            return response()->json(['success' => true, 'List of borrows' => $records->values()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function dashboard(Request $request)//admin
    {
        try {
            $query = Borrow::withTrashed();
            $filters = $request->only(['borrow_status', 'user_id', 'product_id', 'category', 'gender']);
            foreach ($filters as $key => $value) {
                if ($value && !in_array($key, ['category', 'gender'])) {
                    $query->where($key, 'like', "%$value%");
                }
                if ($value && in_array($key, ['gender', 'category'])) {
                    $relation = ($key === 'gender') ? 'users' : 'products';
                    $query->whereHas($relation, function ($relationQuery) use ($key, $value) {
                        $relationQuery->where($key, 'like', "%$value%");
                    });
                }
            }
            $borrow_data = $query->with([
                'users' => function ($query) {
                    $query->withTrashed();
                },
                'products' => function ($query) {
                    $query->withTrashed();
                }
            ])->get();
            $total_borrows = $borrow_data->count();
            $total_returns = $borrow_data->where('borrow_status', 'คืนแล้ว')->count();
            $borrow_status_counts = $borrow_data->groupBy('borrow_status')->map->count(); //pluck แทน groupBy
            // dd($borrow_status_counts);
            $dashboardData = [
                'total_borrows' => $total_borrows,
                'total_returns' => $total_returns,
                'borrow_status_counts' => $borrow_status_counts,
                'all_borrowing' => $borrow_data->map(function ($borrow) {
                    return [
                        'id' => $borrow->id,
                        'user_name' => optional($borrow->users)->name,
                        'product_name' => optional($borrow->products)->name,
                        'borrow_product_number' => $borrow->borrow_product_number,
                        'borrow_status' => $borrow->borrow_status,
                    ];
                }),
            ];
            return response()->json(['success' => true, 'Dashboard Data' => $dashboardData]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function update_user_borrow_count($user_id)
    {
        User::where('id', $user_id)->increment('times_of_borrow');
        // if (!$user_id) {
        //     return ['success' => false, 'message' => 'ไม่มี user_id นี้'];
        // } else {
        // }
    }

    private function update_product_stock($product_id, $borrow_product_number)
    {
        try {
            $product = Product::find($product_id);
            // if (!$product) {
            //     return ['success' => false, 'message' => 'ไม่มี product_id นี้'];
            // }
            if ($product->in_stock > 0) {
                $remaining_stock = $product->in_stock - $borrow_product_number;
                if ($remaining_stock === 0) {
                    $product->decrement('in_stock', $borrow_product_number);
                    $product->update(['status' => 'ของหมด']);
                    return [
                        'success' => true,
                        'message' => "The product was borrowed successfully. now, this product is out of stock.",
                        'remaining_stock' => $remaining_stock,
                        'all_stock' => $product->full_stock
                    ];
                } elseif ($remaining_stock < 0) {
                    return [
                        'success' => false,
                        'message' => 'Do not borrow beyond the available range of product numbers in stock.',
                        'remaining_stock' => $remaining_stock
                    ];
                } elseif ($product->status === 'ยังไม่เปิดให้ยืม') {
                    return [
                        'success' => false,
                        'message' => 'This product is not available for borrowing now',
                        'remaining_stock' => $remaining_stock
                    ];
                } else {
                    $product->decrement('in_stock', $borrow_product_number);
                    return [
                        'success' => true,
                        'message' => "The product was borrowed successfully ",
                        'remaining_stock' => $remaining_stock,
                        'all_stock' => $product->full_stock
                    ];
                }
            } else {
                return ['success' => false, 'message' => 'cant borrow this product. now, this product is out of stock.'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function process_return(Borrow $borrow)
    {
        try {
            if ($borrow->borrow_status !== 'คืนแล้ว') {
                $borrow->update(['borrow_status' => 'คืนแล้ว', 'return_date' => now()]);
                // $productId = $borrow->product_id;
                // $product = Product::find($productId); //ไม่ต้อง find อีกรอบ เพิ่มตาม relation ได้เลย
                $borrow->products()->increment('in_stock', $borrow->borrow_product_number); //ใส่ success ทั้งหมด
                if ($borrow->products->status === 'ของหมด') {
                    $borrow->products()->update(['status' => 'ยืมได้']);
                }
                return ['success' => true, 'message' => 'product returned successfully'];
            } else {
                return ['success' => false, 'message' => 'borrow_id is not found'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function is_borrow_late($return_day, $borrow)
    {
        $return_date = Carbon::parse($return_day)->startOfDay();
        $current_date = Carbon::now()->startOfDay();
        if ($current_date->isAfter($return_date)) {
            $days_late = $return_date->diffInDays($current_date);
            if ($borrow->borrow_status !== 'คืนแล้ว') {
                $borrow->update(['borrow_status' => "เลยกำหนด {$days_late} วัน"]);
            }
            return $days_late;
        }
        return 0;
    }

    private function is_return_late($return_day, $updated_at)
    {
        $return_date = Carbon::parse($return_day)->startOfDay();
        $update_date = Carbon::parse($updated_at)->startOfDay();
        if (!$update_date->isToday()) {
            if ($update_date->isAfter($return_date)) {
                $days_late = $update_date->diffInDays($return_date);
                return $days_late;
            }
        }
        return 0;
    }
}
