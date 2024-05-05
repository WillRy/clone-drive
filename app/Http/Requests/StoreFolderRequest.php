<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends ParentIdBaseRequest
{


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $uniqueInsideUserFolder = Rule::unique('files', 'name')
            ->where('parent_id', $this->parent_id)
            ->where('created_by', auth()->id())
            ->whereNull('deleted_at');

        return [
            ...parent::rules(),
            'name' => ['required', $uniqueInsideUserFolder]
        ];
    }

    public function messages()
    {
        return [
            'name.unique' => 'Folder ":input" already exists'
        ];
    }
}
