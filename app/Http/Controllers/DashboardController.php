<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Akaunting\Apexcharts\Chart;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\GeneralSetting;
use App\Models\Product;
use App\Models\Order;


class DashboardController extends Controller
{
    public function dashboard()
    {
        //return $authUser = auth()->user()->role(auth()->user()->id)->role->permissions->contains('slug', 'view-product-list');
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $generalSetting = GeneralSetting::where('id', '>', 0)->first();
        $currency = $generalSetting->country->symbol;
        $record = 'all';
        ///////////////////////////////////////////////////////////////////////
        $purchases_amount_paid = Purchase::sum('amount_paid');
        $sales_due = Sale::sum('amount_due');
        $sales_paid = Sale::sum('amount_paid');
        $expenses = $this->shorten(Expense::sum('amount'));

        $profit_val = $sales_paid - ($purchases_amount_paid + Expense::sum('amount'));
        $purchases_amount_paid = $this->shorten($purchases_amount_paid);
        $sales_paid = $this->shorten($sales_paid);

        if ($profit_val > 0) {
            $profit = $this->shorten($profit_val);
        } else {
            $profit = $this->shorten($profit_val);
        }

        $customers_count = Customer::count();
        $suppliers_count = Supplier::count();
        $purchases_count = Purchase::count();
        $salesInvoice = Sale::where('parent_id', null)->count();
        $purchasesInvoice = Purchase::where('parent_id', null)->count();

        $sales_count = Sale::count();
        $invoices_count = $salesInvoice + $purchasesInvoice;

        /////////yearly report purchase, sales, expenses/////////////recent products///

        // yearly report
        $start = strtotime(date("Y") .'-01-01');
        $end = strtotime(date("Y") .'-12-31');
        while($start < $end)
        {
            $start_date = date("Y").'-'.date('m', $start).'-'.'01';
            $end_date = date("Y").'-'.date('m', $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));
            
            $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $profit_amount = $sale_amount - $purchase_amount;
            $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount') + $purchase_amount;

            //sum diff btwn columns
            //$profit = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('amount_due - amount_paid'));
            
            $yearly_sale_amount[] = number_format((float)$sale_amount, 2, '.', '');
            $yearly_purchase_amount[] = number_format((float)$purchase_amount, 2, '.', '');
            $yearly_profit_amount[] = number_format((float)$profit_amount, 2, '.', '');
            $yearly_expense_amount[] = number_format((float)$expense_amount, 2, '.', '');
            $start = strtotime("+1 month", $start);
        }
        // return $start;

        $recentProducts = Product::orderBy('id','DESC')->take(3)->get();

        ////////////////////////////////////////////////////////////////////////////

        $products = Product::all();

        //top 5 selling products
        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y"))); //2022-12-31

        //for the current month
        // $best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))->whereDate('created_at', '>=' , $start_date)
        //     ->whereDate('created_at', '<=' , $end_date)->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //yearly
        $yearly_best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))->whereDate('created_at', '>=' , date("Y").'-01-01')
        ->whereDate('created_at', '<=' , date("Y").'-12-31')->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //return $yearly_best_selling_qty[0]->sold_qty;

        $bestSellingProductsBulk = [];
        foreach ($yearly_best_selling_qty as $key => $sale) {
            $product = DB::table('products')->find($sale->product_id);
            $bestSellingProducts['product_id'] = $sale->product_id;
            $bestSellingProducts['product_code'] = $product->code;
            $bestSellingProducts['product_name'] = $product->name;
            $bestSellingProducts['sold_qty'] = $sale->sold_qty;

            $bestSellingProductsBulk[] = $bestSellingProducts;
        }
        //return $bestSellingProductsBulk[0]['sold_qty'];

        /////////////////////////////////////////////////////////////

        $recentOrders = Order::orderBy('id','DESC')->take(5)->get();

        return view('pages.dashboard', compact('authUser', 'user_role', 'generalSetting', 'currency', 'record', 'purchases_amount_paid', 'sales_due', 'sales_paid', 'expenses', 'profit', 'profit_val',
        'customers_count', 'suppliers_count', 'purchases_count', 'sales_count','invoices_count', 'yearly_sale_amount', 'yearly_purchase_amount', 'yearly_profit_amount', 'yearly_expense_amount',
        'recentProducts', 'products', 'yearly_best_selling_qty', 'bestSellingProductsBulk', 'recentOrders'));
    }

    public function todayRecord()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;

        $generalSetting = GeneralSetting::where('id', '>', 0)->first();
        $currency = $generalSetting->country->symbol;
        $record = 'today';
        ///////////////////////////////////////////////////////////////////////

        $dt = Carbon::now();
        $purchases_amount_paid = Purchase::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->sum('amount_paid');
        $sales_due = Sale::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->sum('amount_due');
        $sales_paid = Sale::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->sum('amount_paid');
        $expenses = $this->shorten(Expense::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->sum('amount'));

        $profit_val = $sales_paid - ($purchases_amount_paid + Expense::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->sum('amount'));
        $purchases_amount_paid = $this->shorten($purchases_amount_paid);
        $sales_paid = $this->shorten($sales_paid);

        if ($profit_val > 0) {
            $profit = $this->shorten($profit_val);
        } else {
            $profit = $this->shorten($profit_val);
        }

        $customers_count = Customer::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->count();
        $suppliers_count = Supplier::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->count();
        $purchases_count = Purchase::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->count();
        $salesInvoice = Sale::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->where('parent_id', null)->count();
        $purchasesInvoice = Purchase::whereBetween('created_at', [$dt->copy()->startOfDay(), $dt->copy()->endOfDay()])->where('parent_id', null)->count();

        $sales_count = Sale::count();
        $invoices_count = $salesInvoice + $purchasesInvoice;

        /////////yearly report purchase, sales, expenses/////////////recent products///

        // yearly report
        $start = strtotime(date("Y") .'-01-01');
        $end = strtotime(date("Y") .'-12-31');
        while($start < $end)
        {
            $start_date = date("Y").'-'.date('m', $start).'-'.'01';
            $end_date = date("Y").'-'.date('m', $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));
            
            $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $profit_amount = $sale_amount - $purchase_amount;
            $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount') + $purchase_amount;

            //sum diff btwn columns
            //$profit = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('amount_due - amount_paid'));
            
            $yearly_sale_amount[] = number_format((float)$sale_amount, 2, '.', '');
            $yearly_purchase_amount[] = number_format((float)$purchase_amount, 2, '.', '');
            $yearly_profit_amount[] = number_format((float)$profit_amount, 2, '.', '');
            $yearly_expense_amount[] = number_format((float)$expense_amount, 2, '.', '');
            $start = strtotime("+1 month", $start);
        }
        // return $start;

        $recentProducts = Product::orderBy('id','DESC')->take(3)->get();

        ////////////////////////////////////////////////////////////////////////////

        $products = Product::all();

        //top 5 selling products
        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y"))); //2022-12-31

        //for the current month
        // $best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))->whereDate('created_at', '>=' , $start_date)
        //     ->whereDate('created_at', '<=' , $end_date)->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //yearly
        $yearly_best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))->whereDate('created_at', '>=' , date("Y").'-01-01')
        ->whereDate('created_at', '<=' , date("Y").'-12-31')->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //return $yearly_best_selling_qty[0]->sold_qty;

        $bestSellingProductsBulk = [];
        foreach ($yearly_best_selling_qty as $key => $sale) {
            $product = DB::table('products')->find($sale->product_id);
            $bestSellingProducts['product_id'] = $sale->product_id;
            $bestSellingProducts['product_code'] = $product->code;
            $bestSellingProducts['product_name'] = $product->name;
            $bestSellingProducts['sold_qty'] = $sale->sold_qty;

            $bestSellingProductsBulk[] = $bestSellingProducts;
        }
        //return $bestSellingProductsBulk[0]['sold_qty'];

        /////////////////////////////////////////////////////////////

        $recentOrders = Order::orderBy('id','DESC')->take(5)->get();

        return view('pages.dashboard', compact('authUser', 'user_role', 'generalSetting', 'currency', 'record', 'purchases_amount_paid', 'sales_due', 'sales_paid', 'expenses', 'profit', 'profit_val',
        'customers_count', 'suppliers_count', 'purchases_count', 'sales_count','invoices_count', 'yearly_sale_amount', 'yearly_purchase_amount', 'yearly_profit_amount', 'yearly_expense_amount',
        'recentProducts', 'products', 'yearly_best_selling_qty', 'bestSellingProductsBulk', 'recentOrders'));
    }

    public function weeklyRecord()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        $generalSetting = GeneralSetting::where('id', '>', 0)->first();
        $currency = $generalSetting->country->symbol;
        $record = 'weekly';
        ///////////////////////////////////////////////////////////////////////

        $dt = Carbon::now();
        $purchases_amount_paid = Purchase::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->sum('amount_paid');
        $sales_due = Sale::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->sum('amount_due');
        $sales_paid = Sale::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->sum('amount_paid');
        $expenses = $this->shorten(Expense::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->sum('amount'));

        $profit_val = $sales_paid - ($purchases_amount_paid + Expense::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->sum('amount'));
        $purchases_amount_paid = $this->shorten($purchases_amount_paid);
        $sales_paid = $this->shorten($sales_paid);

        if ($profit_val > 0) {
            $profit = $this->shorten($profit_val);
        } else {
            $profit = $this->shorten($profit_val);
        }

        $customers_count = Customer::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->count();
        $suppliers_count = Supplier::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->count();
        $purchases_count = Purchase::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->count();
        $salesInvoice = Sale::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->where('parent_id', null)->count();
        $purchasesInvoice = Purchase::whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->where('parent_id', null)->count();

        $sales_count = Sale::count();
        $invoices_count = $salesInvoice + $purchasesInvoice;

        /////////yearly report purchase, sales, expenses/////////////recent products///

        // yearly report
        $start = strtotime(date("Y") .'-01-01');
        $end = strtotime(date("Y") .'-12-31');
        while($start < $end)
        {
            $start_date = date("Y").'-'.date('m', $start).'-'.'01';
            $end_date = date("Y").'-'.date('m', $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));
            
            $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $profit_amount = $sale_amount - $purchase_amount;
            $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount') + $purchase_amount;

            //sum diff btwn columns
            //$profit = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('amount_due - amount_paid'));
            
            $yearly_sale_amount[] = number_format((float)$sale_amount, 2, '.', '');
            $yearly_purchase_amount[] = number_format((float)$purchase_amount, 2, '.', '');
            $yearly_profit_amount[] = number_format((float)$profit_amount, 2, '.', '');
            $yearly_expense_amount[] = number_format((float)$expense_amount, 2, '.', '');
            $start = strtotime("+1 month", $start);
        }
        // return $start;

        $recentProducts = Product::orderBy('id','DESC')->take(3)->get();

        ////////////////////////////////////////////////////////////////////////////

        $products = Product::all();

        //top 5 selling products
        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y"))); //2022-12-31

        //for the current month
        // $best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))->whereDate('created_at', '>=' , $start_date)
        //     ->whereDate('created_at', '<=' , $end_date)->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //yearly
        $yearly_best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))
        ->whereBetween('created_at', [$dt->copy()->startOfWeek(), $dt->copy()->endOfWeek()])->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //return $yearly_best_selling_qty[0]->sold_qty;

        $bestSellingProductsBulk = [];
        foreach ($yearly_best_selling_qty as $key => $sale) {
            $product = DB::table('products')->find($sale->product_id);
            $bestSellingProducts['product_id'] = $sale->product_id;
            $bestSellingProducts['product_code'] = $product->code;
            $bestSellingProducts['product_name'] = $product->name;
            $bestSellingProducts['sold_qty'] = $sale->sold_qty;

            $bestSellingProductsBulk[] = $bestSellingProducts;
        }
        //return $bestSellingProductsBulk[0]['sold_qty'];

        /////////////////////////////////////////////////////////////

        $recentOrders = Order::orderBy('id','DESC')->take(5)->get();

        return view('pages.dashboard', compact('authUser', 'user_role', 'generalSetting', 'currency', 'record', 'purchases_amount_paid', 'sales_due', 'sales_paid', 'expenses', 'profit', 'profit_val',
        'customers_count', 'suppliers_count', 'purchases_count', 'sales_count','invoices_count', 'yearly_sale_amount', 'yearly_purchase_amount', 'yearly_profit_amount', 'yearly_expense_amount',
        'recentProducts', 'products', 'yearly_best_selling_qty', 'bestSellingProductsBulk', 'recentOrders'));
    }

    public function monthlyRecord()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        $generalSetting = GeneralSetting::where('id', '>', 0)->first();
        $currency = $generalSetting->country->symbol;
        $record = 'monthly';
        ///////////////////////////////////////////////////////////////////////

        $dt = Carbon::now();
        $purchases_amount_paid = Purchase::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->sum('amount_paid');
        $sales_due = Sale::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->sum('amount_due');
        $sales_paid = Sale::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->sum('amount_paid');
        $expenses = $this->shorten(Expense::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->sum('amount'));

        $profit_val = $sales_paid - ($purchases_amount_paid + Expense::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->sum('amount'));
        $purchases_amount_paid = $this->shorten($purchases_amount_paid);
        $sales_paid = $this->shorten($sales_paid);

        if ($profit_val > 0) {
            $profit = $this->shorten($profit_val);
        } else {
            $profit = $this->shorten($profit_val);
        }

        $customers_count = Customer::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->count();
        $suppliers_count = Supplier::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->count();
        $purchases_count = Purchase::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->count();
        $salesInvoice = Sale::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->where('parent_id', null)->count();
        $purchasesInvoice = Purchase::whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->where('parent_id', null)->count();

        $sales_count = Sale::count();
        $invoices_count = $salesInvoice + $purchasesInvoice;

        /////////yearly report purchase, sales, expenses/////////////recent products///

        // yearly report
        $start = strtotime(date("Y") .'-01-01');
        $end = strtotime(date("Y") .'-12-31');
        while($start < $end)
        {
            $start_date = date("Y").'-'.date('m', $start).'-'.'01';
            $end_date = date("Y").'-'.date('m', $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));
            
            $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $profit_amount = $sale_amount - $purchase_amount;
            $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount') + $purchase_amount;

            //sum diff btwn columns
            //$profit = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('amount_due - amount_paid'));
            
            $yearly_sale_amount[] = number_format((float)$sale_amount, 2, '.', '');
            $yearly_purchase_amount[] = number_format((float)$purchase_amount, 2, '.', '');
            $yearly_profit_amount[] = number_format((float)$profit_amount, 2, '.', '');
            $yearly_expense_amount[] = number_format((float)$expense_amount, 2, '.', '');
            $start = strtotime("+1 month", $start);
        }
        // return $start;

        $recentProducts = Product::orderBy('id','DESC')->take(3)->get();

        ////////////////////////////////////////////////////////////////////////////

        $products = Product::all();

        //top 5 selling products
        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y"))); //2022-12-31

        //for the current month
        // $best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))->whereDate('created_at', '>=' , $start_date)
        //     ->whereDate('created_at', '<=' , $end_date)->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //yearly
        $yearly_best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))
        ->whereBetween('created_at', [$dt->copy()->startOfMonth(), $dt->copy()->endOfMonth()])->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //return $yearly_best_selling_qty[0]->sold_qty;

        $bestSellingProductsBulk = [];
        foreach ($yearly_best_selling_qty as $key => $sale) {
            $product = DB::table('products')->find($sale->product_id);
            $bestSellingProducts['product_id'] = $sale->product_id;
            $bestSellingProducts['product_code'] = $product->code;
            $bestSellingProducts['product_name'] = $product->name;
            $bestSellingProducts['sold_qty'] = $sale->sold_qty;

            $bestSellingProductsBulk[] = $bestSellingProducts;
        }
        //return $bestSellingProductsBulk[0]['sold_qty'];

        /////////////////////////////////////////////////////////////

        $recentOrders = Order::orderBy('id','DESC')->take(5)->get();

        return view('pages.dashboard', compact('authUser', 'user_role', 'generalSetting', 'currency', 'record', 'purchases_amount_paid', 'sales_due', 'sales_paid', 'expenses', 'profit', 'profit_val',
        'customers_count', 'suppliers_count', 'purchases_count', 'sales_count','invoices_count', 'yearly_sale_amount', 'yearly_purchase_amount', 'yearly_profit_amount', 'yearly_expense_amount',
        'recentProducts', 'products', 'yearly_best_selling_qty', 'bestSellingProductsBulk', 'recentOrders'));
    }

    public function yearlyRecord()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        $generalSetting = GeneralSetting::where('id', '>', 0)->first();
        $currency = $generalSetting->country->symbol;
        $record = 'yearly';
        ///////////////////////////////////////////////////////////////////////

        $dt = Carbon::now();
        $purchases_amount_paid = Purchase::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->sum('amount_paid');
        $sales_due = Sale::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->sum('amount_due');
        $sales_paid = Sale::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->sum('amount_paid');
        $expenses = $this->shorten(Expense::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->sum('amount'));

        $profit_val = $sales_paid - ($purchases_amount_paid + Expense::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->sum('amount'));
        $purchases_amount_paid = $this->shorten($purchases_amount_paid);
        $sales_paid = $this->shorten($sales_paid);

        if ($profit_val > 0) {
            $profit = $this->shorten($profit_val);
        } else {
            $profit = $this->shorten($profit_val);
        }

        $customers_count = Customer::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->count();
        $suppliers_count = Supplier::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->count();
        $purchases_count = Purchase::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->count();
        $salesInvoice = Sale::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->where('parent_id', null)->count();
        $purchasesInvoice = Purchase::whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->where('parent_id', null)->count();

        $sales_count = Sale::count();
        $invoices_count = $salesInvoice + $purchasesInvoice;

        /////////yearly report purchase, sales, expenses/////////////recent products///

        // yearly report
        $start = strtotime(date("Y") .'-01-01');
        $end = strtotime(date("Y") .'-12-31');
        while($start < $end)
        {
            $start_date = date("Y").'-'.date('m', $start).'-'.'01';
            $end_date = date("Y").'-'.date('m', $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));
            
            $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount_paid');
            $profit_amount = $sale_amount - $purchase_amount;
            $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount') + $purchase_amount;

            //sum diff btwn columns
            //$profit = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('amount_due - amount_paid'));
            
            $yearly_sale_amount[] = number_format((float)$sale_amount, 2, '.', '');
            $yearly_purchase_amount[] = number_format((float)$purchase_amount, 2, '.', '');
            $yearly_profit_amount[] = number_format((float)$profit_amount, 2, '.', '');
            $yearly_expense_amount[] = number_format((float)$expense_amount, 2, '.', '');
            $start = strtotime("+1 month", $start);
        }
        // return $start;

        $recentProducts = Product::orderBy('id','DESC')->take(3)->get();

        ////////////////////////////////////////////////////////////////////////////

        $products = Product::all();

        //top 5 selling products
        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y"))); //2022-12-31

        //for the current month
        // $best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))->whereDate('created_at', '>=' , $start_date)
        //     ->whereDate('created_at', '<=' , $end_date)->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //yearly
        $yearly_best_selling_qty = Sale::select(DB::raw('product_id, sum(product_qty_sold) as sold_qty'))
        ->whereBetween('created_at', [$dt->copy()->startOfYear(), $dt->copy()->endOfYear()])->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        //return $yearly_best_selling_qty[0]->sold_qty;

        $bestSellingProductsBulk = [];
        foreach ($yearly_best_selling_qty as $key => $sale) {
            $product = DB::table('products')->find($sale->product_id);
            $bestSellingProducts['product_id'] = $sale->product_id;
            $bestSellingProducts['product_code'] = $product->code;
            $bestSellingProducts['product_name'] = $product->name;
            $bestSellingProducts['sold_qty'] = $sale->sold_qty;

            $bestSellingProductsBulk[] = $bestSellingProducts;
        }
        //return $bestSellingProductsBulk[0]['sold_qty'];

        /////////////////////////////////////////////////////////////

        $recentOrders = Order::orderBy('id','DESC')->take(5)->get();

        return view('pages.dashboard', compact('authUser', 'user_role', 'generalSetting', 'currency', 'record', 'purchases_amount_paid', 'sales_due', 'sales_paid', 'expenses', 'profit', 'profit_val',
        'customers_count', 'suppliers_count', 'purchases_count', 'sales_count','invoices_count', 'yearly_sale_amount', 'yearly_purchase_amount', 'yearly_profit_amount', 'yearly_expense_amount',
        'recentProducts', 'products', 'yearly_best_selling_qty', 'bestSellingProductsBulk', 'recentOrders'));
    }

    public function shorten($num, $digits = 1) {
        $num = preg_replace('/[^0-9]/','',$num);
        if ($num >= 1000000000) {
            $num = number_format(abs($num / 1000000000), $digits, '.', '') + 0;
            $num = $num . "b";
        }
        if ($num >= 1000000) {
            $num = number_format(abs($num / 1000000), $digits, '.', '') + 0;
            $num = $num . 'm';
        }
        if ($num >= 1000) {
            $num = number_format(abs($num / 1000), $digits, '.', '') + 0;
            $num = $num . 'k';
        }
        return $num;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
