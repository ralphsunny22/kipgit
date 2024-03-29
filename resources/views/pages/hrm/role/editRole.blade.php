@extends('layouts.design')
@section('title')Edit Role @endsection
@section('content')

<main id="main" class="main">

    <div class="pagetitle">
      <h1>Edit Role</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="/">Home</a></li>
          <li class="breadcrumb-item"><a href="{{ route('allRole') }}">Role List</a></li>
          <li class="breadcrumb-item active">Edit Role</li>
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

    @if(Session::has('role_error'))
    <div class="alert alert-danger mb-3 text-center">
        {{Session::get('role_error')}}
    </div>
    @endif

    <section>
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div class="card-body">
              
              <form class="row g-3 needs-validation" action="{{ route('editRolePost', $role->unique_key) }}" method="POST">@csrf
                <div class="col-md-12">
                  <label for="" class="form-label">Role Name</label>
                  <input type="text" name="role_name" class="form-control @error('role_name') is-invalid @enderror" value="{{ $role->name }}">
                  @error('role_name')
                      <span class="invalid-feedback" role="alert">
                          <strong>{{ $message }}</strong>
                      </span>
                  @enderror
                </div>

                <div class="col-md-12">
                    <div class="row mt-5">
                        <div class="d-flex justify-content-between">
                            <div class="heading">
                                <figure class="text-left">
                                    <blockquote class="blockquote">
                                      <p class="fw-bold">Permissions</p>
                                    </blockquote>
                                    <figcaption class="blockquote-footer">
                                        Tick Appropriate Permissions
                                    </figcaption>
                                </figure>                            
                            </div>

                            <button type="button" class="btn btn-sm text-white d-none"
                                style="background-color: #093040; height: 30px;"
                                data-bs-target="#importModal" data-bs-toggle="modal" data-bs-placement="auto" data-bs-title="Export Data">
                                <i class="fa fa-plus"></i>
                                Add Permissions</button>
                        </div>

                        <!--permissions-->
                        <div>
                            @foreach ($permissions as $perm)
                            <div class="main-card mb-3 card">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $perm->name }} PERMISSIONS</h5>
                                    @if(!empty($perm->permissions))
                                        @foreach ($perm->permissions as $sub_perm)
                                        <div class="position-relative form-check form-check-inline">
                                            <input type="hidden" class="perms_unchecked" name="perms_unchecked[]" data-unchecked-id="{{ $sub_perm->id }}" value="">
                                            <label class="form-check-label">
                                                <input class="form-check-input check-demo" data-id="{{ $sub_perm->id }}"
                                                @if($role->hasPermissionById($sub_perm->id)) checked @endif name="perms[]"
                                                type="checkbox" id="{{ $sub_perm->slug }}" value="{{ $sub_perm->id }}">
                                                {{ $sub_perm->name }}
                                            </label>
                                        </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>

                        

                    </div>
                </div>
                
                <div class="text-end">
                  <button type="submit" class="btn btn-primary">Update Role</button>
                  <button type="reset" class="btn btn-secondary">Reset</button>
                </div>
              </form><!-- End Multi Columns Form -->
              
            </div>
          </div>
        </div>
      </div>
    </section>

</main><!-- End #main -->

@endsection

@section('extra_js')

<!--observe checked n unchecked perms--->
<script>
    $(".check-demo").click(function(){
    if($(this).is(':not(:checked)')){
    
        var id_checked = $(this).attr("data-id");
        //alert($(this).val());   
        var perms_unchecked = $("input[data-unchecked-id='" + id_checked +"']");
        // console.log(perms_unchecked.attr("data-id"));
        unchecked_val = perms_unchecked.val(id_checked);
        // console.log(unchecked_val); 
    }
     if($(this).is(":checked")) {
    
        var id_checked = $(this).attr("data-id");
        //alert($(this).val());   
        var perms_unchecked = $("input[data-unchecked-id='" + id_checked +"']");
        // console.log(perms_unchecked.attr("data-id"));
        unchecked_val = perms_unchecked.val('');
        // console.log(unchecked_val);
       }
    });
</script>

@endsection