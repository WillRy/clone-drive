<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileActionRequest extends ParentIdBaseRequest
{


    protected function prepareForValidation()
    {
        $this->merge([
            'all' => filter_var($this->all, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'all' => 'nullable|boolean',
            'ids.*' => [
                Rule::exists(File::class,'id'),
                function ($attribute, $value, $fail) {
                    $file = File::query()
                        ->leftJoin('file_shares', 'file_shares.file_id', '=', 'files.id')
                        ->where('files.id', $value)
                        ->where(function ($query) {
                            return $query
                                ->where('files.created_by', auth()->id())
                                ->orWhere('file_shares.user_id', auth()->id());
                        })
                        ->first();

                    if(!$file){
                        $fail("Invalid ID {$value}");
                    }
                }
            ],
        ]);
    }
}
