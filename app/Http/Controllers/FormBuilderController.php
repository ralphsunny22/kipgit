<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;
use DB;

use Illuminate\Support\Facades\Mail;
use App\Mail\TestMail;
use App\Events\TestEvent;
use App\Notifications\TestNofication;
use App\Notifications\NewOrder;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Exception;

use App\Models\FormHolder;
use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use App\Models\OutgoingStock;
use App\Models\IncomingStock;
use App\Models\OrderBump;
use App\Models\UpSell;
use App\Models\Customer;
use App\Models\CartAbandon;
use App\Models\UpsellSetting;
use App\Models\GeneralSetting;
use App\Models\SoundNotification;
use App\Models\ThankYou;


class FormBuilderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function formBuilder()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        return view('pages.formBuilder');
    }

    public function newFormBuilder()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $products = Product::all();
        $staffs = User::where('type','staff')->get();
        // $products_jqueryArray = \json_encode($products);

        $string = 'kpf-' . date("his");
        $randomStrings = FormHolder::where('slug', 'like', $string.'%')->pluck('slug');

        do {
            $randomString = $string.rand(100000, 999999);
        } while ($randomStrings->contains($randomString));
    
        $form_code = $randomString;
        
        $package_select = '<select class="form-control select-checkbox" name="packages[]" style="width:100%">
        <option value="">--Select Option--</option>';
        foreach($products as $product):
            $package_select .= '<option value="'. $product->id.'"> '.$product->name.'</option>' ;
        endforeach;
        $package_select .='</select>';

        return view('pages.newFormBuilder', compact('authUser', 'user_role', 'products', 'package_select', 'form_code'));
    }

    //saving built form
    public function newFormBuilderPost(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'name' => 'required|string|unique:form_holders',
            'packages' => 'required',
            'form_names' => 'required',
            'form_labels' => 'required',
        ]);

        $data = $request->all();
        //neccessary fields contain at least one value
        if ((count(array_filter($data['packages'])) == 0) || (count(array_filter($data['form_names'])) == 0) || (count(array_filter($data['form_labels'])) == 0)) {
            return back()->with('field_error', 'Missing Fields');
        }

        $form_names_expected = array("First Name", "Last Name", "Phone Number", "Whatsapp Phone Number", "Active Email", "State", "City", "Address", "Product Package");
        //check form-names & labels
        $form_names = Arr::flatten($data['form_names']);
        foreach ($form_names_expected as $form_name) {
            if (!in_array($form_name, $form_names)) { return back()->with('field_error', $form_name.' is Missing'); }

            //duplicates error
            if (count(array_keys($form_names, $form_name)) > 1) { return back()->with('field_error', $form_name.' Occurred Morethan Once'); }
        }

        //duplicate products selected error
        $packages = Arr::flatten($data['packages']);
        $selected_products = Product::whereIn('id', $data['packages'])->get();
        foreach ($selected_products as $package) {
            if (count(array_keys($packages, $package->id)) > 1) { return back()->with('field_error', 'Product: '.$package->name.' Was Selected Morethan Once'); }
        }
    
        //save FormHolder
        $formHolder = new FormHolder();
        $formHolder->name = $data['name'];
        $formHolder->slug = $request->form_code; //like form_code
        $formHolder->form_data = \serialize($request->except(['products', 'q', 'required', 'form_name_selected', '_token']));
        
        $formHolder->created_by = $authUser->id;
        $formHolder->status = 'true';
        $formHolder->save();

        //save Order
        $order = new Order();
        $order->form_holder_id = $formHolder->id;
        $order->source_type = 'form_holder_module';
        $order->status = 'new';
        $order->save();

        //outgoingStock, in place of orderProduct
        $product_ids = []; $package_bundle = [];
        foreach ($data['packages'] as $package) {
            if (!empty($package)) {
                $product = Product::where('id', $package)->first();
                $product_ids[] = $product->id;
                // $outgoingStock = new OutgoingStock();
                // $outgoingStock->product_id = $product->id;
                // $outgoingStock->order_id = $order->id;
                // $outgoingStock->quantity_removed = 1;
                // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
                // $outgoingStock->reason_removed = 'as_order_firstphase'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
                // $outgoingStock->quantity_returned = 0; //by default
                // $outgoingStock->isCombo = isset($product->combo_product_ids) ? 'true' : null;
                // $outgoingStock->created_by = $authUser->id;
                // $outgoingStock->status = 'true';
                // $outgoingStock->save();

                // Create a new package array for each product ID
                $package_bundles = [
                    'product_id'=>$product->id,
                    'quantity_removed'=>1,
                    'amount_accrued'=>$product->sale_price,
                    'customer_acceptance_status'=>null,
                    'reason_removed'=>'as_order_firstphase',
                    'quantity_returned'=>0,
                    'reason_returned'=>null,
                    'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
                ];
                $package_bundle[] = $package_bundles;
                ////////////////////////////////////////////
    
            } 
        }

        $outgoingStock = new OutgoingStock();
        $outgoingStock->order_id = $order->id;
        $outgoingStock->package_bundle = $package_bundle;
        $outgoingStock->created_by = $authUser->id;
        $outgoingStock->status = 'true';
        $outgoingStock->save();
    
        //update formHolder
        $formHolder->update(['order_id'=>$order->id]);
        $order->update(['products'=>serialize($product_ids)]);

        return back()->with('success', 'Form Created Successfully');
        
    }

    public function allNewFormBuilders()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;

        $formHolds = FormHolder::where('has_edited_duplicate',false)->orderBy('id', 'DESC')->get();
        $formHolders = [];
        foreach ($formHolds as $key => $formHolder) {
            $formHolder['form_data'] = \unserialize($formHolder->form_data);
            $formHolders[] = $formHolder;
        }
        //return $formHolders;
        
        //$products = Product::whereNull('combo_product_ids')->where('status', 'true')->orderBy('id','DESC')->get();
        $products = Product::where('status', 'true')->orderBy('id','DESC')->get();
        $upsellTemplates = UpsellSetting::all();
        $staffs = User::where('type','staff')->get();
        $thankYouTemplates = ThankYou::all();

        return view('pages.allFormBuilders', compact('authUser', 'user_role', 'formHolders', 'products', 'upsellTemplates', 'staffs', 'thankYouTemplates'));
    }

    public function assignStaffToForm(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();
        $form_id = $data['form_id'];
        $staff_id = $data['staff_id'];

        $formHolder = FormHolder::where('id',$form_id)->first();

        //check if form has entries
        if (count($formHolder->customers) > 0) {
            $orders = Order::where('form_holder_id', $formHolder->id)->update(['staff_assigned_id'=>$staff_id]);
        }
        
        //upd form
        $formHolder->update(['staff_assigned_id'=>$staff_id]);
        
         
        return back()->with('success', 'Staff Assigned Successfully');
    }

    //duplicateForm
    public function duplicateForm ($unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            abort(404);
        }

        //form code
        $string = 'kpf-' . date("his");
        $randomStrings = FormHolder::where('slug', 'like', $string.'%')->pluck('slug');

        do {
            $randomString = $string.rand(100000, 999999);
        } while ($randomStrings->contains($randomString));
    
        $form_code = $randomString;

        //data to show/edit
        $formData = \unserialize($formHolder->form_data);
        
        $formContactInfo = [];
        $formPackage = [];
        $form_names = $formData['form_names'];
        $form_labels = $formData['form_labels'];
        $form_types = $formData['form_types'];
        $packages = $formData['packages']; //["1", "2"]

        $products = Product::all();

        foreach($packages as $key=>$package):
        $package_select_edit[] =
        '<div class="row w-100">
            <div class="col-sm-1 rem-on-display" onclick="$(this).closest(\'.row\').remove()">
                <button class="btn btn-sm btn-default" type="button"><span class="bi bi-x-lg"></span></button>
            </div>
            <div class="col-sm-11 d-flex align-items-center">
                <div class="mb-3 q-fc w-100">';
                $package_select_edit[] .=
                '<select class="select2 form-control border select-checkbox" name="packages[]" style="width:100%">';
                   if($this->productById($package) !== ""): $package_select_edit[] .= '<option value="'.$package.'" selected> '.$this->productById($package)->name.' </option>'; endif;
                    foreach($products as $product):
                        $package_select_edit[] .= '<option value="'. $product->id.'"> '.$product->name.'</option>';
                    endforeach;
                $package_select_edit[] .=
                '</select>
                <input type="hidden" name="former_packages[]" value="'.$package.'">
                </div>
            </div>
        </div>';
        endforeach;

        //return $package_select_edit;

        //for cloning
        $package_select = '<select class="form-control select-checkbox" name="packages[]" style="width:100%">
        <option value=""> --Select Product-- </option>';
        foreach($products as $product):
            $package_select .= '<option value="'. $product->id.'"> '.$product->name.'</option>' ;
        endforeach;
        $package_select .='</select>';

        //cos form_names are not determind by staff in form-building
        foreach ( $form_names as $key => $form_name ) {
            //$formContact[Str::slug($form_name)] = [ Str::slug($form_name), $form_labels[$key], $form_types[$key] ];
            $formContactInfo['form_name'] = $form_name;
            $formContactInfo['form_label'] = $form_labels[$key];
            $formContactInfo['form_type'] = $form_types[$key];
            $formContact[] = $formContactInfo;
        }
        // return $formContact;

        //extract products
        foreach ($formContact as $key => $formProduct) {
            if ($formProduct['form_name'] == 'Product Package') {
                $package_form_name = $formProduct['form_name'];
                $package_form_label = $formProduct['form_label'];
                $package_form_type = $formProduct['form_type'];
            }
        }
        
        // return $products;

        return view('pages.duplicateForm', compact('authUser', 'user_role', 'formHolder', 'products', 'package_select', 'form_code', 'formContact', 'packages', 'package_select_edit'));

    }

    public function duplicateFormPost(Request $request, $unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder_former = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder_former)) {
            abort(404);
        }
        
        $request->validate([
            'name' => 'required|string|unique:form_holders',
            'packages' => 'required',
            'form_names' => 'required',
            'form_labels' => 'required',
        ]);

        $data = $request->all();
        //neccessary fields contain at least one value
        if ((count(array_filter($data['packages'])) == 0) || (count(array_filter($data['form_names'])) == 0) || (count(array_filter($data['form_labels'])) == 0)) {
            return back()->with('field_error', 'Missing Fields');
        }

        $form_names_expected = array("First Name", "Last Name", "Phone Number", "Whatsapp Phone Number", "Active Email", "State", "City", "Address", "Product Package");
        //check form-names & labels
        $form_names = Arr::flatten($data['form_names']);
        foreach ($form_names_expected as $form_name) {
            if (!in_array($form_name, $form_names)) { return back()->with('field_error', $form_name.' is Missing'); }

            //duplicates error
            if (count(array_keys($form_names, $form_name)) > 1) { return back()->with('field_error', $form_name.' Occurred Morethan Once'); }
        }

        //duplicate products selected error
        $packages = Arr::flatten($data['packages']);
        $selected_products = Product::whereIn('id', $data['packages'])->get();
        foreach ($selected_products as $package) {
            if (count(array_keys($packages, $package->id)) > 1) { return back()->with('field_error', 'Product: '.$package->name.' Was Selected Morethan Once'); }
        }
        //validation ends

        $formHolder = new FormHolder();
        $formHolder->name = $data['name'];
        $formHolder->parent_id = $formHolder_former->id; //like form_code
        $formHolder->slug = $request->form_code; //like form_code
        $formHolder->form_data = \serialize($request->except(['products', 'q', 'required', 'form_name_selected', '_token']));
        $formHolder->staff_assigned_id = isset($formHolder_former->staff_assigned_id) ? $formHolder_former->staff_assigned_id : null;
        
        $formHolder->created_by = $authUser->id;
        $formHolder->status = 'true';
        $formHolder->save();

        //save Order
        $order = new Order();
        $order->form_holder_id = $formHolder->id;
        $order->staff_assigned_id = isset($formHolder_former->staff_assigned_id) ? $formHolder_former->staff_assigned_id : null;
        $order->source_type = 'form_holder_module';
        $order->status = 'new';

        $order->save();

        //outgoingStock, in place of orderProduct
        $product_ids = []; $package_bundle = [];
        foreach ($data['packages'] as $package) {
            if (!empty($package)) {
                
                $product = Product::where('id', $package)->first();
                $product_ids[] = $product->id;
                // $outgoingStock = new OutgoingStock();
                // $outgoingStock->product_id = $product->id;
                // $outgoingStock->order_id = $order->id;
                // $outgoingStock->quantity_removed = 1;
                // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
                // $outgoingStock->reason_removed = 'as_order_firstphase'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
                // $outgoingStock->quantity_returned = 0; //by default
                // $outgoingStock->created_by = $authUser->id;
                // $outgoingStock->status = 'true';
                // $outgoingStock->save();

                // Create a new package array for each product ID
                $package_bundles = [
                    'product_id'=>$product->id,
                    'quantity_removed'=>1,
                    'amount_accrued'=>$product->sale_price,
                    'customer_acceptance_status'=>null,
                    'reason_removed'=>'as_order_firstphase',
                    'quantity_returned'=>0,
                    'reason_returned'=>null,
                    'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
                ];
                $package_bundle[] = $package_bundles;
            }  
        }

        //create new OutgoingStock
        $outgoingStock = new OutgoingStock();
        $outgoingStock->order_id = $order->id;
        $outgoingStock->package_bundle = $package_bundle;
        $outgoingStock->created_by = $authUser->id;
        $outgoingStock->status = 'true';
        $outgoingStock->save();
        ///////////////////////////////////

        //update formHolder
        $formHolder->update(['order_id'=>$order->id]);
        $order->update(['products'=>serialize($product_ids)]);

        if ( (isset($formHolder_former->orderbump_id)) && (isset($formHolder_former->orderbump->product->id)) ) {
            //orderbump
            $former_orderbump = $formHolder_former->orderbump;

            $orderbump = new OrderBump();
            $orderbump->orderbump_heading = $former_orderbump->orderbump_heading;
            $orderbump->orderbump_subheading = serialize($former_orderbump->orderbump_subheading);
            $orderbump->product_id = $former_orderbump->product_id;
            $orderbump->order_id = $order->id;
            $orderbump->product_expected_quantity_to_be_sold = 1;
            $orderbump->product_expected_amount = 0;
            $orderbump->status = 'true';
            $orderbump->save();

            $product = $former_orderbump->product;

            //outgoing stock for orderbump
            // $outgoingStock = new OutgoingStock();
            // $outgoingStock->product_id = $product->id;
            // $outgoingStock->order_id = $order->id;
            // $outgoingStock->quantity_removed = 1;
            // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
            // $outgoingStock->reason_removed = 'as_orderbump'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
            // $outgoingStock->quantity_returned = 0; //by default
            // $outgoingStock->created_by = $authUser->id;
            // $outgoingStock->status = 'true';
            // $outgoingStock->save();

            // Create a new package array for each product ID
            $package_bundles = [
                'product_id'=>$product->id,
                'quantity_removed'=>1,
                'amount_accrued'=>$product->sale_price,
                'customer_acceptance_status'=>null,
                'reason_removed'=>'as_orderbump',
                'quantity_returned'=>0,
                'reason_returned'=>null,
                'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
            ];
            //$package_bundle[] = $package_bundles;
            $orderPackageBundle = $order->outgoingStock->package_bundle;
            array_push($orderPackageBundle, $package_bundles);

            //create new OutgoingStock
            $outgoingStock = OutgoingStock::where('order_id', $order->id)->first();
            $outgoingStock->order_id = $order->id;
            $outgoingStock->package_bundle = $orderPackageBundle;
            $outgoingStock->created_by = $authUser->id;
            $outgoingStock->status = 'true';
            $outgoingStock->save();
            ////////////////////////////////

            //update formHolder
            $formHolder->update(['orderbump_id'=>$orderbump->id]);
        }

        if ( (isset($formHolder_former->upsell_id)) && (isset($formHolder_former->upsell->product->id)) ) {
            //orderbump
            $former_upsell = $formHolder_former->upsell;

            $upsell = new UpSell();
            $upsell->upsell_heading = $former_upsell->upsell_heading;
            $upsell->upsell_subheading = serialize($former_upsell->upsell_subheading);
            $upsell->upsell_setting_id = $former_upsell->upsell_setting_id;
            $upsell->product_id = $former_upsell->product_id;
            $upsell->order_id = $order->id;
            $upsell->product_expected_quantity_to_be_sold = 1;
            $upsell->product_expected_amount = 0;
            $upsell->status = 'true';
            $upsell->save();

            $product = $former_upsell->product;

            //outgoing stock for upsell
            // $outgoingStock = new OutgoingStock();
            // $outgoingStock->product_id = $product->id;
            // $outgoingStock->order_id = $order->id;
            // $outgoingStock->quantity_removed = 1;
            // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
            // $outgoingStock->reason_removed = 'as_upsell'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
            // $outgoingStock->quantity_returned = 0; //by default
            // $outgoingStock->created_by = $authUser->id;
            // $outgoingStock->status = 'true';
            // $outgoingStock->save();

            // Create a new package array for each product ID
            $package_bundles = [
                'product_id'=>$product->id,
                'quantity_removed'=>1,
                'amount_accrued'=>$product->sale_price,
                'customer_acceptance_status'=>null,
                'reason_removed'=>'as_upsell',
                'quantity_returned'=>0,
                'reason_returned'=>null,
                'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
            ];
            //$package_bundle[] = $package_bundles;
            $orderPackageBundle = $order->outgoingStock->package_bundle;
            array_push($orderPackageBundle, $package_bundles);

            //create new OutgoingStock
            $outgoingStock = new OutgoingStock();
            $outgoingStock->order_id = $order->id;
            $outgoingStock->package_bundle = $orderPackageBundle;
            $outgoingStock->created_by = $authUser->id;
            $outgoingStock->status = 'true';
            $outgoingStock->save();
            ////////////////////////////////

            //update formHolder
            $formHolder->update(['upsell_id'=>$upsell->id]);
        }

        return back()->with('success', 'Duplicate Form Created Successfully');
        
    }

    public function editNewFormBuilder ($unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            abort(404);
        }
        $form_code = $formHolder->slug;

        //data to show/edit
        $formData = \unserialize($formHolder->form_data);
        
        $formContactInfo = [];
        $formPackage = [];
        $form_names = $formData['form_names'];
        $form_labels = $formData['form_labels'];
        $form_types = $formData['form_types'];
        $packages = $formData['packages']; //["1", "2"]

        //Session::put('former_packages', $packages);

        $products = Product::all();

        foreach($packages as $key=>$package):
            $package_select_edit[] =
            '<div class="row w-100">
                <div class="col-sm-1 rem-on-display" onclick="$(this).closest(\'.row\').remove()">
                    <button class="btn btn-sm btn-default" type="button"><span class="bi bi-x-lg"></span></button>
                </div>
                <div class="col-sm-11 d-flex align-items-center">
                    <div class="mb-3 q-fc w-100">';
                    $package_select_edit[] .=
                    '<select class="select2 form-control border select-checkbox" name="packages[]" style="width:100%">';
                       if($this->productById($package) !== ""): $package_select_edit[] .= '<option value="'.$package.'" selected> '.$this->productById($package)->name.' </option>'; endif;
                        foreach($products as $product):
                            $package_select_edit[] .= '<option value="'. $product->id.'"> '.$product->name.'</option>';
                        endforeach;
                    $package_select_edit[] .=
                    '</select>
                    <input type="hidden" name="former_packages[]" value="'.$package.'">
                    </div>
                </div>
            </div>';
        endforeach;

        //return $package_select_edit;
        
        //for cloning
        $package_select = '<select class="form-control select-checkbox" name="packages[]" style="width:100%">
        <option value=""> --Select Product-- </option>';
        foreach($products as $product):
            $package_select .= '<option value="'. $product->id.'"> '.$product->name.'</option>' ;
        endforeach;
        $package_select .='</select>';

        //cos form_names are not determind by staff in form-building
        foreach ( $form_names as $key => $form_name ) {
            //$formContact[Str::slug($form_name)] = [ Str::slug($form_name), $form_labels[$key], $form_types[$key] ];
            $formContactInfo['form_name'] = $form_name;
            $formContactInfo['form_label'] = $form_labels[$key];
            $formContactInfo['form_type'] = $form_types[$key];
            $formContact[] = $formContactInfo;
        }
        // return $formContact;

        //extract products
        foreach ($formContact as $key => $formProduct) {
            if ($formProduct['form_name'] == 'Product Package') {
                $package_form_name = $formProduct['form_name'];
                $package_form_label = $formProduct['form_label'];
                $package_form_type = $formProduct['form_type'];
            }
        }
        
        // return $products;

        return view('pages.editNewFormBuilder', compact('authUser', 'user_role', 'formHolder', 'products', 'package_select', 'form_code', 'formContact', 'packages', 'package_select_edit'));

    }

    public function editNewFormBuilderPost (Request $request, $unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            abort(404);
        }

        $request->validate([
            'name' => 'required|string',
            'packages' => 'required',
            'form_names' => 'required',
            'form_labels' => 'required',
        ]);

        $data = $request->all();
        //neccessary fields contain at least one value
        if ((count(array_filter($data['packages'])) == 0) || (count(array_filter($data['form_names'])) == 0) || (count(array_filter($data['form_labels'])) == 0)) {
            return back()->with('field_error', 'Missing Fields');
        }

        $form_names_expected = array("First Name", "Last Name", "Phone Number", "Whatsapp Phone Number", "Active Email", "State", "City", "Address", "Product Package");
        //check form-names & labels
        $form_names = Arr::flatten($data['form_names']);
        foreach ($form_names_expected as $form_name) {
            if (!in_array($form_name, $form_names)) { return back()->with('field_error', $form_name.' is Missing'); }

            //duplicates error
            if (count(array_keys($form_names, $form_name)) > 1) { return back()->with('field_error', $form_name.' Occurred Morethan Once'); }
        }

        //duplicate products selected error
        $packages = Arr::flatten($data['packages']);
        $selected_products = Product::whereIn('id', $data['packages'])->get();
        foreach ($selected_products as $package) {
            if (count(array_keys($packages, $package->id)) > 1) { return back()->with('field_error', 'Product: '.$package->name.' Was Selected Morethan Once'); }
        }

        $order = $formHolder->order;
        $form_code = $formHolder->slug;
        $former_packages = $data['former_packages2'];

        if (!empty($data['switch_orderbump'])) {
            if ($data['switch_orderbump'] == 'off') {
                $formHolder->orderbump_id = null;
                OrderBump::where('id',$formHolder->orderbump_id)->delete();
            }
        }
        if (!empty($data['switch_upsell'])) {
            if ($data['switch_upsell'] == 'off') {
                $formHolder->upsell_id = null;
                UpSell::where('id',$formHolder->upsell_id)->delete();
            }
        }

        //for unsed forms
        if (count($formHolder->customers) < 1) {
            $formHolder->form_data = \serialize($request->except(['products', 'q', 'required', 'form_name_selected', '_token', 'former_packages', 'former_packages2']));
        
            //$formHolder->created_by = $authUser->id;
            $formHolder->name = $data['name'];
            $formHolder->status = 'true';
            $formHolder->save();

            //remove n replace outgoing stock
            //$former_packages = $data['former_packages2']; //["1","2"]
            //OutgoingStock::whereIn('product_id', json_decode($data['former_packages2']))->where(['order_id'=>$order->id])->delete();
            OutgoingStock::where(['order_id'=>$order->id])->forceDelete();

            $product_ids = []; $package_bundle = [];
            foreach ($data['packages'] as $package) {
                if (!empty($package)) {
                    $product = Product::where('id', $package)->first();
                    $product_ids[] = $product->id;
                    // $outgoingStock = new OutgoingStock();
                    // $outgoingStock->product_id = $product->id;
                    // $outgoingStock->order_id = $order->id;
                    // $outgoingStock->quantity_removed = 1;
                    // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
                    // $outgoingStock->reason_removed = 'as_order_firstphase'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
                    // $outgoingStock->quantity_returned = 0; //by default
                    // $outgoingStock->isCombo = isset($product->combo_product_ids) ? 'true' : null;
                    // $outgoingStock->created_by = $authUser->id;
                    // $outgoingStock->status = 'true';
                    // $outgoingStock->save();

                    // Create a new package array for each product ID
                    $package_bundles = [
                        'product_id'=>$product->id,
                        'quantity_removed'=>1,
                        'amount_accrued'=>$product->sale_price,
                        'customer_acceptance_status'=>null,
                        'reason_removed'=>'as_order_firstphase',
                        'quantity_returned'=>0,
                        'reason_returned'=>null,
                        'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
                    ];
                    $package_bundle[] = $package_bundles;
                } 
            }

            $outgoingStock = new OutgoingStock();
            $outgoingStock->order_id = $order->id;
            $outgoingStock->package_bundle = $package_bundle;
            $outgoingStock->created_by = $authUser->id;
            $outgoingStock->status = 'true';
            $outgoingStock->save();

            //update formHolder, no need
            //$formHolder->update(['order_id'=>$order->id]);
            $order->update(['products'=>serialize($product_ids)]);
            return back()->with('success', 'Form Updated Successfully');
        } else {
            
            //copy from former_form
            $formHolder_former = $formHolder;

            //paste in new duplicate form
            $string = 'kpf-' . date("his");
            $randomString = $string.rand(100000, 999999);
            $formHolder = new FormHolder();
            $formHolder->name = $formHolder_former->name == $data['name'] ? $formHolder_former->name.rand(100000, 999999) : $formHolder_former->name;
            $formHolder->parent_id = $formHolder_former->id; //like form_code
            $formHolder->slug = $randomString; //like form_code
            $formHolder->form_data = $formHolder_former->form_data;
            $formHolder->staff_assigned_id = isset($formHolder_former->staff_assigned_id) ? $formHolder_former->staff_assigned_id : null;
            $formHolder->order_id = $order->id;
            
            $formHolder->created_by = $authUser->id;
            $formHolder->status = 'true';
            $formHolder->has_edited_duplicate = true;
            $formHolder->save();

            //update tables where former_form->id is foreign, with formHolder->id
            $order = $formHolder_former->order;
            $order->update(['form_holder_id'=>$formHolder->id]);
            Customer::where('order_id', $order->id)->update(['form_holder_id'=>$formHolder->id]);
            
            //update former_form with new requests
            $formHolder_former->name = $data['name'];
            $formHolder_former->parent_id = null; //like form_code
            $formHolder_former->form_data = \serialize($request->except(['products', 'q', 'required', 'form_name_selected', '_token', 'former_packages', 'former_packages2']));
            $formHolder_former->staff_assigned_id = isset($formHolder_former->staff_assigned_id) ? $formHolder_former->staff_assigned_id : null;
            
            $formHolder_former->created_by = $authUser->id;
            $formHolder_former->status = 'true';
            $formHolder_former->save();
            
            //save Order, based on updated former_form
            $order = new Order();
            $order->form_holder_id = $formHolder_former->id;
            $order->staff_assigned_id = isset($formHolder_former->staff_assigned_id) ? $formHolder_former->staff_assigned_id : null;
            $order->source_type = 'form_holder_module';
            $order->status = 'new';
            $order->save();

            //outgoingStock, in place of orderProduct
            $product_ids = []; $package_bundle = [];
            foreach ($data['packages'] as $package) {
                if (!empty($package)) {
                    
                    $product = Product::where('id', $package)->first();
                    $product_ids[] = $product->id;
                    // $outgoingStock = new OutgoingStock();
                    // $outgoingStock->product_id = $product->id;
                    // $outgoingStock->order_id = $order->id;
                    // $outgoingStock->quantity_removed = 1;
                    // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
                    // $outgoingStock->reason_removed = 'as_order_firstphase'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
                    // $outgoingStock->quantity_returned = 0; //by default
                    // $outgoingStock->created_by = $authUser->id;
                    // $outgoingStock->status = 'true';
                    // $outgoingStock->save();

                    // Create a new package array for each product ID
                    $package_bundles = [
                        'product_id'=>$product->id,
                        'quantity_removed'=>1,
                        'amount_accrued'=>$product->sale_price,
                        'customer_acceptance_status'=>null,
                        'reason_removed'=>'as_order_firstphase',
                        'quantity_returned'=>0,
                        'reason_returned'=>null,
                        'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
                    ];
                    $package_bundle[] = $package_bundles;
                    
                }  
            }

            //create new OutgoingStock
            $outgoingStock = new OutgoingStock();
            $outgoingStock->order_id = $order->id;
            $outgoingStock->package_bundle = $package_bundle;
            $outgoingStock->created_by = $authUser->id;
            $outgoingStock->status = 'true';
            $outgoingStock->save();

            //update formHolder
            $formHolder_former->update(['order_id'=>$order->id]);
            $order->update(['products'=>serialize($product_ids)]);

            //$package_bundle = [];
            if ( (isset($formHolder_former->orderbump_id)) && (isset($formHolder_former->orderbump->id)) && (isset($formHolder_former->orderbump->product->id)) ) {
                //orderbump
                $former_orderbump = $formHolder_former->orderbump;

                $orderbump = new OrderBump();
                $orderbump->orderbump_heading = $former_orderbump->orderbump_heading;
                $orderbump->orderbump_subheading = serialize($former_orderbump->orderbump_subheading);
                $orderbump->product_id = $former_orderbump->product_id;
                $orderbump->order_id = $order->id;
                $orderbump->product_expected_quantity_to_be_sold = 1;
                $orderbump->product_expected_amount = 0;
                $orderbump->status = 'true';
                $orderbump->save();

                $product = $former_orderbump->product;

                //outgoing stock for orderbump
                // $outgoingStock = new OutgoingStock();
                // $outgoingStock->product_id = $product->id;
                // $outgoingStock->order_id = $order->id;
                // $outgoingStock->quantity_removed = 1;
                // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
                // $outgoingStock->reason_removed = 'as_orderbump'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
                // $outgoingStock->quantity_returned = 0; //by default
                // $outgoingStock->created_by = $authUser->id;
                // $outgoingStock->status = 'true';
                // $outgoingStock->save();

                // Create a new package array for each product ID
                $package_bundles = [
                    'product_id'=>$product->id,
                    'quantity_removed'=>1,
                    'amount_accrued'=>$product->sale_price,
                    'customer_acceptance_status'=>null,
                    'reason_removed'=>'as_orderbump',
                    'quantity_returned'=>0,
                    'reason_returned'=>null,
                    'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
                ];
                //$package_bundle[] = $package_bundles;
                $orderOutgoingStockPackageBundle = $order->outgoingStock->package_bundle;
                array_push($orderOutgoingStockPackageBundle, $package_bundles);

                //create new OutgoingStock
                $outgoingStock = OutgoingStock::where('order_id', $order->id)->first();
                $outgoingStock->order_id = $order->id;
                $outgoingStock->package_bundle = $orderOutgoingStockPackageBundle;
                $outgoingStock->created_by = $authUser->id;
                $outgoingStock->status = 'true';
                $outgoingStock->save();

                //update formHolder
                $formHolder->update(['orderbump_id'=>$orderbump->id]);
                
            } 

            //$package_bundle = [];
            if ( (isset($formHolder_former->upsell_id))  && (isset($formHolder_former->upsell->id)) && (isset($formHolder_former->upsell->product->id)) ) {
                //orderbump
                $former_upsell = $formHolder_former->upsell;

                $upsell = new UpSell();
                $upsell->upsell_heading = $former_upsell->upsell_heading;
                $upsell->upsell_subheading = serialize($former_upsell->upsell_subheading);
                $upsell->upsell_setting_id = $former_upsell->upsell_setting_id;
                $upsell->product_id = $former_upsell->product_id;
                $upsell->order_id = $order->id;
                $upsell->product_expected_quantity_to_be_sold = 1;
                $upsell->product_expected_amount = 0;
                $upsell->status = 'true';
                $upsell->save();

                $product = $former_upsell->product;
    
                //outgoing stock for upsell
                // $outgoingStock = new OutgoingStock();
                // $outgoingStock->product_id = $product->id;
                // $outgoingStock->order_id = $order->id;
                // $outgoingStock->quantity_removed = 1;
                // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
                // $outgoingStock->reason_removed = 'as_upsell'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
                // $outgoingStock->quantity_returned = 0; //by default
                // $outgoingStock->created_by = $authUser->id;
                // $outgoingStock->status = 'true';
                // $outgoingStock->save();

                // Create a new package array for each product ID
                $package_bundles = [
                    'product_id'=>$product->id,
                    'quantity_removed'=>1,
                    'amount_accrued'=>$product->sale_price,
                    'customer_acceptance_status'=>null,
                    'reason_removed'=>'as_upsell',
                    'quantity_returned'=>0,
                    'reason_returned'=>null,
                    'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
                ];
                //$package_bundle[] = $package_bundles;
                $orderOutgoingStockPackageBundle = $order->outgoingStock->package_bundle;
                array_push($orderOutgoingStockPackageBundle, $package_bundles);

                //create new OutgoingStock
                $outgoingStock = OutgoingStock::where('order_id', $order->id)->first();
                $outgoingStock->order_id = $order->id;
                $outgoingStock->package_bundle = $orderOutgoingStockPackageBundle;
                $outgoingStock->created_by = $authUser->id;
                $outgoingStock->status = 'true';
                $outgoingStock->save();

                //update formHolder
                $formHolder->update(['upsell_id'=>$upsell->id]);
 
            }

            // $formHolder_former->has_edited_duplicate = true;
            // $formHolder_former->save();

            return back()->with('success', 'Form Edited Successfully');
        }
    }
    //editform-end

    public function deleteForm($unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            abort(404);
        }
        $formHolder->delete();
        return back()->with('success', 'Form Deleted Successfullly');
    }

    //not used. ajax save form first time
    public function formBuilderSave(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();
        $result = $data['result']; //object
        $form_name = $data['name'];

        if ($form_name == "" || $form_name == null) {
            $rand = \mt_rand(0, 999999);
            $form_name = 'KpForm'.$rand;
        }
        $slug = Str::slug($form_name);
        
        if (FormHolder::where(['slug' => $slug])->get()->count() > 0) {
            return response()->json([
                'data'=>'error',
            ]);
        } else {

            foreach ($result as $key => $val) {
                // $className[] = $val['type'];
                if ($val['name'] == 'package') {
                    $packages[] = $val;
                }
                if ($val['name'] !== 'package') {
                    $contacts[] = $val;
                }
            }
    
            $formHolder = new FormHolder();
            $formHolder->name = $form_name;
            $formHolder->contact = serialize($contacts);
            $formHolder->package = serialize($packages);
            $formHolder->created_by = $authUser->id;
            $formHolder->status = 'true';
            $formHolder->save();
    
            //save Order
            $order = new Order();
            $order->form_holder_id = $formHolder->id;
            $order->source_type = 'form_holder_module';
            $order->status = 'new';
            $order->save();
    
            //outgoingStock, in place of orderProduct
            $product_ids = [];
            foreach ($packages as $package) {
                foreach ($package['values'] as $key => $option) {
                    $product = Product::where('code', $option['value'])->first();
                    $product_ids[] = $product->id;
                    $outgoingStock = new OutgoingStock();
                    $outgoingStock->product_id = $product->id;
                    $outgoingStock->order_id = $order->id;
                    $outgoingStock->quantity_removed = 1;
                    $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
                    $outgoingStock->reason_removed = 'as_order_firstphase'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
                    $outgoingStock->quantity_returned = 0; //by default
                    $outgoingStock->created_by = $authUser->id;
                    $outgoingStock->status = 'true';
                    $outgoingStock->save();
                }
                
            }
    
            //update formHolder
            $formHolder->update(['order_id'=>$order->id]);
            $order->update(['products'=>serialize($product_ids)]);
            
            $data['package'] = $package;
            $data['contacts'] = $contacts;
            $data['form_name'] = $form_name;
    
            return response()->json([
                // 'unique_key'=>$unique_key,
                // 'grand_total'=>$grand_total,
                'data'=>$data,
            ]);

        }

    }
    
    public function allFormBuilders()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolds = FormHolder::orderBy('id', 'DESC')->get();
        $formHolders = [];
        foreach ($formHolds as $key => $formHolder) {
            $formHolder['contact'] = \unserialize($formHolder->contact);
            $formHolder['package'] = \unserialize($formHolder->package);
            $formHolders[] = $formHolder;
        }
        // return $formHolders;

        $products = Product::where('status', 'true')->get();
        
        return view('pages.allFormBuilders', compact('authUser', 'user_role', 'formHolders', 'products'));
    }

    public function addOrderbumpToForm(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'orderbump_product' => 'required',
        ]);

        $data = $request->all();
        $formHolder = FormHolder::where('unique_key', $data['form_unique_key'])->first();
        if (!isset($formHolder)) {
            abort(404);
        }

        if (!empty($data['orderbump_subheading'])) {
            $orderbump_subheading = serialize(array_filter($data['orderbump_subheading'], fn($value) => !is_null($value) && $value !== ''));
        } else {
            $orderbump_subheading = serialize(['It\'s an Amazing Offer']);
        }

        $product = Product::find($data['orderbump_product']);
        
        //orderbump
        $orderbump = new OrderBump();
        $orderbump->orderbump_heading = !empty($data['orderbump_heading']) ? $data['orderbump_heading'] : 'Would You Like to Add this Package to your Order';
        $orderbump->orderbump_subheading = $orderbump_subheading;
        $orderbump->product_id = $data['orderbump_product'];
        $orderbump->order_id = isset($formHolder->order) ? $formHolder->order->id : null;
        $orderbump->product_expected_quantity_to_be_sold = 1;
        $orderbump->product_expected_amount = 0;
        $orderbump->product_actual_selling_price = $product->sale_price;
        $orderbump->product_assumed_selling_price = $product->sale_price + 500;
        // $outgoingStock->created_by = $authUser->id;
        $orderbump->status = 'true';
        $orderbump->save();

        $product = Product::where('id', $data['orderbump_product'])->first();
        
        //outgoing stock
        // $outgoingStock = new OutgoingStock();
        // $outgoingStock->product_id = $data['orderbump_product'];
        // $outgoingStock->order_id = isset($formHolder->order_id) ? $formHolder->order->id : null;
        // $outgoingStock->quantity_removed = 1;
        // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
        // $outgoingStock->reason_removed = 'as_orderbump'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
        // $outgoingStock->quantity_returned = 0; //by default
        // $outgoingStock->created_by = $authUser->id;
        // $outgoingStock->status = 'true';
        // $outgoingStock->save();

        //$package_bundle = [];
        // Create a new package array for each product ID
        $package_bundles = [
            'product_id'=>$data['orderbump_product'],
            'quantity_removed'=>1,
            'amount_accrued'=>$product->sale_price,
            'customer_acceptance_status'=>null,
            'reason_removed'=>'as_orderbump',
            'quantity_returned'=>0,
            'reason_returned'=>null,
            'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
        ];
        // $package_bundle[] = $package_bundles;
        $orderPackageBundle = $formHolder->order->outgoingStock->package_bundle;

        //push to existing array
        array_push($orderPackageBundle,$package_bundles);

        //update existing OutgoingStock row
        $outgoingStock = OutgoingStock::where('order_id', $formHolder->order->id)->first();
        $outgoingStock->order_id = isset($formHolder->order_id) ? $formHolder->order->id : null;
        $outgoingStock->package_bundle = $orderPackageBundle;
        $outgoingStock->created_by = $authUser->id;
        $outgoingStock->status = 'true';
        $outgoingStock->save();

        //update formHolder
        $formHolder->update(['orderbump_id'=>$orderbump->id]);

        return back()->with('success', 'Order bump Added Successfully');
    }

    public function editOrderbumpToForm(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'orderbump_product' => 'required',
        ]);
        $data = $request->all();
        $formHolder = FormHolder::where('unique_key', $data['editOrderbump_form_unique_key'])->first();
        if (!isset($formHolder)) {
            abort(404);
        }
        
        $orderbump = OrderBump::where('id', $formHolder->orderbump->id)->first();
        if ($data['switch_orderbump'] == 'off') {
            $orderbump->delete();
            $formHolder->update(['orderbump_id'=>null]);
            return back()->with('success', 'OrderBump Removed Successfully');
        }
        
        if (!empty($data['orderbump_subheading'])) {
            $orderbump_subheading = array_filter($data['orderbump_subheading'], fn($value) => !is_null($value) && $value !== '');
        }

        //orderbump
        $orderbump->orderbump_heading = !empty($data['orderbump_heading']) ? $data['orderbump_heading'] : 'Would You Like to Add this Package to your Order';
        $orderbump->orderbump_subheading = !empty($data['orderbump_subheading']) ? serialize($orderbump_subheading) : serialize(['It\'s an Amazing Offer']);
        $orderbump->product_id = $data['orderbump_product'];
        $orderbump->order_id = $formHolder->order->id;
        $orderbump->product_expected_quantity_to_be_sold = 1;
        $orderbump->product_expected_amount = 0;
        $orderbump->product_assumed_selling_price = $data['product_assumed_selling_price'];
        // $outgoingStock->created_by = $authUser->id;
        $orderbump->status = 'true';
        $orderbump->save();

        $product = Product::where('id', $data['orderbump_product'])->first();
        
        //outgoing stock
        // $outgoingStock = OutgoingStock::where('order_id', $formHolder->order->id)->where('reason_removed', 'as_orderbump')->first();
        // $outgoingStock->product_id = $data['orderbump_product'];
        // $outgoingStock->order_id = $formHolder->order->id;
        // $outgoingStock->quantity_removed = 1;
        // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
        // $outgoingStock->reason_removed = 'as_orderbump'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
        // $outgoingStock->quantity_returned = 0; //by default
        // $outgoingStock->created_by = $authUser->id;
        // $outgoingStock->status = 'true';
        // $outgoingStock->save();
        
        //now update each row package_bundle
        $outgoingStockPackageBundle = OutgoingStock::where('order_id', $formHolder->order->id)->first()->package_bundle;

        //loop to get new copy of $outgoingStockPackageBundle array
        foreach ($outgoingStockPackageBundle as $key => $value) {
            // Update values with similar keys
            if ( !empty($value['reason_removed']) && $value['reason_removed'] == 'as_orderbump' ) {
                // Merge the data from $package_bundle_1 into the $outgoingStockPackageBundle
                $outgoingStockPackageBundle[$key]['product_id'] = $data['orderbump_product'];
                $outgoingStockPackageBundle[$key]['quantity_removed'] = 1;
                $outgoingStockPackageBundle[$key]['amount_accrued'] = $product->sale_price;
                $outgoingStockPackageBundle[$key]['customer_acceptance_status'] = null;
                $outgoingStockPackageBundle[$key]['reason_removed'] = 'as_orderbump';
                $outgoingStockPackageBundle[$key]['quantity_returned'] = 0;
                $outgoingStockPackageBundle[$key]['reason_returned'] = null;
                $outgoingStockPackageBundle[$key]['isCombo'] = isset($product->combo_product_ids) ? 'true' : null;
            }
        }

        //pudate db column with new copy of $outgoingStockPackageBundle
        OutgoingStock::where('order_id', $formHolder->order->id)->update([
            'package_bundle' => $outgoingStockPackageBundle,
            'created_by' => $authUser->id,
            'status' => 'true',
        ]);
        ////////////////////////////////////////////////////

        //update formHolder
        $formHolder->update(['orderbump_id'=>$orderbump->id]);

        return back()->with('success', 'OrderBump Updated Added Successfully');
    }

    public function addUpsellToForm(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'upsell_product' => 'required',
        ]);

        $data = $request->all();
        $formHolder = FormHolder::where('unique_key', $data['addUpsell_form_unique_key'])->first();
        if (!isset($formHolder)) {
            abort(404);
        }
        
        $templ = UpsellSetting::where('id', $data['upsell_setting_id'])->first();
        
        //upsell
        $upsell = new UpSell();
        $upsell->upsell_heading = !empty($data['upsell_heading']) ? $data['upsell_heading'] : $templ->heading_text;
        $upsell->upsell_subheading = !empty($data['upsell_subheading']) ? $data['upsell_subheading'] : serialize($templ->subheading_text);
        $upsell->upsell_setting_id = $data['upsell_setting_id'];
        $upsell->product_id = $data['upsell_product'];
        $upsell->order_id = $formHolder->order->id;
        $upsell->product_expected_quantity_to_be_sold = 1;
        $upsell->product_expected_amount = 0;
        // $upsell->created_by = $authUser->id;
        $upsell->status = 'true';
        $upsell->save();

        $product = Product::where('id', $data['upsell_product'])->first();
        
        //outgoing stock
        // $outgoingStock = new OutgoingStock();
        // $outgoingStock->product_id = $data['upsell_product'];
        // $outgoingStock->order_id = $formHolder->order->id;
        // $outgoingStock->quantity_removed = 1;
        // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
        // $outgoingStock->reason_removed = 'as_upsell'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
        // $outgoingStock->quantity_returned = 0; //by default
        // $outgoingStock->created_by = $authUser->id;
        // $outgoingStock->status = 'true';
        // $outgoingStock->save();

        //$package_bundle = [];
        // Create a new package array for each product ID
        $package_bundles = [
            'product_id'=>$data['upsell_product'],
            'quantity_removed'=>1,
            'amount_accrued'=>$product->sale_price,
            'customer_acceptance_status'=>null,
            'reason_removed'=>'as_upsell',
            'quantity_returned'=>0,
            'reason_returned'=>null,
            'isCombo'=>isset($product->combo_product_ids) ? 'true' : null,
        ];
        // $package_bundle[] = $package_bundles;
        $orderPackageBundle = $formHolder->order->outgoingStock->package_bundle;
        array_push($orderPackageBundle,$package_bundles);

        //update existing OutgoingStock row
        $outgoingStock = OutgoingStock::where('order_id', $formHolder->order->id)->first();
        $outgoingStock->order_id = isset($formHolder->order_id) ? $formHolder->order->id : null;
        $outgoingStock->package_bundle = $orderPackageBundle;
        $outgoingStock->created_by = $authUser->id;
        $outgoingStock->status = 'true';
        $outgoingStock->save();

        //update formHolder
        $formHolder->update(['upsell_id'=>$upsell->id]);

        return back()->with('success', 'UpSell Added Successfully');
    }

    public function editUpsellToForm(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'upsell_product' => 'required',
        ]);
        $data = $request->all();
        $formHolder = FormHolder::where('unique_key', $data['editUpsell_form_unique_key'])->first();
        if (!isset($formHolder)) {
            abort(404);
        }
        $data = $request->all();

        $upsell = UpSell::where('id', $formHolder->upsell->id)->first();
        if ($data['switch_upsell'] == 'off') {
            $upsell->delete();
            $formHolder->update(['upsell_id'=>null]);
            return back()->with('success', 'Upsell Removed Successfully');
        }
        //upsell
        
        $upsell->upsell_heading = !empty($data['upsell_heading']) ? $data['upsell_heading'] : 'Wait, One More Chance';
        $upsell->upsell_subheading = !empty($data['upsell_subheading']) ? $data['upsell_subheading'] : 'We\'re giving this at a giveaway price';
        $upsell->upsell_setting_id = $data['upsell_setting_id'];
        $upsell->product_id = $data['upsell_product'];
        $upsell->order_id = $formHolder->order->id;
        $upsell->product_expected_quantity_to_be_sold = 1;
        $upsell->product_expected_amount = 0;
        // $upsell->created_by = $authUser->id;
        $upsell->status = 'true';
        $upsell->save();

        $product = Product::where('id', $data['upsell_product'])->first();
        
        //outgoing stock
        // $outgoingStock = OutgoingStock::where('order_id', $formHolder->order->id)->where('reason_removed', 'as_upsell')->first();
        // $outgoingStock->product_id = $data['upsell_product'];
        // $outgoingStock->order_id = $formHolder->order->id;
        // $outgoingStock->quantity_removed = 1;
        // $outgoingStock->amount_accrued = $product->sale_price; //since qty is always one
        // $outgoingStock->reason_removed = 'as_upsell'; //as_order_firstphase, as_orderbump, as_upsell as_expired, as_damaged,
        // $outgoingStock->quantity_returned = 0; //by default
        // $outgoingStock->created_by = $authUser->id;
        // $outgoingStock->status = 'true';
        // $outgoingStock->save();
        
        //now update each row package_bundle
        $outgoingStockPackageBundle = OutgoingStock::where('order_id', $formHolder->order->id)->first()->package_bundle;

        //loop to get new copy of $outgoingStockPackageBundle array
        foreach ($outgoingStockPackageBundle as $key => $value) {
            // Update values with similar keys
            if ( !empty($value['reason_removed']) && $value['reason_removed'] == 'as_upsell' ) {
                // Merge the data from $package_bundle_1 into the $outgoingStockPackageBundle
                $outgoingStockPackageBundle[$key]['product_id'] = $data['upsell_product'];
                $outgoingStockPackageBundle[$key]['quantity_removed'] = 1;
                $outgoingStockPackageBundle[$key]['amount_accrued'] = $product->sale_price;
                $outgoingStockPackageBundle[$key]['customer_acceptance_status'] = null;
                $outgoingStockPackageBundle[$key]['reason_removed'] = 'as_upsell';
                $outgoingStockPackageBundle[$key]['quantity_returned'] = 0;
                $outgoingStockPackageBundle[$key]['reason_returned'] = null;
                $outgoingStockPackageBundle[$key]['isCombo'] = isset($product->combo_product_ids) ? 'true' : null;
            }
        }

        //pudate db column with new copy of $outgoingStockPackageBundle
        OutgoingStock::where('order_id', $formHolder->order->id)->update([
            'package_bundle' => $outgoingStockPackageBundle,
            'created_by' => $authUser->id,
            'status' => 'true',
        ]);
        ////////////////////////////////////////////////////

        //update formHolder
        $formHolder->update(['upsell_id'=>$upsell->id]);

        return back()->with('success', 'UpSell Updated Successfully');
    }

    //addThankYouTemplateToForm
    public function addThankYouTemplateToForm(Request $request)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'thankyou_template_id' => 'required',
        ]);

        $data = $request->all();
        $formHolder = FormHolder::where('unique_key', $data['addThankYou_form_unique_key'])->first();
        if (!isset($formHolder)) {
            abort(404);
        }
        $order = $formHolder->order;

        //updating thankyou templ with current form
        $thankyou = ThankYou::find($data['thankyou_template_id']);
        //$embedded_url = url('/').'/thankYou-embedded/'.$thankyou->unique_key.'/'.$order->id;
        $embedded_url = url('/').'/thankYou-embedded/'.$thankyou->unique_key;
        //$thankyou->url = 'view-thankyou-templates/'.$thankyou->unique_key.'/'.$order->id;
        $thankyou->url = 'view-thankyou-templates/'.$thankyou->unique_key;
        $thankyou->embedded_tag = '<embed type="text/html" src="'.$embedded_url.'"  width="100%" height="700">';
        $thankyou->iframe_tag = '<iframe src="'.$embedded_url.'" width="100%" height="700" style="border:0"></iframe>';
        $thankyou->template_external_url = $data['template_external_url'];
        $thankyou->current_order_id = $order->id;
        $thankyou->save();
    
        //update formHolder
        $formHolder->update(['thankyou_id'=>$data['thankyou_template_id']]);

        return back()->with('success', 'ThankYou Template Added Successfully');
    }

    public function formEmbedded($unique_key, $current_order_id="", $stage="")
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            \abort(404);
        }
        if ($current_order_id !== "") {
            $order = Order::where('id', $current_order_id)->first();
            if (!isset($order)) {
                \abort(404);
            }
            // $order->outgoingStocks()->where(['customer_acceptance_status'=>NULL])
            // ->update(['customer_acceptance_status'=>'rejected', 'quantity_returned'=>1, 'reason_returned'=>'declined']);

            /////////////////////////
            $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
            $package_bundle_1 = [];

            // Loop through the $outgoingStockPackageBundle array with access to keys
            foreach ($outgoingStockPackageBundle as $key => $value) {
                if ( empty($value['customer_acceptance_status']) ) {
                    // Merge the data from $package_bundle_1 into the $outgoingStockPackageBundle
                    $outgoingStockPackageBundle[$key]['customer_acceptance_status'] = 'rejected';
                    $outgoingStockPackageBundle[$key]['reason_removed'] = 'as_order_firstphase';
                    $outgoingStockPackageBundle[$key]['quantity_returned'] = 1;
                    $outgoingStockPackageBundle[$key]['reason_returned'] = 'declined';
                }
            }

            //pudate db column with new copy of $outgoingStockPackageBundle
            $order->outgoingStock()->update(['package_bundle' => $outgoingStockPackageBundle]);
            ////////////////////////
        } else {
            $order = $formHolder->order;
        }
        
        $stage="";

        $authUser = User::find(1);

        $customer_ip_address = \Request::ip();
        $existingCart = CartAbandon::where(['order_id'=>$order->id, 'form_holder_id'=>$formHolder->id, 'customer_ip_address'=>$customer_ip_address])->first();
        if (isset($existingCart)) {
            $cartAbandoned_id = $existingCart->id;
        } else {
            //create new cart-abandoned, for this process
            $cartAbandoned = new CartAbandon();
            $cartAbandoned->order_id = $order->id;
            $cartAbandoned->form_holder_id = $formHolder->id;
            $cartAbandoned->customer_delivery_duration = 1;
            $cartAbandoned->customer_ip_address = $customer_ip_address;
            $cartAbandoned->save();
            $cartAbandoned_id = $cartAbandoned->id;
        }

        $formName = $formHolder->name;
        $formData = \unserialize($formHolder->form_data);
        
        $formContactInfo = [];
        $formPackage = [];
        $form_names = $formData['form_names'];
        $form_labels = $formData['form_labels'];
        $form_types = $formData['form_types'];
        $packages = $formData['packages'];

        //cos form_names are not determind by staff in form-building
        foreach ( $form_names as $key => $form_name ) {
            //$formContact[Str::slug($form_name)] = [ Str::slug($form_name), $form_labels[$key], $form_types[$key] ];
            $formContactInfo['form_name'] = Str::slug($form_name);
            $formContactInfo['form_label'] = $form_labels[$key];
            $formContactInfo['form_type'] = $form_types[$key];
            $formContact[] = $formContactInfo;
        }

        //extract products
        foreach ($formContact as $key => $formProduct) {
            if ($formProduct['form_name'] == Str::slug('Product Package')) {
                $package_form_name = $formProduct['form_name'];
                $package_form_label = $formProduct['form_label'];
                $package_form_type = $formProduct['form_type'];
            }
        }
        
        //products package
        foreach ($packages as $key => $package) {
            $product = Product::where('id', $package)->first();
            $formPackage['id'] = $package; //product_id
            $formPackage['name'] = $product->name;
            $formPackage['combo_product_ids'] = isset($product->combo_product_ids) ? true : false;
            $formPackage['price'] = $product->sale_price;
            $formPackage['stock_available'] = $product->stock_available();
            $formPackage['form_name'] = Str::slug($package_form_name);
            $formPackage['form_label'] = $package_form_label;
            $formPackage['form_type'] = $package_form_type;
            $products[] = $formPackage;
        }
        //name, labels, type, in dat order
      
        //for thankyou part
        $order = $formHolder->order;
        $mainProduct_revenue = 0;  //price * qty
        $qty_main_product = 0;
        // $mainProducts_outgoingStocks = $order->outgoingStocks()->where(['reason_removed'=>'as_order_firstphase',
        // 'customer_acceptance_status'=>'accepted'])->get();

        // if ( count($mainProducts_outgoingStocks) > 0 ) {
        //     foreach ($mainProducts_outgoingStocks as $key => $main_outgoingStock) {
        //         if(isset($main_outgoingStock->product)) {
        //             $mainProduct_revenue = $mainProduct_revenue + ($main_outgoingStock->product->sale_price * $main_outgoingStock->quantity_removed);
        //         } 
        //     }
        // }

        ////////////////
        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        foreach ($outgoingStockPackageBundle as $key=>&$main_outgoingStock) {
            
            if ( ($main_outgoingStock['reason_removed'] == 'as_order_firstphase') && ($main_outgoingStock['customer_acceptance_status'] == 'accepted') ) {
                 $product = Product::where('id', $main_outgoingStock['product_id'])->first();
                 if (isset($product)) {
                    //array_push($mainProducts_outgoingStocks, array('product' => $product)); 
                    $main_outgoingStock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                    $mainProduct_revenue = $mainProduct_revenue + ($product->sale_price * $main_outgoingStock['quantity_removed']);
                    $qty_main_product += $main_outgoingStock['quantity_removed'];
                 }
            } else {
                // Remove the element from the array if the condition is not met
                unset($outgoingStockPackageBundle[$key]);
            }
        }
        //convert to array to array-of-object
        $mainProducts_outgoingStocks = $mainProduct_revenue > 0 ? json_decode(json_encode($outgoingStockPackageBundle)) : collect([]);
        ////////////////

        //orderbump
        $orderbumpProduct_revenue = 0; //price * qty
        $orderbump_outgoingStock = '';
        $qty_orderbump = 0;
        // if (isset($formHolder->orderbump_id)) {
        //     $orderbump_outgoingStock = $order->outgoingStocks()->where('reason_removed', 'as_orderbump')->first();
        //     if (isset($orderbump_outgoingStock->product) && $orderbump_outgoingStock->customer_acceptance_status == 'accepted') {
        //         $orderbumpProduct_revenue = $orderbumpProduct_revenue + ($orderbump_outgoingStock->product->sale_price * $orderbump_outgoingStock->quantity_removed); 
        //     }
        // }

        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        
        if (isset($formHolder->orderbump_id)) {
            foreach ($outgoingStockPackageBundle as $key => &$orderbump_stock) {
                if ( ($orderbump_stock['reason_removed'] == 'as_orderbump') && ($orderbump_stock['customer_acceptance_status'] == 'accepted') ) {
                    $product = Product::where('id', $orderbump_stock['product_id'])->first();
                     if (isset($product)) {
                        $orderbump_stock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                        $orderbumpProduct_revenue = $orderbumpProduct_revenue + ($product->sale_price * $orderbump_stock['quantity_removed']);
                        $qty_orderbump += $orderbump_stock['quantity_removed'];
                     }
                } else {
                    // Remove the element from the array if the condition is not met
                    unset($outgoingStockPackageBundle[$key]);
                }
            }
        }

        //array_values to re-index the array numerically
        //array_merge to remove block brackets
        //json_encode & json_decode to allow $x->y in view
        $orderbump_outgoingStock = $orderbumpProduct_revenue > 0 ? json_decode(json_encode(array_merge(...array_values($outgoingStockPackageBundle)))) : '';

        //upsell
        $upsellProduct_revenue = 0; //price * qty
        $upsell_outgoingStock = '';
        $qty_upsell = 0;
        // if (isset($formHolder->upsell_id)) {
        //     $upsell_outgoingStock = $order->outgoingStocks()->where('reason_removed', 'as_upsell')->first();
        //     if (isset($upsell_outgoingStock->product) && $upsell_outgoingStock->customer_acceptance_status == 'accepted') {
        //         $upsellProduct_revenue += $upsellProduct_revenue + ($upsell_outgoingStock->product->sale_price * $upsell_outgoingStock->quantity_removed);
        //     }
        // }

        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        
        if (isset($formHolder->upsell_id)) {
            foreach ($outgoingStockPackageBundle as $key => &$upsell_stock) {
                if ( ($upsell_stock['reason_removed'] == 'as_upsell') && ($upsell_stock['customer_acceptance_status'] == 'accepted') ) {
                    $product = Product::where('id', $upsell_stock['product_id'])->first();
                     if (isset($product)) {
                        $upsell_stock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                        $upsellProduct_revenue = $upsellProduct_revenue + ($product->sale_price * $upsell_stock['quantity_removed']);
                        $qty_upsell += $upsell_stock['quantity_removed'];
                     }
                } else {
                    // Remove the element from the array if the condition is not met
                    unset($outgoingStockPackageBundle[$key]);
                }
            }
        }
        $upsell_outgoingStock = $upsellProduct_revenue > 0 ? json_decode(json_encode(array_merge(...array_values($outgoingStockPackageBundle)))) : '';
        
        //order total amt
        $order_total_amount = $mainProduct_revenue + $orderbumpProduct_revenue + $upsellProduct_revenue;
        $grand_total = $order_total_amount; //might include discount later
        
        $orderId = ''; //used in thankYou section
        if ($order->id < 10){
            $orderId = '0000'.$order->id;
        }
        // <!-- > 10 < 100 -->
        if (($order->id > 10) && ($order->id < 100)) {
            $orderId = '000'.$order->id;
        }
        // <!-- > 100 < 1000 -->
        if (($order->id) > 100 && ($order->id < 1000)) {
            $orderId = '00'.$order->id;
        }
        // <!-- > 1000 < 10000++ -->
        if (($order->id) > 1000 && ($order->id < 1000)) {
            $orderId = '0'.$order->id;
        }

        //package or product qty. sum = 0, if it doesnt exist
        // $qty_main_product = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_order_firstphase'])->sum('quantity_removed');
        // $qty_orderbump = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_orderbump'])->sum('quantity_removed');
        // $qty_upsell = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_upsell'])->sum('quantity_removed');
        $qty_total = $qty_main_product + $qty_orderbump + $qty_upsell;

        //end thankyou part

        $customer = ''; $invoiceData = [];
        if (isset($order->customer)) {
            //customer
            $customer =  $order->customer;

            $receipients = Arr::collapse([[$authUser->email],[$customer->email]]);

            // event(new TestEvent($invoiceData));
            $this->invoiceData($formHolder, $customer, $order);
        }
        
        return view('pages.formEmbedded', compact('authUser', 'unique_key', 'formHolder', 'formName', 'formContact', 'formPackage', 'products',
        'mainProducts_outgoingStocks', 'order', 'orderId', 'mainProduct_revenue', 'orderbump_outgoingStock', 'orderbumpProduct_revenue', 'upsell_outgoingStock',
        'upsellProduct_revenue', 'customer', 'qty_total', 'order_total_amount', 'grand_total', 'stage', 'cartAbandoned_id'));
    }

    //like single newFormBuilder
    public function newFormLink($unique_key, $current_order_id="", $stage="")
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        
        // $entries_count = 0;
        // foreach ($formHolder->formHolders as $key => $formHolder) {
        //     if (isset($formHolder->order->customer_id)) {
        //         $entries_count += 1;
        //     }
        // }
        // if (isset($formHolder->order->customer_id)) {
        //     $entries_count += 1;
        // }
        
        if (!isset($formHolder)) {
            \abort(404);
        }

        if ($current_order_id !== "") {
            $order = Order::where('id', $current_order_id)->first();
            if (!isset($order)) {
                \abort(404);
            }
            // $order->outgoingStocks()->where(['customer_acceptance_status'=>NULL])
            // ->update(['customer_acceptance_status'=>'rejected', 'quantity_returned'=>1, 'reason_returned'=>'declined']);

            $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
            $package_bundle_1 = [];

            // Loop through the $outgoingStockPackageBundle array with access to keys
            foreach ($outgoingStockPackageBundle as $key => $value) {
                if ( empty($value['customer_acceptance_status']) ) {
                    // Merge the data from $package_bundle_1 into the $outgoingStockPackageBundle
                    $outgoingStockPackageBundle[$key]['customer_acceptance_status'] = 'rejected';
                    $outgoingStockPackageBundle[$key]['reason_removed'] = 'as_order_firstphase';
                    $outgoingStockPackageBundle[$key]['quantity_returned'] = 1;
                    $outgoingStockPackageBundle[$key]['reason_returned'] = 'declined';
                }
            }

            //pudate db column with new copy of $outgoingStockPackageBundle
            $order->outgoingStock()->update(['package_bundle' => $outgoingStockPackageBundle]);
            
        } else {
            $order = $formHolder->order;
        }

        $authUser = User::find(1);

        if (!isset($order)) {
            \abort(404);
        }

        $customer_ip_address = \Request::ip();
        $existingCart = CartAbandon::where(['order_id'=>$order->id, 'form_holder_id'=>$formHolder->id, 'customer_ip_address'=>$customer_ip_address])->first();
        if (isset($existingCart)) {
            $cartAbandoned_id = $existingCart->id;
        } else {
            //create new cart-abandoned, for this process
            $cartAbandoned = new CartAbandon();
            $cartAbandoned->order_id = $order->id;
            $cartAbandoned->form_holder_id = $formHolder->id;
            $cartAbandoned->customer_delivery_duration = 1;
            $cartAbandoned->customer_ip_address = $customer_ip_address;
            $cartAbandoned->save();
            $cartAbandoned_id = $cartAbandoned->id;
        }

        $formName = $formHolder->name;
        $formData = \unserialize($formHolder->form_data);
        
        $formContactInfo = [];
        $formPackage = [];
        $form_names = $formData['form_names'];
        $form_labels = $formData['form_labels'];
        $form_types = $formData['form_types'];
        $packages = $formData['packages'];

        //cos form_names are not determind by staff in form-building
        foreach ( $form_names as $key => $form_name ) {
            //$formContact[Str::slug($form_name)] = [ Str::slug($form_name), $form_labels[$key], $form_types[$key] ];
            $formContactInfo['form_name'] = Str::slug($form_name);
            $formContactInfo['form_label'] = $form_labels[$key];
            $formContactInfo['form_type'] = $form_types[$key];
            $formContact[] = $formContactInfo;
        }

        //extract products
        foreach ($formContact as $key => $formProduct) {
            if ($formProduct['form_name'] == Str::slug('Product Package')) {
                $package_form_name = $formProduct['form_name'];
                $package_form_label = $formProduct['form_label'];
                $package_form_type = $formProduct['form_type'];
            }
        }
        
        //products package
        foreach ($packages as $key => $package) {
            $product = Product::where('id', $package)->first();
            $formPackage['id'] = $package; //product_id
            $formPackage['name'] = $product->name;
            $formPackage['combo_product_ids'] = isset($product->combo_product_ids) ? true : false;
            $formPackage['price'] = $product->sale_price;
            $formPackage['stock_available'] = $product->stock_available();
            $formPackage['form_name'] = Str::slug($package_form_name);
            $formPackage['form_label'] = $package_form_label;
            $formPackage['form_type'] = $package_form_type;
            $products[] = $formPackage;
        }
        //name, labels, type, in dat order
      
        //for thankyou part
        // $order = $formHolder->order;
        $mainProduct_revenue = 0;  //price * qty
        $qty_main_product = 0;
        // $mainProducts_outgoingStocks = $order->outgoingStocks()->where(['reason_removed'=>'as_order_firstphase',
        // 'customer_acceptance_status'=>'accepted'])->get();

        // if ( count($mainProducts_outgoingStocks) > 0 ) {
        //     foreach ($mainProducts_outgoingStocks as $key => $main_outgoingStock) {
        //         if(isset($main_outgoingStock->product)) {
        //             $mainProduct_revenue = $mainProduct_revenue + ($main_outgoingStock->product->sale_price * $main_outgoingStock->quantity_removed);
        //         } 
        //     }
        // }
        // return $order->outgoingStock->id;
        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        foreach ($outgoingStockPackageBundle as $key=>&$main_outgoingStock) {
            
            if ( ($main_outgoingStock['reason_removed'] == 'as_order_firstphase') && ($main_outgoingStock['customer_acceptance_status'] == 'accepted') ) {
                 $product = Product::where('id', $main_outgoingStock['product_id'])->first();
                 if (isset($product)) {
                    //array_push($mainProducts_outgoingStocks, array('product' => $product)); 
                    $main_outgoingStock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                    $mainProduct_revenue = $mainProduct_revenue + ($product->sale_price * $main_outgoingStock['quantity_removed']);
                    $qty_main_product += $main_outgoingStock['quantity_removed'];
                 }
            } else {
                // Remove the element from the array if the condition is not met
                unset($outgoingStockPackageBundle[$key]);
            }
        }
        //converting array immediate & nested(product) array to object. json_decode alone will not do it.
        // if ($mainProduct_revenue > 0) {
        //     $mainProducts_outgoingStocks = array_map(function ($item) {
        //         return (object) $item;
        //     }, $outgoingStockPackageBundle);
        // } else {
        //     $mainProducts_outgoingStocks = collect([]);
        // }
        
        //convert to array to array-of-object
        $mainProducts_outgoingStocks = $mainProduct_revenue > 0 ? json_decode(json_encode($outgoingStockPackageBundle)) : collect([]);
        //$mainProducts_outgoingStocks = $mainProduct_revenue > 0 ? $outgoingStockPackageBundle : collect([]);

        //orderbump
        $orderbumpProduct_revenue = 0; //price * qty
        $orderbump_outgoingStock = '';
        $qty_orderbump = 0;
        // if (isset($formHolder->orderbump_id)) {
        //     $orderbump_outgoingStock = $order->outgoingStocks()->where('reason_removed', 'as_orderbump')->first();
        //     if (isset($orderbump_outgoingStock->product) && $orderbump_outgoingStock->customer_acceptance_status == 'accepted') {
        //         $orderbumpProduct_revenue = $orderbumpProduct_revenue + ($orderbump_outgoingStock->product->sale_price * $orderbump_outgoingStock->quantity_removed);
        //     }
        // }

        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        
        if (isset($formHolder->orderbump_id)) {
            foreach ($outgoingStockPackageBundle as $key => &$orderbump_stock) {
                if ( ($orderbump_stock['reason_removed'] == 'as_orderbump') && ($orderbump_stock['customer_acceptance_status'] == 'accepted') ) {
                    $product = Product::where('id', $orderbump_stock['product_id'])->first();
                     if (isset($product)) {
                        $orderbump_stock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                        $orderbumpProduct_revenue = $orderbumpProduct_revenue + ($product->sale_price * $orderbump_stock['quantity_removed']);
                        $qty_orderbump += $orderbump_stock['quantity_removed'];
                     }
                } else {
                    // Remove the element from the array if the condition is not met
                    unset($outgoingStockPackageBundle[$key]);
                }
            }
        }
        //array_values to re-index the array numerically
        //array_merge to remove block brackets
        //json_encode & json_decode to allow $x->y in view
        $orderbump_outgoingStock = $orderbumpProduct_revenue > 0 ? json_decode(json_encode(array_merge(...array_values($outgoingStockPackageBundle)))) : '';

        //upsell
        $upsellProduct_revenue = 0; //price * qty
        $upsell_outgoingStock = '';
        $qty_upsell = 0;
        // if (isset($formHolder->upsell_id)) {
        //     $upsell_outgoingStock = $order->outgoingStocks()->where('reason_removed', 'as_upsell')->first();
        //     if (isset($upsell_outgoingStock->product) && $upsell_outgoingStock->customer_acceptance_status == 'accepted') {
        //         $upsellProduct_revenue += $upsellProduct_revenue + ($upsell_outgoingStock->product->sale_price * $upsell_outgoingStock->quantity_removed);
        //     }
        // }

        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        
        if (isset($formHolder->upsell_id)) {
            foreach ($outgoingStockPackageBundle as $key => &$upsell_stock) {
                if ( ($upsell_stock['reason_removed'] == 'as_upsell') && ($upsell_stock['customer_acceptance_status'] == 'accepted') ) {
                    $product = Product::where('id', $upsell_stock['product_id'])->first();
                     if (isset($product)) {
                        $upsell_stock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                        $upsellProduct_revenue = $upsellProduct_revenue + ($product->sale_price * $upsell_stock['quantity_removed']);
                        $qty_upsell += $upsell_stock['quantity_removed'];
                     }
                } else {
                    // Remove the element from the array if the condition is not met
                    unset($outgoingStockPackageBundle[$key]);
                }
            }
        }
        $upsell_outgoingStock = $upsellProduct_revenue > 0 ? json_decode(json_encode(array_merge(...array_values($outgoingStockPackageBundle)))) : '';
        
        //order total amt
        $order_total_amount = $mainProduct_revenue + $orderbumpProduct_revenue + $upsellProduct_revenue;
        $grand_total = $order_total_amount; //might include discount later
        
        $orderId = ''; //used in thankYou section
        if (isset($order->id)) {
            $orderId = $order->orderId($order);
        }
        
        //package or product qty. sum = 0, if it doesnt exist
        // $qty_main_product = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_order_firstphase'])->sum('quantity_removed');
        // $qty_orderbump = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_orderbump'])->sum('quantity_removed');
        // $qty_upsell = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_upsell'])->sum('quantity_removed');
        $qty_total = $qty_main_product + $qty_orderbump + $qty_upsell;

        //end thankyou part

        $customer = ''; $invoiceData = [];
        if (isset($order->customer)) {
            //customer
            $customer =  $order->customer;

            $receipients = Arr::collapse([[$authUser->email],[$customer->email]]);

            // event(new TestEvent($invoiceData));
            $this->invoiceData($formHolder, $customer, $order);
        }

        return view('pages.newFormLink', compact('authUser', 'unique_key', 'formHolder', 'formName', 'formContact', 'formPackage', 'products',
        'mainProducts_outgoingStocks', 'order', 'orderId', 'mainProduct_revenue', 'orderbump_outgoingStock', 'orderbumpProduct_revenue', 'upsell_outgoingStock',
        'upsellProduct_revenue', 'customer', 'qty_total', 'order_total_amount', 'grand_total', 'stage', 'cartAbandoned_id'));
    }

    //newFormLinkPost///not-used///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function newFormLinkPost(Request $request, $unique_key, $current_order_id="")
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'first-name' => 'required',
            'last-name' => 'required',
            'phone-number' => 'required',
            'whatsapp-phone-number' => 'required',
            'active-email' => 'required|email',
            'state' => 'required',
            'city' => 'required',
            'address' => 'required',
            'delivery_duration' => 'required',
            'product_packages' => 'required',
        ]);

        if (!empty($request->orderbump_check)) {
            $request->validate([
                'product' => 'required',
            ]);
        } 
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            \abort(404);
        }

        $data = $request->all();
        $order = $formHolder->order;
        
        $existing_customer = Customer::where('order_id', $order->id)->count();
        //order already exist, and customer tries to submit
        if ($existing_customer > 0) {
            if ($request->upsell_available != '') {
                Session::put('upsell_stage', 'true');
                return back();
            } else {
                Session::put('thankyou_stage', 'true');
                return back();
            }
            // return back()->with('info', 'Order Already Taken. We will get back to you shortly');
        }

        //update package in OutgoingStock
        foreach ($data['package'] as $key => $code) {
            if(!empty($code)){
                //accepted updated
                $product_id = Product::where('code', $code)->first()->id;
                OutgoingStock::where(['product_id'=>$product_id, 'order_id'=>$order->id, 'reason_removed'=>'as_order_firstphase'])
                ->update(['customer_acceptance_status'=>'accepted']);
                
                //rejected or declined updated
                $rejected_products = OutgoingStock::where('product_id', '!=', $product_id)->where('order_id', $order->id)
                ->where('reason_removed','as_order_firstphase')->get();
                foreach ($rejected_products as $key => $rejected) {
                    $rejected->update(['customer_acceptance_status'=>'rejected', 'quantity_returned'=>$rejected->quantity_removed]);
                }
                
            } 
        }

        //accepted orderbump
        if (!empty($request->orderbump_check)) {
            //accepted updated
            $product_id = Product::where('id', $data['product'])->first()->id;
            OutgoingStock::where(['product_id'=>$product_id, 'order_id'=>$order->id, 'reason_removed'=>'as_orderbump'])
            ->update(['customer_acceptance_status'=>'accepted']);
        }

        $customer = new Customer();
        $customer->order_id = $order->id;
        $customer->form_holder_id = $formHolder->id;
        $customer->firstname = $data['first-name'];
        $customer->lastname = $data['last-name'];
        $customer->phone_number = $data['phone-number'];
        $customer->whatsapp_phone_number = $data['whatsapp-phone-number'];
        $customer->email = $data['email'];
        $customer->delivery_address = $data['delivery-address'];
        $customer->created_by = 1;
        $customer->status = 'true';
        $customer->save();

        //update order status
        $order->update(['customer_id'=>$customer->id, 'delivery_duration'=>$data['delivery_duration'], 'status'=>'new']);

        //to activate psell & thankyou part
        if ($request->upsell_available != '') {
            Session::put('upsell_stage', 'true');
        } else {
            Session::put('thankyou_stage', 'true');
        }
        
        //return back()->with('order-success', 'Saved Successfully');
        return back();

    }

    //after clicking first main btn, ajax
    public function saveNewFormFromCustomer(Request $request)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();

        //delete cartabandoned

        $cartAbandon = CartAbandon::where('id', $data['cartAbandoned_id']);
        if($cartAbandon->exists()) {
            $cartAbandon->delete();
        }
        
        $formHolder = FormHolder::where('unique_key', $data['unique_key'])->first();
        $order = $formHolder->order;

        //cos order was created initially @ newFormBuilderPost, incase the form originted from edited or duplicate form
        if (isset($order->customer_id)) {
    
            //save Order
            $newOrder = new Order();
            $newOrder->form_holder_id = $formHolder->id;
            $newOrder->source_type = 'form_holder_module';
            $newOrder->status = 'new';
            $newOrder->save();

            //making a copy from the former outgoingStocks, in the case of dealing with an edited or duplicated form
            // $outgoingStocks = $order->outgoingStocks;
            
            // foreach($outgoingStocks as $i => $outgoingStock)
            // {
            //     //make copy of rows, and create new records
            //     if(isset($outgoingStock->product)) {
            //         $outgoingStocks[$i]->order_id = $newOrder->id;
            //         $outgoingStocks[$i]->quantity_returned = 0;
            //         $outgoingStocks[$i]->quantity_removed = 1;
            //         $outgoingStocks[$i]->amount_accrued = $outgoingStock->product->sale_price;
            //         $outgoingStocks[$i]->isCombo = isset($outgoingStock->product->combo_product_ids) ? 'true' : null;
    
            //         $x[$i] = (new OutgoingStock())->create($outgoingStock->only(['product_id', 'order_id', 'quantity_removed', 'amount_accrued',
            //         'reason_removed', 'quantity_returned', 'created_by', 'status']));
            //     }
            // }
            // return $x;

            //////////////////////////////////////////////////////////
            //making a copy from the former outgoingStock, in the case of dealing with an edited or duplicated form
            $outgoingStockPackageBundleFormer = $order->outgoingStock->package_bundle;

            $package_bundle_1 = [];
            foreach ($outgoingStockPackageBundleFormer as $i => $outgoingStock) {
                $product = Product::find($outgoingStock['product_id']);
                // Make a copy of rows and create new records
                $outgoingStockData = [
                    'product_id' => $outgoingStock['product_id'],
                    'quantity_removed' => 1,
                    'amount_accrued' => $product->sale_price,
                    'customer_acceptance_status' => $outgoingStock['customer_acceptance_status'],
                    'reason_removed' => $outgoingStock['reason_removed'], //as_order_firstphase
                    'quantity_returned' => 0,
                    'reason_returned' => isset($outgoingStock['reason_removed']) ? $outgoingStock['reason_removed'] : null,
                    'isCombo' => isset($outgoingStock['isCombo']) ? 'true' : null,
                ];

                $package_bundle_1[] = $outgoingStockData;
            }

            // Create a new OutgoingStock record
            $newOutgoingStock = new OutgoingStock();
            $newOutgoingStock->order_id = $newOrder->id;
            $newOutgoingStock->created_by = $order->outgoingStock->created_by;
            $newOutgoingStock->status = $order->outgoingStock->status;
            $newOutgoingStock->package_bundle = $package_bundle_1;
            $newOutgoingStock->save();

            ///////////////////////////////////////////////////////////////
            
            //update package in OutgoingStock

            #remove later
            $outgoingStock = OutgoingStock::where('order_id', $newOrder->id)->first();
            $outgoingStockPackageBundle = $outgoingStock->package_bundle; //[{},{}]
            foreach ($data['product_packages'] as $key => $product_id) {
                if (!empty($product_id)) {
                    $idPriceQty = explode('-', $product_id);
                    $productId = $idPriceQty[0];
                    $saleUnitPrice = $idPriceQty[1];
                    $qtyRemoved = $idPriceQty[2];
        
                    // Accepted updated
                    $amount_accrued = $qtyRemoved * $saleUnitPrice;

                    foreach ($outgoingStockPackageBundle as &$stock) {
                        $product = Product::find($stock['product_id']);
                        if ($stock['product_id'] == (int) $productId && $stock['reason_removed'] == 'as_order_firstphase') {
                            $stock['quantity_removed'] = $qtyRemoved;
                            $stock['amount_accrued'] = $amount_accrued;
                            $stock['customer_acceptance_status'] = 'accepted';
                        }
                        // Rejected or declined updated
                        if ($stock['product_id'] !== (int) $productId && $stock['reason_removed'] == 'as_order_firstphase') {
                            $stock['customer_acceptance_status'] = 'rejected';
                            $stock['amount_accrued'] = $product->sale_price;
                            $stock['quantity_returned'] = $stock['quantity_removed'];
                            $stock['reason_returned'] = 'declined';
                        }
                    }
        
                }
            }
            #remove later

            #remove later
            $outgoingStock->update(['package_bundle'=>$outgoingStockPackageBundle]);
            #remove later

            /////////start old code/////////////////////////////////////////
            // $order = $order;
            // $package_bundle_1 = [];
            // //updated package in outgoingstock, created above
            // foreach ($data['product_packages'] as $key => $product_id) {
            //     $data['product_id'] = $product_id;
            //     if (!empty($product_id)) {

            //         $idPriceQty = explode('-', $product_id);
            //         $productId = $idPriceQty[0];
            //         $saleUnitPrice = $idPriceQty[1];
            //         $qtyRemoved = $idPriceQty[2];

            //         //accepted updated
            //         $amount_accrued = $qtyRemoved * $saleUnitPrice;
            //         // OutgoingStock::where(['product_id'=>$productId, 'order_id'=>$newOrder->id, 'reason_removed'=>'as_order_firstphase'])
            //         // ->update(['quantity_removed'=>$qtyRemoved, 'amount_accrued'=>$amount_accrued, 'customer_acceptance_status'=>'accepted']);

            //         // Create a new package array for each product ID
            //         $package_bundles = [
            //             'product_id'=>$productId,
            //             'quantity_removed'=>$qtyRemoved,
            //             'amount_accrued'=>$amount_accrued,
            //             'customer_acceptance_status'=>'accepted',
            //         ];
            //         $package_bundle_1[] = $package_bundles;
                    
            //         //rejected or declined updated
            //         // $rejected_products = OutgoingStock::where('product_id', '!=', $productId)->where('order_id', $newOrder->id)
            //         // ->where('reason_removed','as_order_firstphase')->get();
            //         // foreach ($rejected_products as $key => $rejected) {
            //         //     $rejected->update(['customer_acceptance_status'=>'rejected', 'quantity_returned'=>$rejected->quantity_removed]);
            //         // }
                    
            //     } 
            // }

            // //now update each row package_bundle
            // $outgoingStockPackageBundle = OutgoingStock::where('order_id', $newOrder->id)->first()->package_bundle;

            // foreach ($outgoingStockPackageBundle as &$package_bundle) {
            //     // Find the corresponding package_bundle in $package_bundle_1 based on product_id
            //     $matching_package = collect($package_bundle_1)->firstWhere('product_id', $package_bundle['product_id']);
            
            //     // If a matching package is found, update the row in $outgoingStockPackageBundle
            //     if ($matching_package && $package_bundle['reason_removed']=='as_order_firstphase') {
            //         // Merge the matching keys and values from $matching_package into $package_bundle
            //         $package_bundle = array_merge($package_bundle, array_intersect_key($matching_package, $package_bundle));
            //     }
            // }
        
            // // Now $outgoingStockPackageBundle has the updated data
            // //return $outgoingStockPackageBundle;

            // //update outgoingStock
            // OutgoingStock::where(['order_id'=>$newOrder->id])->update(['package_bundle' => $outgoingStockPackageBundle]);
            //////////end old code///////////////////////////////////////
                    
            $customer = new Customer();
            $customer->order_id = $newOrder->id;
            $customer->form_holder_id = $formHolder->id;
            $customer->firstname = $data['firstname'];
            $customer->lastname = $data['lastname'];
            $customer->phone_number = $data['phone_number'];
            $customer->whatsapp_phone_number = $data['whatsapp_phone_number'];
            $customer->email = $data['active_email'];
            $customer->city = $data['city'];
            $customer->state = $data['state'];
            $customer->delivery_address = $data['address'];
            $customer->delivery_duration = $data['delivery_duration'];
            $customer->created_by = 1;
            $customer->status = 'true';
            $customer->save();

            //update order status
            //DB::table('orders')->update(['customer_id'=>$customer->id, 'status'=>'new']);
            $newOrder = Order::find($newOrder->id);
            $newOrder->customer_id = $customer->id;
            $newOrder->status = 'new';
            $newOrder->expected_delivery_date = Carbon::parse($customer->created_at->addDays($customer->delivery_duration))->format('Y-m-d');
            $newOrder->save();

            $has_orderbump = isset($formHolder->orderbump_id) ? true : false;
            $has_upsell = isset($formHolder->upsell_id) ? true : false;
            $data['has_orderbump'] = $has_orderbump; 
            $data['has_upsell'] = $has_upsell;
            $data['order_id'] = $newOrder->id;

            //call notify fxn
            if ($has_orderbump==false && $has_upsell==false) {
                $this->invoiceData($formHolder, $customer, $newOrder);
            }

            return response()->json([
                'status'=>true,
                'data'=>$data,
            ]);

        } else {
            
            //update package in OutgoingStock

            #remove later
            $outgoingStock = OutgoingStock::where('order_id', $order->id)->first();
            $outgoingStockPackageBundle = $outgoingStock->package_bundle; //[{},{}]
            foreach ($data['product_packages'] as $key => $product_id) {
                if (!empty($product_id)) {
                    $idPriceQty = explode('-', $product_id);
                    $productId = $idPriceQty[0];
                    $saleUnitPrice = $idPriceQty[1];
                    $qtyRemoved = $idPriceQty[2];
        
                    // Accepted updated
                    $amount_accrued = $qtyRemoved * $saleUnitPrice;

                    foreach ($outgoingStockPackageBundle as &$stock) {
                        if ($stock['product_id'] == (int) $productId && $stock['reason_removed'] == 'as_order_firstphase') {
                            $stock['quantity_removed'] = $qtyRemoved;
                            $stock['amount_accrued'] = $amount_accrued;
                            $stock['customer_acceptance_status'] = 'accepted';
                        }
                        // Rejected or declined updated
                        if ($stock['product_id'] !== (int) $productId && $stock['reason_removed'] == 'as_order_firstphase') {
                            $stock['customer_acceptance_status'] = 'rejected';
                            $stock['quantity_returned'] = $stock['quantity_removed'];
                            $stock['reason_returned'] = 'declined';
                        }
                    }
        
                }
            }
            #remove later
            
            #remove later
            $outgoingStock->update(['package_bundle'=>$outgoingStockPackageBundle]);
            #remove later
        
            $customer = new Customer();
            $customer->order_id = $order->id;
            $customer->form_holder_id = $formHolder->id;
            $customer->firstname = $data['firstname'];
            $customer->lastname = $data['lastname'];
            $customer->phone_number = $data['phone_number'];
            $customer->whatsapp_phone_number = $data['whatsapp_phone_number'];
            $customer->email = $data['active_email'];
            $customer->city = $data['city'];
            $customer->state = $data['state'];
            $customer->delivery_address = $data['address'];
            $customer->delivery_duration = $data['delivery_duration'];
            $customer->created_by = 1;
            $customer->status = 'true';
            $customer->save();

            //update order status
            //DB::table('orders')->update(['customer_id'=>$customer->id, 'status'=>'new']);
            $order->customer_id = $customer->id;
            $order->status = 'new';
            $order->expected_delivery_date = Carbon::parse($customer->created_at->addDays($customer->delivery_duration))->format('Y-m-d');
            $order->save();
            
            $has_orderbump = isset($formHolder->orderbump_id) ? true : false;
            $has_upsell = isset($formHolder->upsell_id) ? true : false;
            $data['has_orderbump'] = $has_orderbump; 
            $data['has_upsell'] = $has_upsell;
            $data['order_id'] = $order->id;

            $data['order'] = $order->outgoingStock->package_bundle;
            
            //call notify fxn
            if ($has_orderbump==false && $has_upsell==false) {
                $this->invoiceData($formHolder, $customer, $order);
            }

            return response()->json([
                'status'=>true,
                'data'=>$data,
            ]);

        }
    }

    //saveNewFormOrderBumpFromCustomer
    public function saveNewFormOrderBumpFromCustomer(Request $request)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();

        $formHolder = FormHolder::where('unique_key', $data['unique_key'])->first();
        // $order = $formHolder->order;
        $order = Order::where('id', $data['current_order_id'])->first();

        $package_bundle_1 = [];
        //accepted orderbump, update OutgoingStock
        if (!empty($data['orderbump_product_checkbox'])) {
            //accepted updated
            $product = Product::where('id', $data['orderbump_product_checkbox'])->first();
            $isCombo = isset($product->combo_product_ids) ? 'true' : null;
            // OutgoingStock::where(['product_id'=>$product->id, 'order_id'=>$order->id, 'reason_removed'=>'as_orderbump'])
            // ->update(['customer_acceptance_status'=>'accepted', 'isCombo'=>$isCombo]);

            // Create a new package array for each product ID
            $package_bundles = [
                'product_id'=>$product->id,
                'customer_acceptance_status'=>'accepted',
                'isCombo'=>$isCombo,
            ];
            $package_bundle_1[] = $package_bundles;

            //now update each row package_bundle
            $outgoingStockPackageBundle = $order->outgoingStock->package_bundle;

            foreach ($outgoingStockPackageBundle as &$package_bundle) {
                // Find the corresponding package_bundle in $package_bundle_1 based on product_id
                $matching_package = collect($package_bundle_1)->firstWhere('product_id', $package_bundle['product_id']);
            
                // If a matching package is found, update the row in $outgoingStockPackageBundle
                if ($matching_package && $package_bundle['reason_removed']=='as_orderbump') {
                    // Merge the matching keys and values from $matching_package into $package_bundle
                    $package_bundle = array_merge($package_bundle, array_intersect_key($matching_package, $package_bundle));
                }
            }
            //update outgoingStock
            OutgoingStock::where(['order_id'=>$order->id])->update(['package_bundle' => $outgoingStockPackageBundle, 'order_id'=>$order->id]);
        }

        //update order with same orderbump as formholder
        // $order->update(['orderbump_id'=>$formHolder->orderbump_id]);
        $order->orderbump_id = $formHolder->orderbump_id;
        $order->save();

        $has_upsell = isset($formHolder->upsell_id) ? true : false;
        $data['has_upsell'] = $has_upsell;

        $customer =  $order->customer;

        //call notify fxn
        if ($has_upsell==false) {
            $this->invoiceData($formHolder, $customer, $order);
        }

        return response()->json([
            'status'=>true,
            'data'=>$data,
        ]);
    }

    //saveNewFormUpSellFromCustomer
    public function saveNewFormUpSellFromCustomer(Request $request)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();

        $formHolder = FormHolder::where('unique_key', $data['unique_key'])->first();
        // $order = $formHolder->order;
        $order = Order::where('id', $data['current_order_id'])->first();

        $package_bundle_1 = [];
        //accepted orderbump
        if (!empty($data['upsell_product_checkbox'])) {
            //accepted updated
            $product = Product::where('id', $data['upsell_product_checkbox'])->first();
            $isCombo = isset($product->combo_product_ids) ? 'true' : null;
            // OutgoingStock::where(['product_id'=>$product->id, 'order_id'=>$order->id, 'reason_removed'=>'as_upsell'])
            // ->update(['customer_acceptance_status'=>'accepted', 'isCombo'=>$isCombo]);

            // Create a new package array for each product ID
            $package_bundles = [
                'product_id'=>$product->id,
                'customer_acceptance_status'=>'accepted',
                'isCombo'=>$isCombo,
            ];
            $package_bundle_1[] = $package_bundles;

            //now update each row package_bundle
            $outgoingStockPackageBundle = $order->outgoingStock->package_bundle;

            foreach ($outgoingStockPackageBundle as &$package_bundle) {
                // Find the corresponding package_bundle in $package_bundle_1 based on product_id
                $matching_package = collect($package_bundle_1)->firstWhere('product_id', $package_bundle['product_id']);
            
                // If a matching package is found, update the row in $outgoingStockPackageBundle
                if ($matching_package && $package_bundle['reason_removed']=='as_upsell') {
                    // Merge the matching keys and values from $matching_package into $package_bundle
                    $package_bundle = array_merge($package_bundle, array_intersect_key($matching_package, $package_bundle));
                }
            }
            //update outgoingStock
            OutgoingStock::where(['order_id'=>$order->id])->update(['package_bundle' => $outgoingStockPackageBundle, 'order_id'=>$order->id]);
        }

        //update order with same orderbump as formholder
        $order->update(['upsell_id'=>$formHolder->upsell_id]);

        //////////////////////////////////////////////////////////////////////////////
        $customer =  $order->customer;
        $this->invoiceData($formHolder, $customer, $order);
    
        //////////////////////////////////////////////////////////////////////////////
        
        return response()->json([
            'status'=>true,
            'data'=>$data,
        ]);
    }

    //declined orderbump. refusal
    public function saveNewFormOrderBumpRefusalFromCustomer(Request $request)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();

        $formHolder = FormHolder::where('unique_key', $data['unique_key'])->first();
        // $order = $formHolder->order;
        $order = Order::where('id', $data['current_order_id'])->first();

        $package_bundle_1 = [];
        //accepted orderbump
        if (!empty($data['orderbump_product_checkbox'])) {
            //accepted updated
            $product = Product::where('id', $data['orderbump_product_checkbox'])->first();
            $isCombo = isset($product->combo_product_ids) ? 'true' : null;
            // OutgoingStock::where(['product_id'=>$product->id, 'order_id'=>$order->id, 'reason_removed'=>'as_orderbump'])
            // ->update(['customer_acceptance_status'=>'rejected', 'quantity_returned'=>1, 'reason_returned'=>'declined', 'isCombo'=>$isCombo]);

            // Create a new package array for each product ID
            $package_bundles = [
                'product_id'=>$product->id,
                'customer_acceptance_status'=>'rejected',
                'quantity_returned'=>1,
                'reason_returned'=>'declined',
                'isCombo'=>$isCombo,
            ];
            $package_bundle_1[] = $package_bundles;

            //now update each row package_bundle
            $outgoingStockPackageBundle = $order->outgoingStock->package_bundle;

            foreach ($outgoingStockPackageBundle as &$package_bundle) {
                // Find the corresponding package_bundle in $package_bundle_1 based on product_id
                $matching_package = collect($package_bundle_1)->firstWhere('product_id', $package_bundle['product_id']);
            
                // If a matching package is found, update the row in $outgoingStockPackageBundle
                if ($matching_package && $package_bundle['reason_removed']=='as_orderbump') {
                    // Merge the matching keys and values from $matching_package into $package_bundle
                    $package_bundle = array_merge($package_bundle, array_intersect_key($matching_package, $package_bundle));
                }
            }
            //update outgoingStock
            OutgoingStock::where(['order_id'=>$order->id])->update(['package_bundle' => $outgoingStockPackageBundle, 'order_id'=>$order->id]);
            //////////////////////////////////////////////////////////////
        }

        //update order with same orderbump as formholder
        $order->update(['orderbump_id'=>null]);

        $has_upsell = isset($formHolder->upsell_id) ? true : false;
        $data['has_upsell'] = $has_upsell;

        return response()->json([
            'status'=>true,
            'data'=>$data,
        ]);
    }

    //declined upsell
    public function saveNewFormUpSellRefusalFromCustomer(Request $request)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();

        $formHolder = FormHolder::where('unique_key', $data['unique_key'])->first();
        // $order = $formHolder->order;
        $order = Order::where('id', $data['current_order_id'])->first();

        $package_bundle_1 = [];
        //declined upsell
        if (!empty($data['upsell_product_checkbox'])) {
            //accepted updated
            $product = Product::where('id', $data['upsell_product_checkbox'])->first();
            $isCombo = isset($product->combo_product_ids) ? 'true' : null;
            // OutgoingStock::where(['product_id'=>$product->id, 'order_id'=>$order->id, 'reason_removed'=>'as_upsell'])
            // ->update(['customer_acceptance_status'=>'rejected', 'quantity_returned'=>1, 'reason_returned'=>'declined', 'isCombo'=>$isCombo]);

            // Create a new package array for each product ID
            $package_bundles = [
                'product_id'=>$product->id,
                'customer_acceptance_status'=>'rejected',
                'quantity_returned'=>1,
                'reason_returned'=>'declined',
                'isCombo'=>$isCombo,
            ];
            $package_bundle_1[] = $package_bundles;

            //now update each row package_bundle
            $outgoingStockPackageBundle = $order->outgoingStock->package_bundle;

            foreach ($outgoingStockPackageBundle as &$package_bundle) {
                // Find the corresponding package_bundle in $package_bundle_1 based on product_id
                $matching_package = collect($package_bundle_1)->firstWhere('product_id', $package_bundle['product_id']);
            
                // If a matching package is found, update the row in $outgoingStockPackageBundle
                if ($matching_package && $package_bundle['reason_removed']=='as_upsell') {
                    // Merge the matching keys and values from $matching_package into $package_bundle
                    $package_bundle = array_merge($package_bundle, array_intersect_key($matching_package, $package_bundle));
                }
            }
            //update outgoingStock
            OutgoingStock::where(['order_id'=>$order->id])->update(['package_bundle' => $outgoingStockPackageBundle, 'order_id'=>$order->id]);
            //////////////////////////////////////////////////////////////
        }

        //update order with same upsell as formholder
        $order->update(['upsell_id'=>null]);

        $has_upsell = isset($formHolder->upsell_id) ? true : false;
        $data['has_upsell'] = $has_upsell;

        return response()->json([
            'status'=>true,
            'data'=>$data,
        ]);
    }

    //cart abandon. gets cleared once submit btn is clicked
    public function cartAbandonContact(Request $request)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();
        $contact_data = $request->except(['unique_key']);
        $cartAbandoned_id = $data['cartAbandoned_id'];

        $contactFieldArr = explode('|', $data['inputVal']); //Ugo|first-name
        $contact = $contactFieldArr[0]; //Ugo
        $field = $contactFieldArr[1]; //first-name

        $cartAbandon = CartAbandon::where('id',$cartAbandoned_id)->first();
        if (isset($cartAbandon)) {
            if ($field=='first-name') { $cartAbandon->customer_firstname = $contact; }
            if ($field=='last-name') { $cartAbandon->customer_lastname = $contact; }
            if ($field=='phone-number') { $cartAbandon->customer_phone_number = $contact; }
            if ($field=='whatsapp-phone-number') { $cartAbandon->customer_whatsapp_phone_number = $contact; }
            if ($field=='active-email') { $cartAbandon->customer_email = $contact; }
            if ($field=='state') { $cartAbandon->customer_state = $contact; }
            if ($field=='city') { $cartAbandon->customer_city = $contact; }
            if ($field=='address') { $cartAbandon->customer_delivery_address = $contact; }
            
            $cartAbandon->save();
        }
        
        return response()->json([
            'status'=>true,
            'data'=>$data,
        ]);
    }

    public function cartAbandonDeliveryDuration(Request $request)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();
        $contact_data = $request->except(['unique_key']);
        $cartAbandoned_id = $data['cartAbandoned_id'];

        $contactFieldArr = explode('|', $data['inputVal']); //Ugo|first-name
        $contact = $contactFieldArr[0]; //Ugo
        $field = $contactFieldArr[1]; //first-name

        $cartAbandon = CartAbandon::where('id',$cartAbandoned_id)->first();

        if (isset($cartAbandon)) {
            if ($field=='first-name') { $cartAbandon->customer_firstname = $contact; }
            if ($field=='last-name') { $cartAbandon->customer_lastname = $contact; }
            if ($field=='phone-number') { $cartAbandon->customer_phone_number = $contact; }
            if ($field=='whatsapp-phone-number') { $cartAbandon->customer_whatsapp_phone_number = $contact; }
            if ($field=='active-email') { $cartAbandon->customer_email = $contact; }
            if ($field=='state') { $cartAbandon->customer_state = $contact; }
            if ($field=='city') { $cartAbandon->customer_city = $contact; }
            if ($field=='address') { $cartAbandon->customer_delivery_address = $contact; }
            
            $cartAbandon->customer_delivery_duration = $data['delivery_duration'];
    
            $cartAbandon->save();
        }
        
        return response()->json([
            'status'=>true,
            'data'=>$data,
        ]);
    }

    public function cartAbandonPackage(Request $request)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $data = $request->all();
        $package_data = $request->except(['unique_key']);

        $cartAbandoned_id = $data['cartAbandoned_id'];

        $contactFieldArr = explode('|', $data['inputVal']); //Ugo|first-name
        $contact = $contactFieldArr[0]; //Ugo
        $field = $contactFieldArr[1]; //first-name

        $cartAbandon = CartAbandon::where('id',$cartAbandoned_id)->first();

        if (isset($cartAbandon)) {
            if ($field=='first-name') { $cartAbandon->customer_firstname = $contact; }
            if ($field=='last-name') { $cartAbandon->customer_lastname = $contact; }
            if ($field=='phone-number') { $cartAbandon->customer_phone_number = $contact; }
            if ($field=='whatsapp-phone-number') { $cartAbandon->customer_whatsapp_phone_number = $contact; }
            if ($field=='active-email') { $cartAbandon->customer_email = $contact; }
            if ($field=='state') { $cartAbandon->customer_state = $contact; }
            if ($field=='city') { $cartAbandon->customer_city = $contact; }
            if ($field=='address') { $cartAbandon->customer_delivery_address = $contact; }
    
            $cartAbandon->package_info = serialize($data['product_package']);
            $cartAbandon->save();
        }
        
        return response()->json([
            'status'=>true,
            'data'=>$data,
        ]);
    }

    //invoiceData(), callback for notifying admin abt new order
    public function invoiceData($formHolder, $customer, $order)
    {
        // $authUser = auth()->user();
        // $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;

        $staffAssigned = isset($order->staff_assigned_id) ? $order->staff->name : '';
        //create soundNotification
        $soundNotification = new SoundNotification();
        $soundNotification->type = 'Order';
        $soundNotification->topic = 'New Order';
        $soundNotification->content = 'Customer placed an order';
        $soundNotification->link = 'order-form/'.$order->unique_key;
        $soundNotification->order_id = $order->id;
        $soundNotification->status = 'new';
        $soundNotification->save();
        
        //mainProduct_revenue
        $mainProduct_revenue = 0;  //price * qty
        $qty_main_product = 0;
        // $mainProducts_outgoingStocks = $order->outgoingStocks()->where(['reason_removed'=>'as_order_firstphase', 'customer_acceptance_status'=>'accepted'])->get();

        // if ( count($mainProducts_outgoingStocks) > 0 ) {
        //     foreach ($mainProducts_outgoingStocks as $key => $main_outgoingStock) {
        //         if(isset($main_outgoingStock->product)) {
        //             $mainProduct_revenue = $mainProduct_revenue + ($main_outgoingStock->product->sale_price * $main_outgoingStock->quantity_removed);
        //         } 
        //     }
        // }

        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        foreach ($outgoingStockPackageBundle as $key => &$main_outgoingStock) {
            if ( ($main_outgoingStock['reason_removed'] == 'as_order_firstphase') && ($main_outgoingStock['customer_acceptance_status'] == 'accepted') ) {
                $product = Product::where('id', $main_outgoingStock['product_id'])->first();
                 if (isset($product)) {
                    //array_push($mainProducts_outgoingStocks, array('product' => $product)); 
                    $main_outgoingStock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                    $mainProduct_revenue = $mainProduct_revenue + ($product->sale_price * $main_outgoingStock['quantity_removed']);
                    $qty_main_product += $main_outgoingStock['quantity_removed'];
                 }
            } else {
                // Remove the element from the array if the condition is not met
                unset($outgoingStockPackageBundle[$key]);
            }
        }

        //converting array immediate & nested(product) array to object. json_decode alone will not do it.
        // $mainProducts_outgoingStocks = array_map(function ($item) {
        //     return (object) $item;
        // }, $outgoingStockPackageBundle);
        
        $mainProducts_outgoingStocks = $mainProduct_revenue > 0 ? json_decode(json_encode($outgoingStockPackageBundle)) : collect([]);

        //orderbump
        $orderbumpProduct_revenue = 0; //price * qty
        $orderbump_outgoingStock = '';
        $qty_orderbump = 0;
        // if (isset($formHolder->orderbump_id)) {
        //     $orderbump_outgoingStock = $order->outgoingStocks()->where('reason_removed', 'as_orderbump')->first();
        //     if (isset($orderbump_outgoingStock->product) && $orderbump_outgoingStock->customer_acceptance_status == 'accepted') {
        //         $orderbumpProduct_revenue = $orderbumpProduct_revenue + ($orderbump_outgoingStock->product->sale_price * $orderbump_outgoingStock->quantity_removed);
        //     }
        // }

        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        
        if (isset($formHolder->orderbump_id)) {
            foreach ($outgoingStockPackageBundle as $key => &$orderbump_stock) {
                if ( ($orderbump_stock['reason_removed'] == 'as_orderbump') && ($orderbump_stock['customer_acceptance_status'] == 'accepted') ) {
                    $product = Product::where('id', $orderbump_stock['product_id'])->first();
                     if (isset($product)) {
                        $orderbump_stock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                        $orderbumpProduct_revenue = $orderbumpProduct_revenue + ($product->sale_price * $orderbump_stock['quantity_removed']);
                        $qty_orderbump += $orderbump_stock['quantity_removed'];
                     }
                } else {
                    // Remove the element from the array if the condition is not met
                    unset($outgoingStockPackageBundle[$key]);
                }
            }
        }
        //$orderbump_outgoingStock = $orderbumpProduct_revenue > 0 ? json_decode(json_encode($outgoingStockPackageBundle)) : '';
        $orderbump_outgoingStock = $orderbumpProduct_revenue > 0 ? json_decode(json_encode(array_merge(...array_values($outgoingStockPackageBundle)))) : '';
        
        //upsell
        $upsellProduct_revenue = 0; //price * qty
        $upsell_outgoingStock = '';
        $qty_upsell = 0;
        // if (isset($formHolder->upsell_id)) {
        //     $upsell_outgoingStock = $order->outgoingStocks()->where('reason_removed', 'as_upsell')->first();
        //     if (isset($upsell_outgoingStock->product) && $upsell_outgoingStock->customer_acceptance_status == 'accepted') {
        //         $upsellProduct_revenue += $upsellProduct_revenue + ($upsell_outgoingStock->product->sale_price * $upsell_outgoingStock->quantity_removed);
        //     }
        // }

        $outgoingStockPackageBundle = $order->outgoingStock->package_bundle; //[{}, {}]
        
        if (isset($formHolder->upsell_id)) {
            foreach ($outgoingStockPackageBundle as $key => &$upsell_stock) {
                if ( ($upsell_stock['reason_removed'] == 'as_upsell') && ($upsell_stock['customer_acceptance_status'] == 'accepted') ) {
                    $product = Product::where('id', $upsell_stock['product_id'])->first();
                     if (isset($product)) {
                        $upsell_stock['product'] = $product; //append 'product' key to $outgoingStockPackageBundle array
                        $upsellProduct_revenue = $upsellProduct_revenue + ($product->sale_price * $upsell_stock['quantity_removed']);
                        $qty_upsell += $upsell_stock['quantity_removed'];
                     }
                } else {
                    // Remove the element from the array if the condition is not met
                    unset($outgoingStockPackageBundle[$key]);
                }
            }
        }
        $upsell_outgoingStock = $upsellProduct_revenue > 0 ? json_decode(json_encode(array_merge(...array_values($outgoingStockPackageBundle)))) : '';
        
        //order total amt

        $orderId = ''; //used in thankYou section
        if ($order->id < 10){
            $orderId = '0000'.$order->id;
        }
        // <!-- > 10 < 100 -->
        if (($order->id > 10) && ($order->id < 100)) {
            $orderId = '000'.$order->id;
        }
        // <!-- > 100 < 1000 -->
        if (($order->id) > 100 && ($order->id < 1000)) {
            $orderId = '00'.$order->id;
        }
        // <!-- > 1000 < 10000++ -->
        if (($order->id) > 1000 && ($order->id < 1000)) {
            $orderId = '0'.$order->id;
        }

        //package or product qty. sum = 0, if it doesnt exist
        // $qty_main_product = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_order_firstphase'])->sum('quantity_removed');
        // $qty_orderbump = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_orderbump'])->sum('quantity_removed');
        // $qty_upsell = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_upsell'])->sum('quantity_removed');
        $qty_total = $qty_main_product + $qty_orderbump + $qty_upsell;

        $order_total_amount = $mainProduct_revenue + $orderbumpProduct_revenue + $upsellProduct_revenue;
        $grand_total = $order_total_amount; //might include discount later

        $admin = GeneralSetting::first();

        // $whatsapp_phone_number = '';
        // if (substr($customer->whatsapp_phone_number, 0, 1) === '0') {
        //     $whatsapp_phone_number = '234' . substr($customer->whatsapp_phone_number, 1);
        // }

        //$whatsapp_msg = "Hi ".$customer->firstname." ".$customer->lastname.", you just placed order with Invoice-id: kp-".$orderId.". We will get back to you soon";
        if ($staffAssigned == '') {
            $whatsapp_msg = "Hello ".$customer->firstname." ".$customer->lastname." I am contacting you from KeepMeFit, concerning of the order you placed for ";
        } else {
            $whatsapp_msg = "Hello ".$customer->firstname." ".$customer->lastname.". My name is ".$staffAssigned.", I am contacting you from KeepMeFit and I am the Customer Service Representative incharge of the order you placed for ";
        }
        
        $whatsapp_msg .= "";
        foreach($mainProducts_outgoingStocks as $main_outgoingStock):
            if ($main_outgoingStock->customer_acceptance_status):
                $whatsapp_msg .= " [Product: ".$main_outgoingStock->product->name.". Price: ".$mainProduct_revenue.". Qty: ".$main_outgoingStock->quantity_removed."], ";
            endif;
        endforeach;

        if(isset($orderbump_outgoingStock->product) && $orderbump_outgoingStock != ''):
            $whatsapp_msg .= "[Product: ".$orderbump_outgoingStock->product->name.". Price: ".$orderbump_outgoingStock->product->sale_price * $orderbump_outgoingStock->quantity_removed.". Qty: ".$orderbump_outgoingStock->quantity_removed."], ";
        endif;

        if(isset($upsell_outgoingStock->product) && $upsell_outgoingStock != ''):
            $whatsapp_msg .= "[Product: ".$upsell_outgoingStock->product->name.". Price: ".$upsell_outgoingStock->product->sale_price * $upsell_outgoingStock->quantity_removed.". Qty: ".$upsell_outgoingStock->quantity_removed."]. ";
        endif;

        $whatsapp_msg .= "I am reaching out to you to confirm your order and to let you know the delivery person will call you to deliver your order. Kindly confirm if the details you sent are correct ";

        $whatsapp_msg .= "[Phone Number: ".$customer->phone_number.". Whatsapp Phone Number: ".$customer->whatsapp_phone_number.". Delivery Address: ".$customer->delivery_address."]. ";

        $whatsapp_msg .= "Please kindly let me know when we can deliver your order. Thank you!";

        // //mail user about their new order
        $invoiceData = [
            'order' => $order,
            'orderId' => $orderId,
            'customer' => $customer,
            'mainProducts_outgoingStocks' => $mainProducts_outgoingStocks,
            'mainProduct_revenue' => $mainProduct_revenue,
            'orderbump_outgoingStock' => $orderbump_outgoingStock,
            'orderbumpProduct_revenue' => $orderbumpProduct_revenue,
            'whatsapp_msg' => $whatsapp_msg,
            'upsell_outgoingStock' => $upsell_outgoingStock,
            'upsellProduct_revenue' => $upsellProduct_revenue,
            'qty_total' => $qty_total,
            'order_total_amount' => $order_total_amount,
            'grand_total' => $grand_total,
        ];

        try {
            Notification::route('mail', [$admin->official_notification_email])->notify(new NewOrder($invoiceData));
        } catch (Exception $exception) {
            // return back()->withError($exception->getMessage())->withInput();
            return back();
        }

        
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    //not used
    public function formLink($unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            \abort(404);
        }
        $formName = $formHolder->name;
        $formContact = \unserialize($formHolder->contact);
        $formPackage = \unserialize($formHolder->package);
        foreach ($formPackage as $package) {
            foreach ($package['values'] as $key => $option) {
                $option['product_price'] = Product::where('code', $option['value'])->first()->sale_price;
                $option['type'] = $package['type'] == 'radio-group' ? 'radio' : 'checkbox';
                $option['name'] = isset($package['name']) ? $package['name'] : 'package';
                $products[] = $option;
            }
            
        }

        // return $products;
        // [
        //     {
        //         "type":"radio-group","required":"false","label":"Select A Package From Below:","inline":"false","name":"package","access":"false","other":"false",
        //         "values":[
        //             {"label":"1 Bottle of Instant FLusher Pill + 1 Pack of Instant Flusher Tea","value":null,"selected":"false"},
        //             {"label":"2 Bottles of Instant FLusher Pill + 2 Packs of Instant Flusher Tea","value":null,"selected":"false"}
        //             ]
        //     }
        // ]

        //for thankyou part
        $order = $formHolder->order;
        $mainProduct_revenue = 0;  //price * qty
        $mainProducts_outgoingStocks = $order->outgoingStocks()->where(['reason_removed'=>'as_order_firstphase',
        'customer_acceptance_status'=>'accepted'])->get();

        if ( count($mainProducts_outgoingStocks) > 0 ) {
            foreach ($mainProducts_outgoingStocks as $key => $main_outgoingStock) {
                if(isset($main_outgoingStock->product)) {
                    $mainProduct_revenue = $mainProduct_revenue + ($main_outgoingStock->product->sale_price * $main_outgoingStock->quantity_removed);
                } 
            }
        }

        //orderbump
        $orderbumpProduct_revenue = 0; //price * qty
        $orderbump_outgoingStock = $order->outgoingStocks()->where('reason_removed', 'as_orderbump')->first();
        if (isset($main_outgoingStock->product) && $orderbump_outgoingStock->customer_acceptance_status == 'accepted') {
            $orderbumpProduct_revenue = $orderbumpProduct_revenue + ($orderbump_outgoingStock->product->sale_price * $orderbump_outgoingStock->quantity_removed);
        }
        
        //upsell
        $upsellProduct_revenue = 0; //price * qty
        $upsell_outgoingStock = $order->outgoingStocks()->where('reason_removed', 'as_upsell')->first();
        if (isset($upsell_outgoingStock->product) && $upsell_outgoingStock->customer_acceptance_status == 'accepted') {
            $upsellProduct_revenue = $upsellProduct_revenue + ($upsell_outgoingStock->product->sale_price * $upsell_outgoingStock->quantity_removed);
        }

        //order total amt
        $order_total_amount = $mainProduct_revenue + $orderbumpProduct_revenue + $upsellProduct_revenue;
        $grand_total = $order_total_amount; //might include discount later
        
        $orderId = ''; //used in thankYou section
        if ($order->id < 10){
            $orderId = '0000'.$order->id;
        }
        // <!-- > 10 < 100 -->
        if (($order->id > 10) && ($order->id < 100)) {
            $orderId = '000'.$order->id;
        }
        // <!-- > 100 < 1000 -->
        if (($order->id) > 100 && ($order->id < 100)) {
            $orderId = '00'.$order->id;
        }
        // <!-- > 1000 < 10000++ -->
        if (($order->id) > 100 && ($order->id < 100)) {
            $orderId = '0'.$order->id;
        }

        //customer
        $customer = isset($order->customer) ? $order->customer : '';

        //package or product qty. sum = 0, if it doesnt exist
        $qty_main_product = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_order_firstphase'])->sum('quantity_removed');
        $qty_orderbump = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_orderbump'])->sum('quantity_removed');
        $qty_upsell = OutgoingStock::where(['order_id'=>$order->id, 'customer_acceptance_status'=>'accepted', 'reason_removed'=>'as_upsell'])->sum('quantity_removed');
        $qty_total = $qty_main_product + $qty_orderbump + $qty_upsell;

        //end thankyou part
        
        return view('pages.formLink', compact('authUser', 'user_role', 'unique_key', 'formHolder', 'formName', 'formContact', 'formPackage', 'products',
        'mainProducts_outgoingStocks', 'order', 'orderId', 'mainProduct_revenue', 'orderbump_outgoingStock', 'upsell_outgoingStock',
        'customer', 'qty_total', 'order_total_amount', 'grand_total'));
    }

    //not used formLinkPost
    public function formLinkPost(Request $request, $unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $request->validate([
            'first-name' => 'required',
            'last-name' => 'required',
            'phone-number' => 'required',
            'whatsapp-phone-number' => 'required',
            'email' => 'required|email',
            'delivery-address' => 'required',
            'package' => 'required'
        ]);

        if (!empty($request->orderbump_check)) {
            $request->validate([
                'product' => 'required',
            ]);
        } 
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            \abort(404);
        }

        $data = $request->all();
        $order = $formHolder->order;
        
        $existing_customer = Customer::where('order_id', $order->id)->count();
        //order already exist, and customer tries to submit
        if ($existing_customer > 0) {
            if ($request->upsell_available != '') {
                Session::put('upsell_stage', 'true');
                return back();
            } else {
                Session::put('thankyou_stage', 'true');
                return back();
            }
            // return back()->with('info', 'Order Already Taken. We will get back to you shortly');
        }

        //update package in OutgoingStock
        foreach ($data['package'] as $key => $code) {
            if(!empty($code)){
                //accepted updated
                $product_id = Product::where('code', $code)->first()->id;
                OutgoingStock::where(['product_id'=>$product_id, 'order_id'=>$order->id, 'reason_removed'=>'as_order_firstphase'])
                ->update(['customer_acceptance_status'=>'accepted']);
                
                //rejected or declined updated
                $rejected_products = OutgoingStock::where('product_id', '!=', $product_id)->where('order_id', $order->id)
                ->where('reason_removed','as_order_firstphase')->get();
                foreach ($rejected_products as $key => $rejected) {
                    $rejected->update(['customer_acceptance_status'=>'rejected', 'quantity_returned'=>$rejected->quantity_removed]);
                }
                
            }
            
        }

        //accepted orderbump
        if (!empty($request->orderbump_check)) {
            //accepted updated
            $product_id = Product::where('id', $data['product'])->first()->id;
            OutgoingStock::where(['product_id'=>$product_id, 'order_id'=>$order->id, 'reason_removed'=>'as_orderbump'])
            ->update(['customer_acceptance_status'=>'accepted']);
        }

        $customer = new Customer();
        $customer->order_id = $order->id;
        $customer->form_holder_id = $formHolder->id;
        $customer->firstname = $data['first-name'];
        $customer->lastname = $data['last-name'];
        $customer->phone_number = $data['phone-number'];
        $customer->whatsapp_phone_number = $data['whatsapp-phone-number'];
        $customer->email = $data['email'];
        $customer->delivery_address = $data['delivery-address'];
        $customer->created_by = 1;
        $customer->status = 'true';
        $customer->save();

        //update order status
        $order->update(['customer_id'=>$customer->id, 'status'=>'new']);

        //to activate psell & thankyou part
        if ($request->upsell_available != '') {
            Session::put('upsell_stage', 'true');
        } else {
            Session::put('thankyou_stage', 'true');
        }
        
        //return back()->with('order-success', 'Saved Successfully');
        return back();

    }

    //not used
    public function formLinkUpsellPost(Request $request, $unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formHolder = FormHolder::where('unique_key', $unique_key)->first();
        if (!isset($formHolder)) {
            \abort(404);
        }

        $data = $request->all();
        $order = $formHolder->order;

        if (!empty($request->upsell_product)) {
            //accepted updated
            $product_id = Product::where('id', $data['upsell_product'])->first()->id;
            OutgoingStock::where(['product_id'=>$product_id, 'order_id'=>$order->id, 'reason_removed'=>'as_upsell'])
            ->update(['customer_acceptance_status'=>'accepted']);
        }
        if (!empty(Session::get('upsell_stage'))) {
            Session::forget('upsell_stage');
        }
        Session::put('thankyou_stage', 'true');
        return back();
    }

    //not used
    public function editFormHolder($unique_key)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        $formLabel = OrderLabel::where('unique_key', $unique_key)->first();
        $order_id = $formLabel->order_id;

        $orderProducts = OrderProduct::where('order_id', $order_id)->get(['product_id']); //by column
        $orderBump_product = OrderBump::where('order_id', $order_id)->first();
        $upSell_product = OrderBump::where('order_id', $order_id)->first();

        return view('pages.forms.editForm', compact('authUser', 'user_role', 'formLabel', 'orderProducts', 'orderBump_product', 'upSell_product'));
    }

    public function productById($id) {
        $product = Product::where('id',$id)->first();
        if(isset($product)){
            return $product;
        } else {
            return "";
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function test()
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        return view('pages.test');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $authUser = auth()->user();
        $user_role = $authUser->hasAnyRole($authUser->id) ? $authUser->role($authUser->id)->role : false;
        
        //
    }

    //not used
    public function search(Request $request)
    {
        try {
            $user = User::findOrFail($request->input('user_id'));
        } catch (ModelNotFoundException $exception) {
            return back()->withError($exception->getMessage())->withInput();
        }
        return view('users.search', compact('user'));
    }


}
