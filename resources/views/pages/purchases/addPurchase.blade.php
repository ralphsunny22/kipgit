@extends('layouts.design')
@section('title')Add Purchase @endsection
@section('extra_css')
<style>
    /* select2 arrow */
    select{
        -webkit-appearance: listbox !important
    }

    /* custom-select border & inline edit */
    .btn-light {
        background-color: #fff !important;
        color: #000 !important;
    }
    div.filter-option-inner-inner{
        color: #000 !important;
    }
    /* custom-select border & inline edit */

    /* select2 height proper */
    .select2-selection__rendered {
        line-height: 31px !important;
    }
    .select2-container .select2-selection--single {
        height: 35px !important;
    }
    .select2-selection__arrow {
        height: 34px !important;
    }
    /* select2 height proper */
</style>
@endsection
@section('content')

<main id="main" class="main">

    <div class="pagetitle">
      <h1>Add Purchase</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="/">Home</a></li>
          <li class="breadcrumb-item"><a href="{{ route('allPurchase') }}">Purchases</a></li>
          <li class="breadcrumb-item active">Add Purchase</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">

      </div>
    </section>

    @if(Session::has('success'))
    <div class="alert alert-success mb-3 text-center">
        {{Session::get('success')}}
    </div>
    @endif

    @if(Session::has('duplicate_error'))
    <div class="alert alert-danger mb-3 text-center">
        {{Session::get('duplicate_error')}}
    </div>
    @endif

    <section>
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div class="card-body">
              
              <form class="row g-3 needs-validation" action="{{ route('addPurchasePost') }}" method="POST"
              enctype="multipart/form-data">@csrf
              <div class="col-md-12 mb-3">The field labels marked with * are required input fields.</div>

                <div class="col-md-6">
                  <label for="" class="form-label">Purchase Code *</label>
                  <input type="text" name="purchase_code" class="form-control @error('purchase_code') is-invalid @enderror" value="{{ $purchase_code }}" readonly>
                  @error('purchase_code')
                      <span class="invalid-feedback" role="alert">
                          <strong>{{ $message }}</strong>
                      </span>
                  @enderror
                </div>

                <div class="col-md-6">
                    <label for="" class="form-label">Select Supplier *</label>
                    <div class="d-flex @error('category') is-invalid @enderror">
                        <select name="supplier" id="addSupplierSelect" class="select2 form-control border @error('supplier') is-invalid @enderror">
                            <option value="">Nothing Selected</option>
        
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">
                                    {{ $supplier->company_name }}
                                </option>
                            @endforeach
                                
                        </select>

                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSupplier">
                        <i class="bi bi-plus"></i></button>
                    </div>
                    
                    @error('supplier')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-md-4 d-none">
                  <label for="" class="form-label">Date</label>
                  <input type="date" name="purchase_date" class="form-control @error('purchase_date') is-invalid @enderror" id="" >
                  @error('purchase_date')
                      <span class="invalid-feedback" role="alert">
                          <strong>{{ $message }}</strong>
                      </span>
                  @enderror
                </div>

                <div class="col-md-12">
                  <label for="" class="form-label">Select Product *</label>
                  <select name="product" id="product" data-live-search="true" class="custom-select form-control border @error('product') is-invalid @enderror" id="">
                    <option value="">Nothing Selected</option>

                    @foreach ($products as $product)
                        <!---1-30-3000--->
                        <option value="{{ $product->code }}|{{ $product->name }}|{{ $product->id }}|{{ $product->purchase_price }}">
                            {{ $product->code }} | {{ $product->name }} | Stock: {{ $product->stock_available() }}
                            @if (isset($product->purchase_price)) | Purchase Price: {{ $product->purchase_price }} @endif
                            @if (isset($product->sale_price)) | Selling Price: {{ $product->sale_price }} @endif
                        </option>
                    @endforeach
                        
                  </select>
                  @error('product')
                      <span class="invalid-feedback" role="alert">
                          <strong>{{ $message }}</strong>
                      </span>
                  @enderror
                </div>
            
                <div class="col-md-12">
                    <table id="orderTable" class="table caption-top">
                        <caption class="fw-bolder">Order Table *</caption>
                        <thead>
                          <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Unit Price</th>
                            <th scope="col">Total</th>
                            <th scope="col"><i class="bi bi-trash fw-bolder"></i></th>
                          </tr>
                        </thead>
                        <tbody>
                          
                        </tbody>
                    </table>
                </div>

                
                <div class="col-md-4">
                    <label for="" class="form-label">Payment Type *</label>
                    <select name="payment_type" id="payment_type" data-live-search="true" class="custom-select form-control border @error('payment_type') is-invalid @enderror" id="">
                      <option value="cash">Cash</option>
                      <option value="card">Card</option>
                      <option value="cheque">Cheque</option>
                      <option value="bank_transfer">Bank Transfer</option>
                        
                    </select>
                    @error('payment_type')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="" class="form-label">Purchase Status *</label>
                    <select name="purchase_status" id="purchase_status" data-live-search="true" class="custom-select form-control border @error('purchase_status') is-invalid @enderror" id="">
                      <option value="received">Received</option>
                      <option value="partial">Partial</option>
                      <option value="pending">Pending</option>
                      <option value="ordered">Ordered</option>
                        
                    </select>
                    @error('purchase_status')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="" class="form-label">Attach File
                        <i class="bi bi-question-circle text-info border rounded-pill" data-bs-toggle="tooltip" data-bs-placement="top" title="Only jpg, jpeg, png, pdf, csv, docx, xlsx, gif, svg, webp and txt file is supported"></i>
                      </label>
                    <input type="file" name="attached_document" class="form-control @error('attached_document') is-invalid @enderror" placeholder="" >
                    @error('attached_document')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-md-4 d-none">
                    <label for="" class="form-label">Order Tax</label>
                    <input type="text" name="order_tax" class="form-control @error('order_tax') is-invalid @enderror" placeholder="" >
                    @error('order_tax')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-md-4 d-none">
                    <label for="" class="form-label">Discount</label>
                    <input type="text" name="discount" class="form-control @error('discount') is-invalid @enderror" placeholder="" >
                    @error('discount')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-md-4 d-none">
                    <label for="" class="form-label">Shipping Cost</label>
                    <input type="text" name="shipping_cost" class="form-control @error('shipping_cost') is-invalid @enderror" placeholder="" >
                    @error('shipping_cost')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-md-12">
                    <label for="" class="form-label">Note</label>
                    <textarea name="note" id="" name="note" class="form-control @error('note') is-invalid @enderror" cols="30" rows="10"></textarea>
                    
                    @error('note')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                
                <div class="text-end">
                  <button type="submit" class="btn btn-primary">Save Purchase</button>
                  <button type="reset" class="btn btn-secondary">Reset</button>
                </div>
              </form><!-- End Multi Columns Form -->
              
            </div>
          </div>
        </div>
      </div>
    </section>

</main><!-- End #main -->

<!-- ModalSupplier -->
<div class="modal fade" id="addSupplier" tabindex="-1" aria-labelledby="addSupplierLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Add Supplier</h1>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addSupplierForm" action="" enctype="multipart/form-data">@csrf
                <div class="modal-body">
                    
                    <div class="d-grid mb-2">
                        <label for="" class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" id="">
                        @error('company_name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                      </div>
                      
                      <div class="d-grid mb-2">
                        <label for="" class="form-label">Supplier Full Name</label>
                        <input type="text" name="supplier_name" class="form-control @error('supplier_name') is-invalid @enderror" id="">
                        @error('supplier_name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                      </div>
      
                      <div class="d-grid mb-2">
                        <label for="" class="form-label">Email</label>
                        <input type="text" name="email" class="form-control @error('email') is-invalid @enderror" id="" >
                        @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                      </div>
      
                      <div class="d-grid mb-2">
                        <label for="" class="form-label">Phone</label>
                        <input type="tel" name="phone_number" class="form-control @error('phone_number') is-invalid @enderror" placeholder="" >
                        @error('phone_1')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                      </div>
                  
                      <div class="d-grid mb-2 d-none">
                        <label for="" class="form-label">Company Logo | Optional</label>
                        <input type="file" name="company_logo" class="form-control @error('company_logo') is-invalid @enderror" id="">
                        @error('company_logo')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                      </div>
                                    
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary addCategoryBtn">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
  </div>

@endsection

@section('extra_js')

<!--addSupplier-->
<script>
    //addCategory Modal
   $('#addSupplierForm').submit(function(e){
        e.preventDefault();
    
            // var formData = new FormData($('#addSupplierForm')[0]);
            var formData = $('#addSupplierForm').serialize();
            
            $('#addSupplier').modal('hide');

            $.ajax({
                type:'get',
                url:'/ajax-create-supplier',
                data: formData,
                //cache: false,
                // contentType: false,
                // processData: false,
                success:function(resp){
                    
                    if (resp.status) {
                        
                        var datas = {
                            id: resp.data.supplier.id,
                            text: resp.data.supplier.company_name
                        };
                        var newOption = new Option(datas.text, datas.id, false, false);
                        $('#addSupplierSelect').prepend(newOption).trigger('change');
                        
                        //$('#addCategorySelect').prepend('<option value='+resp.data.category.id+'>'+resp.data.category.name+'</option>')
                        alert('Supplier Added Successfully')
                        // return false;
                    } 
                        
                },error:function(){
                    alert("Error");
                }
            });
        
        
   });
  </script>

<script>
    $('#product').change(function(){ 
    var product = $(this).val();
    
    var productArr = product.split('|');
    var code = productArr[0];
    var name = productArr[1];
    var id = productArr[2];
    var unitprice = productArr[3];
    // console.log(productArr)

    var productText = '';
    $("#orderTable > tbody").append("<tr><th scope='row'>"+name+"</th><td><input type='hidden' name='product_id[]' value='"+id+"'>"+code+"</td><td style='width:150px'><input type='number' name='product_qty[]' class='form-control product-qty' value='1'></td><td style='width:150px'><input type='number' name='unit_price[]' class='form-control unit-price' value='"+unitprice+"'></td><td class='total'>"+unitprice+"</td><td class='btnDelete btn btn-danger btn-sm mt-1 mb-1'>Remove</td></tr>");
});
</script>

<script>
    $("#orderTable").on('click', '.btnDelete', function () {
        $(this).closest('tr').remove();
    });
</script>

<script>
    $("#orderTable").on('click', '.editOrderBtn', function () {
        var product = $(this).attr('data-product');
        console.log(product)
    });
</script>

<script>
    $("#orderTable").on('input', '.product-qty', function () {
        var productQty = $(this).val();
        //console.log(productQty)
        var unitPrice = parseInt($(this).closest('tr').find('.unit-price').val());
        var total = productQty * unitPrice;
        //replace total
        $(this).closest('tr').find('.total').text(total);
    });

    $("#orderTable").on('input', '.unit-price', function () {
        var unitPrice = $(this).val();
        //console.log(productQty)
        var productQty = parseInt($(this).closest('tr').find('.product-qty').val());
        var total = productQty * unitPrice;
        //replace total
        $(this).closest('tr').find('.total').text(total);
    });
</script>







@endsection