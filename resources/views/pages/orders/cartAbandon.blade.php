@extends('layouts.design')
@section('title')Carts Abandoned @endsection

@section('extra_css')
    <style>
        /* select2 arrow */
        select{
            -webkit-appearance: listbox !important
        }

        .btn-light {
            background-color: #fff !important;
            color: #000 !important;
        }
    
        div.filter-option-inner-inner{
            color: #000 !important;
        }
          
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
    <h1>Carts Abandoned</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.html">Home</a></li>
        <li class="breadcrumb-item active">Carts Abandoned</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  
  <section class="users-list-wrapper">
    <div class="users-list-filter px-1">
      
    </div>

  </section>

  @if(Session::has('success'))
    <div class="alert alert-success mb-3 text-center">
        {{Session::get('success')}}
    </div>
  @endif

  <section>
    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-body pt-3">
            
          <div class="clearfix mb-2">

            <div class="float-start text-start d-none">
                <button data-bs-target="#addMoneyTransfer" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Export Data">
                  <i class="bi bi-plus"></i> <span>Add Money Transfer</span></button>
            </div>

            <div class="float-end text-end d-none">
              <button class="btn btn-sm btn-danger rounded-pill" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Delete All"><i class="bi bi-trash"></i> <span>Delete All</span></button>
            </div>
          </div>
          <hr>
          
          <div class="table table-responsive">
            <table id="products-table" class="table custom-table" style="width:100%">
              <thead>
                  <tr>
                    <th>Cart Id</th>
                    <th>Form Id</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Phone</th>
                    <th>Whatsapp Phone</th>
                    <th>Email</th>
                    
                    <th>State</th>
                    <th>City</th>
                    <th>Address</th>
                    <th>Date</th>
                    {{-- <th>Status</th>
                    <th>Actions</th>  --}}
                  </tr>
              </thead>
              <tbody>

                @if (count($carts) > 0)
                  @foreach ($carts as $key=>$cart)
                
                    <tr>
                      <th>kpcart-0{{ $cart->id }}
                        <div class="d-flex justify-content-start align-items-center">
                          <a href="{{ route('singleCartAbandon', $cart->unique_key) }}" class="badge badge-dark">View</a>
                          <a href="{{ route('deleteCartAbandon', $cart->unique_key) }}" onclick="return confirm('Are you sure?')" class="badge badge-danger">Delete</a>
                        </div>
                        
                      </th>

                      <td>
                        {{ $cart->formHolder->slug }}
                      </td>

                      <td>{{ isset($cart->customer_firstname) ? $cart->customer_firstname : '' }}</td>
                      <td>{{ isset($cart->customer_lastname) ? $cart->customer_lastname : '' }}</td>
                      <td>{{ isset($cart->customer_phone_number) ? $cart->customer_phone_number : '' }}</td>
                      <td>{{ isset($cart->customer_whatsapp_phone_number) ? $cart->customer_whatsapp_phone_number : '' }}</td>
                      <td>{{ isset($cart->customer_email) ? $cart->customer_email : '' }}</td>
                      <td>{{ isset($cart->customer_state) ? $cart->customer_state : '' }}</td>
                      <td>{{ isset($cart->customer_city) ? $cart->customer_city : '' }}</td>
                      <td>{{ isset($cart->customer_delivery_address) ? $cart->customer_delivery_address : '' }}</td>

                      <td>
                        {{ $cart->created_at->format('D, jS M Y, g:ia') }}
                      </td>
                    
                    </tr>
                  @endforeach
                @endif
                  
              </tbody>
          </table>
          </div>
          </div>
        </div>
      </div>
    </div>
  </section>

</main><!-- End #main -->

<!-- Modal addAgentModal -->
<div class="modal fade" id="addAgentModal" tabindex="-1" aria-labelledby="addAgentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="addAgentModalLabel">Assign Agent</h1>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('assignAgentToOrder') }}" method="POST">@csrf
                <div class="modal-body">
                    
                    <input type="hidden" id="order_id" class="order_id" name="order_id" value="">
                    <div class="d-grid mb-3">
                        <label for="">Select Agent</label>
                        <select name="agent_id" id="" data-live-search="true" class="custom-select form-control border border-dark">
                            <option value="">Nothing Selected</option>

                            @foreach ($agents as $agent)
                              <option value="{{ $agent->id }}">{{ $agent->name }} | {{ $agent->id }}</option>
                            @endforeach
                            
                        </select>
                    </div>
                
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary addAgentBtn">Assign Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal changeAgentModal -->
<div class="modal fade" id="changeAgentModal" tabindex="-1" aria-labelledby="changeAgentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h1 class="modal-title fs-5" id="changeAgentModalLabel">Change Assigned Agent</h1>
              <button type="button" class="btn-close"
                  data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="{{ route('assignAgentToOrder') }}" method="POST">@csrf
              <div class="modal-body">
                  
                  <input type="hidden" id="order_id" class="order_id" name="order_id" value="">
                  <div class="d-grid mb-3">
                      <label for="">Select Agent</label>
                      <select name="agent_id" id="changeAgentModalSelect" data-live-search="true" class="custom-select form-control border border-dark">
                          <option value="kkk" selected>Nothing Selected</option>

                          @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }} | {{ $agent->id }}</option>
                          @endforeach
                          
                      </select>
                  </div>
              
              </div>
              <div class="modal-footer">
                  <button type="submit" class="btn btn-primary addAgentBtn">Assign Agent</button>
              </div>
          </form>
      </div>
  </div>
</div>

@endsection

@section('extra_js')

<script>
  function addAgentModal($orderId="") {
    $('#addAgentModal').modal("show");
    $('.order_id').val($orderId);
  }

  function changeAgentModal($orderId="") {
    $('#changeAgentModal').modal("show");
    $('.order_id').val($orderId);

  //  var option = $('#changeAgentModalSelect').val();
  //  console.log(option)

  }

  // $('.addAgentBtn').click(function(e){
  //       e.preventDefault();
  //       var order_id = $('.order_id').val();
  //       var agent_id = $('.agent_id').val();
        
  //       // alert(category_name)
        
  //       $('#addAgentModal').modal('hide');

  //       $.ajax({
  //           type:'get',
  //           url:'/assign-agent-to-order',
  //           data:{ order_id:order_id, agent_id:agent_id },
  //           success:function(resp){
                
  //               if (resp.status) {
  //                   alert('Agent Assigned Successfully')
  //               } 
                    
  //           },error:function(){
  //               alert("Error");
  //           }
  //       });
        
        
  // });
</script>
    
@endsection