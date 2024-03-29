<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\WareHouse;
use App\Models\User;
use App\Models\Country;
use App\Models\OutgoingStock;
use App\Models\Order;
use App\Models\GeneralSetting;
use App\Models\Product;

class WareHouseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allWarehouse()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $warehouses = WareHouse::all();
        return view('pages.warehouses.allWarehouse', compact('authUser', 'user_role', 'warehouses'));
    }

    //add
    public function addWarehouse()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        $warehouses = WareHouse::all();
        
        $agents = User::where('type', 'agent')->get();
        $countries = Country::all();
        return view('pages.warehouses.addWarehouse', compact('authUser', 'user_role', 'warehouses', 'agents', 'countries'));
    }

    //addpost
    public function addWarehousePost(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'name' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
        ]);

        $data = $request->all();
        $warehouse = new WareHouse();
        $warehouse->agent_id = !empty($data['agent_id']) ? $data['agent_id'] : null;
        $warehouse->name = $data['name'];
        $warehouse->city = !empty($data['city']) ? $data['city'] : null;
        $warehouse->state = !empty($data['state']) ? $data['state'] : null;
        $warehouse->country_id = !empty($data['country']) ? $data['country'] : null;
        $warehouse->address = !empty($data['address']) ? $data['address'] : null;
        $warehouse->created_by = $authUser->id;
        $warehouse->status = 'true';
        $warehouse->save();

        return back()->with('success', 'Warehouse Added Successfully');
    }

    public function singleWarehouse($unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $warehouse = WareHouse::where('unique_key', $unique_key)->first();
        if(!isset($warehouse)){
            abort(404);
        }

        $generalSetting = GeneralSetting::where('id', '>', 0)->first();
        $currency = $generalSetting->country->symbol;

        $products = $warehouse->products;
        if (count($products) > 0) {
            foreach ($products as $key => $product) {
                # code...
            }
        }

        //warehouse orders
        $orders = $warehouse->orders()->where('status', 'delivered_and_remitted')->get(); $outgoingStocks = '';
        if (count($orders) > 0) {

            //grab accepted OutgoingStocks from these orders
            //$outgoingStocks = OutgoingStock::whereIn('order_id', $orders->pluck('id'))->where('customer_acceptance_status', 'accepted')->orderBy('id', 'DESC');
            $outgoingStocks = OutgoingStock::whereIn('order_id', $orders->pluck('id'))->get(); //[[{}], [{}], [{}]]

            // Flatten the multidimensional array into a single array
            $flattenedArray = array_merge(...$outgoingStocks->pluck('package_bundle')); //[{}, {}]

            $packages = []; $total_revenue = 0; //total revenue in warehouse
            if (count($outgoingStocks) > 0) {
                foreach ($flattenedArray as $key => $package) {
                    if ($package['customer_acceptance_status'] == 'accepted') {
                        $total_revenue += isset($package['amount_accrued']) ? (int) $package['amount_accrued'] : 0; //sum
                    }
                }

                foreach ($orders as $key => $order) {
                    $orderRevenue = $order->outgoingStock->package_bundle[0]['amount_accrued'];
                    $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
                    foreach ($outgoingStockPackageBundle as &$stock_bundle) {
                        $stock_bundle["product"] = Product::find($stock_bundle['product_id']);
                    }
                    $warehouseOrders = [
                        'order' => $order,
                        'outgoingStockPackageBundle' => $outgoingStockPackageBundle,
                        'orderRevenue'=> $orderRevenue,
                    ];
                    $packages[] = $warehouseOrders;
                }

                return view('pages.warehouses.singleWarehouse', compact('authUser', 'user_role', 'warehouse', 'orders', 'outgoingStocks', 'currency', 'total_revenue', 'warehouseOrders', 'packages'));

            }
    
        } 
        
        return view('pages.warehouses.singleWarehouse', compact('authUser', 'user_role', 'warehouse', 'orders', 'outgoingStocks'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editWarehouse($unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $warehouse = WareHouse::where('unique_key', $unique_key)->first();
        if(!isset($warehouse)){
            abort(404);
        }
        $agents = User::where('type', 'agent')->get();
        $countries = Country::all();
        return view('pages.warehouses.editWarehouse', compact('authUser', 'user_role', 'warehouse', 'agents', 'countries'));
    }

    public function editWarehousePost(Request $request, $unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $warehouse = WareHouse::where('unique_key', $unique_key)->first();
        if(!isset($warehouse)){
            abort(404);
        }
        $request->validate([
            'name' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
        ]);

        $data = $request->all();
        
        $warehouse->agent_id = !empty($data['agent_id']) ? $data['agent_id'] : null;
        $warehouse->name = $data['name'];
        $warehouse->city = !empty($data['city']) ? $data['city'] : null;
        $warehouse->state = !empty($data['state']) ? $data['state'] : null;
        $warehouse->country_id = !empty($data['country']) ? $data['country'] : null;
        $warehouse->address = !empty($data['address']) ? $data['address'] : null;
        $warehouse->status = 'true';
        $warehouse->save();

        return back()->with('success', 'Warehouse Updated Successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addWarehouseAjax(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();
        $warehouse = new WareHouse();
        if ($data['agent_id'] != "" || $data['agent_id'] != null) {
            $warehouse->agent_id = $data['agent_id'];
        }
        if ($data['name'] != "" || $data['name'] != null) {
            $warehouse->name = $data['name'];
        }
        if ($data['type'] != "" || $data['type'] != null) {
            $warehouse->type = $data['type'];
        }
        if ($data['city'] != "" || $data['city'] != null) {
            $warehouse->city = $data['city'];
        }
        if ($data['state'] != "" || $data['state'] != null) {
            $warehouse->state = $data['state'];
        }
        if ($data['country'] != "" || $data['country'] != null) {
            $warehouse->country_id = $data['country'];
        }
        if ($data['address'] != "" || $data['address'] != null) {
            $warehouse->address = $data['address'];
        }
        $warehouse->created_by = $authUser->id;
        $warehouse->status = 'true';
        $warehouse->save();

        //store in array
        $data['warehouse'] = $warehouse;

        // $categories = ExpenseCategory::all();

        return response()->json([
            'status'=>true,
            'data'=>$data
        ]);
    }
}
