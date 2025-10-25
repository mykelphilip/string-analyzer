<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Strings extends Model
{
    //

    protected $fillable = [
        'value',
        'length',
        'is_palindrome',
        'unique_characters',
        'word_count',
        'sha256_hash',
        'character_frequency_map'
    ];


    protected $casts = [
        'character_frequency_map' => 'array',
        'is_palindrome' => 'boolean',
    ];

}
