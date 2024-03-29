@extends('layouts.design')
@section('title')Edit Form Builder @endsection

@section('extra_css')
<style>
    
    select{
        -webkit-appearance: listbox !important /* for arrow in select-field */
    }

    .select-checkbox option::before {
        content: "\2610";
        width: 1.3em;
        text-align: center;
        display: inline-block;
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

    .card.question-item .item-move {
        position: absolute;
        left: 3px;
        top: 50%;
        z-index: 2;
        content: "";
        width: 20px;
        height: 30px;
        background-repeat: no-repeat;
        opacity: .5;
        cursor: move;
    }
</style>
@endsection

@section('content')

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Edit Form Builder</h1>
        <nav>
          <div class="d-flex justify-content-between align-items-center">
              <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="/">Home</a></li>
                  <li class="breadcrumb-item"><a href="{{ route('allNewFormBuilders') }}">All Forms</a></li>
                  <li class="breadcrumb-item active">Add Form</li>
              </ol>
    
              <button type="button" id="saveData" class="btn btn-success d-none" style="width: 30%;">Save Form</button>
          </div>
          
        </nav>
    </div><!-- End Page Title -->

    @if(Session::has('success'))
        <div class="alert alert-success mb-3 text-center">
            {{Session::get('success')}}
        </div>
    @endif

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(Session::has('field_error'))
        <div class="alert alert-danger mb-3 text-center">
            {{Session::get('field_error')}}
        </div>
    @endif
        
    <section class="mt-5">
        <div class="container" id="form-field">
            <form id="form-data" action="{{ route('editNewFormBuilderPost', $formHolder->unique_key) }}" method="POST">@csrf
                <div class="row">
                    <div class="col-md-12">
                        <div class="p-1">
                            <h5 title="Unique Form Code" class="text-center">Form Codes: {{ $form_code }}</h5>
                            <input type="hidden" name="form_code" value="{{ $form_code }}">
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="" placeholder="Enter Form Name" value="{{ $formHolder->name }}">
                            @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                            {{-- <h5 title="Enter Title" class="text-center" id="form-title">Fields marked * are mandatory</h5> --}}
                            {{-- <h3 contenteditable="true" title="Enter Title" class="text-center" id="form-title">Enter Title Here</h3> --}}
                            {{-- <hr class="border-primary"> --}}
                            {{-- <p contenteditable="true"  id="form-description" title="Enter Description" class="form-description text-center">Enter Description Here</p> --}}
                            
                            <div class="mt-3 d-flex align-items-center justify-content-between">
                                @if ( (isset($formHolder->orderbump_id)) )
                                <div class="orderbump border rounded p-1">
                                    <div>OrderBump Status</div>
                                    <div class="d-flex align-items-center" style="gap: 20px;">
                                        <div class='category'>
                                          <input type="radio" name="switch_orderbump" value="on" id="on" checked />
                                          <label for="on" class="ml-1">On</label>
                                        </div>
                                          
                                        <div class='category'>
                                            <input type="radio" name="switch_orderbump" value="off" id="off" />
                                          <label for="off">Off</label>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                @if ( (isset($formHolder->upsell_id)) )
                                <div class="upsell border rounded p-1">
                                    <div>Upsell Status</div>
                                    <div class="d-flex align-items-center" style="gap: 20px;">
                                        <div class='category'>
                                          <input type="radio" name="switch_upsell" value="on" id="on" checked />
                                          <label for="on" class="ml-1">On</label>
                                        </div>
                                          
                                        <div class='category'>
                                            <input type="radio" name="switch_upsell" value="off" id="off" />
                                          <label for="off">Off</label>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                            
                            
                        </div>

                        
                        
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <h5>Form Fields</h5>
                    </div>
                </div>
                
                <!---used in my-form-builder.js--->
                <input type="hidden" name="products[]" class="package_select" value="{{ $package_select }}">
                <input type="hidden" name="former_packages2" class="package_select" value="{{ json_encode($packages) }}">

                <div>
                    
                    {{-- <div> --}}
                        
                        <div id="question-field" class='row ml-2 mr-2'>
                            @foreach ($formContact as $key=>$contact)
                            @if (($contact['form_name']) !== 'Product Package')
                                <div class="card mt-3 mb-3 col-md-12 question-item ui-state-default" data-item="{{ $key }}">
                                    <span class="item-move"><i class="bi bi-grip-vertical"></i></span>
                                    <div class="card-body">
                                        <div class="row align-items-center d-flex">
                                            
                                            <input type="hidden" name="form_name_selected[]" class="form_name_selected" value="">
                                            <div class="col-sm-4">
                                                <select title="interested info" name="form_names[]" class='form-control form_name'>
                                                    <option value="{{ $contact['form_name'] }}">{{ $contact['form_name'] }}</option>
                                                    <option value="First Name">First Name</option>
                                                    <option value="Last Name">Last Name</option>
                                                    <option value="Phone Number">Phone Number</option>
                                                    <option value="Whatsapp Phone Number">Whatsapp Phone Number</option>
                                                    <option value="Active Email">Active Email</option>
                                                    <option value="State">State</option>
                                                    <option value="City">City</option>
                                                    <option value="Address">Address</option>
                                                    <option value="Product Package">Product Package</option>
                                                </select>
                                            </div>

                                            <div class="col-sm-4">
                                                <input type="text" name="form_labels[]" class="form-control col-sm-12 form_label" placeholder="Edit Input Label" value="{{ $contact['form_label'] }}">
                                                {{-- <p class="question-text m-0" contenteditable="true" title="Write you question here">Write Form Label Here</p> --}}
                                            </div>

                                            <div class="col-sm-4">
                                                <select title="question choice type" name="form_types[]" class='form-control choice-option'>
                                                    
                                                    @if ($contact['form_type']=='text_field')
                                                        <option value="text_field" selected>Text: Simple Input Field</option>
                                                            
                                                        @elseif($contact['form_type']=='number_field')
                                                        <option value="number_field" selected>Number: Simple Input Field</option>

                                                        @elseif($contact['form_type']=='package_single')
                                                        <option value="package_single" selected>Multi-Choice Package (single option)</option>

                                                        @elseif($contact['form_type']=='package_multi')
                                                        <option value="package_multi" selected>Multi-Choice Package (multiple option)</option>

                                                    @endif
                                                    
                                                    <option value="text_field">Text: Simple Input Field </option>
                                                    <option value="number_field">Number: Simple Input Field </option>
                                                    <option value="package_single">Multi-Choice Package (single option)</option>
                                                    <option value="package_multi">Multi-Choice Package (multiple option)</option>

                                                    {{-- <option value="radio">Mupliple Choice (single option) Package</option>
                                                    <option value="checkbox">Mupliple Choice (multiple option) Package</option> --}}
                                                    
                                                    <option value="p">Textarea</option>
                                                    <option value="file">File upload</option>
                                                </select>
                                            </div>
                                        </div>
                                        <hr class="border-dark">
                                        <div class="row ">
                                            <div class="form-group choice-field col-md-12">
                                                <input type="text" name="q[0]" class="form-control col-sm-12" placeholder="Default Value | Optional">
                                                {{-- <textarea name="q[0]" class="form-control col-sm-12" cols="30" rows="5" placeholder="Write your answer here"></textarea> --}}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="w-100 d-flex justify-content-between align-items-center">
                                            <div class="form-check">
                                                <input class="form-check-input req-item" name="required[]" type="checkbox" value="" checked>
                                                <label class="form-check-label req-chk" for="">
                                                    * Required
                                                </label>
                                            </div>
                                            <button class="btn btn-danger border rem-q-item" type="button"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (($contact['form_name']) == 'Product Package')
                                <div class="card mt-3 mb-3 col-md-12 question-item ui-state-default" data-item="{{ $key }}">
                                    <span class="item-move"><i class="bi bi-grip-vertical"></i></span>
                                    <div class="card-body">
                                        <div class="row align-items-center d-flex">
                                            
                                            <input type="hidden" name="form_name_selected[]" class="form_name_selected" value="">
                                            <div class="col-sm-4">
                                                <select title="interested info" name="form_names[]" class='form-control form_name'>
                                                    <option value="{{ $contact['form_name'] }}">{{ $contact['form_name'] }}</option>
                                                    <option value="First Name">First Name</option>
                                                    <option value="Last Name">Last Name</option>
                                                    <option value="Phone Number">Phone Number</option>
                                                    <option value="Whatsapp Phone Number">Whatsapp Phone Number</option>
                                                    <option value="Active Email">Active Email</option>
                                                    <option value="State">State</option>
                                                    <option value="City">City</option>
                                                    <option value="Address">Address</option>
                                                    <option value="Product Package">Product Package</option>
                                                </select>
                                            </div>

                                            <div class="col-sm-4">
                                                <input type="text" name="form_labels[]" class="form-control col-sm-12 form_label" placeholder="Edit Input Label" value="{{ $contact['form_label'] }}">
                                                {{-- <p class="question-text m-0" contenteditable="true" title="Write you question here">Write Form Label Here</p> --}}
                                            </div>

                                            <div class="col-sm-4">
                                                <select title="question choice type" name="form_types[]" class='form-control choice-option'>
                                                    
                                                    
                                                        @if ($contact['form_type']=='text_field')
                                                        <option value="text_field" selected>Text: Simple Input Field</option>

                                                        @elseif($contact['form_type']=='number_field')
                                                        <option value="number_field" selected>Number: Simple Input Field</option>

                                                        @elseif($contact['form_type']=='package_single')
                                                        <option value="package_single" selected>Multi-Choice Package (single option)</option>

                                                        @elseif($contact['form_type']=='package_multi')
                                                        <option value="package_multi" selected>Multi-Choice Package (multiple option)</option>

                                                        @endif
                                                    
                                                    <option value="number_field">Text: Simple Input Field </option>
                                                    <option value="number_field">Number: Simple Input Field </option>
                                                    <option value="package_single">Multi-Choice Package (single option)</option>
                                                    <option value="package_multi">Multi-Choice Package (multiple option)</option>

                                                    {{-- <option value="radio">Mupliple Choice (single option) Package</option>
                                                    <option value="checkbox">Mupliple Choice (multiple option) Package</option> --}}
                                                    
                                                    <option value="p">Textarea</option>
                                                    <option value="file">File upload</option>
                                                </select>
                                            </div>
                                        </div>
                                        <hr class="border-dark">

                                        <div class="row ">
                                            <div class="form-group choice-field col-md-12">
                                                
                                                @if(($contact['form_type']=='package_single') || ($contact['form_type']=='package_multi'))
                                                {{-- @foreach ($packages as $key=>$item) --}}
                                                @foreach ($package_select_edit as $selected)
                                                    {!! $selected !!}
                                                @endforeach
                                                <button type="button" class="add_package btn btn-sm btn-success border"><i class="bi bi-plus"></i> Add option</button>
                                                {{-- @endforeach --}}
                                                
                                                @else
                                                    <input type="text" name="q[0]" class="form-control col-sm-12" placeholder="Default Value | Optional">
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="w-100 d-flex justify-content-between align-items-center">
                                            <div class="form-check">
                                                <input class="form-check-input req-item" name="required[]" type="checkbox" value="" checked>
                                                <label class="form-check-label req-chk" for="">
                                                    * Required
                                                </label>
                                            </div>
                                            <button class="btn btn-danger border rem-q-item" type="button"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @endforeach
                        </div>
                    {{-- </div> --}}
                        
                </div>
                

                <div class="d-flex w-100 justify-content-center" id="form-buidler-action">
                    <button class="btn btn-success border ms-3" type="button" id="add_q-item"><i class="fa fa-plus"></i> Add Item</button>
                    <button type="submit" class="btn btn-default border ms-3" type="button" id="save_new_form"><i class="fa fa-save"></i> Save Form</button>
                </div>
            </form>
        </div>

        <!--cloned on click of Add-Item Btn--->
        <div class="d-none" id = "q-item-clone">
    
            <div class="card mt-3 mb-3 col-md-12 question-item ui-state-default" data-item="0">
                <span class="item-move"><i class="bi bi-grip-vertical"></i></span>
                <div class="card-body">
                    <div class="row align-items-center d-flex">
                        
                        <input type="hidden" name="form_name_selected[]" class="form_name_selected" value="">
                        <div class="col-sm-4">
                            <select title="interested info" name="form_names[]" class='form-control form_name'>
                                <option value="">Select Input Label *</option>
                                <option value="First Name">First Name</option>
                                <option value="Last Name">Last Name</option>
                                <option value="Phone Number">Phone Number</option>
                                <option value="Whatsapp Phone Number">Whatsapp Phone Number</option>
                                <option value="Active Email">Active Email</option>
                                <option value="State">State</option>
                                <option value="City">City</option>
                                <option value="Address">Address</option>
                                <option value="Product Package">Product Package</option>
                            </select>
                        </div>

                        <div class="col-sm-4">
                            <input type="text" name="form_labels[]" class="form-control col-sm-12 form_label" placeholder="Edit Input Label" value="">
                            {{-- <p class="question-text m-0" contenteditable="true" title="Write you question here">Write Form Label Here</p> --}}
                        </div>

                        <div class="col-sm-4">
                            <select title="question choice type" name="form_types[]" class='form-control choice-option'>
                                
                                <option value="text_field" selected>Text: Simple Input Field</option>
                                <option value="number_field">Number: Simple Input Field </option>
                                <option value="package_single">Multi-Choice Package (single option)</option>
                                <option value="package_multi">Multi-Choice Package (multiple option)</option>

                                {{-- <option value="radio">Mupliple Choice (single option) Package</option>
                                <option value="checkbox">Mupliple Choice (multiple option) Package</option> --}}
                                
                                <option value="p">Textarea</option>
                                <option value="file">File upload</option>
                            </select>
                        </div>
                    </div>
                    <hr class="border-dark">
                    <div class="row ">
                        <div class="form-group choice-field col-md-12">
                            <input type="text" name="q[0]" class="form-control col-sm-12" placeholder="Default Value | Optional">
                            {{-- <textarea name="q[0]" class="form-control col-sm-12" cols="30" rows="5" placeholder="Write your answer here"></textarea> --}}
                        </div>
                    </div>
                </div>
            
                <div class="card-footer">
                    <div class="w-100 d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input req-item" name="required[]" type="checkbox" value="" checked>
                            <label class="form-check-label req-chk" for="">
                                * Required
                            </label>
                        </div>
                        <button class="btn btn-danger border rem-q-item" type="button"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
            
        </div>
    </section>

    
    

</main>


@endsection

@section('extra_js')

<script src="{{ asset('/assets/js/jquery-ui.min.js') }}"></script>
<script src="{{ asset('/myassets/js/my-form-builder.js') }}"></script>

<script>
    
</script>

@endsection