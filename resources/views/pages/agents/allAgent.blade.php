@extends('layouts.design')
@section('title')Agent @endsection
@section('content')

<main id="main" class="main">

  <div class="pagetitle">
    <h1>Agent</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Home</a></li>
        <li class="breadcrumb-item active">Agent</li>
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
              <a href="{{ route('addAgent') }}" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Add Agent">
                <i class="bi bi-plus"></i> <span>Add Agent</span></a>
            </div>

            <div class="float-end text-end">
              <button data-bs-target="#importModal" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Import Data">
                <i class="bi bi-upload"></i> <span>Import</span></button>
              <a href="{{ route('agentsExport') }}"><button class="btn btn-sm btn-secondary rounded-pill" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Export Data">
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
                @if (count($agents) > 0)
                    @foreach ($agents as $agent)
                    <tr>
                      <td>
                        @if (isset($agent->profile_picture))
                            <a
                            href="{{ asset('/storage/agent/'.$agent->profile_picture) }}"
                            data-fancybox="gallery"
                            data-caption="{{ isset($agent->profile_picture) ? $agent->name : 'no caption' }}"
                            >   
                            <img src="{{ asset('/storage/agent/'.$agent->profile_picture) }}" width="50" class="img-thumbnail img-fluid"
                            alt="{{$agent->name}}"></a>
                        @else
                        <img src="{{ asset('/storage/agent/person.png') }}" width="50" class="img-thumbnail img-fluid"
                            alt="{{$agent->name}}">
                        @endif
                        
                      </td>
                      <td>{{ $agent->name }}</td>
                      <td>{{ isset($agent->city) ? $agent->city : 'N/A' }}</td>
                      
                      <td>{{ $agent->state }} | {{ $agent->country->name }}</td>
                      
                      <td>{{ $agent->created_at }}</td>
                      <td>
                        <div class="d-flex">
                          <a href="{{ route('singleAgent', $agent->unique_key) }}" class="btn btn-primary btn-sm me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View"><i class="bi bi-eye"></i></a>
                          <a href="{{ route('editAgent', $agent->unique_key) }}" class="btn btn-success btn-sm me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Edit"><i class="bi bi-pencil-square"></i></a>
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
        <h1 class="modal-title fs-5" id="exampleModalLabel">Import Agents CSV File</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form action="{{ route('agentsImport') }}" method="POST" enctype="multipart/form-data">@csrf
        <div class="modal-body">
          <div>Download sample Excel file <a href="{{ route('agentsSampleExport') }}" class="btn btn-sm rounded-pill btn-primary"><i class="bi bi-download me-1"></i> Download</a></div>

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