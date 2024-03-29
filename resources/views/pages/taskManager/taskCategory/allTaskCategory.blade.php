@extends('layouts.design')
@section('title')Task Categories @endsection

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
    <h1>Task Categories</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Home</a></li>
        <li class="breadcrumb-item active">Task Categories</li>
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
                <button data-bs-target="#addCategory" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-toggle="tooltip" data-bs-placement="auto" data-bs-title="Add Category">
                  <i class="bi bi-plus"></i> <span>Add Category</span></button>
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
                      <th>Category Name</th>
                      <th>Tasks</th>
                      <th>Created By</th>
                      <th>Date</th>
                      <th>Action</th>
                  </tr>
              </thead>
              <tbody>
                @if (count($categories) > 0)
                    @foreach ($categories as $category)
                    
                        <tr>
                            <td>{{ $category->name }}</td>

                            <!--productsByCategory-->
                            <td>
                              @if ($category->tasks->count() > 0)
                              <a href="{{ route('productsByCategory', $category->unique_key) }}" class="badge badge-dark">{{ $category->tasks->count() }}</a>
                              @else
                              0
                              @endif
                            </td>

                            <td>{{ $category->createdBy->name }}</td>
                            <td>{{ $category->created_at }}</td>
                            
                            <td>
                                <div class="d-flex">
                                <a href="javascript:void(0)" onclick="editTaskCategoryModal({{ json_encode($category) }})" class="btn btn-success btn-sm me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Edit"><i class="bi bi-pencil-square"></i></a>
                                <a href="{{ route('deleteTaskCategory', $category->unique_key) }}" onclick="return confirm('Are you sure?')" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Delete"><i class="bi bi-trash"></i></a>
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

<!-- Modal addCategory -->
<div class="modal fade" id="addCategory" tabindex="-1" aria-labelledby="addCategoryLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Add Category</h1>
                <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('addTaskCategoryPost') }}" method="POST">@csrf
                <div class="modal-body">
                    
                    <div class="d-grid mb-3">
                        <label for="">Category Name</label>
                        <input type="text" name="category_name" id="" class="form-control @error('category_name') is-invalid @enderror">
                        @error('category_name')
                          <span class="invalid-feedback" role="alert">
                              <strong>{{ $message }}</strong>
                          </span>
                        @enderror
                    </div>               
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary addMoneyTransferBtn">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal editCategory -->
<div class="modal fade" id="editCategory" tabindex="-1" aria-labelledby="editCategoryLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Edit Category</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('editTaskCategoryPost') }}" method="POST">@csrf
                <div class="modal-body">
                    <input type="hidden" name="category_id"  class="category_id">
                    <div class="d-grid mb-3">
                        <label for="">Category Name</label>
                        <input type="text" name="category_name" id="" class="edit_category_name form-control @error('category_name') is-invalid @enderror">
                        @error('category_name')
                          <span class="invalid-feedback" role="alert">
                              <strong>{{ $message }}</strong>
                          </span>
                        @enderror
                    </div>               
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary addMoneyTransferBtn">Update Category</button>
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
            $('#addCategory').modal('show');
        });
    </script>
  <?php endif ?>

  <script>
    function editTaskCategoryModal($category="") {
        $('#editCategory').modal('show');
        $('.category_id').val($category.id);
        $('.edit_category_name').val($category.name);
    }
  </script>

@endsection