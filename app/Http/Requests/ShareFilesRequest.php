<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareFilesRequest extends FileActionRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'email' => [
                'required',
                'email',
                // Rule::exists('users','email')->where(function ($query) {
                //     return $query->where('id', '!=', auth()->id());
                // })
            ]
        ]);
    }
}
