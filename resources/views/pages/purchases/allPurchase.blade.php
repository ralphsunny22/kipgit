@extends('layouts.design')
@section('title')Purchases @endsection
@section('content')

<main id="main" class="main">

  <div class="pagetitle">
    <h1>Purchases</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.html">Home</a></li>
        <li class="breadcrumb-item active">Purchases</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  
  <section class="users-list-wrapper">
    <div class="users-list-filter px-1">
      
    </div>

  </section>

  <section>
    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-body pt-3">
            
          <div class="clearfix mb-2">
            <div class="float-end text-end">
              <button data-bs-target="#importModal" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Export Data">
                <i class="bi bi-upload"></i> <span>Import</span></button>
              <button class="btn btn-sm btn-secondary rounded-pill" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Import Data"><i class="bi bi-download"></i> <span>Export</span></button>
              <button class="btn btn-sm btn-danger rounded-pill" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Delete All"><i class="bi bi-trash"></i> <span>Delete All</span></button>
            </div>
          </div>
          <hr>
          
          <div class="table table-responsive">
            <table id="products-table" class="table custom-table" style="width:100%">
              <thead>
                  <tr>
                      <th>Purchase Code</th>
                      <th>Supplier</th>
                      <th>Purchase Status</th>
                      <th>Amount</th>
                      <th>Due</th>
                      <th>Payment Status</th>
                      <th>Date</th>
                      <th>Action</th>
                  </tr>
              </thead>
              <tbody>
                @if (count($purchases) > 0)
                    @foreach ($purchases as $purchase)
            
                        <tr>
                    
                            <td>{{ $purchase->purchase_code }}</td>
                            <td>{{ isset($purchase->supplier_id) ? $purchase->supplier->company_name : '' }}</td>
                            <td>
                                @if($purchase->status=='received')
                                    <span class="badge badge-success text-white fw-bold" style="font-size: 10px">Received</span>
                                @elseif($purchase->status=='partial')
                                    <span class="badge badge-info text-white fw-bold" style="font-size: 10px">Partial</span>
                                @elseif($purchase->status=='pending')
                                    <span class="badge badge-danger text-white fw-bold" style="font-size: 10px">Pending</span>
                                @elseif($purchase->status=='ordered')
                                    <span class="badge badge-secondary text-white fw-bold" style="font-size: 10px">Ordered</span>
                                @endif
                            </td>
                            <td>{{ $purchase->amountPaidAccrued($purchase->purchase_code) }}</td>
                            <td>{{ $purchase->amountDueAccrued($purchase->purchase_code) }}</td>
                            
                            <td>
                                @if($purchase->amountPaidAccrued($purchase->purchase_code) > $purchase->amountDueAccrued($purchase->purchase_code))
                                  <span class="badge badge-danger text-white fw-bold" style="font-size: 10px">Due</span>
                                @else
                                  <span class="badge badge-success text-white fw-bold" style="font-size: 10px">Paid</span>
                                @endif
                            </td>
        
                            <td>{{ $purchase->purchaseDate() }}</td>
        
                            <td>
                                <div class="d-flex">
                                <a href="{{ route('singlePurchase', $purchase->unique_key) }}" class="btn btn-primary btn-sm me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('editPurchase', $purchase->unique_key) }}" class="btn btn-success btn-sm me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Edit"><i class="bi bi-pencil-square"></i></a>
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

<!-- Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">Import Product CSV File</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div>Download sample product CSV file <a href="#" class="btn btn-sm rounded-pill btn-primary"><i class="bi bi-download me-1"></i> Download</a></div>
        <div class="mt-3">
          <label for="formFileSm" class="form-label">Click to upload file</label>
          <input class="form-control form-control-sm" id="formFileSm" type="file">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
      </div>
    </div>
  </div>
</div>

@endsection