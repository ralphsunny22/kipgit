<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\UpsellSetting;

class UpsellSettingController extends Controller
{
    //allUpsellTemplates
    public function allUpsellTemplates()
    {
        $upsellTemplates = UpsellSetting::all();
        return view('pages.settings.upsell.allUpsellTemplates', \compact('upsellTemplates'));
    }

    public function singleUpsellTemplate($unique_key)
    {
        $upsellTemplate = UpsellSetting::where('unique_key', $unique_key);
        // $sale_code = $sale->first()->sale_code;
        if(!$upsellTemplate->exists()){
            abort(404);
        }
        $upsellTemplate = $upsellTemplate->first();

        return view('pages.settings.upsell.singleUpsellTemplate', \compact('upsellTemplate'));
    }

    //allUpsellTemplates
    public function addUpsellTemplate()
    {
        $string = 'kpups-' . date("Ymd") . '-'. date("his");
        $randomStrings = UpsellSetting::where('template_code', 'like', $string.'%')->pluck('template_code');

        do {
            $randomString = 'kpups-' . date("Ymd") . '-'. date("his");
        } while ($randomStrings->contains($randomString));
    
        $template_code = $randomString;
        return view('pages.settings.upsell.addUpsellTemplate', \compact('template_code'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addUpsellTemplatePost(Request $request)
    {
        $request->validate([
            'heading_text' => 'required|string',
            'subheading_text' => 'required|string',
            'description_text' => 'nullable|string',
        ]);

        $data = $request->all();

        $upsellTemplate = new UpsellSetting();
        
        $upsellTemplate->template_code = $data['template_code'];

        $upsellTemplate->body_bg_color = $data['body_bg_color'];
        $upsellTemplate->body_border_style = $data['body_border_style']; //solid, dotted, dashed
        $upsellTemplate->body_border_color = $data['body_border_color'];
        $upsellTemplate->body_border_thickness = $data['body_border_thickness']; //1px, 2px etc
        $upsellTemplate->body_border_radius = $data['body_border_radius']; //normal, rounded, rounded-pill

        $upsellTemplate->heading_text = $data['heading_text'];
        $upsellTemplate->heading_text_style = $data['heading_text_style']; //normal, italic
        $upsellTemplate->heading_text_align = $data['heading_text_align']; //left, center, right
        $upsellTemplate->heading_text_color = $data['heading_text_color'];

        $upsellTemplate->subheading_text = $data['subheading_text'];
        $upsellTemplate->subheading_text_style = $data['subheading_text_style']; //normal, italic
        $upsellTemplate->subheading_text_align = $data['subheading_text_align']; //left, center, right
        $upsellTemplate->subheading_text_color = $data['subheading_text_color'];

        if (!empty($data['description_text'])) {
            $upsellTemplate->description_text = $data['description_text'];
            $upsellTemplate->description_text_style = $data['description_text_style']; //normal, italic
            $upsellTemplate->description_text_align = $data['description_text_align']; //left, center, right
            $upsellTemplate->description_text_color = $data['description_text_color'];
        }
        
        $upsellTemplate->package_text_style = $data['package_text_style']; //normal, italic
        $upsellTemplate->package_text_align = $data['package_text_align']; //left, center, right
        $upsellTemplate->package_text_color = $data['package_text_color'];

        $upsellTemplate->button_bg_color = $data['button_bg_color'];
        $upsellTemplate->button_text = $data['button_text'];
        $upsellTemplate->button_text_style = $data['button_text_style']; //normal, itallic
        $upsellTemplate->button_text_align = $data['button_text_align']; //left, center, right
        $upsellTemplate->button_text_color = $data['button_text_color'];

        $upsellTemplate->created_by = 1;
        $upsellTemplate->status = 'true';

        $upsellTemplate->save();

        return back()->with('success', 'Template Created Successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editUpsellTemplate($unique_key)
    {
        $upsellTemplate = UpsellSetting::where('unique_key', $unique_key);
        // $sale_code = $sale->first()->sale_code;
        if(!$upsellTemplate->exists()){
            abort(404);
        }
        $upsellTemplate = $upsellTemplate->first();


        return view('pages.settings.upsell.editUpsellTemplate', \compact('upsellTemplate'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editUpsellTemplatePost(Request $request, $unique_key)
    {
        $upsellTemplate = UpsellSetting::where('unique_key', $unique_key);
        if(!$upsellTemplate->exists()){
            abort(404);
        }
        $upsellTemplate = $upsellTemplate->first();

        $request->validate([
            'heading_text' => 'required|string',
            'subheading_text' => 'required|string',
            'description_text' => 'nullable|string',
        ]);

        $data = $request->all();

        $upsellTemplate->template_code = $data['template_code'];

        $upsellTemplate->body_bg_color = $data['body_bg_color'];
        $upsellTemplate->body_border_style = $data['body_border_style']; //solid, dotted, dashed
        $upsellTemplate->body_border_color = $data['body_border_color'];
        $upsellTemplate->body_border_thickness = $data['body_border_thickness']; //1px, 2px etc
        $upsellTemplate->body_border_radius = $data['body_border_radius']; //normal, rounded, rounded-pill

        $upsellTemplate->heading_text = $data['heading_text'];
        $upsellTemplate->heading_text_style = $data['heading_text_style']; //normal, italic
        $upsellTemplate->heading_text_align = $data['heading_text_align']; //left, center, right
        $upsellTemplate->heading_text_color = $data['heading_text_color'];

        $upsellTemplate->subheading_text = $data['subheading_text'];
        $upsellTemplate->subheading_text_style = $data['subheading_text_style']; //normal, italic
        $upsellTemplate->subheading_text_align = $data['subheading_text_align']; //left, center, right
        $upsellTemplate->subheading_text_color = $data['subheading_text_color'];

        if (!empty($data['description_text'])) {
            $upsellTemplate->description_text = $data['description_text'];
            $upsellTemplate->description_text_style = $data['description_text_style']; //normal, italic
            $upsellTemplate->description_text_align = $data['description_text_align']; //left, center, right
            $upsellTemplate->description_text_color = $data['description_text_color'];
        }
        
        $upsellTemplate->package_text_style = $data['package_text_style']; //normal, italic
        $upsellTemplate->package_text_align = $data['package_text_align']; //left, center, right
        $upsellTemplate->package_text_color = $data['package_text_color'];

        $upsellTemplate->button_bg_color = $data['button_bg_color'];
        $upsellTemplate->button_text = $data['button_text'];
        $upsellTemplate->button_text_style = $data['button_text_style']; //normal, itallic
        $upsellTemplate->button_text_align = $data['button_text_align']; //left, center, right
        $upsellTemplate->button_text_color = $data['button_text_color'];

        $upsellTemplate->created_by = 1;
        $upsellTemplate->status = 'true';

        $upsellTemplate->save();

        return back()->with('success', 'Template Created Successfully');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}