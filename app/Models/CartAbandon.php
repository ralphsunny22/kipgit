<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CartAbandon extends Model
{
    use HasFactory;

    protected $guarded = []; 
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->unique_key = $model->createUniqueKey(Str::random(30));
        });
    }

    //check if unique_key exists
    private function createUniqueKey($string){
        if (static::whereUniqueKey($unique_key = $string)->exists()) {
            $random = rand(1000, 9000);
            $unique_key = $string.''.$random;
            return $unique_key;
        }

        return $string;
    }

    public function getPackageInfoAttribute($value)
    {
        return $product_info = unserialize($value);
        
        
        // return \Carbon\Carbon::parse($value->created_at)->diffForHumans();
    }

    public function formHolder()
    {
        return $this->belongsTo(FormHolder::class, 'form_holder_id');  
    }
}
