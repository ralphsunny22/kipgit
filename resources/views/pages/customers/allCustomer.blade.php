@extends('layouts.design')
@section('title')Customer @endsection
@section('content')

<main id="main" class="main">

  <div class="pagetitle">
    <h1>Customer</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.html">Home</a></li>
        <li class="breadcrumb-item active">Customer</li>
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
            <div class="float-start text-start">
              <a href="{{ route('addCustomer') }}" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Add Agent">
                <i class="bi bi-plus"></i> <span>Add Customer</span></a>
            </div>

            <div class="float-end text-end">
              <button data-bs-target="#importModal" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Import Data">
                <i class="bi bi-upload"></i> <span>Import</span></button>
              <a href="{{ route('customersExport') }}"><button class="btn btn-sm btn-secondary rounded-pill" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Export Data">
                <i class="bi bi-download"></i> <span>Export</span></button></a>
              <button class="btn btn-sm btn-danger rounded-pill d-none" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Delete All"><i class="bi bi-trash"></i> <span>Delete All</span></button>
            </div>
          </div>
          <hr>
          
          <div class="table table-responsive">
            <table id="products-table" class="table custom-table" style="width:100%">
              <thead>
                  <tr>
                      <th>Photo</th>
                      <th>Name</th>
            
                      <th>City/Town</th>
                      <th>State | Country</th>
                      <th>Date Joined</th>
                      <th>Action</th>
                  </tr>
              </thead>
              <tbody>
                @if (count($customers) > 0)
                    @foreach ($customers as $customer)
                    <tr>
                      <td>
                        @if (isset($customer->profile_picture))
                            <a
                            href="{{ asset('/storage/customer/'.$customer->profile_picture) }}"
                            data-fancybox="gallery"
                            data-caption="{{ isset($customer->profile_picture) ? $customer->name : 'no caption' }}"
                            >   
                            <img src="{{ asset('/storage/customer/'.$customer->profile_picture) }}" width="50" class="rounded-circle img-thumbnail img-fluid"
                            alt="{{$customer->name}}"></a>
                        @else
                        <img src="{{ asset('/storage/customer/person.png') }}" width="50" class="rounded-circle img-thumbnail img-fluid"
                            alt="{{$customer->name}}">
                        @endif
                        
                      </td>
                      <td>{{ $customer->firstname }} {{ $customer->lastname }}</td>
                      <td>{{ isset($customer->city) ? $customer->city : 'N/A' }}</td>
                      
                      <td>{{ $customer->state }} | {{ isset($customer->country_id) ? $customer->country->name : '' }}</td>
                      
                      <td>{{ $customer->created_at }}</td>
                      <td>
                        <div class="d-flex">
                          <div class="me-2 btn-group">
                            <button type="button" aria-haspopup="true" aria-expanded="false" data-bs-toggle="dropdown"
                            class="dropdown-toggle btn btn-primary btn-sm fw-bolder" style="font-size: 10px;">Filter</button>

                            <div tabindex="-1" role="menu" aria-hidden="true" class="dropdown-menu" x-placement="bottom-start"
                            style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(0px, 33px, 0px);">
            
                                <a href="{{ route('singleCustomerSales', $customer->unique_key) }}">
                                    <button type="button" tabindex="0" class="dropdown-item">Products Bought</button></a>
                                <div tabindex="-1" class="dropdown-divider"></div>
                                
                            </div>
                          </div>
                          <a href="{{ route('singleCustomer', $customer->unique_key) }}" class="btn btn-primary btn-sm me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View"><i class="bi bi-eye"></i></a>
                          <a href="{{ route('editCustomer', $customer->unique_key) }}" class="btn btn-success btn-sm me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Edit"><i class="bi bi-pencil-square"></i></a>
                          <a class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Delete"><i class="bi bi-trash"></i></a>
                        </div>
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

<!--Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">Import Customers CSV File</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form action="{{ route('customersImport') }}" method="POST" enctype="multipart/form-data">@csrf
        <div class="modal-body">
          <div>Download sample Excel file <a href="{{ route('customersSampleExport') }}" class="btn btn-sm rounded-pill btn-primary"><i class="bi bi-download me-1"></i> Download</a></div>

          @if (count($errors) > 0)
          <div class="row mt-3">
              <div class="col-md-12">
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-hidden="true">??</button>
                    <h4> Error!</h4>
                    @foreach($errors->all() as $error)
                    {{ $error }} <br>
                    @endforeach      
                </div>
              </div>
          </div>
          @endif

          <div class="mt-3">
            <label for="formFileSm" class="form-label">Click to upload file</label>
            <input type="file" class="form-control form-control-sm" name="file" id="formFileSm">
          </div>
        </div>
      
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
        </div>
      </form>

    </div>
  </div>
</div>

@endsection

@section('extra_js')
  
  <?php if(count($errors) > 0) : ?>
    <script>
        $( document ).ready(function() {
            $('#importModal').modal('show');
        });
    </script>
  <?php endif ?>

@endsection