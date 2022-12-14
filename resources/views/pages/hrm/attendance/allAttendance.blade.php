@extends('layouts.design')
@section('title')Attendance @endsection
@section('content')

<main id="main" class="main">

  <div class="pagetitle">
    <h1>Attendance List</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.html">Home</a></li>
        <li class="breadcrumb-item active">Attendance List</li>
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

              <div class="float-start text-start">
                  <a href="{{ route('addAttendance') }}"><button data-bs-target="#addMoneyTransfer" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Export Data">
                    <i class="bi bi-arrow-up"></i> <span>On Arrival</span></button></a>

                    <a href="{{ route('addAttendance') }}" class="d-none"><button data-bs-target="#addMoneyTransfer" class="btn btn-sm btn-danger rounded-pill" data-bs-toggle="modal" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Export Data">
                      <i class="bi bi-arrow-down"></i> <span>On Exit</span></button></a>
              </div>
  
              <div class="float-end text-end d-none">
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
                      <th>Date</th>
                      <th>Employee</th>
            
                      <th>Check-In</th>
                      <th>Check-Out</th>

                      <th>Status</th>
                      <th>Action</th>
                  </tr>
              </thead>
              <tbody>
                @if (count($attendances) > 0)
                    @foreach ($attendances as $attendance)
                    <tr>
                      {{-- $date = Carbon::parse('2016-11-24 11:59:56')->addHour(); --}}
                      <td>{{ $attendance->created_at->format('D, M j, Y') }}</td>
                      <td>{{ $attendance->employee->name }}</td>
                      
                      <td>{{ $attendance->check_in }} <br> <span class="badge badge-dark">{{ \Carbon\Carbon::parse($attendance->created_at->addHour(1))->format('H:i') }}</span> </td>

                      <td>
                        @if (isset($attendance->check_out))                           
                        {{ $attendance->check_out }}
                        <br> <span class="badge badge-danger">{{ \Carbon\Carbon::parse($attendance->updated_at->addHour(1))->format('H:i') }}</span>
                        @endif
                      </td>
                      
                      <td>
                        @if ( $attendance->daily_status == 'present' )
                          <span class="badge badge-success">Present</span>
                        @elseif( $attendance->daily_status == 'late' )
                          <span class="badge badge-danger">Late</span>
                        @endif
                      </td>

                      <td>
                        <a href="{{ route('editAttendance', $attendance->unique_key) }}"><button data-bs-target="#addMoneyTransfer" class="btn btn-sm btn-danger rounded-pill" data-bs-toggle="modal" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Export Data">
                          <i class="bi bi-arrow-down"></i> <span>On Exit</span></button></a>
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